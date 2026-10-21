<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BundleResource\Pages;
use App\Models\Bundle;
use App\Models\Chocolate;
use App\Models\Product;
use App\Models\Wrapping;
use App\Support\Letter;
use App\Support\LiveMaterials;
use App\Support\Price;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Ready packages: one price, nothing for the customer to decide.
 *
 * The owner puts one together here — this design, this bar, this paper, the
 * letter, the live photograph — gives the lot one price, and then hangs it
 * on a banner. The customer presses the banner's button and lands on the
 * design page with all of it already settled; he adds his photograph and
 * his words, and that is the whole of it.
 *
 * The parts cannot be changed on the page, by him or by a tampered form:
 * the price belongs to the package as a whole, and the basket reads the
 * package again rather than believing what was posted.
 */
class BundleResource extends Resource
{
    protected static ?string $model = Bundle::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift-top';

    protected static ?string $navigationGroup = 'Satış';

    protected static ?string $modelLabel = 'Paket';

    protected static ?string $pluralModelLabel = 'Paketlər';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Paket')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Paketin adı')
                        ->helperText('Müştəri bunu dizayn səhifəsində görür. Məsələn «Yeni il dəsti 3-ü 1-də».')
                        ->required()->maxLength(80),

                    Forms\Components\Select::make('product_id')
                        ->label('Dizayn')
                        ->relationship('product', 'name', fn ($query) => $query->where('is_active', true)->orderBy('name'))
                        ->searchable()->preload()->required()
                        ->helperText('Hansı qutu. Paketin linki elə bu dizaynın səhifəsini açır.'),

                    Forms\Components\Select::make('chocolate_id')
                        ->label('Şokolad')
                        ->options(fn () => Chocolate::shown()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->preload()->placeholder('Şokoladsız'),

                    Forms\Components\Select::make('wrapping_id')
                        ->label('Qablaşdırma')
                        ->options(fn () => Wrapping::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->preload()->placeholder('Qablaşdırmasız'),

                    Forms\Components\Toggle::make('letter_on')
                        ->label('Polaroid məktub paketə daxildir')
                        ->helperText(fn () => Letter::enabled()
                            ? 'Müştəri yenə də öz şəklini və sözlərini yazır — sadəcə qiyməti paketin içindədir.'
                            : 'Polaroid məktub hazırda saytda söndürülüb; paketə qoysanız da görünməyəcək.'),

                    Forms\Components\Toggle::make('ar_on')
                        ->label('Canlı şəkil (AR) paketə daxildir')
                        ->helperText(fn () => LiveMaterials::enabled()
                            ? 'Müştəri videonu özü yükləyir.'
                            : 'Canlı şəkil hazırda saytda söndürülüb; paketə qoysanız da görünməyəcək.'),
                ])->columns(2),

            Forms\Components\Section::make('Qiymət və görünüş')
                ->schema([
                    Forms\Components\TextInput::make('price')
                        ->label('Paketin qiyməti, ₼')
                        ->numeric()->step(0.01)->minValue(0)->required()
                        ->helperText(fn (?Bundle $record) => $record?->fullPrice()
                            ? 'Ayrı-ayrı alınsa ' . Price::format($record->fullPrice())
                                . ' olardı — səhifədə həmin rəqəm üstündən xətlə göstərilir.'
                            : 'Ayrıca alınanda nə qədər olduğunu saxlayıb yazsanız, səhifədə köhnə qiymət üstündən xətli görünür.'),

                    Forms\Components\FileUpload::make('image')
                        ->label('Şəkil (banner üçün)')
                        ->image()->disk('public')->directory('bundles')->maxSize(8192),

                    Forms\Components\Toggle::make('is_active')->label('İşləyir')->default(true),
                    Forms\Components\TextInput::make('sort_order')->label('Sıra')->numeric()->default(0),
                ])->columns(2),

            Forms\Components\Placeholder::make('link')
                ->label('Bannerin düyməsi üçün link')
                ->content(fn (?Bundle $record) => $record?->exists
                    ? $record->url()
                    : 'Paketi yadda saxlayandan sonra burada görünəcək — onu Banner səhifəsində «Düymənin linki» xanasına qoyun.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('image')->label('')->disk('public')->square(),
                Tables\Columns\TextColumn::make('name')->label('Paket')->searchable()
                    ->description(fn (Bundle $row) => $row->product?->name),
                Tables\Columns\TextColumn::make('parts')->label('İçində')
                    ->getStateUsing(fn (Bundle $row) => collect([
                        $row->chocolate?->name,
                        $row->wrapping?->name,
                        $row->letter_on ? 'polaroid məktub' : null,
                        $row->ar_on ? 'canlı şəkil' : null,
                    ])->filter()->implode(', ') ?: '—')
                    ->wrap()->visibleFrom('md'),
                Tables\Columns\TextColumn::make('price')->label('Qiymət')
                    ->formatStateUsing(fn ($state) => Price::format((float) $state))
                    ->description(fn (Bundle $row) => $row->fullPrice()
                        ? 'ayrıca ' . Price::format($row->fullPrice())
                        : null),
                Tables\Columns\ToggleColumn::make('is_active')->label('İşləyir'),
            ])
            ->actions([
                Tables\Actions\Action::make('copy')
                    ->label('Linki götür')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->action(fn () => null)
                    ->modalHeading('Bannerin düyməsi üçün link')
                    ->modalDescription(fn (Bundle $row) => $row->url())
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Bağla'),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()])
            ->emptyStateHeading('Hələ paket yoxdur')
            ->emptyStateDescription('Bir dizayn, bir şokolad, bir qablaşdırma və bir qiymət — sonra onu bannerə qoyursunuz.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBundles::route('/'),
            'create' => Pages\CreateBundle::route('/create'),
            'edit' => Pages\EditBundle::route('/{record}/edit'),
        ];
    }
}
