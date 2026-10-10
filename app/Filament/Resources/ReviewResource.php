<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * What customers have written, and whether anybody else sees it.
 *
 * Nothing goes up on its own. A hand-made gift goes wrong in ways worth
 * answering privately first — a late delivery, a photograph that came out
 * dark — and a shop that can answer before the page does will usually have
 * a happier customer than one that cannot.
 */
class ReviewResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = Review::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Rəylər';

    protected static ?string $modelLabel = 'rəy';

    protected static ?string $pluralModelLabel = 'rəylər';

    protected static ?int $navigationSort = 4;

    /** Ones nobody has looked at yet sit as a number in the menu. */
    public static function getNavigationBadge(): ?string
    {
        $waiting = static::getModel()::whereNull('approved_at')->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Rəy')
                ->schema([
                    Forms\Components\Placeholder::make('whose')
                        ->label('Kim yazdı')
                        ->content(fn (?Review $record) => $record
                            ? $record->user?->name . ' — sifariş #' . $record->order_id
                                . ($record->product ? ' · ' . $record->product->name : '')
                            : '—'),
                    Forms\Components\Placeholder::make('stars_view')
                        ->label('Ulduz')
                        ->content(fn (?Review $record) => $record
                            ? str_repeat('★', (int) $record->stars) . str_repeat('☆', Review::MOST - (int) $record->stars)
                                . '  ' . $record->stars . '/5'
                            : '—'),
                    Forms\Components\Textarea::make('body')->label('Yazdığı')->rows(5)
                        ->maxLength(2000)->columnSpanFull(),
                    Forms\Components\TextInput::make('shown_name')
                        ->label('Saytda görünən ad')
                        ->maxLength(60)
                        ->helperText('Boş qoysanız, yalnız adı görünür — soyadı yox.'),
                    Forms\Components\Placeholder::make('photo_view')
                        ->label('Müştərinin şəkli')
                        ->content(fn (?Review $record) => $record?->photoUrl()
                            ? new HtmlString('<a href="' . e($record->photoUrl()) . '" target="_blank" rel="noopener">'
                                . '<img src="' . e($record->photoUrl()) . '" alt="" style="max-height:16rem; max-width:100%; '
                                . 'border-radius:.6rem; border:1px solid rgba(128,128,128,.35)"></a>')
                            : 'Şəkil göndərməyib')
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('Saytda')
                ->description('Rəy siz icazə verməyincə saytda görünmür. Problemli rəydə əvvəlcə müştəri ilə '
                    . 'danışmaq, sonra qərar vermək daha yaxşıdır.')
                ->schema([
                    Forms\Components\Toggle::make('shown')
                        ->label('Saytda görünsün')
                        ->afterStateHydrated(fn (Forms\Components\Toggle $component, ?Review $record) => $component->state((bool) $record?->approved_at))
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('reply')
                        ->label('Bizim cavabımız')
                        ->rows(3)->maxLength(1000)
                        ->helperText('Rəyin altında «Nefis.az» imzası ilə görünür. Pis rəyə verilən sakit cavab '
                            . 'çox vaxt rəyin özündən daha yaxşı işləyir.')
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('approved_when')
                        ->label('Nə vaxt yerləşdirildi')
                        ->content(fn (?Review $record) => $record?->approved_at
                            ? $record->approved_at->format('d.m.Y H:i')
                                . ($record->approver ? ' — ' . $record->approver->name : '')
                            : 'Hələ yox')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Tarix')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('stars')->label('Ulduz')->sortable()
                    ->formatStateUsing(fn (int $state) => str_repeat('★', $state))
                    ->color(fn (int $state) => $state >= 4 ? 'success' : ($state === 3 ? 'warning' : 'danger')),
                Tables\Columns\TextColumn::make('user.name')->label('Müştəri')->searchable(),
                Tables\Columns\TextColumn::make('order_id')->label('Sifariş')->prefix('#')
                    ->url(fn (Review $record) => OrderResource::getUrl('edit', ['record' => $record->order_id])),
                Tables\Columns\TextColumn::make('body')->label('Yazdığı')->limit(60)->wrap()->searchable(),
                Tables\Columns\ImageColumn::make('photo')->label('Şəkil')->disk('public')->height(38)->visibleFrom('lg'),
                Tables\Columns\IconColumn::make('approved_at')->label('Saytda')->boolean()
                    ->trueIcon('heroicon-o-check-circle')->falseIcon('heroicon-o-clock')
                    ->trueColor('success')->falseColor('warning'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\Filter::make('waiting')
                    ->label('Yoxlanmayanlar')
                    ->query(fn ($query) => $query->whereNull('approved_at'))
                    ->default(),
                Tables\Filters\Filter::make('bad')
                    ->label('3 ulduz və aşağı')
                    ->query(fn ($query) => $query->where('stars', '<=', 3)),
            ])
            ->actions([
                /* One tap for the common case: he has read it and it is fine. */
                Tables\Actions\Action::make('show')
                    ->label(fn (Review $record) => $record->approved_at ? 'Saytdan götür' : 'Saytda göstər')
                    ->icon(fn (Review $record) => $record->approved_at ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (Review $record) => $record->approved_at ? 'gray' : 'success')
                    ->action(fn (Review $record) => $record->forceFill([
                        'approved_at' => $record->approved_at ? null : now(),
                        'approved_by' => $record->approved_at ? null : auth()->id(),
                    ])->save()),
                Tables\Actions\EditAction::make()->label('Aç'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
        ];
    }
}
