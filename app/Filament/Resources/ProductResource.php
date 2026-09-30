<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Scene;
use App\Support\Media;
use App\Support\Price;
use App\Support\YandexDisk;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Məhsullar';

    protected static ?string $modelLabel = 'məhsul';

    protected static ?string $pluralModelLabel = 'məhsullar';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Əsas məlumat')
                    ->description('Qutunun şəkilləri, foto və mətn sahələri "Qutu redaktoru"nda qurulur — yaratdıqdan sonra ora keçəcəksiniz.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Ad')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),
                        Forms\Components\TextInput::make('slug')
                            ->label('Səhifə ünvanı (slug)')
                            ->helperText('Addan özü yaranır, məs. dark-spotify. Şəklin linki buraya yox, aşağıdakı "Kataloq posteri"nə yazılır.')
                            ->required()
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->validationMessages(['regex' => 'Yalnız kiçik latın hərfləri, rəqəmlər və defis olmalıdır, məs. dark-spotify. Link bu sahəyə yazılmır.'])
                            ->unique(ignoreRecord: true),
                        Forms\Components\Textarea::make('description')
                            ->label('Açıqlama')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('tag')
                            ->label('Etiket (məs. "Populyar")'),
                        Forms\Components\Select::make('category')
                            ->label('Kateqoriya')
                            // Switched-off shelves are offered too: a design
                            // already filed under one must not lose it on save.
                            ->options(Product::categories(false))
                            ->helperText('Dizaynlar səhifəsində qruplaşdırma üçün.'),
                        Forms\Components\TextInput::make('price')
                            ->label('Qiymət (₼)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->placeholder('məs. 4.90')
                            ->suffix('₼')
                            ->helperText('Boş buraxsanız "Qiymət sorğu ilə" göstərilir.'),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sıra nömrəsi')
                            ->numeric()
                            ->default(0),
                        Forms\Components\ColorPicker::make('box_color')
                            ->label('Qutunun rəngi (mokaplarda)')
                            ->helperText(fn (?Product $record) => 'Boş buraxsanız, dizaynın kənar rəngi avtomatik götürülür'
                                . ($record?->box_color_auto ? ' (hazırda ' . $record->box_color_auto . ')' : '')
                                . '. Qutu redaktorunda pipetlə dizaynın istənilən yerindən də seçə bilərsiniz.')
                            ->regex('/^#[0-9a-fA-F]{6}$/'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Saytda görünsün')
                            ->default(true),
                        // This switch belongs to the design's photo windows, not to the products
                        // table, so EditProduct reads it from them and writes it back by hand.
                        // Hidden while creating: a design has no photo window until the box
                        // editor draws one, so there would be nowhere to keep the answer.
                        // What the switch said when the page was opened, so a save
                        // that never touched it leaves the photo windows alone.
                        Forms\Components\Hidden::make('face_cutout_was')
                            ->dehydrated(false),
                        Forms\Components\Toggle::make('face_cutout')
                            ->label('Üz avtomatik kəsilsin')
                            ->dehydrated(false)
                            ->visible(fn (?Product $record) => (bool) $record)
                            ->helperText(fn (?Product $record) => $record?->photoSlots()->exists()
                                ? 'Müştəri adi şəkil yükləyir, brauzer isə ondan yalnız başı kəsib fonu atır — üzün hazır gövdənin üstünə oturduğu dizaynlar üçün. '
                                    . 'Bu dizaynın bütün foto sahələrinə tətbiq olunur; sahələri ayrı-ayrılıqda seçmək üçün "Qutu redaktoru"ndan istifadə edin. '
                                    . 'Qutu redaktoru açıqdırsa, saxlamadan əvvəl o səhifəni yeniləyin.'
                                : 'Bu dizaynda hələ foto sahəsi yoxdur — əvvəlcə "Qutu redaktoru"nda foto sahəsi əlavə edin.'),
                        // Unlike the face switch, this one is the design's own
                        // column: nothing is drawn per window, the customer
                        // simply gets one more field to fill.
                        Forms\Components\Toggle::make('spotify_code')
                            ->label('Spotify kodu istənilsin')
                            ->helperText('Müştəri sifariş edərkən mahnının Spotify linkini yapışdırır, siz isə sifarişdə həmin mahnının '
                                . 'skan olunan kodunu görürsünüz və çapa göndərirsiniz. Yalnız bu dizaynda soruşulur.'),
                    ])->columns(2),
                Forms\Components\Section::make('Kataloq posteri')
                    ->description('Dizaynlar səhifəsindəki kartda bu şəkil görünür (4:5, məs. 1080×1350). Şəkli Yandex Diskdə paylaşın və linkini bura yapışdırın — saxlayanda sayt onu özü yükləyir.')
                    ->schema([
                        Forms\Components\TextInput::make('poster_url')
                            ->label('Yandex Disk linki')
                            ->placeholder('https://disk.yandex.ru/i/…')
                            ->maxLength(500)
                            ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                                if (filled($value) && ! YandexDisk::isPublicLink($value)) {
                                    $fail('Bu Yandex Disk linki deyil. Link belə görünməlidir: https://disk.yandex.ru/i/…');
                                }
                            })
                            ->helperText('Boş buraxsanız, kartda səhnə qapağı və ya dizaynın özü göstərilir. Şəkli Yandexdə dəyişsəniz, linki silib yenidən yapışdırın.'),
                        Forms\Components\Placeholder::make('poster_preview')
                            ->label('Hazırkı poster')
                            ->content(fn (?Product $record) => $record?->poster_image
                                ? new HtmlString('<img src="' . e(Media::url($record->poster_image)) . '" alt="" style="max-height:220px;border-radius:.6rem">')
                                : 'Yoxdur'),
                    ])
                    ->columns(2)
                    ->collapsible(),
                Forms\Components\Section::make('Səhnələr')
                    ->description('Müştəri bu qutunu hansı mokaplarda görsün. Heç biri seçilməsə, bütün aktiv səhnələrdə göstərilir.')
                    ->schema([
                        Forms\Components\Select::make('cover_scene_id')
                            ->label('Kataloq qapağı')
                            ->helperText('Kataloqda bu dizayn seçdiyiniz səhnənin içində göstərilir. Saxlayanda qapaq özü hazırlanır.')
                            ->options(fn () => Scene::orderBy('sort_order')->orderBy('id')->pluck('name', 'id'))
                            ->placeholder('Yoxdur — kataloqda vizual göstərilir'),
                        Forms\Components\Placeholder::make('cover_preview')
                            ->label('Hazırkı qapaq')
                            ->content(fn (?Product $record) => $record?->cover_image
                                ? new HtmlString('<img src="' . e(Media::url($record->cover_image)) . '" alt="" style="max-height:220px;border-radius:.6rem">')
                                : 'Hələ yoxdur'),
                        Forms\Components\CheckboxList::make('scenes')
                            ->label('Müştəri hansı səhnələrdə görsün')
                            ->relationship('scenes', 'name', fn ($query) => $query->orderBy('scenes.sort_order'))
                            ->columns(3)
                            ->bulkToggleable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),
                \App\Filament\Forms\Translations::section(['name' => 'Ad', 'description' => 'Təsvir'], ['description']),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('preview_image')
                    ->label('Şəkil')
                    ->getStateUsing(fn (Product $record) => Media::url($record->catalogImage())),
                Tables\Columns\TextColumn::make('name')
                    ->label('Ad')
                    ->searchable()
                    ->wrap(),
                // On a phone: the picture, the name, the price and ⋮ — the rest from a tablet up.
                Tables\Columns\TextColumn::make('category')
                    ->label('Kateqoriya')
                    ->formatStateUsing(fn (?string $state) => Product::categories(false)[$state] ?? $state ?? '—')
                    ->badge()
                    ->visibleFrom('md'),
                Tables\Columns\IconColumn::make('template_image')
                    ->label('Fərdiləşir')
                    ->boolean()
                    ->getStateUsing(fn (Product $record) => $record->isCustomizable())
                    ->visibleFrom('lg'),
                Tables\Columns\IconColumn::make('face_cutout_slots_count')
                    ->label('Üz kəsimi')
                    ->boolean()
                    ->getStateUsing(fn (Product $record) => $record->face_cutout_slots_count > 0
                        || ($record->face_cutout_angles_count ?? 0) > 0)
                    ->visibleFrom('lg'),
                Tables\Columns\IconColumn::make('spotify_code')
                    ->label('Spotify')
                    ->boolean()
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Qiymət')
                    ->formatStateUsing(fn ($state) => $state ? Price::format($state) : 'Sorğu ilə')
                    ->sortable(),
                // Switched here, in the list: opening the design's form to
                // take it off the site for an hour is three clicks too many.
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aktiv')
                    ->afterStateUpdated(function (Product $record, bool $state) {
                        if ($state && $record->isBlank()) {
                            Notification::make()->warning()
                                ->title($record->name . ' boşdur')
                                ->body('Dizayn açıqdır, amma içində heç nə yoxdur — müştəri onu açanda «hazırlanır» yazısını görəcək. Redaktorda çəkin.')
                                ->persistent()->send();
                        }
                    }),
                Tables\Columns\TextColumn::make('blank')
                    ->label('İçi')
                    ->badge()
                    ->getStateUsing(fn (Product $record) => $record->isBlank() ? 'Boş' : 'Hazır')
                    ->color(fn (string $state) => $state === 'Boş' ? 'danger' : 'success')
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sıra')
                    ->numeric()
                    ->sortable()
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Yaradılıb')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Both icon columns ask a relation a question; counted here so the list stays one query.
            ->modifyQueryUsing(fn ($query) => $query
                ->withCount('layers')
                // Counted here so "is it empty?" costs no query per row.
                ->withCount(['shapes', 'photoSlots'])
                ->withCount(['photoSlots as face_cutout_slots_count' => fn ($q) => $q->where('cutout', true)])
                // Designs from before the box editor keep windows on their angles too.
                ->withCount(['angles as face_cutout_angles_count' => fn ($q) => $q
                    ->whereHas('photoSlots', fn ($slot) => $slot->where('cutout', true))]))
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Kateqoriya')
                    ->options(Product::categories(false)),
                Tables\Filters\TernaryFilter::make('blank')
                    ->label('İçi boş olanlar')
                    ->placeholder('Hamısı')
                    ->trueLabel('Yalnız boşlar')
                    ->falseLabel('Yalnız hazırlar')
                    ->queries(
                        true: fn ($q) => $q->whereNull('template_image')
                            ->doesntHave('layers')->doesntHave('shapes')->doesntHave('photoSlots'),
                        false: fn ($q) => $q->where(fn ($w) => $w->whereNotNull('template_image')
                            ->orHas('layers')->orHas('shapes')->orHas('photoSlots')),
                        blank: fn ($q) => $q,
                    ),
            ])
            ->actions([
                // Behind one ⋮ button, so the row fits a phone screen.
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('editor')
                        ->label('Redaktor')
                        ->icon('heroicon-o-paint-brush')
                        ->url(fn (Product $record) => route('box.edit', $record->slug)),
                    Tables\Actions\EditAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('cover')
                        ->label('Kataloq qapağı seç')
                        ->icon('heroicon-o-photo')
                        ->form([
                            Forms\Components\Select::make('scene')
                                ->label('Səhnə')
                                ->options(fn () => Scene::orderBy('sort_order')->orderBy('id')->pluck('name', 'id'))
                                ->placeholder('Yoxdur — vizual göstərilsin'),
                        ])
                        ->action(function (Collection $records, array $data, $livewire) {
                            $records->each(fn (Product $p) => $p->forceFill(['cover_scene_id' => $data['scene'] ?: null])->save());
                            $livewire->redirect(route('cover.page', [
                                'ids' => $records->pluck('id')->implode(','),
                                'back' => static::getUrl('index', isAbsolute: false),
                            ]));
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
