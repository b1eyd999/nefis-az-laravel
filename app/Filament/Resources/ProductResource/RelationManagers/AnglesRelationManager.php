<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Filament\Resources\ProductResource;
use Filament\Forms;
use App\Support\Media;
use Filament\Forms\Form;
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
                Forms\Components\FileUpload::make('overlay_image')
                    ->label('Üst qat (istəyə bağlı)')
                    ->image()
                    ->disk('public')
                    ->directory('products')
                    ->helperText('Müştərinin şəklinin ÜSTÜNDƏN keçən hissə — çərçivə və ön plan elementləri.')
                    ->columnSpanFull(),

                Forms\Components\Section::make('Arxa fon (istəyə bağlı)')
                    ->description('Qutu mokupunu bir səhnə/arxa fonun üzərinə "yerləşdirmək" üçün. Boş saxlasanız qutu şəkli birbaşa tam kadrda göstərilir.')
                    ->schema([
                        Forms\Components\FileUpload::make('background_image')
                            ->label('Arxa fon şəkli')
                            ->image()
                            ->disk('public')
                            ->directory('backgrounds')
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (! $state) {
                                    return;
                                }
                                $path = method_exists($state, 'getRealPath') ? $state->getRealPath() : null;
                                if ($path && $size = @getimagesize($path)) {
                                    $set('background_width', $size[0]);
                                    $set('background_height', $size[1]);
                                }
                            })
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('background_width')
                            ->label('Arxa fonun eni (px)')->numeric(),
                        Forms\Components\TextInput::make('background_height')
                            ->label('Arxa fonun hündürlüyü (px)')->numeric(),
                        Forms\Components\TextInput::make('box_area_x')
                            ->label('Qutu X (soldan)')->numeric(),
                        Forms\Components\TextInput::make('box_area_y')
                            ->label('Qutu Y (yuxarıdan)')->numeric(),
                        Forms\Components\TextInput::make('box_area_width')
                            ->label('Qutu eni')->numeric(),
                        Forms\Components\TextInput::make('box_area_height')
                            ->label('Qutu hündürlüyü')->numeric(),
                        Forms\Components\TextInput::make('box_area_rotation')
                            ->label('Qutu bucağı (dərəcə)')->numeric()->default(0),
                        Forms\Components\TextInput::make('content_x')
                            ->label('Şəklin öz X-i')->numeric(),
                        Forms\Components\TextInput::make('content_y')
                            ->label('Şəklin öz Y-i')->numeric(),
                        Forms\Components\TextInput::make('content_width')
                            ->label('Şəklin öz eni')->numeric(),
                        Forms\Components\TextInput::make('content_height')
                            ->label('Şəklin öz hündürlüyü')->numeric(),
                        Forms\Components\TextInput::make('content_rotation')
                            ->label('Şəklin öz bucağı (dərəcə)')->numeric()->default(0),
                    ])->columns(3),

                Forms\Components\Section::make('Foto sahələri')
                    ->description('Bu bucaqda müştərinin şəkillərinin düşəcəyi sahələr. Sıra ön görünüşdəki sahələrlə eyni olmalıdır.')
                    ->schema([ProductResource::photoSlotsRepeater()]),

                Forms\Components\Section::make('Mətn sahələri')
                    ->description('Bu bucaqdakı mətn mövqeləri. Sıra ön görünüşdəki mətn sahələri ilə eyni olmalıdır.')
                    ->schema([ProductResource::textSlotsRepeater()]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                Tables\Columns\ImageColumn::make('template_image')
                    ->label('Şəkil')
                    ->getStateUsing(fn ($record) => Media::url($record->template_image)),
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
