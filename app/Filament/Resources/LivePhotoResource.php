<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\LivePhotoResource\Pages;
use App\Models\LivePhoto;
use App\Support\YandexDisk;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Live photos (augmented reality): a printed picture, a video on Yandex Disk
 * and the QR code that makes one play over the other in a phone's camera.
 */
class LivePhotoResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = LivePhoto::class;

    protected static ?string $navigationIcon = 'heroicon-o-play-circle';

    protected static ?string $navigationLabel = 'Canlı şəkillər (AR)';

    protected static ?string $modelLabel = 'canlı şəkil';

    protected static ?string $pluralModelLabel = 'canlı şəkillər';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Canlı şəkil')
                    ->description('Qutuda çap olunan şəkli və Yandex Diskdəki videonu verin. Saxlayandan sonra "Hədəfi hazırla" düyməsi ilə şəkli kamera üçün hazırlayın və QR kodu götürün.')
                    ->schema([
                        Forms\Components\TextInput::make('title')->label('Ad')->required()->maxLength(120)
                            ->placeholder('Məs. Sifariş #25 — Aysel'),
                        Forms\Components\TextInput::make('video_url')
                            ->label('Video — Yandex Disk linki')
                            ->placeholder('https://disk.yandex.ru/i/…')
                            ->helperText('Videonu Yandex Diskə yükləyin, "Paylaş" edin və linki bura yapışdırın. Video hostinqdə saxlanmır.')
                            ->required()
                            ->maxLength(500)
                            ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                                if (! YandexDisk::isPublicLink($value)) {
                                    $fail('Bu Yandex Disk linki deyil. Link belə görünməlidir: https://disk.yandex.ru/i/…');
                                }
                            }),
                        Forms\Components\FileUpload::make('target_image')
                            ->label('Qutudakı şəkil (çap olunan kimi)')
                            ->helperText('Kamera məhz bu şəkli axtaracaq: qutuda necə çap olunubsa, elə olsun. Çox detallı, kontrastlı şəkillər daha yaxşı tanınır.')
                            ->image()
                            ->disk('public')
                            ->directory('live')
                            ->maxSize(8192)
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')->label('İşləsin')->default(true),
                        Forms\Components\Hidden::make('order_item_id'),
                    ])->columns(2),
                Forms\Components\Section::make('Hazırlıq və QR kod')
                    ->schema([
                        Forms\Components\ViewField::make('ar_panel')->view('filament.live-photo-panel')->dehydrated(false),
                    ])
                    ->visible(fn (?LivePhoto $record) => (bool) $record),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('target_image')->label('')->disk('public')->height(56),
                Tables\Columns\TextColumn::make('title')->label('Ad')->searchable()->weight('bold')
                    ->description(fn (LivePhoto $l) => $l->url()),
                Tables\Columns\IconColumn::make('ready')->label('Hazır')->boolean()
                    ->getStateUsing(fn (LivePhoto $l) => filled($l->target_mind)),
                Tables\Columns\TextColumn::make('views')->label('Baxış')->sortable(),
                Tables\Columns\TextColumn::make('orderItem.order_id')->label('Sifariş')
                    ->formatStateUsing(fn ($state) => $state ? '#' . $state : null)->placeholder('—'),
                Tables\Columns\ToggleColumn::make('is_active')->label('İşləyir'),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\Action::make('open')->label('Aç')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (LivePhoto $l) => $l->url())->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Hələ canlı şəkil yoxdur')
            ->emptyStateDescription('Şəkil və video əlavə edin — QR kodu qutuya çap edin, müştəri telefonu tutanda şəkil canlanacaq.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLivePhotos::route('/'),
            'create' => Pages\CreateLivePhoto::route('/create'),
            'edit' => Pages\EditLivePhoto::route('/{record}/edit'),
        ];
    }
}
