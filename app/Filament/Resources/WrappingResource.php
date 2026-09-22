<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\WrappingResource\Pages;
use App\Models\Wrapping;
use App\Support\Price;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Gift wraps: a paper and a ribbon each. The customer picks one after the
 * chocolate and sees the box wrapped in it on the mockups.
 */
class WrappingResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = Wrapping::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Qablaşdırma';

    protected static ?string $modelLabel = 'qablaşdırma';

    protected static ?string $pluralModelLabel = 'qablaşdırmalar';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Qablaşdırma')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Ad')
                            ->placeholder('Məs. Ürəklər, krem lent')
                            ->required()
                            ->maxLength(80),
                        Forms\Components\TextInput::make('price')
                            ->label('Qiymət')
                            ->numeric()->minValue(0)->step(0.01)->suffix('₼')->required()->default(2)
                            ->helperText('Müştəri səhifəsində eyni qiymətli qablaşdırmalar bir qrupda göstərilir.'),
                        Forms\Components\FileUpload::make('pattern')
                            ->label('Kağızın naxışı')
                            ->helperText('Kağızın düz şəkli (çap faylı kimi, perspektivsiz və kölgəsiz), PNG və ya JPG. Naxış qutunun üstündə təkrarlanır.')
                            ->image()
                            ->disk('public')
                            ->directory('wrappings')
                            ->maxSize(8192)
                            ->required()
                            ->live()
                            ->columnSpanFull(),
                        Forms\Components\Select::make('ribbon')
                            ->label('Lent')
                            ->options(Wrapping::RIBBONS)
                            ->default(Wrapping::SATIN)
                            ->live()
                            ->required(),
                        Forms\Components\ColorPicker::make('ribbon_color')
                            ->label('Lentin rəngi')
                            ->default('#F3D3B4')
                            ->live()
                            ->regex('/^#[0-9a-fA-F]{6}$/')
                            ->visible(fn (Forms\Get $get) => $get('ribbon') !== Wrapping::NONE),
                        Forms\Components\TextInput::make('pattern_scale')
                            ->label('Naxışın ölçüsü')
                            ->numeric()->minValue(0.1)->maxValue(2)->step(0.05)->default(0.5)
                            ->live(onBlur: true)
                            ->helperText('Bir naxış parçasının eni, qutunun eninə nisbətən: 0.5 — yarısı, 1 — tam eni.'),
                        Forms\Components\TextInput::make('sort_order')->label('Sıra nömrəsi')->numeric()->default(0),
                        Forms\Components\Toggle::make('is_active')->label('Saytda görünsün')->default(true),
                    ])->columns(2),
                Forms\Components\Section::make('Qutunun üstündə')
                    ->schema([
                        Forms\Components\ViewField::make('preview')
                            ->view('filament.wrapping-preview')
                            ->dehydrated(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('pattern')->label('')->disk('public')->height(56)->square(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Ad')
                    ->weight('bold')
                    ->searchable()
                    ->description(fn (Wrapping $w) => Wrapping::RIBBONS[$w->ribbon] ?? $w->ribbon),
                Tables\Columns\ColorColumn::make('ribbon_color')->label('Lent'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Qiymət')
                    ->formatStateUsing(fn ($state) => Price::format((float) $state))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Saytda')->boolean(),
            ])
            ->defaultSort('price')
            ->reorderable('sort_order')
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->emptyStateHeading('Hələ qablaşdırma yoxdur')
            ->emptyStateDescription('Əlavə edilməyincə müştəri qablaşdırma seçimini görmür.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWrappings::route('/'),
            'create' => Pages\CreateWrapping::route('/create'),
            'edit' => Pages\EditWrapping::route('/{record}/edit'),
        ];
    }
}
