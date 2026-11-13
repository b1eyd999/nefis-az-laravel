<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Product;
use App\Models\Wrapping;
use App\Support\Contact;
use App\Support\DeliveryTime;
use App\Support\Letter;
use App\Support\LiveMaterials;
use App\Support\PhoneOrder;
use App\Support\Price;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

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

            // What goes with the box: the paper round it, the Polaroid inside
            // it, the live photo on it. Each is priced as the shop prices it,
            // and the price can be changed for what was agreed.
            Forms\Components\Section::make('Əlavələr')
                ->schema([
                    Forms\Components\Select::make('wrapping_id')
                        ->label('Qablaşdırma (bükmə)')
                        ->options(fn () => Wrapping::shown()->pluck('name', 'id'))
                        ->placeholder('Yoxdur')
                        ->live()
                        ->afterStateUpdated(fn ($state, Forms\Set $set) => $set(
                            'wrapping_price', $state ? Wrapping::find($state)?->price : null)),
                    Forms\Components\TextInput::make('wrapping_price')
                        ->label('Qablaşdırma, ₼')->numeric()->step(0.01)->minValue(0)
                        ->visible(fn (Forms\Get $get) => filled($get('wrapping_id'))),

                    Forms\Components\Toggle::make('letter_on')
                        ->label('Polaroid məktub')->live()->columnSpanFull()
                        ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('letter_price', $state ? Letter::price() : null)),
                    Forms\Components\Group::make([
                        Forms\Components\FileUpload::make('letter_photo')
                            ->label('Polaroid şəkli')->image()->disk('public')->directory(PhoneOrder::PHOTO_DIR)
                            ->maxSize(25 * 1024),
                        Forms\Components\Textarea::make('letter_text')
                            ->label('Məktubun mətni')->rows(3)->maxLength(Letter::maxLength())
                            ->helperText('Şəkil, mətn və ya hər ikisi.'),
                        Forms\Components\TextInput::make('letter_price')
                            ->label('Məktub, ₼')->numeric()->step(0.01)->minValue(0),
                    ])->visible(fn (Forms\Get $get) => (bool) $get('letter_on'))->columns(3)->columnSpanFull(),

                    Forms\Components\Toggle::make('live_on')
                        ->label('Canlı şəkil (video ilə oyanma)')->live()->columnSpanFull()
                        ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('live_price', $state ? LiveMaterials::price() : null)),
                    Forms\Components\Group::make([
                        Forms\Components\FileUpload::make('live_video')
                            ->label('Video')->disk('public')->directory('cart-videos')
                            ->acceptedFileTypes(['video/mp4', 'video/quicktime', 'video/webm', 'video/x-m4v'])
                            ->maxSize(LiveMaterials::videoMb() * 1024)
                            ->helperText('Hələ gəlməyibsə, boş qoyun: sonra «Canlı şəkillər» bölməsində əlavə edilir. Şəkil olaraq qutudakı birinci şəkil götürülür.'),
                        Forms\Components\TextInput::make('live_price')
                            ->label('Canlı şəkil, ₼')->numeric()->step(0.01)->minValue(0),
                    ])->visible(fn (Forms\Get $get) => (bool) $get('live_on'))->columns(2)->columnSpanFull(),
                ])->columns(2),

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
                            'awaiting_payment' => 'Ödəniş gözlənilir (ödəniş linki yaradılsın)',
                        ])
                        ->default('confirmed')
                        ->required()
                        ->helperText('«Təsdiqləndi» — Telegram-a kart gedir və sifariş işə düşür. '
                            .'«Ödəniş gözlənilir» — sifariş yaranır və müştəriyə göndərmək üçün ödəniş linki verilir; '
                            .'pul gələndə sifarişdə «Ödənişi təsdiqlə» basın.'),
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

    /**
     * Not paid yet: the link the customer pays through, ready to copy or to
     * send on WhatsApp. It is shown once here and stays on the order's page.
     */
    protected function afterCreate(): void
    {
        $order = $this->getRecord();

        if (! $order->awaitsPayment()) {
            return;
        }

        Notification::make()
            ->success()
            ->title('Ödəniş linki hazırdır — '.Price::format($order->total()))
            ->body(new HtmlString('<input type="text" readonly value="'.e(PhoneOrder::payLink($order))
                .'" onclick="this.select();navigator.clipboard&&navigator.clipboard.writeText(this.value)" '
                .'style="width:100%;margin-top:.4rem;padding:.4rem;border:1px solid #888;border-radius:.4rem;font-size:.75rem">'
                .'<div style="margin-top:.3rem;font-size:.75rem">Toxunun — köçürülür. Link '.PhoneOrder::LINK_HOURS.' saat işləyir.</div>'))
            ->actions(array_filter([
                PhoneOrder::payWhatsApp($order)
                    ? NotificationAction::make('wa')->label('WhatsApp-da göndər')
                        ->url(PhoneOrder::payWhatsApp($order), shouldOpenInNewTab: true)->button()
                    : null,
            ]))
            ->persistent()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return OrderResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
