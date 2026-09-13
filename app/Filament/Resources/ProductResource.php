<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
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
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Ad')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug (URL)')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Forms\Components\Textarea::make('description')
                            ->label('Açıqlama')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('tag')
                            ->label('Etiket (məs. "Populyar")'),
                        Forms\Components\TextInput::make('price')
                            ->label('Qiymət (₼)')
                            ->numeric()
                            ->suffix('₼')
                            ->helperText('Boş buraxsanız "Qiymət sorğu ilə" göstərilir.'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktivdir')
                            ->default(true),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sıra nömrəsi')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                Forms\Components\Section::make('Qutu şəkli')
                    ->description('Qutunun əsas mokup şəkli. Yüklədikdən sonra en/hündürlük avtomatik dolacaq.')
                    ->schema([
                        Forms\Components\FileUpload::make('template_image')
                            ->label('Şəkil')
                            ->image()
                            ->disk('public')
                            ->directory('products')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (! $state) {
                                    return;
                                }
                                $path = method_exists($state, 'getRealPath') ? $state->getRealPath() : null;
                                if ($path && $size = @getimagesize($path)) {
                                    $set('template_width', $size[0]);
                                    $set('template_height', $size[1]);
                                }
                            })
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('template_width')
                            ->label('Şəklin eni (px)')
                            ->numeric()
                            ->required()
                            ->default(1000),
                        Forms\Components\TextInput::make('template_height')
                            ->label('Şəklin hündürlüyü (px)')
                            ->numeric()
                            ->required()
                            ->default(1000),
                    ]),

                Forms\Components\Section::make('Foto sahəsi')
                    ->description('Müştərinin şəklinin qutu üzərində hansı sahəyə yerləşəcəyini piksel dəyərləri ilə təyin edin (yuxarıdakı şəklin əsl ölçüsünə görə).')
                    ->schema([
                        Forms\Components\TextInput::make('photo_area_x')
                            ->label('X (soldan)')->numeric()->required()->default(0),
                        Forms\Components\TextInput::make('photo_area_y')
                            ->label('Y (yuxarıdan)')->numeric()->required()->default(0),
                        Forms\Components\TextInput::make('photo_area_width')
                            ->label('En')->numeric()->required()->default(100),
                        Forms\Components\TextInput::make('photo_area_height')
                            ->label('Hündürlük')->numeric()->required()->default(100),
                        Forms\Components\TextInput::make('photo_area_rotation')
                            ->label('Bucaq (dərəcə)')->numeric()->required()->default(0),
                    ])->columns(3),

                Forms\Components\Section::make('Mətn sahəsi')
                    ->schema([
                        Forms\Components\Toggle::make('allow_text')
                            ->label('Fərdi mətnə icazə ver')
                            ->live()
                            ->default(true),
                        Forms\Components\TextInput::make('text_x')
                            ->label('X')->numeric()->required()->default(0)
                            ->visible(fn (Get $get) => $get('allow_text')),
                        Forms\Components\TextInput::make('text_y')
                            ->label('Y')->numeric()->required()->default(0)
                            ->visible(fn (Get $get) => $get('allow_text')),
                        Forms\Components\TextInput::make('text_max_width')
                            ->label('Maks en')->numeric()->required()->default(300)
                            ->visible(fn (Get $get) => $get('allow_text')),
                        Forms\Components\TextInput::make('text_font_size')
                            ->label('Şrift ölçüsü')->numeric()->required()->default(32)
                            ->visible(fn (Get $get) => $get('allow_text')),
                        Forms\Components\ColorPicker::make('text_color')
                            ->label('Rəng')->default('#3A2617')
                            ->visible(fn (Get $get) => $get('allow_text')),
                        Forms\Components\Select::make('text_align')
                            ->label('Düzülüş')
                            ->options(['left' => 'Sol', 'center' => 'Mərkəz', 'right' => 'Sağ'])
                            ->default('center')
                            ->visible(fn (Get $get) => $get('allow_text')),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('template_image')
                    ->label('Şəkil')
                    ->disk('public'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Ad')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tag')
                    ->label('Etiket'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Qiymət')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state) . ' ₼' : 'Sorğu ilə')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktiv')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sıra')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Yaradılıb')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AnglesRelationManager::class,
        ];
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
