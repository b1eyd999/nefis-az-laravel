<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\FontResource\Pages;
use App\Models\Font;
use App\Support\Media;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * The typefaces the box editor offers for captions: the ones that ship with
 * the site, and whatever the owner uploads here.
 */
class FontResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = Font::class;

    protected static ?string $navigationIcon = 'heroicon-o-language';

    protected static ?string $navigationLabel = 'Şriftlər';

    protected static ?string $modelLabel = 'şrift';

    protected static ?string $pluralModelLabel = 'şriftlər';

    protected static ?int $navigationSort = 3;

    /** A line of the alphabet drawn in the font itself, so it can be judged. */
    public static function preview(Font $font, string $text = 'Nefis Şokolad Evi — ABCÇD əğıöşü 123'): HtmlString
    {
        $family = e($font->family);
        $face = $font->file
            ? '@font-face{font-family:"' . $family . '";src:url("' . e(Media::url($font->file)) . '");font-display:swap}'
            : '';

        return new HtmlString('<style>' . $face . '</style>'
            . '<div style="font-family:\'' . $family . '\', Inter, sans-serif; font-weight:' . (int) $font->weight
            . '; font-size:1.35rem; line-height:1.5; word-break:break-word;">' . e($text) . '</div>');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Şrift')
                    ->description('Fayl yüklədikdə qutu redaktorunda mətnlər üçün seçilə bilər.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Ad')
                            ->helperText('Redaktorda bu adla görünür, məs. "Mark Pro Bold".')
                            ->required()
                            ->maxLength(60)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('weight')
                            ->label('Qalınlıq')
                            ->numeric()->minValue(100)->maxValue(900)->step(100)->default(400)
                            ->helperText('Faylın öz qalınlığı. Artırmaq brauzerə saxta qalın çəkdirir.'),
                        Forms\Components\FileUpload::make('file')
                            ->label('Şrift faylı (TTF, OTF, WOFF, WOFF2)')
                            ->disk('public')
                            ->directory('fonts/custom')
                            ->acceptedFileTypes(['font/ttf', 'font/otf', 'font/woff', 'font/woff2',
                                'application/font-woff', 'application/x-font-ttf', 'application/octet-stream'])
                            ->maxSize(10240)
                            ->required(fn (?Font $record) => $record === null)
                            ->helperText(fn (?Font $record) => $record && ! $record->file
                                ? 'Bu şrift Google Fonts-dan gəlir, faylı yoxdur.'
                                : 'Yalnız özünüzə icazə verilən şriftləri yükləyin.')
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('preview')
                            ->label('Görünüş')
                            ->content(fn (?Font $record) => $record ? self::preview($record) : 'Fayl yüklədikdən sonra görünəcək')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Ad')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Font $r) => $r->file ? 'Yüklənmiş fayl' : 'Google Fonts'),
                Tables\Columns\TextColumn::make('preview')
                    ->label('Görünüş')
                    ->getStateUsing(fn (Font $r) => self::preview($r, 'Nefis Şokolad — ABCÇ əğıöşü 123'))
                    ->html()
                    ->wrap(),
                Tables\Columns\TextColumn::make('weight')->label('Qalınlıq')->sortable(),
            ])
            ->defaultSort('name')
            // The whole library on one page, so a face is found by eye.
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->emptyStateHeading('Şrift yoxdur');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFonts::route('/'),
            'create' => Pages\CreateFont::route('/create'),
            'edit' => Pages\EditFont::route('/{record}/edit'),
        ];
    }

    /** An uploaded face gets a family of its own, so it cannot clash. */
    public static function familyFor(string $name): string
    {
        return 'NF ' . Str::of($name)->trim();
    }
}
