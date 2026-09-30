<?php

namespace App\Filament\Resources\LibraryAssetResource\Pages;

use App\Filament\Resources\LibraryAssetResource;
use App\Models\LibraryAsset;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Http\UploadedFile;

class ListLibraryAssets extends ListRecords
{
    protected static string $resource = LibraryAssetResource::class;

    public function getSubheading(): ?string
    {
        return 'Qutu redaktorunda "Kitabxana" düyməsi bu şəkilləri göstərir. Qutuya qoyulan şəkil o qutunun öz qovluğuna kopyalanır, '
            . 'ona görə buradan silmək hazır dizaynı pozmur.';
    }

    protected function getHeaderActions(): array
    {
        return [
            // Twenty stickers at once: one by one through the form would be a
            // long evening.
            Actions\Action::make('bulk')
                ->label('Bir neçə şəkil yüklə')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Forms\Components\Select::make('category')
                        ->label('Növ')
                        ->options(LibraryAsset::CATEGORIES)
                        ->default('sticker')
                        ->required(),
                    Forms\Components\FileUpload::make('files')
                        ->label('Fayllar')
                        ->multiple()
                        ->image()
                        ->acceptedFileTypes(['image/png', 'image/webp', 'image/jpeg'])
                        ->maxSize(20480)
                        // Kept as they came, so each one's own name becomes its
                        // name on the shelf.
                        ->storeFiles(false)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $made = 0;
                    foreach ($data['files'] as $file) {
                        if (! $file instanceof UploadedFile) {
                            continue;
                        }
                        LibraryAsset::create([
                            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                            'category' => $data['category'],
                            'image' => LibraryAssetResource::keep($file),
                        ]);
                        $made++;
                    }

                    Notification::make()->success()->title($made . ' şəkil kitabxanaya əlavə olundu')->send();
                }),
            Actions\CreateAction::make()->label('Şəkil əlavə et'),
        ];
    }
}
