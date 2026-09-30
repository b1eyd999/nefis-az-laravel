<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\LibraryAssetResource\Pages;
use App\Models\LibraryAsset;
use App\Support\ImageStore;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;

/**
 * The shelf the box editor reaches into: frames, patterns and stickers the
 * owner uploads once and then puts on any design.
 *
 * Everything is kept as WebP with its transparency intact, which is a fraction
 * of a PNG's weight — the hosting carries every one of these files.
 */
class LibraryAssetResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = LibraryAsset::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Kitabxana';

    protected static ?string $modelLabel = 'şəkil';

    protected static ?string $pluralModelLabel = 'kitabxana şəkilləri';

    protected static ?int $navigationSort = 5;

    /** Re-encoded on the way in, so a 4 MB PNG frame lands as a few hundred KB. */
    public static function keep(UploadedFile $file): string
    {
        [$path] = ImageStore::store($file, LibraryAsset::DIRECTORY, 'lib', 92, 2600);

        return $path;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Şəkil')
                    ->description('Şəffaf PNG ən yaxşısıdır: qutu redaktorunda hazır dizaynın üstünə qoyulur.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Ad')
                            ->helperText('Redaktorda bu adla görünür, məs. "Qızıl çərçivə".')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\Select::make('category')
                            ->label('Növ')
                            ->options(LibraryAsset::CATEGORIES)
                            ->default('frame')
                            ->required()
                            ->helperText('Redaktorda şəkillər bu növlərə görə süzülür.'),
                        Forms\Components\FileUpload::make('image')
                            ->label('Fayl (PNG, WebP, JPG)')
                            ->image()
                            ->disk('public')
                            ->directory(LibraryAsset::DIRECTORY)
                            ->acceptedFileTypes(['image/png', 'image/webp', 'image/jpeg'])
                            ->maxSize(20480)
                            ->saveUploadedFileUsing(fn (UploadedFile $file) => self::keep($file))
                            ->required(fn (?LibraryAsset $record) => $record === null)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Redaktorda təklif olunsun')
                            ->default(true),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sıra')
                            ->numeric()->default(0)
                            ->helperText('Kiçik rəqəm əvvəl görünür.'),
                        Forms\Components\Placeholder::make('size')
                            ->label('Ölçü')
                            ->content(fn (?LibraryAsset $record) => $record && $record->width
                                ? $record->width . ' × ' . $record->height . ' nöqtə'
                                : 'Yüklədikdən sonra görünəcək'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')->label('')->disk('public')->height(64),
                Tables\Columns\TextColumn::make('name')
                    ->label('Ad')
                    ->searchable()
                    ->sortable()
                    ->description(fn (LibraryAsset $r) => $r->width ? $r->width . ' × ' . $r->height : null),
                Tables\Columns\TextColumn::make('category')
                    ->label('Növ')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => LibraryAsset::CATEGORIES[$state] ?? $state),
                Tables\Columns\IconColumn::make('is_active')->label('Təklif olunur')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Növ')
                    ->options(LibraryAsset::CATEGORIES),
            ])
            ->defaultSort('sort_order')
            // Dragging is how the owner decides what the editor shows first.
            ->reorderable('sort_order')
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()])
            ->emptyStateHeading('Kitabxana boşdur')
            ->emptyStateDescription('Çərçivə, naxış və naklyekaları bir dəfə yükləyin — sonra hər qutuya qoyula bilər.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLibraryAssets::route('/'),
            'create' => Pages\CreateLibraryAsset::route('/create'),
            'edit' => Pages\EditLibraryAsset::route('/{record}/edit'),
        ];
    }
}
