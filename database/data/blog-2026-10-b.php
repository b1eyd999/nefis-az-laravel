<?php

/*
 * The blog's first articles, part two of three. See blog-2026-10-a.php for
 * what the copy in these files may and may not say.
 */
return [

    // ---------------------------------------------------------------- 3
    [
        'slug' => 'sifaris-nece-hazirlanir',
        'topic' => 'Necə hazırlanır',
        'emoji' => '📦',
        'date' => '2026-10-22 12:00',
        'title' => 'Sifariş verdikdən sonra nə olur',
        'meta_title' => 'Fərdi şokolad qutusu necə hazırlanır | Nefis',
        'meta_description' => 'Sayta yüklədiyiniz şəkil qutuya necə çevrilir: ödəniş, çap, yığılma, çatdırılma və sifarişin statusu barədə məktublar.',
        'excerpt' => 'Şəkli yükləyib sifarişi verdiniz — sonra nə olur? Qutunun saytdan qapınıza qədər yolunu addım-addım yazdıq.',
        'body' => <<<'MD'
## 1. Siz qutunu özünüz yığırsınız

İş sizdən başlayır. Dizaynı seçirsiniz, şəkli yükləyirsiniz, sözləri yazırsınız, içinə hansı şokoladın qoyulacağını seçirsiniz — və qutunun necə görünəcəyini elə ekranda görürsünüz. Bu, təsvir deyil, elə həmin qapaqdır: çapa gedən fayl ekranda gördüyünüzdən yığılır.

Ona görə sifariş verməzdən əvvəl bir dəqiqə önizləməyə baxmağa dəyər. [Şəkil](/bloq/qutuya-hansi-sekli-secmek) və [yazı](/bloq/qutuya-ne-yazmaq) barədə ayrıca yazılarımız var.

## 2. Ödəniş

Səbətdə dəqiq məbləği görürsünüz — qutu, içindəki şokolad, seçdiyiniz əlavələr və çatdırılma. Kartla ödəyə bilərsiniz, bank köçürməsi ilə də. Köçürmə seçsəniz, çeki yüklədikdən sonra ödənişi yoxlayırıq.

## 3. Növbə və hazırlanma

Ödəniş təsdiqləndikdən sonra sifariş növbəyə düşür. Hər qutu əl ilə hazırlanır: qapaq çap olunur, kəsilir, seçdiyiniz şokolad yerinə qoyulur, qutu yığılır.

Sifariş verərkən ən tez hansı günü seçə biləcəyinizi səhifə özü göstərir — bu, həmin günün yükündən asılı olaraq dəyişir. Tarix daha tez lazımdırsa, «Təcili hazırlansın» seçimi var: belə sifariş növbədənkənar hazırlanır.

## 4. Əlavələr

Seçmisinizsə, bu mərhələdə əlavə olunur:

- [hədiyyə qablaşdırması](/qablasdirma) — qutunu naxışlı kağıza büküb lentlə bağlayırıq;
- [polaroid məktub](/mektub) — şəkil və sözlərinizlə çap olunub qutunun içinə qoyulur;
- [canlı şəkil](/canli-sekil) — qutuya QR kod çap olunur, telefonu şəklə tutanda videonuz oynayır.

## 5. Statusu siz də görürsünüz

Sifarişin vəziyyəti dəyişəndə e-poçtunuza məktub gedir: ödəniş təsdiqləndi, hazırlanır, hazırdır, təhvil verildi. Eyni sözlər lazım olanda WhatsApp-la da göndərilir — burada çox adam onu məktubdan tez oxuyur.

## 6. Çatdırılma

Bakıda ünvana çatdırırıq. İstəsəniz metro stansiyasında təhvil veririk. Bölgələrə poçtla göndəririk.

Hədiyyəni birbaşa ona göndərəcəksinizsə, çatdırılma ünvanına onun adını, telefonunu və ünvanını yazın. Qiymət etiketi qutuya qoyulmur.
MD,
        'faq' => [
            ['q' => 'Sifarişi verdikdən sonra şəkli dəyişə bilərəmmi?', 'a' => 'Qapaq çap olunmayıbsa, bizə yazın — mümkün olan yerdə dəyişirik. Çapdan sonra qapağı dəyişmək olmur, ona görə sifariş verməzdən əvvəl önizləməyə baxmaq ən etibarlı yoldur.'],
            ['q' => 'Ən tez neçə günə hazır olur?', 'a' => 'Sifariş səhifəsi ən tez mümkün günü özü göstərir: o, həmin günün yükündən asılıdır. «Təcili hazırlansın» seçsəniz, sifariş növbədənkənar hazırlanır.'],
            ['q' => 'Hədiyyəni başqasının ünvanına göndərmək olar?', 'a' => 'Bəli. Çatdırılma ünvanına onun adını, telefonunu və ünvanını yazın; qiymət etiketi qutuya qoyulmur.'],
            ['q' => 'Ödənişi necə edə bilərəm?', 'a' => 'Kartla və ya bank köçürməsi ilə. Dəqiq məbləği səbətdə görürsünüz.'],
        ],
        'designs' => [],
        'ru' => [
            'slug' => 'kak-delaetsya-korobka',
            'topic' => 'Как делается',
            'title' => 'Что происходит после того, как вы оформили заказ',
            'meta_title' => 'Как делается шоколад с фотографией | Nefis',
            'meta_description' => 'Как загруженное фото превращается в коробку: оплата, печать, сборка, доставка и письма о статусе заказа.',
            'excerpt' => 'Вы загрузили фото и оформили заказ — а дальше? Расписали путь коробки от сайта до вашей двери по шагам.',
            'body' => <<<'MD'
## 1. Коробку собираете вы

Работа начинается с вас. Вы выбираете дизайн, загружаете фотографию, пишете текст, выбираете шоколад внутрь — и видите будущую коробку на экране. Это не иллюстрация, а та самая крышка: файл, который уходит в печать, собирается из того, что вы видели.

Поэтому минуту на предпросмотр потратить стоит. Про [фотографию](/ru/blog/kakoe-foto-vybrat) и про [надпись](/ru/blog/chto-napisat-na-korobke) у нас есть отдельные статьи.

## 2. Оплата

В корзине видна точная сумма — коробка, шоколад внутри, выбранные дополнения и доставка. Оплатить можно картой или банковским переводом. Если выбрали перевод, после загрузки чека мы проверяем оплату.

## 3. Очередь и изготовление

После подтверждения оплаты заказ встаёт в очередь. Каждая коробка собирается руками: крышка печатается, подрезается, внутрь кладётся выбранный шоколад, коробка собирается.

Самую раннюю доступную дату страница заказа показывает сама — она зависит от загрузки на этот день. Если нужно быстрее, есть опция «Срочно»: такой заказ делается вне очереди.

## 4. Дополнения

Если вы их выбрали, на этом шаге они и добавляются:

- [подарочная упаковка](/ru/qablasdirma) — заворачиваем коробку в узорную бумагу и перевязываем лентой;
- [полароид-письмо](/ru/mektub) — печатается с вашим фото и словами и кладётся внутрь;
- [живое фото](/ru/canli-sekil) — на коробку печатается QR-код, и при наведении телефона на фотографию играет ваше видео.

## 5. Статус видите и вы

Когда состояние заказа меняется, на вашу почту уходит письмо: оплата подтверждена, собирается, готов, передан. Те же слова при необходимости отправляем и в WhatsApp — здесь его читают быстрее, чем почту.

## 6. Доставка

По Баку доставляем на адрес. По желанию передаём у станции метро. В регионы отправляем почтой.

Если подарок едет сразу адресату, в адрес доставки впишите его имя, телефон и адрес. Ценник в коробку не кладётся.
MD,
            'faq' => [
                ['q' => 'Можно поменять фото после оформления заказа?', 'a' => 'Если крышка ещё не отпечатана — напишите нам, где возможно, меняем. После печати крышку изменить нельзя, поэтому надёжнее посмотреть предпросмотр до заказа.'],
                ['q' => 'Как быстро может быть готово?', 'a' => 'Самую раннюю возможную дату страница заказа показывает сама, она зависит от загрузки на этот день. С опцией «Срочно» заказ делается вне очереди.'],
                ['q' => 'Можно отправить подарок на другой адрес?', 'a' => 'Да. Впишите в адрес доставки имя, телефон и адрес получателя; ценник в коробку не кладётся.'],
                ['q' => 'Как оплатить?', 'a' => 'Картой или банковским переводом. Точная сумма видна в корзине.'],
            ],
        ],
        'en' => [
            'slug' => 'how-a-box-is-made',
            'topic' => 'How it is made',
            'title' => 'What happens after you place the order',
            'meta_title' => 'How a personalised chocolate box is made | Nefis',
            'meta_description' => 'How the photo you uploaded becomes a box: payment, printing, assembly, delivery and the letters about your order status.',
            'excerpt' => 'You uploaded the photo and placed the order — then what? Here is the route a box takes from the site to your door, step by step.',
            'body' => <<<'MD'
## 1. You assemble the box yourself

The work starts with you. You pick a design, upload a photograph, write the words, choose which chocolate goes inside — and you see the box on screen. That is not an illustration but the lid itself: the file that goes to print is built from what you were looking at.

Which is why a minute on the preview is worth it. We have separate articles on [the photograph](/en/blog/which-photo-to-choose) and on [the words](/en/blog/what-to-write-on-the-box).

## 2. Payment

The cart shows the exact total — the box, the chocolate inside, the extras you chose and delivery. You can pay by card or by bank transfer. With a transfer, you upload the receipt and we check the payment.

## 3. The queue and the making

Once payment is confirmed the order joins the queue. Every box is assembled by hand: the lid is printed, trimmed, the chocolate you chose goes in, the box is put together.

The order page shows you the earliest day available on its own — it moves with how full that day is. If you need it sooner there is a rush option: such an order is made out of turn.

## 4. The extras

If you chose any, this is the step where they are added:

- [gift wrapping](/en/qablasdirma) — we wrap the box in patterned paper and tie it with a ribbon;
- [a polaroid letter](/en/mektub) — printed with your photo and your words and placed inside the box;
- [a live photo](/en/canli-sekil) — a QR code is printed on the box, and holding a phone over the photograph plays your video.

## 5. You see the status too

When the state of the order changes a letter goes to your e-mail: payment confirmed, being made, ready, handed over. The same words go to WhatsApp when needed — here people read that sooner than their mail.

## 6. Delivery

In Baku we deliver to an address. If you prefer, we hand the box over at a metro station. To the regions we send it by post.

If the gift is going straight to the person it is for, put their name, phone number and address in the delivery details. No price tag goes in the box.
MD,
            'faq' => [
                ['q' => 'Can I change the photo after ordering?', 'a' => 'If the lid is not printed yet, write to us and we change it where we can. A printed lid cannot be changed, so looking at the preview before ordering is the safer route.'],
                ['q' => 'How soon can it be ready?', 'a' => 'The order page shows the earliest day available by itself; it depends on how full that day is. With the rush option an order is made out of turn.'],
                ['q' => 'Can you send the gift straight to the person it is for?', 'a' => 'Yes. Put their name, phone number and address in the delivery details; no price tag goes in the box.'],
                ['q' => 'How can I pay?', 'a' => 'By card or by bank transfer. The exact total is shown in the cart.'],
            ],
        ],
    ],

    // ---------------------------------------------------------------- 4
    [
        'slug' => 'canli-sekil-nedir',
        'topic' => 'Canlı şəkil',
        'emoji' => '🎬',
        'date' => '2026-10-18 13:00',
        'title' => 'Canlı şəkil: qutudakı şəkil necə hərəkət edir',
        'meta_title' => 'Canlı şəkil (AR) nədir və necə işləyir | Nefis',
        'meta_description' => 'Qutudakı şəklin üstündə videonun oynaması: QR kod, telefon kamerası və tətbiq yükləmədən işləyən AR. Hansı video uyğundur?',
        'excerpt' => 'Hədiyyəni açan adam telefonu qutudakı şəklə tutur və şəklin üstündə sizin videonuz oynayır. Bu necə işləyir və hansı video uyğun gəlir.',
        'body' => <<<'MD'
## Nə baş verir

Qutunun üzərində adi çap olunmuş şəkil var. Hədiyyəni alan adam qutudakı QR kodu telefonla oxuyur, sonra kameranı şəklə tutur — və şəklin üstündə sizin videonuz oynamağa başlayır, elə həmin çərçivədə, elə həmin yerdə. Telefonu çəkəndə şəkil yenə adi şəkil olur.

Tətbiq yükləmək lazım deyil. Hər şey telefonun öz brauzerində işləyir.

## Niyə belə təsir bağışlayır

Çünki gözlənilən deyil. Qutu adi hədiyyə qutusu kimi görünür, şəkil adi şəkil kimi. Sonra isə birdən tərpənir. Hədiyyədə ən çox təəccübləndirən hissə adətən elə bu olur — xüsusən uşaqlarda və onu ilk dəfə görən adamlarda.

Bir mənalı tərəfi də var: şəkil bir anı saxlayır, video isə həmin anın səsini də saxlayır.

## Hansı video uyğundur

Bir neçə sadə şərt:

- **Qısa olsun.** On-on beş saniyə kifayətdir. Uzun videonu heç kim telefonu havada tutub sona qədər izləmir.
- **Şəkillə eyni səhnədən olsun.** Ən yaxşı nəticə o zaman alınır ki, video elə şəkil çəkilən andan olsun: şəkil «canlanır», çünki arxa fon eynidir.
- **Dik çəkilsin.** Qutunun şəkil yeri hündürdür, eni azdır — eninə çəkilmiş video oraya tam yerləşmir.
- **Üz görünsün.** Qaranlıqda və ya çox uzaqdan çəkilmiş videoda telefon ekranında heç nə seçilmir.

Səs barədə: video səslə oynayır, ona görə ya sözləri aydın deyin, ya da fon musiqisi olmayan yerdə çəkin.

## Nə vaxt işə yarayır, nə vaxt yox

İşə yarayır: ad günü təbriki, körpənin ilk addımları, toy kadrları, uzaqdan göndərilən hədiyyə — adamın sizi görə bilmədiyi hallarda.

Az işə yarayır: rəsmi, korporativ hədiyyələr; qutunu dərhal açmayan, bir neçə gün rəfdə saxlayan adamlar. Belə hallarda QR kod yenə işləyir, sadəcə təsiri azalır.

Ətraflı və sifariş: [canlı şəkil səhifəsi](/canli-sekil).
MD,
        'faq' => [
            ['q' => 'Tətbiq yükləmək lazımdır?', 'a' => 'Xeyr. QR kodu oxuyursunuz, sonra telefonu qutudakı şəklə tutursunuz — video telefonun öz brauzerində oynayır.'],
            ['q' => 'Video nə qədər uzun ola bilər?', 'a' => 'Qısa video daha yaxşı işləyir: on-on beş saniyə. Uzun videonu telefonu havada tutub sona qədər izləmək çətindir.'],
            ['q' => 'Videonu dik, yoxsa eninə çəkmək lazımdır?', 'a' => 'Dik. Qutunun şəkil yeri hündür və dar olduğu üçün eninə çəkilmiş video çərçivəyə tam yerləşmir.'],
            ['q' => 'İnternet lazımdır?', 'a' => 'Bəli, video oynayarkən telefonda internet olmalıdır.'],
        ],
        'designs' => [],
        'ru' => [
            'slug' => 'chto-takoe-zhivoe-foto',
            'topic' => 'Живое фото',
            'title' => 'Живое фото: как фотография на коробке начинает двигаться',
            'meta_title' => 'Что такое живое фото (AR) и как это работает | Nefis',
            'meta_description' => 'Видео, которое играет поверх фотографии на коробке: QR-код, камера телефона и AR без установки приложений. Какое видео подойдёт.',
            'excerpt' => 'Человек наводит телефон на фотографию на коробке, и поверх неё играет ваше видео. Как это устроено и какое видео для этого годится.',
            'body' => <<<'MD'
## Что происходит

На коробке — обычная напечатанная фотография. Получатель считывает телефоном QR-код с коробки, затем наводит камеру на фото — и поверх снимка начинает играть ваше видео, в той же рамке, на том же месте. Уберёшь телефон — фотография снова просто фотография.

Устанавливать приложение не нужно. Всё работает в обычном браузере телефона.

## Почему это производит впечатление

Потому что этого не ждут. Коробка выглядит как коробка, фотография — как фотография. А потом она вдруг двигается. Обычно именно это в подарке удивляет сильнее всего — особенно детей и тех, кто видит такое впервые.

Есть и содержательная сторона: фотография сохраняет момент, а видео сохраняет ещё и его звук.

## Какое видео подойдёт

Несколько простых условий:

- **Короткое.** Десять-пятнадцать секунд достаточно. Длинное видео никто не станет смотреть до конца, держа телефон на весу.
- **Из той же сцены, что и фото.** Лучший результат получается, когда видео снято в тот же момент, что и снимок: фотография буквально «оживает», потому что фон тот же.
- **Вертикальное.** Место для фото на коробке высокое и узкое — горизонтальное видео в него целиком не встаёт.
- **С видимым лицом.** В тёмном или снятом издалека видео на экране телефона ничего не разобрать.

Про звук: видео играет со звуком, поэтому либо говорите слова разборчиво, либо снимайте там, где нет фоновой музыки.

## Когда это работает, а когда нет

Работает: поздравление с днём рождения, первые шаги ребёнка, кадры со свадьбы, подарок, отправленный издалека — то есть когда человек не может вас увидеть.

Работает слабее: официальные корпоративные подарки; люди, которые не открывают коробку сразу, а оставляют её на несколько дней на полке. QR-код и тогда сработает, просто эффект будет меньше.

Подробнее и заказ — на [странице живого фото](/ru/canli-sekil).
MD,
            'faq' => [
                ['q' => 'Нужно устанавливать приложение?', 'a' => 'Нет. Вы считываете QR-код, затем наводите телефон на фотографию на коробке — видео играет в обычном браузере телефона.'],
                ['q' => 'Какой длины может быть видео?', 'a' => 'Лучше короткое: десять-пятнадцать секунд. Длинное трудно досмотреть, держа телефон на весу.'],
                ['q' => 'Видео снимать вертикально или горизонтально?', 'a' => 'Вертикально. Место для фотографии на коробке высокое и узкое, поэтому горизонтальное видео в рамку целиком не встаёт.'],
                ['q' => 'Нужен интернет?', 'a' => 'Да, во время воспроизведения на телефоне должен быть интернет.'],
            ],
        ],
        'en' => [
            'slug' => 'what-is-a-live-photo',
            'topic' => 'Live photo',
            'title' => 'Live photo: how the picture on the box starts moving',
            'meta_title' => 'What a live photo (AR) is and how it works | Nefis',
            'meta_description' => 'A video playing over the photograph on the box: a QR code, the phone camera, and AR with no app to install. Which video works.',
            'excerpt' => 'Someone holds a phone over the photograph on the box and your video plays on top of it. How that works, and which video is right for it.',
            'body' => <<<'MD'
## What happens

On the box there is an ordinary printed photograph. The person opening the gift scans the QR code on the box, then points the camera at the photo — and your video starts playing over it, in the same frame, in the same place. Take the phone away and the photograph is just a photograph again.

There is no app to install. It all runs in the phone's own browser.

## Why it lands

Because nobody expects it. The box looks like a box, the photograph like a photograph. And then it moves. This is usually the part of the gift that surprises people most — children above all, and anyone seeing it for the first time.

There is a plainer reason too: a photograph keeps a moment, a video keeps its sound as well.

## Which video works

A few simple conditions:

- **Short.** Ten to fifteen seconds is enough. Nobody watches a long video to the end holding a phone in the air.
- **From the same scene as the photo.** The best result comes when the video was filmed at the moment the photo was taken: the picture really does come alive, because the background is the same.
- **Shot upright.** The photo window on the box is tall and narrow — a landscape video does not fit into it whole.
- **With the face visible.** In a dark video, or one filmed from far away, nothing can be made out on a phone screen.

On sound: the video plays with its sound, so either say the words clearly or film somewhere without background music.

## When it works and when it does not

It works for: a birthday message, a baby's first steps, wedding footage, a gift sent from far away — anywhere the person cannot see you in the flesh.

It works less well for: formal corporate gifts; and for people who do not open the box straight away but leave it on a shelf for a few days. The QR code still works then, the effect is simply smaller.

More, and ordering, on the [live photo page](/en/canli-sekil).
MD,
            'faq' => [
                ['q' => 'Does anyone need to install an app?', 'a' => 'No. You scan the QR code, then point the phone at the photograph on the box — the video plays in the phone’s own browser.'],
                ['q' => 'How long can the video be?', 'a' => 'Short works better: ten to fifteen seconds. A long one is hard to watch to the end with a phone held in the air.'],
                ['q' => 'Should the video be upright or landscape?', 'a' => 'Upright. The photo window on the box is tall and narrow, so a landscape video does not fit the frame whole.'],
                ['q' => 'Is an internet connection needed?', 'a' => 'Yes, the phone needs a connection while the video is playing.'],
            ],
        ],
    ],

];
