<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\HeroSlideResource\Pages;
use App\Models\HeroSlide;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * The home page's opening banner: each slide its own words, buttons and
 * picture. With more than one shown they turn on their own.
 */
class HeroSlideResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = HeroSlide::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Slaydlar';

    protected static ?string $modelLabel = 'slayd';

    protected static ?string $pluralModelLabel = 'slaydlar';

    protected static ?int $navigationSort = 85;

    /** Pages a button can lead to, offered as suggestions; any link can be typed. */
    public const LINKS = ['#collections', '/dizaynlar', '/qablasdirma', '#how', '#faq'];

    private static function linkField(string $name, string $label): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($name)
            ->label($label)
            ->datalist(self::LINKS)
            ->placeholder('/dizaynlar')
            ->maxLength(255)
            ->helperText('Saytın səhifəsi (/dizaynlar, /qablasdirma), ana səhifədə yer (#collections) və ya başqa sayt (https://…).')
            ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                if (filled($value) && HeroSlide::href($value) === null) {
                    $fail('Link / ilə, # ilə və ya https:// ilə başlamalıdır.');
                }
            });
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Yeri')
                    ->description('Bu slayd hansı səhifənin başında dursun.')
                    ->schema([
                        Forms\Components\Select::make('place')
                            ->label('Səhifə')
                            ->options(HeroSlide::PLACES)
                            ->default('home')
                            ->selectablePlaceholder(false)
                            ->required(),
                    ]),
                Forms\Components\Section::make('Mətn')
                    ->schema([
                        Forms\Components\TextInput::make('eyebrow')
                            ->label('Yuxarıdakı kiçik yazı')
                            ->placeholder('Nefis Şokolad Evi')
                            ->maxLength(80),
                        Forms\Components\Textarea::make('title')
                            ->label('Başlıq')
                            ->helperText('Yeni sətir üçün Enter basın.')
                            ->rows(2)
                            ->required()
                            ->maxLength(160),
                        Forms\Components\Textarea::make('text')
                            ->label('Mətn')
                            ->rows(3)
                            ->maxLength(400),
                        Forms\Components\TagsInput::make('badges')
                            ->label('Nöqtəli yazılar')
                            ->placeholder('Məs. Premium Şokolad')
                            ->helperText('Başlığın altındakı qısa üstünlüklər — yazıb Enter basın. '
                                . '«{dizayn}» yazsanız, saytda satışdakı dizaynların sayı görünəcək: '
                                . 'kataloq dəyişəndə rəqəmi əl ilə düzəltmək lazım gəlməyəcək.')
                            ->reorderable(),
                    ]),
                Forms\Components\Section::make('Düymələr')
                    ->schema([
                        Forms\Components\TextInput::make('button1_label')->label('Əsas düymə')->placeholder('İndi Sifariş Ver')->maxLength(40),
                        self::linkField('button1_url', 'Əsas düymənin linki'),
                        Forms\Components\TextInput::make('button2_label')->label('İkinci düymə')->placeholder('Dizaynlara Bax')->maxLength(40),
                        self::linkField('button2_url', 'İkinci düymənin linki'),
                    ])->columns(2),
                Forms\Components\Section::make('Şəkil')
                    ->description('Sağdakı böyük şəkil. Boş qalsa, hazırkı dekor göstərilir.')
                    ->schema([
                        Forms\Components\FileUpload::make('image')
                            ->label('Şəkil')
                            ->image()
                            ->disk('public')
                            ->directory('hero')
                            ->maxSize(8192)
                            ->imageEditor()
                            ->columnSpanFull(),
                        Forms\Components\Select::make('image_fit')
                            ->label('Şəkil necə yerləşsin')
                            ->options(['cover' => 'Çərçivəni doldursun (kənarları kəsilə bilər)', 'contain' => 'Tam görünsün'])
                            ->default('cover')
                            ->required(),
                        Forms\Components\TextInput::make('ribbon')
                            ->label('Küncdəki lent')
                            ->placeholder('Fərdi Hədiyyə')
                            ->helperText('Boş qalsa, lent göstərilmir.')
                            ->maxLength(30),
                    ])->columns(2),
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Toggle::make('is_active')->label('Saytda göstər')->default(true),
                        Forms\Components\TextInput::make('sort_order')->label('Sıra nömrəsi')->numeric()->default(0),
                    ])->columns(2),
                \App\Filament\Forms\Translations::section(['eyebrow' => 'Üst yazı', 'title' => 'Başlıq', 'text' => 'Mətn', 'button1_label' => '1-ci düymə', 'button2_label' => '2-ci düymə'], ['title', 'text']),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')->label('')->disk('public')->height(56),
                Tables\Columns\TextColumn::make('title')
                    ->label('Başlıq')
                    ->formatStateUsing(fn (string $state) => Str::limit(str_replace("\n", ' ', $state), 60))
                    ->description(fn (HeroSlide $s) => $s->eyebrow)
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('place')
                    ->label('Səhifə')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => HeroSlide::PLACES[$state] ?? HeroSlide::PLACES['home']),
                Tables\Columns\ToggleColumn::make('is_active')->label('Saytda'),
            ])
            /* Dragging sets the order inside one page, so the list is read one
               page at a time — mixed together, the handles would lie. */
            ->groups([
                Tables\Grouping\Group::make('place')
                    ->label('Səhifə')
                    ->getTitleFromRecordUsing(fn (HeroSlide $s) => HeroSlide::PLACES[$s->place] ?? HeroSlide::PLACES['home']),
            ])
            ->defaultGroup('place')
            ->filters([
                Tables\Filters\SelectFilter::make('place')->label('Səhifə')->options(HeroSlide::PLACES),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\ReplicateAction::make()->label('Kopyala')
                ->beforeReplicaSaved(fn (HeroSlide $replica) => $replica->fill(['is_active' => false, 'image' => null])),
                Tables\Actions\DeleteAction::make()])
            ->emptyStateHeading('Slayd yoxdur')
            ->emptyStateDescription('Slayd olmayanda səhifə birbaşa məzmunla başlayır.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHeroSlides::route('/'),
            'create' => Pages\CreateHeroSlide::route('/create'),
            'edit' => Pages\EditHeroSlide::route('/{record}/edit'),
        ];
    }
}
