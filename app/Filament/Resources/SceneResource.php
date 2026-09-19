<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AdminOnly;
use App\Filament\Resources\SceneResource\Pages;
use App\Models\Scene;
use App\Support\Media;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SceneResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = Scene::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Səhnələr';

    protected static ?string $modelLabel = 'səhnə';

    protected static ?string $pluralModelLabel = 'səhnələr';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Səhnə')
                    ->description('Müştərinin qutusunu gördüyü mokap. Fon, qutu renderi və dizaynın yeri "Səhnə redaktoru"nda qurulur — yaratdıqdan sonra ora keçəcəksiniz.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Ad')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sıra nömrəsi')
                            ->helperText('Kiçik nömrə əvvəl göstərilir.')
                            ->numeric()
                            ->default(0),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Saytda göstər')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('preview_image')
                    ->label('Görünüş')
                    ->height(80)
                    ->getStateUsing(fn (Scene $record) => Media::url($record->preview_image ?: $record->background)),
                Tables\Columns\TextColumn::make('name')
                    ->label('Ad')
                    ->searchable(),
                Tables\Columns\TextColumn::make('elements')
                    ->label('Dizayn yeri')
                    ->getStateUsing(fn (Scene $record) => collect($record->elements ?? [])->where('type', 'design')->count())
                    ->formatStateUsing(fn ($state) => $state ? $state . ' ədəd' : 'Yoxdur')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'danger'),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Saytda'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sıra')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Dəyişib')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\Action::make('editor')
                    ->label('Redaktor')
                    ->icon('heroicon-o-paint-brush')
                    ->url(fn (Scene $record) => route('scene.edit', $record)),
                Tables\Actions\Action::make('copy')
                    ->label('Kopyala')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function (Scene $record) {
                        $copy = $record->replicate(['preview_image']);
                        $copy->name = $record->name . ' (kopya)';
                        $copy->is_active = false;
                        $copy->save();
                    }),
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListScenes::route('/'),
            'create' => Pages\CreateScene::route('/create'),
            'edit' => Pages\EditScene::route('/{record}/edit'),
        ];
    }
}
