<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Support\Media;
use Filament\Forms;
use Filament\Forms\Form;
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
                    ->description('Qutunun şəkilləri, foto və mətn sahələri "Qutu redaktoru"nda qurulur — yaratdıqdan sonra ora keçəcəksiniz.')
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
                        Forms\Components\Select::make('category')
                            ->label('Kateqoriya')
                            ->options(Product::CATEGORIES)
                            ->helperText('Dizaynlar səhifəsində qruplaşdırma üçün.'),
                        Forms\Components\TextInput::make('price')
                            ->label('Qiymət (₼)')
                            ->numeric()
                            ->suffix('₼')
                            ->helperText('Boş buraxsanız "Qiymət sorğu ilə" göstərilir.'),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sıra nömrəsi')
                            ->numeric()
                            ->default(0),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Saytda görünsün')
                            ->default(true),
                    ])->columns(2),
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
                    ->searchable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Kateqoriya')
                    ->formatStateUsing(fn (?string $state) => Product::CATEGORIES[$state] ?? '—')
                    ->badge(),
                Tables\Columns\IconColumn::make('template_image')
                    ->label('Fərdiləşir')
                    ->boolean()
                    ->getStateUsing(fn (Product $record) => $record->isCustomizable()),
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
            ->modifyQueryUsing(fn ($query) => $query->withCount('layers'))
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Kateqoriya')
                    ->options(Product::CATEGORIES),
            ])
            ->actions([
                Tables\Actions\Action::make('editor')
                    ->label('Redaktor')
                    ->icon('heroicon-o-paint-brush')
                    ->url(fn (Product $record) => route('box.edit', $record->slug)),
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
