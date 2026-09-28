<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\WithdrawalResource\Pages;
use App\Models\Withdrawal;
use App\Support\Price;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Money taken out of the till, and what it went for. */
class WithdrawalResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = Withdrawal::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Mühasibatlıq';

    protected static ?string $navigationLabel = 'Pul çıxarışı';

    protected static ?string $modelLabel = 'çıxarış';

    protected static ?string $pluralModelLabel = 'çıxarışlar';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('taken_on')->label('Tarix')->default(now())->required()
                    ->native(false)->displayFormat('d.m.Y'),
                Forms\Components\TextInput::make('amount')->label('Məbləğ')->numeric()->minValue(0.01)->step(0.01)
                    ->suffix('₼')->required()
                    ->helperText('Kassadan çıxan məbləğ. Mənfəətə toxunmur — qazanılan qazanılıb, bu sadəcə əldə qalan puldur.'),
                Forms\Components\TextInput::make('purpose')->label('Nə üçün')->required()->maxLength(120)
                    ->datalist(Withdrawal::PURPOSES)->placeholder('Məs. Pay ödənişi'),
                Forms\Components\TextInput::make('note')->label('Qeyd')->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('taken_on')->label('Tarix')->date('d.m.Y')->sortable(),
                Tables\Columns\TextColumn::make('purpose')->label('Nə üçün')->badge()->color('warning')->searchable(),
                Tables\Columns\TextColumn::make('amount')->label('Məbləğ')->sortable()
                    ->formatStateUsing(fn ($state) => Price::format($state))
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Cəmi')
                        ->formatStateUsing(fn ($state) => Price::format((float) $state))),
                Tables\Columns\TextColumn::make('user.name')->label('Kim')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('note')->label('Qeyd')->placeholder('—')->wrap(),
            ])
            ->defaultSort('taken_on', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('purpose')->label('Nə üçün')
                    ->options(fn () => Withdrawal::query()->distinct()->orderBy('purpose')->pluck('purpose', 'purpose')->all()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Hələ pul çıxarılmayıb')
            ->emptyStateDescription('Kassadan pul götürəndə bura yazın: nə qədər və nə üçün. Balansda "Kassa" o qədər azalacaq.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageWithdrawals::route('/'),
        ];
    }
}
