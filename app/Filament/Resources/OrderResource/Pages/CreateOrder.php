<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Product;
use App\Support\Contact;
use App\Support\DeliveryTime;
use App\Support\PhoneOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * An order the owner writes down for a customer who sent it on WhatsApp:
 * who, which box, the photographs and the captions. The order itself is made
 * by PhoneOrder, the same way for every line.
 */
class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'WhatsApp sifarişi əlavə et';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Müştəri')
                ->description('Nömrə hesabın açarıdır: bu nömrə ilə hesab varsa, sifariş ona yazılır, yoxdursa yenisi açılır.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Ad')->required()->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->label('Telefon')->tel()->required()->maxLength(30)
                        ->placeholder('+994 55 555 55 55')
                        ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                            if (Contact::az($value) === null) {
                                $fail('Nömrəni +994 55 555 55 55 şəklində yazın.');
                            }
                        }),
                ])->columns(2),

            Forms\Components\Section::make('Sifariş')
                ->schema([
                    Forms\Components\Select::make('product_id')
                        ->label('Dizayn')
                        ->options(fn () => Product::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            $set('price', $state ? Product::find($state)?->price : null);
                        }),
                    Forms\Components\TextInput::make('quantity')
                        ->label('Say')->numeric()->minValue(1)->default(1)->required(),
                    Forms\Components\TextInput::make('price')
                        ->label('Qutunun qiyməti, ₼')->numeric()->step(0.01)->minValue(0)
                        ->helperText('Dizaynın qiyməti yazılır; razılaşdığınız qiymət fərqlidirsə, dəyişin.'),

                    // The windows and captions of the chosen design, as the
                    // customer would have met them on its page.
                    Forms\Components\Group::make(fn (Forms\Get $get): array => $this->slotFields($get('product_id')))
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('note')
                        ->label('Qeyd')->rows(2)->maxLength(500)->columnSpanFull(),
                ])->columns(3),

            Forms\Components\Section::make('Çatdırılma və status')
                ->schema([
                    Forms\Components\TextInput::make('recipient_name')->label('Alan şəxs')->maxLength(120),
                    Forms\Components\TextInput::make('delivery_address')->label('Ünvan')->maxLength(255),
                    Forms\Components\DatePicker::make('delivery_date')->label('Nə vaxta')
                        ->native(false)->minDate(today()),
                    Forms\Components\Select::make('delivery_slot')->label('Saat')
                        ->options(fn () => array_combine(DeliveryTime::slots(), DeliveryTime::slots())),
                    Forms\Components\TextInput::make('delivery_price')
                        ->label('Çatdırılma, ₼')->numeric()->step(0.01)->minValue(0),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'confirmed' => 'Təsdiqləndi (ödəniş alınıb)',
                            'awaiting_payment' => 'Ödəniş gözlənilir',
                        ])
                        ->default('confirmed')
                        ->required()
                        ->helperText('«Təsdiqləndi» — Telegram-a kart gedir və sifariş işə düşür.'),
                ])->columns(2),
        ]);
    }

    /** One upload per window the design asks a photograph for, one box per caption it asks for. */
    private function slotFields(mixed $productId): array
    {
        $product = $productId ? Product::with(['photoSlots', 'textSlots'])->find($productId) : null;
        if (! $product) {
            return [];
        }

        $fields = [];

        foreach ($product->photoSlots->values() as $i => $slot) {
            if (! $slot->needsUpload()) {
                continue;
            }
            $fields[] = Forms\Components\FileUpload::make("photo_$i")
                ->label('Şəkil: '.($slot->label ?: 'Şəkil '.($i + 1)))
                ->image()
                ->disk('public')
                ->directory(PhoneOrder::PHOTO_DIR)
                ->maxSize(25 * 1024)
                ->required();
        }

        foreach ($product->textSlots->values() as $i => $slot) {
            if ($slot->isGiven()) {
                continue;
            }
            $fields[] = Forms\Components\TextInput::make("text_$i")
                ->label('Yazı: '.($slot->label ?: 'Mətn '.($i + 1)))
                ->maxLength($slot->limit());
        }

        return $fields;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $product = Product::with(['photoSlots', 'textSlots'])->findOrFail($data['product_id']);

        $photos = [];
        foreach ($product->photoSlots->values() as $i => $slot) {
            if ($slot->needsUpload() && ! empty($data["photo_$i"])) {
                $photos[] = $data["photo_$i"];
            }
        }

        $texts = [];
        foreach ($product->textSlots->values() as $i => $slot) {
            $texts[$i] = $data["text_$i"] ?? '';
        }

        return PhoneOrder::create($data + ['photos' => $photos, 'texts' => $texts]);
    }

    protected function getRedirectUrl(): string
    {
        return OrderResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
