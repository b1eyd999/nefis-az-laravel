<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Forms\Translations;
use App\Filament\Resources\ProductCategoryResource\Pages;
use App\Models\ProductCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * The shelves of the catalogue. The owner adds one, renames it, drags it into
 * place — and /dizaynlar is arranged that way.
 */
class ProductCategoryResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = ProductCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationLabel = 'Kateqoriyalar';

    protected static ?string $modelLabel = 'kateqoriya';

    protected static ?string $pluralModelLabel = 'kateqoriyalar';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Kateqoriya')
                    ->description('Dizaynlar səhifəsində hər kateqoriya bir rəf olur. Sıranı siyahıda sürüşdürərək dəyişin.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Ad')
                            ->helperText('Saytda başlıq kimi görünür, məs. "Ad günü dizaynları".')
                            ->required()
                            ->maxLength(80)
                            ->live(onBlur: true)
                            // The key is made from the name once, when the shelf is
                            // put up; renaming it later leaves the designs alone.
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state, ?ProductCategory $record) {
                                if (! $record && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        Forms\Components\TextInput::make('slug')
                            ->label('Açar')
                            ->helperText('Dizaynların bu kateqoriyaya bağlandığı söz. Sonradan dəyişmək dizaynları bu rəfdən çıxarır.')
                            ->required()
                            ->maxLength(40)
                            ->unique(ignoreRecord: true)
                            ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->validationMessages(['regex' => 'Yalnız kiçik latın hərfləri, rəqəmlər və tire: "ad-gunu".']),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Saytda görünsün')
                            ->default(true)
                            ->helperText('Söndürülsə, rəf kataloqda göstərilmir; dizaynlar "Digər" başlığı altında qalır.'),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sıra')
                            ->numeric()->default(0)
                            ->helperText('Kiçik rəqəm əvvəl görünür.'),
                    ])->columns(2),
                Translations::section(['name' => 'Ad']),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Ad')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->label('Açar')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Dizayn')
                    ->counts('products')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Saytda')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->paginated([25, 50, 'all'])
            ->actions([
                Tables\Actions\EditAction::make(),
                // A shelf with designs on it is switched off, never deleted:
                // deleting it would leave them filed under a word nobody shows.
                Tables\Actions\DeleteAction::make()
                    ->disabled(fn (ProductCategory $r) => $r->products()->exists())
                    ->tooltip(fn (ProductCategory $r) => $r->products()->exists()
                        ? 'Bu kateqoriyada dizaynlar var. Əvvəlcə onları başqa kateqoriyaya keçirin və ya rəfi söndürün.'
                        : null),
            ])
            ->emptyStateHeading('Kateqoriya yoxdur')
            ->emptyStateDescription('Kataloqdakı rəflər buradan qurulur.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductCategories::route('/'),
            'create' => Pages\CreateProductCategory::route('/create'),
            'edit' => Pages\EditProductCategory::route('/{record}/edit'),
        ];
    }
}
