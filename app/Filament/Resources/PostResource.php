<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\PostResource\Pages;
use App\Models\Post;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * The blog: nefis.az/bloq, and the same articles under /ru/blog and /en/blog.
 *
 * An article belongs to one language. The Russian and English ones point at
 * their Azerbaijani twin, which is what tells Google they are the same
 * article and what lets the reader switch language without losing his place.
 */
class PostResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = Post::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Bloq';

    protected static ?string $modelLabel = 'bloq yazısı';

    protected static ?string $pluralModelLabel = 'bloq yazıları';

    protected static ?string $slug = 'blog';

    protected static ?int $navigationSort = 87;

    /** Offered as suggestions in the topic field; anything can be typed. */
    public const TOPICS = ['Hədiyyə fikirləri', 'Şəkil və çap', 'Qutunun içi', 'Necə hazırlanır', 'Şirkətlər', 'Xəbər'];

    /** What the address looks like in each language. */
    public const PATHS = ['az' => 'nefis.az/bloq/', 'ru' => 'nefis.az/ru/blog/', 'en' => 'nefis.az/en/blog/'];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Yazı')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Başlıq (H1)')
                            ->placeholder('Qutuya hansı şəkli seçmək lazımdır')
                            ->helperText('Səhifənin böyük başlığı. İnsanların Google-da yazdığı sözləri işlədin.')
                            ->required()
                            ->maxLength(160)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('locale')
                            ->label('Dil')
                            ->options(Post::LOCALES)
                            ->default('az')
                            ->required()
                            ->live()
                            ->helperText('Hər yazı bir dilə aiddir. Tərcüməsini ayrıca yazı kimi əlavə edin.'),
                        Forms\Components\Select::make('alt_of')
                            ->label('Azərbaycanca variantı')
                            ->options(fn () => Post::where('locale', 'az')->orderByDesc('published_at')->pluck('title', 'id'))
                            ->searchable()
                            ->visible(fn (Forms\Get $get) => in_array($get('locale'), ['ru', 'en'], true))
                            /* Only a translation is a translation of
                               something. Moved back to Azerbaijani, the row
                               would otherwise keep pointing at the article it
                               was once paired with, and Google would be told
                               an article is its own translation. */
                            ->dehydratedWhenHidden()
                            ->dehydrateStateUsing(fn ($state, Forms\Get $get) => in_array($get('locale'), ['ru', 'en'], true) ? $state : null)
                            ->helperText('Google bilsin ki, bu, həmin yazının tərcüməsidir. Dizaynları da ondan götürür.'),
                        Forms\Components\TextInput::make('slug')
                            ->label('Link')
                            ->prefix(fn (Forms\Get $get) => self::PATHS[$get('locale')] ?? self::PATHS['az'])
                            ->placeholder('sekil-secimi')
                            ->helperText('Yalnız kiçik latın hərfləri, rəqəmlər və "-". Yazı Google-a düşəndən sonra dəyişməyin.')
                            ->required()
                            ->maxLength(120)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('topic')
                            ->label('Mövzu')
                            ->datalist(self::TOPICS)
                            ->placeholder('Şəkil və çap')
                            ->helperText('Yazının yanında kiçik etiket kimi görünür. Boş qala bilər.')
                            ->maxLength(60),
                        Forms\Components\TextInput::make('emoji')
                            ->label('Emoji')
                            ->placeholder('📸')
                            ->helperText('Şəkil qoymasanız, kartda bu görünür.')
                            ->maxLength(8),
                        Forms\Components\Textarea::make('excerpt')
                            ->label('Giriş mətni')
                            ->helperText('Başlığın altındaki 1–2 cümlə; kartlarda da bu görünür.')
                            ->rows(3)
                            ->maxLength(400)
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('cover')
                            ->label('Üst şəkil')
                            ->image()
                            ->disk('public')
                            ->directory('blog')
                            ->maxSize(8192)
                            ->imageEditor()
                            ->helperText('Kartda 16:9 çərçivəyə yerləşir. Boş qalsa, emoji görünür.')
                            ->columnSpanFull(),
                    ])->columns(2),
                Forms\Components\Section::make('Mətn')
                    ->description('"## Başlıq" — alt başlıq, "- " — siyahı, **qalın**. Linki /dizaynlar kimi yazın.')
                    ->schema([
                        Forms\Components\MarkdownEditor::make('body')
                            ->label('')
                            ->toolbarButtons(['heading', 'bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo']),
                    ]),
                Forms\Components\Section::make('Google nəticəsində necə görünsün')
                    ->description('Boş qalsa, başlıq və giriş mətni götürülür.')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('meta_title')
                            ->label('Axtarışdakı başlıq')
                            ->helperText('Tövsiyə: 50–65 simvol.')
                            ->maxLength(120),
                        Forms\Components\Textarea::make('meta_description')
                            ->label('Axtarışdakı təsvir')
                            ->helperText('Tövsiyə: 120–160 simvol.')
                            ->rows(3)
                            ->maxLength(300),
                    ]),
                Forms\Components\Section::make('Suallar və cavablar')
                    ->description('Yazının sonunda göstərilir, Google da onları oxuyur.')
                    ->collapsed()
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
                    ->collapsed()
                    ->schema([
                        Forms\Components\Select::make('products')
                            ->label('Yazının sonunda göstərilən dizaynlar')
                            ->relationship('products', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Boş qalsa, dizayn bölməsi heç görünmür — yazının mövzusuna uyğun '
                                . 'olanları seçin. Tərcümələr azərbaycanca variantın dizaynlarını götürür.'),
                    ]),
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Tarix')
                            ->seconds(false)
                            ->default(now())
                            ->required()
                            ->helperText('Yazının altında bu tarix görünür. Gələcək tarix yazsanız, '
                                . 'yazı həmin vaxta qədər saytda görünmür.'),
                        Forms\Components\Toggle::make('is_active')->label('Saytda göstər')->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Yazı')
                    ->formatStateUsing(fn (Post $record) => trim(($record->emoji ? $record->emoji . ' ' : '') . $record->title))
                    ->description(fn (Post $record) => $record->topic)
                    ->weight('bold')
                    ->wrap()
                    ->searchable(['title', 'excerpt', 'body']),
                Tables\Columns\TextColumn::make('locale')
                    ->label('Dil')
                    ->formatStateUsing(fn (string $state) => Post::LOCALES[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'ru' => 'warning',
                        'en' => 'info',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Tarix')
                    ->dateTime('d.m.Y')
                    // A date not yet reached is why the article is not on the
                    // site, so the row says so instead of looking broken.
                    ->description(fn (Post $record) => $record->published_at?->isFuture() ? 'hələ görünmür' : null)
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Link')
                    ->formatStateUsing(fn (Post $record) => (self::PATHS[$record->locale] ?? '') . $record->slug)
                    ->url(fn (Post $record) => $record->url(), shouldOpenInNewTab: true)
                    ->color('gray')
                    ->visibleFrom('lg'),
                Tables\Columns\ToggleColumn::make('is_active')->label('Saytda'),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('locale')->label('Dil')->options(Post::LOCALES),
                Tables\Filters\SelectFilter::make('topic')
                    ->label('Mövzu')
                    ->options(fn () => Post::query()->whereNotNull('topic')
                        ->distinct()->orderBy('topic')->pluck('topic', 'topic')->all()),
            ])
            ->actions([
                Tables\Actions\Action::make('view')->label('Bax')->icon('heroicon-o-eye')->color('gray')
                    ->url(fn (Post $record) => $record->url())->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Bloq yazısı yoxdur');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
