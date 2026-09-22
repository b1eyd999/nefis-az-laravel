<?php

/*
 * The second set of gift-idea pages: the product search itself ("şəkilli
 * şokolad"), the holidays the first set missed, and the buyers who look for
 * something else entirely — teachers, colleagues, companies, weddings.
 * Each entry carries its Russian version; the designs come from the
 * Azerbaijani page, and `products` are design slugs (empty = all designs).
 */
return [
    [
        'slug' => 'sekilli-sokolad',
        'menu_label' => 'Şəkilli şokolad',
        'link_text' => 'Şəkilli şokolad',
        'emoji' => '🍫',
        'title' => 'Şəkilli şokolad — öz şəklinizlə fərdi qutu',
        'meta_title' => 'Şəkilli şokolad — fotolu fərdi şokolad qutusu | Nefis',
        'meta_description' => 'Şəkilli şokolad sifarişi: öz fotonuz və sözləriniz qutunun üzərində çap olunur. 26+ dizayn, önizləmə saytda, Bakıda və bölgələrə çatdırılma.',
        'eyebrow' => 'Şəkilli şokolad',
        'intro' => 'Şəklinizi yükləyin, sözlərinizi yazın — biz onu şokolad qutusunun üzərinə çap edirik. Nəticəni sifarişdən əvvəl elə saytda görürsünüz.',
        'body' => <<<'MD'
## Şəkilli şokolad necə hazırlanır?

1. Kolleksiyadan dizayn seçirsiniz — hazır şablonun içində şəkil və yazı yerləri var.
2. Şəklinizi yükləyir, adı və istədiyiniz sözləri yazırsınız. Qutunun görüntüsü dərhal dəyişir.
3. Qutunun içinə şokolad plitkasını seçirsiniz (90–105 q).
4. İstəyə görə hədiyyə kağızı, polaroid məktub və ya canlı şəkil əlavə edirsiniz.

## Şəkil necə olmalıdır?

Aydın, işıqlı və kifayət qədər böyük şəkil ən yaxşı nəticəni verir. Köhnə çap şəkilləri də olar — onları telefonla yaxşı işıqda çəkin. Önizləmədə şəklin qutuda necə yerləşdiyini görüb dəyişə bilərsiniz.

## Hara uyğundur?

Ad günü, sevgiliyə hədiyyə, 8 Mart, ildönümü, körpə doğumu, bayramlar və korporativ hədiyyələr — eyni qutu hər münasibətə uyğunlaşır, çünki üzərindəki şəkli və sözləri siz seçirsiniz.
MD,
        'faq' => [
            ['q' => 'Şəkil birbaşa şokoladın üzərinə çap olunur?', 'a' => 'Şəkil qutunun üzərinə çap olunur, içəridə isə seçdiyiniz şokolad plitkası olur.'],
            ['q' => 'Neçə ədəd sifariş etmək olar?', 'a' => 'İstədiyiniz qədər. Sayı çox olan sifarişlər üçün Instagramda yazın — müddəti dəqiqləşdirək.'],
            ['q' => 'Şəkil keyfiyyətsiz çıxsa nə olur?', 'a' => 'Önizləmədə şəklin necə görünəcəyini sifarişdən əvvəl görürsünüz; şübhə olanda bizə yazın, birlikdə seçək.'],
        ],
        'products' => [],
        'ru' => [
            'slug' => 'shokolad-s-foto',
            'menu_label' => 'Шоколад с фото',
            'link_text' => 'Шоколад с фото',
            'title' => 'Шоколад с фото — коробка с вашей фотографией',
            'meta_title' => 'Шоколад с фото — персональная коробка с фотографией | Nefis',
            'meta_description' => 'Шоколад с вашим фото: загрузите фотографию и текст, мы напечатаем их на коробке. 26+ дизайнов, предпросмотр на сайте, доставка по Баку и регионам.',
            'eyebrow' => 'Шоколад с фото',
            'intro' => 'Загрузите фотографию, напишите свои слова — мы печатаем их на коробке шоколада. Результат видно прямо на сайте, ещё до заказа.',
            'body' => <<<'MD'
## Как делается шоколад с фото

1. Выбираете дизайн — в готовом шаблоне уже есть места под фото и надписи.
2. Загружаете фотографию, пишете имя и текст. Коробка на экране меняется сразу.
3. Выбираете плитку шоколада внутрь (90–105 г).
4. При желании добавляете подарочную упаковку, полароид-письмо или «живое фото».

## Какое фото подойдёт

Лучше всего — чёткое, светлое и достаточно крупное. Старые бумажные фотографии тоже подойдут: переснимите их телефоном при хорошем свете. В предпросмотре видно, как фото ложится на коробку, и его можно подвинуть.

## Для каких поводов

День рождения, подарок девушке или парню, 8 марта, годовщина, рождение ребёнка, праздники и корпоративные подарки — коробка подходит к любому поводу, ведь фото и слова выбираете вы.
MD,
            'faq' => [
                ['q' => 'Фото печатается прямо на шоколаде?', 'a' => 'Фотография печатается на коробке, а внутри лежит выбранная вами плитка шоколада.'],
                ['q' => 'Сколько штук можно заказать?', 'a' => 'Сколько нужно. Для больших заказов напишите нам в Instagram — согласуем сроки.'],
                ['q' => 'А если фото окажется плохого качества?', 'a' => 'В предпросмотре вы видите результат до оформления заказа; если сомневаетесь — напишите нам, подберём вместе.'],
            ],
        ],
    ],
    [
        'slug' => 'muellime',
        'menu_label' => 'Müəlliməyə',
        'link_text' => 'Müəllimə hədiyyə',
        'emoji' => '📚',
        'title' => 'Müəllimə hədiyyə — 5 Oktyabr üçün fərdi şokolad',
        'meta_title' => 'Müəllimə hədiyyə — Müəllimlər günü üçün şokolad | Nefis',
        'meta_description' => 'Müəllimlər günü və il sonu üçün hədiyyə: təşəkkür sözləri və şəkillə fərdi şokolad qutusu. Sinif üçün bir neçə ədəd. Bakıda çatdırılma.',
        'eyebrow' => 'Müəllimlər günü',
        'intro' => '5 Oktyabr və ya dərs ilinin sonu — müəlliminizə təşəkkür sözlərinizlə hazırlanmış şokolad qutusu həm zərif, həm də yadda qalan hədiyyədir.',
        'body' => <<<'MD'
## Müəlliməyə nə hədiyyə etmək olar?

Müəllimlər gününə çox vaxt eyni cür hədiyyələr alınır. Üzərində sinif şəkli və uşaqların təşəkkürü olan qutu isə fərqlənir: müəllim onu illərlə saxlayır.

## Fikirlər

- **Sinif şəkli** — uşaqların birgə şəkli və "Təşəkkür edirik" yazısı.
- **Şagirdin şəkli** — kiçik siniflər üçün uşağın öz şəkli ilə.
- **Sadə və zərif dizayn** — yalnız ad və bir neçə səmimi söz.

## Bir neçə müəllim üçün

Eyni dizaynı hər müəllimin adı ilə ayrıca sifariş edə bilərsiniz. Sayı çox olanda Instagramda yazın — hazırlanma müddətini əvvəlcədən deyək.
MD,
        'faq' => [
            ['q' => 'Valideynlər birlikdə sifariş verə bilərmi?', 'a' => 'Bəli. Adətən bir nəfər sifariş verir, qutunun üzərinə isə bütün sinfin adından yazı qoyulur.'],
            ['q' => 'Sinif şəkli uyğun olar?', 'a' => 'Bəli, aydın və işıqlı olsun. Önizləmədə şəklin necə yerləşdiyini görürsünüz.'],
            ['q' => 'Neçə günə hazır olur?', 'a' => 'Adətən 1–3 iş günü. Bayram ərəfəsində tez sifariş verin.'],
        ],
        'products' => ['family-frame', 'frame-player', 'milka', 'alpen-gold', 'alyonka-aze-sytle-vol-1', 'dark-spotify'],
        'ru' => [
            'slug' => 'uchitelyu',
            'menu_label' => 'Учителю',
            'link_text' => 'Подарок учителю',
            'title' => 'Подарок учителю — шоколад с фото и благодарностью',
            'meta_title' => 'Подарок учителю — шоколад с фото на День учителя | Nefis',
            'meta_description' => 'Подарок учителю на 5 октября и конец учебного года: коробка шоколада с фотографией класса и словами благодарности. Доставка по Баку.',
            'eyebrow' => 'День учителя',
            'intro' => '5 октября или конец учебного года — коробка шоколада со словами благодарности от класса выглядит и скромно, и по-настоящему тепло.',
            'body' => <<<'MD'
## Что подарить учителю

На День учителя обычно дарят одно и то же. А коробка с фотографией класса и подписью от детей запоминается: такие вещи хранят годами.

## Идеи

- **Фото класса** — общая фотография и надпись «Спасибо вам».
- **Фото ученика** — для младших классов, с фотографией ребёнка.
- **Сдержанный дизайн** — только имя и несколько искренних слов.

## Если учителей несколько

Один и тот же дизайн можно заказать для каждого — со своим именем. Для больших заказов напишите нам в Instagram, подскажем сроки.
MD,
            'faq' => [
                ['q' => 'Можно заказать от лица всего класса?', 'a' => 'Да. Обычно заказ оформляет один родитель, а на коробке пишут поздравление от всего класса.'],
                ['q' => 'Подойдёт ли фотография класса?', 'a' => 'Да, если она чёткая и светлая. В предпросмотре видно, как она ляжет на коробку.'],
                ['q' => 'За сколько дней делается?', 'a' => 'Обычно 1–3 рабочих дня. Перед праздником заказывайте заранее.'],
            ],
        ],
    ],
    [
        'slug' => 'novruz',
        'menu_label' => 'Novruz',
        'link_text' => 'Novruz hədiyyəsi',
        'emoji' => '🌱',
        'title' => 'Novruz hədiyyəsi — fərdi şokolad qutusu',
        'meta_title' => 'Novruz hədiyyəsi — şəkilli fərdi şokolad qutusu | Nefis',
        'meta_description' => 'Novruz üçün hədiyyə: ailə şəkli və bayram təbriki ilə fərdi şokolad qutusu. Qonaqlara paylamaq üçün bir neçə ədəd. Bakıda və bölgələrə çatdırılma.',
        'eyebrow' => 'Novruz bayramı',
        'intro' => 'Bayram süfrəsinə və qonaqlara — ailə şəkliniz və Novruz təbrikinizlə hazırlanan şokolad qutusu.',
        'body' => <<<'MD'
## Novruzda nə hədiyyə etmək olar?

Novruzda qonaq çox olur, hədiyyələr isə bir-birinə bənzəyir. Üzərində ailənizin şəkli və təbrikiniz olan qutu həm süfrəyə yaraşır, həm də sonradan xatirə kimi qalır.

## Kimə

- **Valideynlərə və nənə-babaya** — ailə şəkli ilə.
- **Qohumlara və qonşulara** — eyni dizayn, hər ailənin adı ilə.
- **Uşaqlara** — sevimli dizaynlarda, öz şəkilləri ilə.

## Əvvəlcədən sifariş edin

Bayram ərəfəsində sifarişlər çoxalır. Hədiyyələrin vaxtında çatması üçün bir həftə əvvəl sifariş verin.
MD,
        'faq' => [
            ['q' => 'Novruz üçün xüsusi dizayn varmı?', 'a' => 'Bayram təbrikini istənilən dizaynın yazı sahəsinə yaza bilərsiniz. Yeni dizaynlar üçün Instagramda bizə yazın.'],
            ['q' => 'Bir neçə ailəyə eyni hədiyyə göndərmək olar?', 'a' => 'Bəli, hər birini ayrıca fərdiləşdirib səbətə əlavə edin — hamısını bir sifarişlə göndəririk.'],
            ['q' => 'Bölgələrə çatdırırsınız?', 'a' => 'Bəli, Azərbaycanın bütün bölgələrinə poçtla göndəririk.'],
        ],
        'products' => ['alyonka-aze-sytle-vol-1', 'alyonka-aze-sytle-vol-2', 'family-frame', 'milka', 'alpen-gold', 'kinder-vol-1', 'kinder-vol-2', 'cici-bebe-sari'],
        'ru' => [
            'slug' => 'na-novruz',
            'menu_label' => 'На Новруз',
            'link_text' => 'Подарок на Новруз',
            'title' => 'Подарок на Новруз — шоколад с фото',
            'meta_title' => 'Подарок на Новруз — шоколад с фотографией | Nefis',
            'meta_description' => 'Подарок на Новруз: коробка шоколада с семейным фото и поздравлением. Несколько коробок для гостей и родных. Доставка по Баку и регионам.',
            'eyebrow' => 'Новруз',
            'intro' => 'К праздничному столу и для гостей — коробка шоколада с вашей семейной фотографией и поздравлением с Новрузом.',
            'body' => <<<'MD'
## Что подарить на Новруз

В Новруз много гостей, а подарки часто одинаковые. Коробка с фотографией вашей семьи и поздравлением и стол украсит, и останется на память.

## Кому

- **Родителям, бабушкам и дедушкам** — с семейным фото.
- **Родственникам и соседям** — один дизайн, с именем каждой семьи.
- **Детям** — в любимых дизайнах, с их фотографиями.

## Закажите заранее

Перед праздником заказов больше обычного. Чтобы подарки успели, оформите их за неделю.
MD,
            'faq' => [
                ['q' => 'Есть ли отдельный новрузовский дизайн?', 'a' => 'Поздравление можно написать в текстовом поле любого дизайна. По новым дизайнам напишите нам в Instagram.'],
                ['q' => 'Можно отправить подарки нескольким семьям?', 'a' => 'Да, оформите каждую коробку отдельно и добавьте в корзину — отправим одним заказом.'],
                ['q' => 'Доставляете в регионы?', 'a' => 'Да, по всему Азербайджану отправляем почтой.'],
            ],
        ],
    ],
    [
        'slug' => 'korporativ',
        'menu_label' => 'Korporativ',
        'link_text' => 'Korporativ hədiyyələr',
        'emoji' => '💼',
        'title' => 'Korporativ hədiyyələr — loqonuzla şokolad qutuları',
        'meta_title' => 'Korporativ hədiyyələr — loqolu şokolad qutuları | Nefis',
        'meta_description' => 'Əməkdaşlara və müştərilərə korporativ hədiyyə: şirkətin loqosu və təbriki ilə şokolad qutuları. Çox sayda sifariş, Bakıda çatdırılma.',
        'eyebrow' => 'Korporativ',
        'intro' => 'Əməkdaşlarınıza, müştərilərinizə və tərəfdaşlarınıza — şirkətin loqosu, əməkdaşın adı və təbrikinizlə hazırlanan şokolad qutuları.',
        'body' => <<<'MD'
## Şirkət üçün hədiyyə

Korporativ hədiyyə iki şeyi göstərir: diqqəti və zövqü. Loqonuzla və hər əməkdaşın adı ilə hazırlanan qutu bunu sadə şəkildə edir — həm şirin, həm şəxsi.

## Necə olur

- **Loqonuzu şəkil kimi yükləyirsiniz** — dizaynın şəkil yerinə düşür.
- **Hər qutuda ayrı ad** — əməkdaşın və ya müştərinin adı yazılır.
- **Bayram təbriki** — Yeni il, 8 Mart, şirkətin ildönümü.

## Çox sayda sifariş

Sayı çox olan sifarişlər üçün Instagramda bizə yazın: hazırlanma müddətini və şərtləri əvvəlcədən dəqiqləşdirək.
MD,
        'faq' => [
            ['q' => 'Loqonu qutuya qoymaq olar?', 'a' => 'Bəli, loqonu şəkil kimi yükləyin — dizaynın şəkil yerində görünəcək. Şəffaf fonlu, aydın fayl daha yaxşı çıxır.'],
            ['q' => 'Neçə ədəddən sifariş qəbul edirsiniz?', 'a' => 'Az sayda da olar. Böyük sifarişlər üçün Instagramda yazın — müddəti birlikdə planlaşdıraq.'],
            ['q' => 'Qablaşdırma da var?', 'a' => 'Bəli, qutuları hədiyyə kağızına büküb lentlə bağlaya bilərik.'],
        ],
        'products' => ['netflix', 'google', 'dark-spotify', 'frame-player', 'alpen-gold', 'milka'],
        'ru' => [
            'slug' => 'korporativnye',
            'menu_label' => 'Корпоративные',
            'link_text' => 'Корпоративные подарки',
            'title' => 'Корпоративные подарки — шоколад с вашим логотипом',
            'meta_title' => 'Корпоративные подарки — шоколад с логотипом | Nefis',
            'meta_description' => 'Корпоративные подарки сотрудникам и клиентам: коробки шоколада с логотипом компании и поздравлением. Крупные заказы, доставка по Баку.',
            'eyebrow' => 'Для компаний',
            'intro' => 'Сотрудникам, клиентам и партнёрам — коробки шоколада с логотипом компании, именем получателя и вашим поздравлением.',
            'body' => <<<'MD'
## Подарок от компании

Корпоративный подарок говорит о внимании и вкусе. Коробка с вашим логотипом и именем каждого сотрудника делает это просто: и сладко, и лично.

## Как это работает

- **Логотип загружаете как фотографию** — он встаёт в место под фото в дизайне.
- **На каждой коробке своё имя** — сотрудника или клиента.
- **Поздравление** — с Новым годом, 8 марта или годовщиной компании.

## Большие заказы

Для крупных партий напишите нам в Instagram: заранее согласуем сроки и условия.
MD,
            'faq' => [
                ['q' => 'Можно разместить логотип на коробке?', 'a' => 'Да, загрузите логотип как фотографию — он встанет в место под фото. Лучше файл с прозрачным фоном и в хорошем качестве.'],
                ['q' => 'От какого количества работаете?', 'a' => 'Можно и небольшую партию. Для крупных заказов напишите в Instagram — спланируем сроки.'],
                ['q' => 'Упаковку тоже делаете?', 'a' => 'Да, коробки можно завернуть в подарочную бумагу и перевязать лентой.'],
            ],
        ],
    ],
    [
        'slug' => 'toya',
        'menu_label' => 'Toya',
        'link_text' => 'Toy hədiyyəsi',
        'emoji' => '💒',
        'title' => 'Toy hədiyyəsi və qonaqlara şokolad',
        'meta_title' => 'Toy hədiyyəsi — bəy-gəlin şəkli ilə şokolad | Nefis',
        'meta_description' => 'Toy və nişan üçün: bəy-gəlinin şəkli ilə hədiyyə qutusu və qonaqlara paylamaq üçün adlı şokoladlar. Bakıda və bölgələrə çatdırılma.',
        'eyebrow' => 'Toy və nişan',
        'intro' => 'Bəy-gəlinə hədiyyə və ya qonaqlara xatirə — üzərində onların şəkli, adları və toy tarixi olan şokolad qutuları.',
        'body' => <<<'MD'
## Toya nə hədiyyə etmək olar?

Toy hədiyyəsi çox vaxt zərf olur. Əgər yadda qalan bir şey istəyirsinizsə, bəy-gəlinin şəkli, adları və toy tarixi ilə hazırlanan qutu bunu edir.

## Qonaqlara şokolad

Bir çox cütlük qonaqlara xatirə olaraq kiçik hədiyyə paylayır. Eyni dizaynda, bəy-gəlinin şəkli və toy tarixi ilə şokolad qutuları bunun üçün uyğundur.

## Nişan və hinaya da

Eyni qutuları nişan, hina və ildönümü üçün də hazırlaya bilərsiniz — dəyişən yalnız şəkil və yazıdır.
MD,
        'faq' => [
            ['q' => 'Qonaqlar üçün çox sayda sifariş etmək olar?', 'a' => 'Bəli. Say çox olanda Instagramda yazın — hazırlanma müddətini əvvəlcədən planlaşdıraq.'],
            ['q' => 'Toy tarixini yazmaq olar?', 'a' => 'Bəli, yazı sahələrinə adları və tarixi yazırsınız.'],
            ['q' => 'Toy videosunu əlavə etmək olar?', 'a' => 'Bəli, canlı şəkil ilə: qutudakı şəklə telefonu tutanda videonuz oynayır.'],
        ],
        'products' => ['love-story-vol-1', 'love-story-vol-2', 'love-story-vol-3', 'i-love', 'family-frame', 'love-is-red', 'frame-player'],
        'ru' => [
            'slug' => 'na-svadbu',
            'menu_label' => 'На свадьбу',
            'link_text' => 'Подарок на свадьбу',
            'title' => 'Подарок на свадьбу и шоколад для гостей',
            'meta_title' => 'Подарок на свадьбу — шоколад с фото молодожёнов | Nefis',
            'meta_description' => 'Подарок на свадьбу и бонбоньерки гостям: коробки шоколада с фотографией молодожёнов, их именами и датой свадьбы. Доставка по Баку.',
            'eyebrow' => 'Свадьба и помолвка',
            'intro' => 'Подарок молодожёнам или память для гостей — коробки шоколада с их фотографией, именами и датой свадьбы.',
            'body' => <<<'MD'
## Что подарить на свадьбу

Чаще всего на свадьбу дарят конверт. Если хочется чего-то запоминающегося — коробка с фотографией пары, именами и датой свадьбы подойдёт как нельзя лучше.

## Шоколад для гостей

Многие пары раздают гостям маленькие подарки на память. Коробки в одном дизайне, с фотографией молодожёнов и датой, отлично для этого подходят.

## И на помолвку

Те же коробки делают на помолвку, хну и годовщину — меняются только фотография и надпись.
MD,
            'faq' => [
                ['q' => 'Можно заказать много коробок для гостей?', 'a' => 'Да. При большом количестве напишите в Instagram — заранее спланируем сроки.'],
                ['q' => 'Можно написать дату свадьбы?', 'a' => 'Да, имена и дату вы вписываете в текстовые поля.'],
                ['q' => 'Можно добавить свадебное видео?', 'a' => 'Да, через «живое фото»: наводите телефон на фотографию — и играет ваше видео.'],
            ],
        ],
    ],
    [
        'slug' => 'hemkara',
        'menu_label' => 'Həmkara',
        'link_text' => 'Həmkara hədiyyə',
        'emoji' => '☕',
        'title' => 'Həmkara hədiyyə — iş yoldaşına şokolad',
        'meta_title' => 'Həmkara hədiyyə — iş yoldaşına fərdi şokolad | Nefis',
        'meta_description' => 'İş yoldaşına hədiyyə: adı, şəkli və zarafatlı yazısı ilə şokolad qutusu. Ad günü, 8 Mart və komanda üçün bir neçə ədəd. Bakıda çatdırılma.',
        'eyebrow' => 'İş yoldaşına',
        'intro' => 'Ofisdə ad günü, 8 Mart və ya sadəcə təşəkkür — həmkarınızın adı və kiçik zarafatla hazırlanan qutu həmişə yerinə düşür.',
        'body' => <<<'MD'
## Həmkara nə hədiyyə etmək olar?

İş yoldaşına hədiyyə çox şəxsi də olmamalıdır, quru da. Üzərində adı və komandanın zarafatı olan şokolad qutusu tam ortasıdır: şirin, gülməli və hamının xoşuna gəlir.

## Komanda üçün

Bir neçə həmkar üçün eyni dizaynı hər birinin adı ilə sifariş edin — sifariş bir olur, qutular fərqli.

## Fikirlər

- Komandanın şəkli və "İlin ən yaxşı komandası" yazısı.
- Spotify dizaynında ofisin "himni".
- Netflix üslubunda həmkarınızın "seriyası".
MD,
        'faq' => [
            ['q' => 'Bir neçə həmkar üçün sifariş verə bilərəmmi?', 'a' => 'Bəli, hər birini ayrıca adla fərdiləşdirib səbətə əlavə edin.'],
            ['q' => 'Ofisə çatdırırsınız?', 'a' => 'Bəli, Bakıda istənilən ünvana çatdırırıq.'],
            ['q' => 'Neçə günə hazır olur?', 'a' => 'Adətən 1–3 iş günü.'],
        ],
        'products' => ['milka', 'alpen-gold', 'netflix', 'google', 'dark-spotify', 'chocolate-puppin'],
        'ru' => [
            'slug' => 'kollege',
            'menu_label' => 'Коллеге',
            'link_text' => 'Подарок коллеге',
            'title' => 'Подарок коллеге — шоколад с именем и шуткой',
            'meta_title' => 'Подарок коллеге — шоколад с именем и фото | Nefis',
            'meta_description' => 'Подарок коллеге: коробка шоколада с именем, фотографией и дружеской надписью. На день рождения, 8 марта и для всей команды. Доставка по Баку.',
            'eyebrow' => 'Коллеге',
            'intro' => 'День рождения в офисе, 8 марта или просто спасибо — коробка с именем коллеги и небольшой шуткой всегда к месту.',
            'body' => <<<'MD'
## Что подарить коллеге

Подарок коллеге не должен быть ни слишком личным, ни совсем формальным. Коробка шоколада с его именем и внутренней шуткой команды — как раз середина: и сладко, и весело.

## Для всей команды

Закажите один дизайн для нескольких коллег — с именем каждого. Заказ один, коробки разные.

## Идеи

- Фото команды и надпись «Лучшая команда года».
- «Гимн» отдела в дизайне Spotify.
- «Сериал» про коллегу в стиле Netflix.
MD,
            'faq' => [
                ['q' => 'Можно заказать для нескольких коллег?', 'a' => 'Да, оформите каждую коробку со своим именем и добавьте в корзину.'],
                ['q' => 'Доставите в офис?', 'a' => 'Да, по Баку доставляем на любой адрес.'],
                ['q' => 'Сколько занимает изготовление?', 'a' => 'Обычно 1–3 рабочих дня.'],
            ],
        ],
    ],
];
