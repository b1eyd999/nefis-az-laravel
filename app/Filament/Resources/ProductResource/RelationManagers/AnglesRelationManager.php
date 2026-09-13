<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AnglesRelationManager extends RelationManager
{
    protected static string $relationship = 'angles';

    protected static ?string $title = 'Əlavə bucaqlar (çevirmə)';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('label')
                    ->label('Ad (məs. "Yan görünüş")'),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Sıra nömrəsi')
                    ->numeric()
                    ->default(0),

                Forms\Components\FileUpload::make('template_image')
                    ->label('Şəkil')
                    ->image()
                    ->disk('public')
                    ->directory('products')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        if (! $state) {
                            return;
                        }
                        $path = method_exists($state, 'getRealPath') ? $state->getRealPath() : null;
                        if ($path && $size = @getimagesize($path)) {
                            $set('template_width', $size[0]);
                            $set('template_height', $size[1]);
                        }
                    })
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('template_width')
                    ->label('Şəklin eni (px)')
                    ->numeric()
                    ->required()
                    ->default(1000),
                Forms\Components\TextInput::make('template_height')
                    ->label('Şəklin hündürlüyü (px)')
                    ->numeric()
                    ->required()
                    ->default(1000),

                Forms\Components\Section::make('Foto sahəsi')
                    ->schema([
                        Forms\Components\TextInput::make('photo_area_x')
                            ->label('X (soldan)')->numeric()->required()->default(0),
                        Forms\Components\TextInput::make('photo_area_y')
                            ->label('Y (yuxarıdan)')->numeric()->required()->default(0),
                        Forms\Components\TextInput::make('photo_area_width')
                            ->label('En')->numeric()->required()->default(100),
                        Forms\Components\TextInput::make('photo_area_height')
                            ->label('Hündürlük')->numeric()->required()->default(100),
                        Forms\Components\TextInput::make('photo_area_rotation')
                            ->label('Bucaq (dərəcə)')->numeric()->required()->default(0),
                        Forms\Components\Select::make('photo_area_shape')
                            ->label('Forma')
                            ->options(['rectangle' => 'Düzbucaqlı', 'ellipse' => 'Oval'])
                            ->default('rectangle')
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Mətn sahəsi')
                    ->schema([
                        Forms\Components\Toggle::make('allow_text')
                            ->label('Fərdi mətnə icazə ver')
                            ->live()
                            ->default(true),
                        Forms\Components\TextInput::make('text_x')
                            ->label('X')->numeric()->required()->default(0)
                            ->visible(fn (Get $get) => $get('allow_text')),
                        Forms\Components\TextInput::make('text_y')
                            ->label('Y')->numeric()->required()->default(0)
                            ->visible(fn (Get $get) => $get('allow_text')),
                        Forms\Components\TextInput::make('text_max_width')
                            ->label('Maks en')->numeric()->required()->default(300)
                            ->visible(fn (Get $get) => $get('allow_text')),
                        Forms\Components\TextInput::make('text_font_size')
                            ->label('Şrift ölçüsü')->numeric()->required()->default(32)
                            ->visible(fn (Get $get) => $get('allow_text')),
                        Forms\Components\ColorPicker::make('text_color')
                            ->label('Rəng')->default('#3A2617')
                            ->visible(fn (Get $get) => $get('allow_text')),
                        Forms\Components\Select::make('text_align')
                            ->label('Düzülüş')
                            ->options(['left' => 'Sol', 'center' => 'Mərkəz', 'right' => 'Sağ'])
                            ->default('center')
                            ->visible(fn (Get $get) => $get('allow_text')),
                    ])->columns(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                Tables\Columns\ImageColumn::make('template_image')
                    ->label('Şəkil')
                    ->disk('public'),
                Tables\Columns\TextColumn::make('label')
                    ->label('Ad')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sıra')
                    ->numeric(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
