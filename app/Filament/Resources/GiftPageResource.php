<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\GiftPageResource\Pages;
use App\Models\GiftPage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Gift-idea pages for Google and Yandex: one per search people make
 * ("ad günü hədiyyəsi", "sevgiliyə hədiyyə"…), at nefis.az/hediyye/{slug}.
 */
class GiftPageResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = GiftPage::class;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?string $navigationLabel = 'Hədiyyə səhifələri (SEO)';

    protected static ?string $modelLabel = 'hədiyyə səhifəsi';

    protected static ?string $pluralModelLabel = 'hədiyyə səhifələri';

    protected static ?string $slug = 'gift-pages';

    protected static ?int $navigationSort = 86;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Səhifə')
                    ->schema([
                        Forms\Components\TextInput::make('menu_label')
                            ->label('Qısa ad')
                            ->placeholder('Ad günü')
                            ->helperText('Menyuda, düymələrdə və footerdə görünür.')
                            ->required()
                            ->maxLength(40),
                        Forms\Components\TextInput::make('emoji')->label('Emoji')->placeholder('🎂')->maxLength(8),
                        Forms\Components\TextInput::make('title')
                            ->label('Başlıq (H1)')
                            ->placeholder('Ad günü üçün fərdi hədiyyə')
                            ->helperText('Səhifənin böyük başlığı. İnsanların Google-da yazdığı sözləri işlədin.')
                            ->required()
                            ->maxLength(120)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('slug')
                            ->label('Link')
                            ->prefix('nefis.az/hediyye/')
                            ->placeholder('ad-gunu')
                            ->helperText('Yalnız kiçik latın hərfləri, rəqəmlər və "-". Səhifə Google-a düşəndən sonra dəyişməyin.')
                            ->required()
                            ->maxLength(80)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('eyebrow')->label('Başlığın üstündəki kiçik yazı')->placeholder('Ad günü hədiyyəsi')->maxLength(80),
                        Forms\Components\Textarea::make('intro')
                            ->label('Giriş mətni')
                            ->helperText('Başlığın altındakı 1–2 cümlə.')
                            ->rows(3)
                            ->maxLength(400)
                            ->columnSpanFull(),
                    ])->columns(2),
                Forms\Components\Section::make('Google nəticəsində necə görünsün')
                    ->description('Boş qalsa, başlıq və giriş mətni götürülür.')
                    ->schema([
                        Forms\Components\TextInput::make('meta_title')
                            ->label('Axtarışdakı başlıq')
                            ->placeholder('Ad günü hədiyyəsi — şəkilli şokolad qutusu | Nefis')
                            ->helperText('Tövsiyə: 50–65 simvol.')
                            ->maxLength(90),
                        Forms\Components\Textarea::make('meta_description')
                            ->label('Axtarışdakı təsvir')
                            ->helperText('Tövsiyə: 120–160 simvol. Nə təklif etdiyinizi və "Bakıda çatdırılma" kimi üstünlüyü yazın.')
                            ->rows(3)
                            ->maxLength(300),
                    ]),
                Forms\Components\Section::make('Məqalə')
                    ->description('Dizaynların altındakı mətn. "## Başlıq" — alt başlıq, "- " — siyahı, **qalın**.')
                    ->schema([
                        Forms\Components\MarkdownEditor::make('body')
                            ->label('')
                            ->toolbarButtons(['heading', 'bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo']),
                    ]),
                Forms\Components\Section::make('Suallar və cavablar')
                    ->description('Səhifənin sonunda göstərilir, Google da onları oxuyur.')
                    ->schema([
                        Forms\Components\Repeater::make('faq')
                            ->label('')
                            ->schema([
                                Forms\Components\TextInput::make('q')->label('Sual')->required()->maxLength(200),
                                Forms\Components\Textarea::make('a')->label('Cavab')->required()->rows(2)->maxLength(600),
                            ])
                            ->itemLabel(fn (array $state) => $state['q'] ?? null)
                            ->addActionLabel('Sual əlavə et')
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0),
                    ]),
                Forms\Components\Section::make('Dizaynlar')
                    ->schema([
                        Forms\Components\Select::make('products')
                            ->label('Bu səhifədə göstərilən dizaynlar')
                            ->relationship('products', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Boş qalsa, bütün dizaynlar göstərilir.'),
                    ]),
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Toggle::make('is_active')->label('Saytda göstər')->default(true),
                        Forms\Components\TextInput::make('sort_order')->label('Sıra nömrəsi')->numeric()->default(0),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('menu_label')
                    ->label('Səhifə')
                    ->formatStateUsing(fn (GiftPage $record) => $record->label())
                    ->description(fn (GiftPage $record) => $record->title)
                    ->weight('bold')
                    ->searchable(['menu_label', 'title']),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Link')
                    ->formatStateUsing(fn (string $state) => '/hediyye/' . $state)
                    ->url(fn (GiftPage $record) => $record->url(), shouldOpenInNewTab: true)
                    ->color('gray')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Dizayn')
                    ->counts('products')
                    ->formatStateUsing(fn (int $state) => $state ?: 'hamısı')
                    ->visibleFrom('md'),
                Tables\Columns\ToggleColumn::make('is_active')->label('Saytda'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\Action::make('view')->label('Bax')->icon('heroicon-o-eye')->color('gray')
                    ->url(fn (GiftPage $record) => $record->url())->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Səhifə yoxdur');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGiftPages::route('/'),
            'create' => Pages\CreateGiftPage::route('/create'),
            'edit' => Pages\EditGiftPage::route('/{record}/edit'),
        ];
    }
}
