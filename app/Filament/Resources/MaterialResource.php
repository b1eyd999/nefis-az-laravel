<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\MaterialResource\Pages;
use App\Filament\Resources\MaterialResource\RelationManagers;
use App\Models\Material;
use App\Support\Accounting;
use App\Support\Price;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * The stock: what goes into every box, how much is left, and what one box's
 * worth costs. Orders take their materials out by themselves.
 */
class MaterialResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = Material::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Mühasibatlıq';

    protected static ?string $navigationLabel = 'Anbar';

    protected static ?string $modelLabel = 'material';

    protected static ?string $pluralModelLabel = 'anbar';

    protected static ?int $navigationSort = 2;

    private static function qty(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ' '), '0'), '.');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Material')
                    ->schema([
                        Forms\Components\TextInput::make('name')->label('Ad')->required()->placeholder('Məs. Qutu kağızı'),
                        Forms\Components\TextInput::make('unit')->label('Ölçü vahidi')->required()->default('ədəd')
                            ->helperText('Məs. vərəq, ml, ədəd, qutuluq'),
                    ])->columns(2),
                Forms\Components\Section::make('Alış və sərfiyyat')
                    ->description('Məs. 1 paçka kağız 9.80 ₼, içində 50 vərəq; bir qutuya 1 vərəq gedir.')
                    ->schema([
                        Forms\Components\TextInput::make('pack_price')->label('Bir paçkanın qiyməti')->numeric()->minValue(0)->step(0.01)->suffix('₼')->required()->live(onBlur: true),
                        Forms\Components\TextInput::make('pack_size')->label('Paçkada neçə vahid')->numeric()->minValue(0.001)->required()->live(onBlur: true),
                        Forms\Components\TextInput::make('per_box')->label('Bir qutuya sərf')->numeric()->minValue(0)->required()->live(onBlur: true)
                            ->helperText('0 — sifarişlərdə istifadə olunmur'),
                        Forms\Components\Placeholder::make('cost')
                            ->label('Bir qutuya dəyəri')
                            ->content(function (Get $get) {
                                $size = (float) $get('pack_size');
                                $unit = $size > 0 ? (float) $get('pack_price') / $size : 0;

                                return Price::format(round($unit * (float) $get('per_box'), 3)) . '  (vahidi ' . Price::format(round($unit, 4)) . ')';
                            }),
                    ])->columns(2),
                Forms\Components\Section::make('Anbar')
                    ->schema([
                        Forms\Components\TextInput::make('stock')->label('Hazırda anbarda')->numeric()->default(0)
                            ->helperText('Yeni material üçün başlanğıc miqdar. Sonra "Alış" və "Sayım" ilə dəyişin.')
                            ->disabled(fn (?Material $record) => (bool) $record)->dehydrated(fn (?Material $record) => ! $record),
                        Forms\Components\TextInput::make('low_stock')->label('Az qalanda xəbərdar et')->numeric()->helperText('Bu miqdardan az qalanda qırmızı göstərilir'),
                        Forms\Components\Toggle::make('is_active')->label('Sifarişlərdə istifadə olunur')->default(true),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Material')->weight('bold')
                    ->description(fn (Material $m) => Price::format($m->pack_price) . ' / ' . self::qty($m->pack_size) . ' ' . $m->unit),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Anbarda')
                    ->formatStateUsing(fn (Material $m) => self::qty($m->stock) . ' ' . $m->unit)
                    ->description(fn (Material $m) => $m->boxesLeft() !== null && $m->unit !== 'qutuluq' ? '≈ ' . $m->boxesLeft() . ' qutuluq' : null)
                    ->color(fn (Material $m) => $m->isLow() ? 'danger' : null)
                    ->weight(fn (Material $m) => $m->isLow() ? 'bold' : null),
                Tables\Columns\TextColumn::make('per_box')
                    ->label('Bir qutuya')
                    ->formatStateUsing(fn (Material $m) => self::qty($m->per_box) . ' ' . $m->unit),
                Tables\Columns\TextColumn::make('box_cost')
                    ->label('Qutuya dəyəri')
                    ->getStateUsing(fn (Material $m) => Price::format(round($m->costPerBox(), 3))),
                Tables\Columns\IconColumn::make('is_active')->label('İstifadədə')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\Action::make('purchase')
                    ->label('Alış')
                    ->icon('heroicon-o-shopping-cart')
                    ->color('success')
                    ->fillForm(fn (Material $m) => ['packs' => 1, 'price' => $m->pack_price])
                    ->form(fn (Material $m) => [
                        Forms\Components\TextInput::make('packs')->label('Neçə paçka')->numeric()->minValue(0.001)->required()
                            ->helperText('Bir paçkada ' . self::qty($m->pack_size) . ' ' . $m->unit),
                        Forms\Components\TextInput::make('price')->label('Bir paçkanın qiyməti')->numeric()->minValue(0)->step(0.01)->suffix('₼')->required(),
                        Forms\Components\TextInput::make('note')->label('Qeyd')->placeholder('Məs. harada alındı'),
                    ])
                    ->action(function (Material $m, array $data) {
                        $move = Accounting::purchase($m, (float) $data['packs'], (float) $data['price'], $data['note'] ?? null);
                        Notification::make()->success()
                            ->title($m->name . ': +' . self::qty($move->quantity) . ' ' . $m->unit)
                            ->body('Xərc: ' . Price::format($move->amount))->send();
                    }),
                Tables\Actions\Action::make('count')
                    ->label('Sayım')
                    ->icon('heroicon-o-calculator')
                    ->fillForm(fn (Material $m) => ['counted' => $m->stock])
                    ->form(fn (Material $m) => [
                        Forms\Components\TextInput::make('counted')->label('Anbarda əslində neçə ' . $m->unit . ' var')->numeric()->minValue(0)->required(),
                        Forms\Components\TextInput::make('note')->label('Qeyd'),
                    ])
                    ->action(function (Material $m, array $data) {
                        Accounting::adjust($m, (float) $data['counted'], $data['note'] ?? null);
                        Notification::make()->success()->title($m->name . ': ' . self::qty((float) $data['counted']) . ' ' . $m->unit)->send();
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\MovementsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterials::route('/'),
            'create' => Pages\CreateMaterial::route('/create'),
            'edit' => Pages\EditMaterial::route('/{record}/edit'),
        ];
    }
}
