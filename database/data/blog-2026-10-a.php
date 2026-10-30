<?php

/*
 * The blog's first articles, each in all three languages. Part one of three:
 * the files are split only because of their size, and the migration merges
 * them in order.
 *
 * Nothing in here promises anything the shop does not already say on its own
 * pages: no prices, no number of days, no free delivery, no approved
 * mock-up. What a reader is told about choosing a photograph is what the
 * site's own photo guide tells him; what he is told about making and
 * delivering a box is what the checkout already shows him.
 *
 * `designs` are slug prefixes, not slugs: the catalogue is rearranged often
 * enough that naming exact designs would attach the wrong ones or none. The
 * migration matches what is actually there and skips the rest.
 */
return [

    // ---------------------------------------------------------------- 1
    [
        'slug' => 'qutuya-hansi-sekli-secmek',
        'topic' => 'Şəkil və çap',
        'emoji' => '📸',
        'date' => '2026-10-30 10:00',
        'title' => 'Qutuya hansı şəkli seçmək lazımdır',
        'meta_title' => 'Şokolad qutusu üçün şəkil necə seçilir | Nefis',
        'meta_description' => 'Qapağa çap olunan şəkil: hansı şəkil yaxşı çıxır, hansı çıxmır və sifariş verməzdən əvvəl nəyi yoxlamaq lazımdır. Nefis-in iş təcrübəsindən.',
        'excerpt' => 'Ekranda yaxşı görünən hər şəkil qapaqda da yaxşı görünmür. Fərq nədən asılıdır və sifariş verməzdən əvvəl nəyi yoxlamaq lazımdır.',
        'body' => <<<'MD'
## Ekran işıqlıdır, kağız deyil

Telefonun ekranı şəkli arxadan işıqlandırır — ona görə qaranlıq şəkil də ekranda normal görünür. Qutunun qapağı isə kağızdır, onu yalnız otağın işığı işıqlandırır. Çapda ən çox itən də elə kölgədə qalan hissələr olur: ekranda seçilən detal kağızda tündləşib bir-birinə qarışır.

Praktik nəticə sadədir — qaranlıq şəkil deyil, işıqlı şəkil seçin. Gündüz işığında, ya da pəncərəyə tərəf durub çəkilmiş şəkil axşam kafedə çəkilmiş şəkildən həmişə yaxşı çıxır.

## Üz nə qədər böyük olmalıdır

Qapaqdaki şəkil yeri kiçikdir — bir plitka boydadır. Tam boy şəkil oraya yerləşəndə üz barmaq ucu boyda qalır, hədiyyəni açan adam isə ilk növbədə öz üzünü axtarır.

Ona görə:

- Üz şəklin mərkəzində olsun, çiyinlər görünsün.
- Düz kameraya baxılmış şəkil seçin: profildən və ya aşağıdan çəkilmiş şəkildə üz çətin tanınır.
- Telefonu göz səviyyəsində tutun — aşağıdan çəkiləndə çənə böyüyür, yuxarıdan çəkiləndə alın.
- Bir neçə nəfər varsa, hamısı kameraya baxsın. Biri yana baxırsa, qapaqda elə o nəzərə çarpacaq.

Saytda hər şəkil yerinin altında eyni nümunə var: «Nümunəyə bax» düyməsi bir düzgün və iki ən çox rast gəlinən səhv şəkli göstərir.

## Hansı fayl yaxşıdır

Şəkli telefonun qalereyasından olduğu kimi yükləyin. Ən pis üç variant bunlardır:

- **Ekrandan çəkilmiş şəkil.** Mesajla gələn şəkli ekranda açıb üstündən şəkil çəkmək — belə faylda piksellər artıq korlanıb, çapda bu daha aydın görünür.
- **Dəfələrlə göndərilmiş şəkil.** Mesaj proqramları şəkli hər göndərişdə kiçildir; beş dəfə ötürülmüş şəkildən qalan artıq azdır.
- **Bulanıq şəkil.** Ekranda kiçik görünəndə bulanıqlıq hiss olunmur, qapaqda isə böyüdülür.

Köhnə, kağız şəkil varsa, skan etmək onu telefonla çəkməkdən yaxşıdır. Skan yoxdursa, kağızı düz səthə qoyub pəncərə işığında, üzərinə kölgə salmadan çəkin.

## Sifariş verməzdən əvvəl bir dəqiqə

Şəkli yüklədikdən sonra qutunun necə görünəcəyini elə saytda görürsünüz. Şəkli iki barmaqla böyüdə-kiçildə, çevirə və pəncərədə yerini dəyişə bilərsiniz. Bir dəqiqə ayırın: çox vaxt şəkli bir az böyütmək kifayət edir ki, üz pəncərəni doldursun.

Bir şeyi də yadda saxlayın: ekranda qutu böyük görünür, əlinizdə isə kiçik olacaq. Ekranda güclə seçilən detal əsl qutuda ümumiyyətlə görünməyəcək.
MD,
        'faq' => [
            ['q' => 'Şəkli hansı ölçüdə göndərmək lazımdır?', 'a' => 'Xüsusi ölçü lazım deyil, telefonun qalereyasındakı adi şəkil kifayətdir. Əsas odur ki, şəkil kiçildilməmiş və bulanıq olmasın — ona görə faylı mesajla bir neçə dəfə ötürmək yerinə birbaşa saytda yükləyin.'],
            ['q' => 'Şəklin fonu qarışıqdırsa nə olacaq?', 'a' => 'Şəkil yeri kiçik olduğu üçün fonun çoxu kənarda qalır. Şəkli böyüdüb üzü mərkəzə gətirin — nəticəni dərhal ekranda görəcəksiniz.'],
            ['q' => 'Qara-ağ şəkil yaxşı çıxır?', 'a' => 'Bəli, bəzən rəngli şəkildən də yaxşı: rəng uyğunsuzluğu olmur. Şərt yenə eynidir — şəkildə işıq olsun.'],
            ['q' => 'Bir qutuya bir neçə şəkil qoymaq olar?', 'a' => 'Dizayndan asılıdır: bəzilərində bir şəkil yeri, bəzilərində bir neçəsi var. Dizaynı açanda nə qədər şəkil yeri olduğunu görürsünüz.'],
        ],
        'designs' => [],
        'ru' => [
            'slug' => 'kakoe-foto-vybrat',
            'topic' => 'Фото и печать',
            'title' => 'Какое фото выбрать для коробки',
            'meta_title' => 'Какое фото подойдёт для шоколада с фотографией | Nefis',
            'meta_description' => 'Фото, которое печатается на крышке: какое выйдет хорошо, какое нет и что стоит проверить перед заказом. Из опыта Nefis.',
            'excerpt' => 'Не каждое фото, которое хорошо смотрится на экране, так же хорошо смотрится на крышке. От чего это зависит и что проверить перед заказом.',
            'body' => <<<'MD'
## Экран светится, бумага — нет

Экран телефона подсвечивает фотографию сзади, поэтому тёмный снимок на нём выглядит нормально. Крышка коробки — это бумага, её освещает только свет в комнате. Больше всего при печати теряется именно то, что в тени: деталь, которая на экране различима, на бумаге темнеет и сливается.

Отсюда простое правило: выбирайте не тёмный кадр, а светлый. Снимок при дневном свете или у окна всегда выходит лучше вечернего кадра в кафе.

## Насколько крупным должно быть лицо

Окно для фотографии на крышке небольшое — размером с плитку. Если туда поставить снимок в полный рост, лицо окажется размером с ноготь, а человек, открывающий подарок, в первую очередь ищет на крышке именно себя.

Поэтому:

- Лицо — в центре кадра, плечи видны.
- Берите кадр, где человек смотрит прямо в камеру: в профиль или снизу лицо узнаётся плохо.
- Держите телефон на уровне глаз — снизу увеличивается подбородок, сверху лоб.
- Если людей несколько, в камеру должны смотреть все. Тот, кто отвернулся, на крышке и будет бросаться в глаза.

На сайте под каждым местом для фотографии есть одна и та же подсказка: кнопка «Посмотреть пример» показывает один правильный кадр и две самые частые ошибки.

## Какой файл лучше

Загружайте фотографию из галереи телефона как есть. Три худших варианта:

- **Снимок экрана.** Открыть присланное фото на экране и переснять его — в таком файле пиксели уже испорчены, а в печати это видно отчётливее.
- **Фото, пересланное много раз.** Мессенджеры сжимают снимок при каждой пересылке; после пятой от него мало что остаётся.
- **Размытый кадр.** На маленьком экране размытость незаметна, на крышке её увеличивают.

Если фотография старая и бумажная, лучше её отсканировать, чем переснять телефоном. Нет сканера — положите снимок на ровную поверхность и сфотографируйте у окна, не загораживая свет собой.

## Одна минута перед заказом

После загрузки вы видите будущую коробку прямо на сайте. Фотографию можно увеличить двумя пальцами, повернуть и подвинуть в окне. Потратьте на это минуту: чаще всего достаточно немного увеличить снимок, чтобы лицо заполнило окно.

И помните: на экране коробка большая, а в руках будет маленькой. Деталь, которую на экране едва видно, на настоящей коробке не будет видно вовсе.
MD,
            'faq' => [
                ['q' => 'Какого размера должно быть фото?', 'a' => 'Особый размер не нужен, подойдёт обычный снимок из галереи телефона. Важно, чтобы он не был сжат и не был размытым — поэтому загружайте файл прямо на сайте, а не пересылайте его сначала через мессенджеры.'],
                ['q' => 'Что будет, если фон на фото пёстрый?', 'a' => 'Окно небольшое, поэтому большая часть фона остаётся за кадром. Увеличьте снимок и выведите лицо в центр — результат сразу виден на экране.'],
                ['q' => 'Чёрно-белое фото выходит хорошо?', 'a' => 'Да, иногда даже лучше цветного: не бывает расхождений по цвету. Условие то же — на снимке должен быть свет.'],
                ['q' => 'Можно поставить на коробку несколько фотографий?', 'a' => 'Зависит от дизайна: в одних одно место для фото, в других несколько. Открыв дизайн, вы видите, сколько их.'],
            ],
        ],
        'en' => [
            'slug' => 'which-photo-to-choose',
            'topic' => 'Photo and print',
            'title' => 'Which photo to put on the box',
            'meta_title' => 'Choosing a photo for a chocolate box | Nefis',
            'meta_description' => 'The photo printed on the lid: which ones come out well, which do not, and what to check before you order. From what we see every day.',
            'excerpt' => 'Not every photo that looks good on a screen looks good on a lid. What makes the difference, and what to check before you order.',
            'body' => <<<'MD'
## A screen glows, paper does not

A phone screen lights the picture from behind, so even a dark photo looks fine on it. The lid of a box is paper, lit only by the light in the room. What printing loses first is whatever sits in shadow: a detail you can make out on screen goes dark on paper and merges with what is next to it.

The practical rule is simple — pick the bright frame, not the dark one. A photo taken in daylight, or facing a window, always beats one taken in a café in the evening.

## How large the face has to be

The photo window on the lid is small — about the size of a chocolate bar. Put a full-length photo in it and the face ends up the size of a fingernail, while the person opening the gift is looking for exactly that face first.

So:

- Face in the middle of the frame, shoulders in.
- Choose a frame where the person looks straight at the camera; in profile or from below a face is hard to recognise.
- Hold the phone at eye level — from below it enlarges the chin, from above the forehead.
- With several people, all of them should look at the camera. The one who looked away is the one the lid will show off.

The site carries the same guide under every photo slot: the "See an example" button shows one good frame and the two mistakes we see most often.

## Which file is best

Upload the photo from your phone's gallery as it is. The three worst options:

- **A photo of a screen.** Opening a picture someone sent you and photographing the screen — those pixels are already damaged, and print shows it more clearly.
- **A photo forwarded many times.** Messengers shrink an image at every forward; after the fifth there is not much left.
- **A blurry frame.** Blur goes unnoticed on a small screen; on the lid it is enlarged.

If the photo is an old print, scanning it beats photographing it. With no scanner, lay it on a flat surface and shoot it by a window without casting your own shadow on it.

## One minute before you order

Once the photo is loaded you see the box itself on the site. You can pinch to enlarge the photo, turn it, and move it inside the window. Spend a minute on that: usually enlarging the photo a little is all it takes for the face to fill the window.

And keep one thing in mind: the box looks big on screen and will be small in your hands. A detail you can barely see on screen will not be visible at all on the real thing.
MD,
            'faq' => [
                ['q' => 'What size should the photo be?', 'a' => 'No particular size — an ordinary photo from your phone gallery is enough. What matters is that it has not been shrunk and is not blurry, so upload the file on the site rather than forwarding it through messengers first.'],
                ['q' => 'What if the background is busy?', 'a' => 'The window is small, so most of the background stays out of frame. Enlarge the photo and bring the face to the middle — you see the result on screen straight away.'],
                ['q' => 'Do black and white photos work?', 'a' => 'Yes, sometimes better than colour ones: nothing can clash. The condition is the same — there has to be light in the photo.'],
                ['q' => 'Can one box carry several photos?', 'a' => 'It depends on the design: some have one photo slot, some several. Opening a design shows you how many it has.'],
            ],
        ],
    ],

    // ---------------------------------------------------------------- 2
    [
        'slug' => 'qutuya-ne-yazmaq',
        'topic' => 'Sözlər',
        'emoji' => '✍️',
        'date' => '2026-10-26 11:00',
        'title' => 'Qutuya nə yazmaq: işləyən sözlər və işləməyənlər',
        'meta_title' => 'Hədiyyə qutusuna nə yazmaq olar | Nefis',
        'meta_description' => 'Şokolad qutusunun qapağına hansı sözləri yazmaq: niyə «Ad günün mübarək» işləmir, nümunələr və yazının uzunluğu barədə.',
        'excerpt' => 'Şəkli seçmək bir dəqiqəlik işdir, sözləri seçmək isə yox. Qapaqdakı yazı qutudan sonra da qalır — ona görə ona vaxt ayırmağa dəyər.',
        'body' => <<<'MD'
## «Ad günün mübarək» niyə işləmir

Ona görə ki, onu hər kəs hər kəsə yaza bilər. Belə yazı pis deyil — sadəcə heç nə demir, çünki onun arxasında konkret iki adam yoxdur.

Bir fərqi də nəzərə alın: içindəki şokolad bir axşamda bitir, qapaq isə qalır. Çox adam onu atmır — rəfə, iş masasının üstünə, ya da bir qutunun içinə qoyur. Yəni yazdığınız cümlə şokoladdan uzun yaşayır.

## Yalnız ikinizin bildiyi bir şey yazın

Ən yaxşı cümlələr tərifli deyil, dəqiq olanlardır:

- evdə çağırdığınız ad;
- illərdir təkrarladığınız bir zarafat;
- bir tarix — tanış olduğunuz gün, köçdüyünüz gün;
- birlikdə dəfələrlə baxdığınız filmdən bir replika;
- bir yer adı: «Həmin kafe, həmin masa».

Belə yazı yanındakılara heç nə demir, hədiyyəni alana isə hər şeyi deyir. Elə məqsəd də budur.

## Nə qədər uzun yazmaq olar

Qapaqda yazı yeri azdır. Qısa yazı böyük hərflərlə çap olunur və uzaqdan oxunur; uzun yazı kiçilir və qutunun şəklini sıxışdırır. Praktikada ən yaxşı nəticə üç-dörd sözdə alınır, bir cümlədə isə hələ də yaxşıdır.

Uzun yazmaq istəyirsinizsə, onu qapağa yox, [polaroid məktuba](/mektub) yazın: məktub qutunun içinə qoyulur, orada yer çoxdur. Qapaqda bir cümlə, içində bir məktub — bu ikisi bir-birinə mane olmur.

## Beş nümunə

- **Ad gününə:** «33 yaşın mübarək, köhnə dost» yerinə — «Bu il də səninləyik, Sevil».
- **Sevgiliyə:** «Səni sevirəm» yerinə — «14.08.2019 — hər şey o axşam başladı».
- **Anaya:** «Ən yaxşı ana» yerinə — «Mətbəxdəki o qoxu, ana».
- **Dosta:** «Təbrik edirəm» yerinə — «İkinci kursdan bəri».
- **Həmkara:** «Uğurlar» yerinə — «Beş ildir bir masada».

Hamısında eyni prinsip işləyir: ümumi tərif yerinə bir konkret detal.

## Son yoxlama

Yazdıqca qutunun necə görünəcəyini ekranda görürsünüz — hərflərin necə yerləşdiyi də orada bəlli olur. İki şeyi mütləq yoxlayın: adın düz yazıldığını və tarixdə rəqəmlərin yerini. Qapaq çap olunandan sonra onu dəyişmək olmur, ekrandaki önizləmə isə nə göstərirsə, əsl qutuda da o olacaq.
MD,
        'faq' => [
            ['q' => 'Yazını hansı dildə yazmaq olar?', 'a' => 'İstədiyiniz dildə. Yazdıqca hərflərin qapaqda necə düşdüyünü ekranda görürsünüz, ona görə seçim əvvəlcədən yoxlanıla bilər.'],
            ['q' => 'Emoji yazmaq olar?', 'a' => 'Yazını yazdıqdan sonra önizləməyə baxın: ekranda göründüyü kimi çap olunur, görünmürsə, sözlə yazmaq daha etibarlıdır.'],
            ['q' => 'Heç nə yazmasam olar?', 'a' => 'Olar. Bəzi dizaynlar yalnız şəkillə də tamamlanır — yazı sahəsini boş qoysanız, qapaqda yazı olmur.'],
            ['q' => 'Uzun mətni hara yazmaq olar?', 'a' => 'Qutunun içinə qoyulan polaroid məktuba: orada yer qapaqdan çox daha genişdir.'],
        ],
        'designs' => [],
        'ru' => [
            'slug' => 'chto-napisat-na-korobke',
            'topic' => 'Слова',
            'title' => 'Что написать на коробке: слова, которые работают',
            'meta_title' => 'Что написать на подарочной коробке шоколада | Nefis',
            'meta_description' => 'Какие слова писать на крышке коробки: почему «С днём рождения» не работает, примеры и сколько текста влезает.',
            'excerpt' => 'Выбрать фото — минута, выбрать слова — нет. Надпись на крышке остаётся и после того, как шоколад закончился, поэтому на неё стоит потратить время.',
            'body' => <<<'MD'
## Почему «С днём рождения» не работает

Потому что это можно написать кому угодно от кого угодно. Фраза не плохая — она просто ничего не сообщает, потому что за ней не стоят два конкретных человека.

Есть и ещё одно обстоятельство: шоколад внутри закончится в тот же вечер, а крышка останется. Её обычно не выбрасывают — ставят на полку, на рабочий стол, кладут в коробку с бумагами. То есть написанная вами фраза живёт дольше шоколада.

## Напишите то, что понятно только вам двоим

Лучшие надписи не хвалебные, а точные:

- домашнее имя;
- шутка, которую вы повторяете годами;
- дата — день знакомства, день переезда;
- реплика из фильма, который вы пересматривали вместе;
- название места: «То кафе, тот столик».

Такая надпись ничего не говорит окружающим и говорит всё тому, кому она адресована. В этом и смысл.

## Сколько текста влезает

Места на крышке немного. Короткая надпись печатается крупно и читается издалека; длинная мельчает и поджимает фотографию. На практике лучше всего работают три-четыре слова, одна фраза — тоже хорошо.

Если хочется написать много, пишите не на крышке, а в [полароид-письме](/ru/mektub): оно кладётся внутрь коробки, и места там достаточно. Фраза на крышке и письмо внутри друг другу не мешают.

## Пять примеров

- **На день рождения:** вместо «С 33-летием, друг» — «И в этом году мы рядом, Севиль».
- **Любимому человеку:** вместо «Люблю тебя» — «14.08.2019 — всё началось тем вечером».
- **Маме:** вместо «Лучшая мама» — «Тот запах на кухне, мама».
- **Другу:** вместо «Поздравляю» — «Со второго курса».
- **Коллеге:** вместо «Успехов» — «Пять лет за одним столом».

Везде работает один принцип: вместо общей похвалы — одна конкретная деталь.

## Последняя проверка

По мере набора вы видите коробку на экране — там же видно, как встали буквы. Обязательно проверьте две вещи: правильно ли написано имя и не перепутаны ли цифры в дате. После печати крышку уже не изменить, а что показывает предпросмотр, то и будет на настоящей коробке.
MD,
            'faq' => [
                ['q' => 'На каком языке можно написать?', 'a' => 'На любом. По мере набора видно, как буквы встают на крышке, так что выбор можно проверить заранее.'],
                ['q' => 'Можно вставить эмодзи?', 'a' => 'Наберите текст и посмотрите предпросмотр: печатается так, как видно на экране. Если эмодзи не отображается, надёжнее написать словами.'],
                ['q' => 'А можно вообще без надписи?', 'a' => 'Можно. Некоторые дизайны хороши и с одной фотографией — оставьте поле текста пустым, и надписи не будет.'],
                ['q' => 'Куда писать длинный текст?', 'a' => 'В полароид-письмо, которое кладётся внутрь коробки: места там намного больше, чем на крышке.'],
            ],
        ],
        'en' => [
            'slug' => 'what-to-write-on-the-box',
            'topic' => 'Words',
            'title' => 'What to write on the box: words that work',
            'meta_title' => 'What to write on a gift box of chocolate | Nefis',
            'meta_description' => 'Which words belong on the lid: why "Happy birthday" does not work, examples, and how much text actually fits.',
            'excerpt' => 'Choosing the photo takes a minute; choosing the words does not. The line on the lid outlives the chocolate, so it is worth the time.',
            'body' => <<<'MD'
## Why "Happy birthday" does not work

Because anyone could write it to anyone. The line is not bad — it simply says nothing, because there are no two particular people behind it.

There is a second thing to weigh. The chocolate inside is gone the same evening; the lid stays. Most people do not throw it out — it goes on a shelf, on a desk, into a box of papers. The sentence you write outlives the chocolate.

## Write the thing only the two of you know

The best lines are not flattering, they are exact:

- the name used at home;
- a joke you have been repeating for years;
- a date — the day you met, the day you moved;
- a line from a film you have watched together too many times;
- the name of a place: "That café, that table."

A line like that tells the people around nothing and tells the person it is for everything. That is the point of it.

## How much text fits

There is not much room on a lid. A short line prints large and reads from across a room; a long one shrinks and crowds the photograph. In practice three or four words work best, and one sentence still works well.

If you want to write at length, write it in the [polaroid letter](/en/mektub) instead: it goes inside the box and there is room there. A line on the lid and a letter inside do not compete.

## Five examples

- **A birthday:** instead of "Happy 33rd, old friend" — "Still here this year too, Sevil."
- **Someone you love:** instead of "I love you" — "14.08.2019 — it all started that evening."
- **A mother:** instead of "Best mum" — "That smell in the kitchen, mum."
- **A friend:** instead of "Congratulations" — "Since second year."
- **A colleague:** instead of "Good luck" — "Five years at one desk."

The same principle every time: one concrete detail instead of general praise.

## The last check

As you type you see the box on screen, and with it how the letters fall. Check two things without fail: that the name is spelt right, and that the digits in the date are the right way round. A printed lid cannot be changed, and whatever the preview shows is what the real box will carry.
MD,
            'faq' => [
                ['q' => 'Which language can I write in?', 'a' => 'Any. As you type you can see how the letters fall on the lid, so the choice can be checked in advance.'],
                ['q' => 'Can I use emoji?', 'a' => 'Type the text and look at the preview: it prints as it appears on screen. If an emoji does not show, writing it in words is safer.'],
                ['q' => 'Can I leave the lid without any text?', 'a' => 'Yes. Some designs are fine with the photograph alone — leave the text field empty and no line is printed.'],
                ['q' => 'Where do I put a long message?', 'a' => 'In the polaroid letter that goes inside the box: there is far more room there than on the lid.'],
            ],
        ],
    ],

];
