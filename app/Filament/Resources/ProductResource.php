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
                            ->options(Product::CATEGORIES)
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
                    ->formatStateUsing(fn (?string $state) => Product::CATEGORIES[$state] ?? '—')
                    ->badge()
                    ->visibleFrom('md'),
                Tables\Columns\IconColumn::make('template_image')
                    ->label('Fərdiləşir')
                    ->boolean()
                    ->getStateUsing(fn (Product $record) => $record->isCustomizable())
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Qiymət')
                    ->formatStateUsing(fn ($state) => $state ? Price::format($state) : 'Sorğu ilə')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktiv')
                    ->boolean()
                    ->visibleFrom('md'),
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
            ->modifyQueryUsing(fn ($query) => $query->withCount('layers'))
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Kateqoriya')
                    ->options(Product::CATEGORIES),
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
