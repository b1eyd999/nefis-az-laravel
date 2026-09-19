<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use App\Support\Price;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Spending other than stock (which the Anbar page records): ads, courier, rent… */
class ExpenseResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Mühasibatlıq';

    protected static ?string $navigationLabel = 'Xərclər';

    protected static ?string $modelLabel = 'xərc';

    protected static ?string $pluralModelLabel = 'xərclər';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('spent_on')->label('Tarix')->default(now())->required()->native(false)->displayFormat('d.m.Y'),
                Forms\Components\TextInput::make('amount')->label('Məbləğ')->numeric()->minValue(0.01)->step(0.01)->suffix('₼')->required(),
                Forms\Components\TextInput::make('category')->label('Növ')->required()
                    ->datalist(Expense::CATEGORIES)->placeholder('Məs. Reklam'),
                Forms\Components\TextInput::make('note')->label('Qeyd'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('spent_on')->label('Tarix')->date('d.m.Y')->sortable(),
                Tables\Columns\TextColumn::make('category')->label('Növ')->badge(),
                Tables\Columns\TextColumn::make('amount')->label('Məbləğ')->formatStateUsing(fn ($state) => Price::format($state))->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Cəmi')->formatStateUsing(fn ($state) => Price::format($state))),
                Tables\Columns\TextColumn::make('note')->label('Qeyd')->placeholder('—')->wrap(),
            ])
            ->defaultSort('spent_on', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('category')->label('Növ')
                    ->options(fn () => Expense::query()->distinct()->orderBy('category')->pluck('category', 'category')->all()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageExpenses::route('/'),
        ];
    }
}
