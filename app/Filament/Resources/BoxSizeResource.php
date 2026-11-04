<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\BoxSizeResource\Pages;
use App\Models\BoxSize;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * The sizes the company box comes in: /sirketler-ucun lets a company pick
 * one, and the page follows it — the shape of the box in the preview, the
 * render behind it, the measurements chip and the price.
 *
 * The minimum order and the making time are not here. They are the same
 * whatever the size, so they stay on «Şirkətlər üçün» where they already are.
 */
class BoxSizeResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = BoxSize::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationLabel = 'Qutu ölçüləri';

    protected static ?string $modelLabel = 'qutu ölçüsü';

    protected static ?string $pluralModelLabel = 'qutu ölçüləri';

    protected static ?string $slug = 'box-sizes';

    protected static ?int $navigationSort = 89;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Ölçü')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Ad')
                            ->placeholder('Mini — bir dilim')
                            ->helperText('Səhifədə seçim düyməsinin üstündə görünür.')
                            ->required()
                            ->maxLength(60),
                        Forms\Components\TextInput::make('dimensions')
                            ->label('Ölçülər (yazı)')
                            ->placeholder('4,5 × 9 × 1,5 sm — bir dilim şokolad')
                            ->helperText('Seçilən ölçünün yanında olduğu kimi yazılır.')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('width_mm')
                            ->label('Çap olunan üzün eni, mm')
                            ->helperText('Qutunun üstündəki yazının sahəsi, qalınlığı yox.')
                            ->numeric()->required()->minValue(5)->maxValue(500)->default(45),
                        Forms\Components\TextInput::make('height_mm')
                            ->label('Çap olunan üzün hündürlüyü, mm')
                            ->numeric()->required()->minValue(5)->maxValue(500)->default(90),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sıra nömrəsi')->numeric()->default(0),
                        Forms\Components\Toggle::make('is_active')->label('Saytda göstər')->default(true),
                    ])->columns(2),

                Forms\Components\Section::make('Başqa dillərdə')
                    ->description('Boş qalsa, azərbaycanca yazdığınız görünür.')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('i18n.ru.name')->label('Ad (rusca)')->maxLength(60),
                        Forms\Components\TextInput::make('i18n.ru.dimensions')->label('Ölçülər (rusca)')->maxLength(120),
                        Forms\Components\TextInput::make('i18n.en.name')->label('Ad (ingiliscə)')->maxLength(60),
                        Forms\Components\TextInput::make('i18n.en.dimensions')->label('Ölçülər (ingiliscə)')->maxLength(120),
                    ])->columns(2),

                Forms\Components\Section::make('Qutunun şəkli')
                    ->description('Blender renderiniz. Boş qalsa, səhifə qutunu həmişəki kimi özü çəkir — '
                        . 'ziyanı yoxdur, sadəcə şəkilsiz. Düz öndən və düz arxadan, fonu şəffaf PNG. '
                        . 'Çap olunan üzü düz ağ qoyun: loqo, şüar və QR onun üstündə çəkilir, '
                        . 'müştərinin seçdiyi rəng də oraya düşür.')
                    ->schema([
                        Forms\Components\FileUpload::make('front_image')
                            ->label('Ön tərəf')
                            ->image()->disk('public')->directory('box-sizes')->maxSize(4096),
                        Forms\Components\FileUpload::make('back_image')
                            ->label('Arxa tərəf')
                            ->image()->disk('public')->directory('box-sizes')->maxSize(4096),
                    ])->columns(2),

                Forms\Components\Section::make('Çap olunan üzün yeri')
                    ->description('Şəkildə çap olunan üz harada başlayır və harada bitir — şəklin eninin və '
                        . 'hündürlüyünün faizi ilə. Render yalnız üzün özüdürsə, 0 / 0 / 100 / 100 qalsın. '
                        . 'Kənarda kölgə və ya qutunun yan tərəfi varsa, rəqəmləri ona görə daraldın və '
                        . 'saytda yoxlayın.')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('face_left')->label('Soldan, %')
                            ->numeric()->default(0)->minValue(0)->maxValue(100),
                        Forms\Components\TextInput::make('face_top')->label('Yuxarıdan, %')
                            ->numeric()->default(0)->minValue(0)->maxValue(100),
                        Forms\Components\TextInput::make('face_right')->label('Sağ kənarı, %')
                            ->numeric()->default(100)->minValue(0)->maxValue(100),
                        Forms\Components\TextInput::make('face_bottom')->label('Alt kənarı, %')
                            ->numeric()->default(100)->minValue(0)->maxValue(100),
                    ])->columns(4),

                Forms\Components\Section::make('Qiymət — sayına görə')
                    ->description('Bu ölçü üçün. Boş qalsa, «Şirkətlər üçün» səhifəsindəki ümumi qiymət '
                        . 'siyahısı işləyir.')
                    ->schema([
                        Forms\Components\Repeater::make('ladder')
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('from')->label('Neçə ədəddən')
                                    ->numeric()->required()->minValue(1),
                                Forms\Components\TextInput::make('price')->label('Bir ədədin qiyməti, ₼')
                                    ->numeric()->required()->minValue(0)->step('0.01'),
                            ])
                            ->itemLabel(fn (array $state) => filled($state['from'] ?? null)
                                ? $state['from'] . ' ədəddən — ' . ($state['price'] ?? '?') . ' ₼' : null)
                            ->addActionLabel('Pillə əlavə et')
                            ->reorderable()->collapsible()->defaultItems(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('front_image')->label('')->height(44),
                Tables\Columns\TextColumn::make('name')
                    ->label('Ölçü')
                    ->description(fn (BoxSize $record) => $record->dimensions)
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('width_mm')
                    ->label('Üz')
                    ->formatStateUsing(fn (BoxSize $record) => $record->width_mm . ' × ' . $record->height_mm . ' mm')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('ladder')
                    ->label('Qiymət')
                    // An empty ladder is not a blank: it is the page's own,
                    // and the row says so rather than looking unfinished.
                    ->formatStateUsing(fn (BoxSize $record) => $record->ladder
                        ? count($record->ladder) . ' pillə'
                        : 'ümumi siyahı')
                    ->visibleFrom('md'),
                Tables\Columns\ToggleColumn::make('is_active')->label('Saytda'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Ölçü yoxdur')
            ->emptyStateDescription('Ölçü əlavə etməyincə səhifə həmişəki tək ölçünü göstərir.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBoxSizes::route('/'),
            'create' => Pages\CreateBoxSize::route('/create'),
            'edit' => Pages\EditBoxSize::route('/{record}/edit'),
        ];
    }
}
