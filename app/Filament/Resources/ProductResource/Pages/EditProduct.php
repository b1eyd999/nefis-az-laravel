<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Support\YandexDisk;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /** Set after a save that changed what the catalogue cover shows. */
    private bool $coverOutdated = false;

    /** A new poster, fetched before saving so a bad link stops the save. */
    private ?UploadedFile $poster = null;

    /**
     * The face-cutout switch is not a column on products: the answer sits on
     * each photo window of this design, so it is read back from there. One
     * marked window is enough for the design to count as a face design.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['face_cutout'] = $this->cutsFaces($this->record);
        // Kept beside it so the save request, which builds a new component,
        // can still tell whether the owner actually moved the switch.
        $data['face_cutout_was'] = $data['face_cutout'];

        /* The addresses this design used to live at. Kept twice for the same
           reason: on saving, only what the owner actually added or removed
           is acted on. Renaming the design writes a row of its own, and that
           row is in neither list, so a careless save cannot wipe it. */
        $data['old_slugs'] = $this->record->oldSlugs()->orderBy('slug')->pluck('slug')->all();
        $data['old_slugs_was'] = $data['old_slugs'];

        return $data;
    }

    protected function beforeSave(): void
    {
        $link = trim((string) ($this->data['poster_url'] ?? ''));
        if ($link === '' || ($link === $this->record->poster_url && $this->record->poster_image)) {
            return;
        }

        try {
            $this->poster = YandexDisk::downloadImage($link);
        } catch (\RuntimeException $e) {
            Notification::make()->danger()->title('Poster yüklənmədi')->body($e->getMessage())->persistent()->send();
            $this->halt();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('editor')
                ->label('Qutu redaktoru')
                ->icon('heroicon-o-paint-brush')
                ->url(fn () => route('box.edit', $this->record->slug)),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $product = $this->record;

        // Before the cover block below, which returns early for most designs.
        $this->applyFaceCutout($product);
        $this->applyOldSlugs($product);

        if ($this->poster) {
            $product->storePoster($this->poster, $product->poster_url);
        } elseif (! $product->poster_url && $product->poster_image) {
            $product->removePoster();
        }

        if (! $product->cover_scene_id) {
            if ($product->cover_image) {
                Storage::disk('public')->delete($product->cover_image);
                $product->forceFill(['cover_image' => null])->saveQuietly();
            }

            return;
        }

        // Nothing to draw until the box editor has a visual to put in the scene.
        $this->coverOutdated = $product->preview_image
            && ($product->wasChanged(['cover_scene_id', 'box_color']) || ! $product->cover_image);
    }

    /**
     * The panel's switch speaks for the whole design, while the box editor can
     * mark one photo window and leave the one beside it alone. So a save that
     * left the switch where it was changes nothing: a design with a face in one
     * window and an ordinary picture in the other keeps that mix, and only the
     * owner flipping the switch here gives every window the same answer.
     */
    private function applyFaceCutout(Product $product): void
    {
        $wanted = (bool) ($this->data['face_cutout'] ?? false);

        // Against what the page showed, not against the database: while this
        // tab stood open the owner may have marked a single window in the box
        // editor, and a save of the price here must not undo that.
        if (! array_key_exists('face_cutout_was', $this->data) || $wanted === (bool) $this->data['face_cutout_was']) {
            return;
        }

        // A design whose photo windows are not drawn yet has nothing to mark.
        $product->photoSlots()->update(['cutout' => $wanted]);
        // Designs from before the box editor keep a window on every angle too.
        foreach ($product->angles as $angle) {
            $angle->photoSlots()->update(['cutout' => $wanted]);
        }

        $this->data['face_cutout_was'] = $wanted;
    }

    /**
     * Only the addresses the owner actually typed in or crossed out.
     *
     * Acting on the difference rather than on the whole list matters: this
     * same save may have renamed the design, and the model will have written
     * the address it is moving away from. That row was never on the form, so
     * it is in neither list and survives — which is the behaviour anyone
     * would expect, and the opposite of what replacing the list wholesale
     * would do.
     */
    private function applyOldSlugs(Product $product): void
    {
        if (! array_key_exists('old_slugs_was', $this->data)) {
            return;
        }

        $was = (array) $this->data['old_slugs_was'];
        $now = (array) ($this->data['old_slugs'] ?? []);

        $product->forgetOldSlugs(array_diff($was, $now));
        $product->rememberOldSlugs(array_diff($now, $was));

        $this->data['old_slugs_was'] = $now;
    }

    private function cutsFaces(Product $product): bool
    {
        return $product->photoSlots()->where('cutout', true)->exists()
            || $product->angles()->whereHas('photoSlots', fn ($slot) => $slot->where('cutout', true))->exists();
    }

    /** A cover is drawn in the browser, on a page that then comes back here. */
    protected function getRedirectUrl(): ?string
    {
        if (! $this->coverOutdated) {
            return null;
        }

        return route('cover.page', [
            'ids' => $this->record->id,
            'back' => ProductResource::getUrl('edit', ['record' => $this->record], isAbsolute: false),
        ]);
    }
}
