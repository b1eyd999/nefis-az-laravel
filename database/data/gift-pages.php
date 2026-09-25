<?php

/*
 * The first gift-idea pages (/hediyye/...): one per search people make —
 * "ad günü hədiyyəsi", "sevgiliyə hədiyyə", "8 mart hədiyyəsi"… Written once
 * by the migration; after that the owner edits them in admin → Hədiyyə səhifələri.
 * `products` are design slugs; a page with none shows every design.
 */
return [
    [
        'slug' => 'ad-gunu',
        'menu_label' => 'Ad günü',
        'emoji' => '🎂',
        'title' => 'Ad günü üçün fərdi hədiyyə',
        'meta_title' => 'Ad günü hədiyyəsi, şəkilli şokolad qutusu | Nefis',
        'meta_description' => 'Ad günü üçün orijinal hədiyyə: ad günü sahibinin şəkli və sizin təbrikinizlə fərdi şokolad qutusu. Onlayn sifariş, Bakıda və bölgələrə çatdırılma.',
        'eyebrow' => 'Ad günü hədiyyəsi',
        'intro' => 'Ad günü sahibinin şəkli, adı və sizin təbrik sözlərinizlə hazırlanan şokolad qutusu, şokolad yeyilib bitəndən sonra da xatirə kimi qalan hədiyyə.',
        'body' => <<<'MD'
## Ad gününə nə hədiyyə etmək olar?

Ad günü hədiyyəsi seçmək çox vaxt çətin olur: gül bir neçə günə solur, adi şokolad isə tez unudulur. Nefis-də siz dizaynı seçir, ad günü sahibinin şəklini yükləyir və təbrikinizi yazırsınız, biz onu çap edib içinə sevdiyi şokoladı qoyuruq. Belə hədiyyəni heç kim "yenə şokolad" deyib kənara qoymur: qutunu açmazdan əvvəl hamı şəklə baxır.

## Kimə uyğundur?

- **Sevgiliyə və həyat yoldaşına**, birgə şəkliniz və ürəyinizdən keçən sözlərlə.
- **Uşağa**, Kinder, Barbie və maşın dizaynlarında, öz şəkli ilə.
- **Dosta, qardaşa, bacıya**, Netflix, Google, Spotify üslubunda zarafatlı dizaynlarla.
- **Anaya, ataya, nənəyə**, ailə şəkli ilə.

## Necə sifariş etmək olar?

1. Aşağıdakı dizaynlardan birini seçin.
2. Şəkli yükləyin, adı və təbriki yazın, nəticəni saytda dərhal görürsünüz.
3. Qutunun içinə şokoladı seçin, istəsəniz hədiyyə qablaşdırması və polaroid məktub əlavə edin.
4. Sifarişi verin, Bakıda ünvana çatdırırıq, bölgələrə poçtla göndəririk.

Ad gününə az qalıb? Sifarişi mümkün qədər tez verin: hazırlanma və çatdırılma adətən 1–3 iş günü çəkir.
MD,
        'faq' => [
            ['q' => 'Ad günü hədiyyəsi neçə günə hazır olur?', 'a' => 'Sifariş adətən 1–3 iş günü ərzində hazırlanıb çatdırılır. Tarix yaxındırsa, sifarişi tez verin və Instagramda bizə yazın.'],
            ['q' => 'Qutuya ad günü sahibinin adını və yaşını yaza bilərəmmi?', 'a' => 'Bəli. Dizayndakı yazı sahələrinə ad, yaş, tarix və təbrik sözlərini yazırsınız; nəticəni sifarişdən əvvəl saytda görürsünüz.'],
            ['q' => 'Qutunun içində hansı şokolad olur?', 'a' => 'Şokoladı özünüz seçirsiniz: Milka, Alpen Gold və digər brendlərin 90–105 qramlıq plitkaları.'],
            ['q' => 'Hədiyyəni birbaşa ad günü sahibinə göndərə bilərsinizmi?', 'a' => 'Bəli. Sifarişdə alıcının adını, telefonunu və ünvanını yazın, Bakıda qapıya çatdırırıq, bölgələrə poçtla göndəririk.'],
        ],
        'products' => [],
    ],
    [
        'slug' => 'sevgiliye',
        'menu_label' => 'Sevgiliyə',
        'emoji' => '💝',
        'title' => 'Sevgiliyə hədiyyə, şəkilli şokolad qutusu',
        'meta_title' => 'Sevgiliyə hədiyyə, birgə şəkillə fərdi şokolad | Nefis',
        'meta_description' => 'Sevgilinizə romantik və fərdi hədiyyə: birgə şəkliniz və sevgi sözlərinizlə şokolad qutusu, polaroid məktub və canlı şəkil. Bakıda çatdırılma.',
        'eyebrow' => 'Sevgiliyə hədiyyə',
        'intro' => 'Birgə şəkliniz, sizə aid bir tarix və yalnız ikinizin başa düşəcəyi sözlər, sevgiliyə hədiyyə ən çox belə yadda qalır.',
        'body' => <<<'MD'
## Sevgiliyə nə almaq olar?

Qıza və ya oğlana hədiyyə seçəndə ən vacibi onun məhz onun üçün düşünüldüyünü hiss etməsidir. Fərdi şokolad qutusunda birgə şəkliniz, tanış olduğunuz gün və ya sevgi etirafınız olur, belə hədiyyəni başqa heç yerdən almaq mümkün deyil.

## Hədiyyəni necə daha xüsusi etmək olar?

- **Polaroid məktub**, qutunun içinə şəkil və sözlərinizlə çap olunmuş kiçik məktub qoyun.
- **Canlı şəkil**, telefonu qutudakı şəklə tutanda üstündə sizin videonuz oynayır, heç bir tətbiq yükləmədən.
- **Hədiyyə qablaşdırması**, qutunu naxışlı kağıza büküb lentlə bağlayırıq.

## Hansı dizaynı seçim?

"Love story" dizaynları birgə şəkil və uzun sevgi mətni üçündür, "Love is…" qısa və şirin sözlər üçün, Spotify və "Frame & Player" isə "öz mahnısı" olan cütlüklər üçün.
MD,
        'faq' => [
            ['q' => 'Hədiyyəni sevgilimə sürpriz kimi göndərə bilərəmmi?', 'a' => 'Bəli. Alıcı olaraq sevgilinizin adını, telefonunu və ünvanını yazın. Qutunun üzərində və içində yalnız sizin seçdiyiniz şəkil və sözlər olur.'],
            ['q' => 'Bir qutuya neçə şəkil qoymaq olar?', 'a' => 'Dizayndan asılıdır: bəzilərində bir, bəzilərində bir neçə şəkil yeri var. Dizaynı açanda bunu görəcəksiniz.'],
            ['q' => 'Canlı şəkil nədir?', 'a' => 'Qutudakı şəklə telefonun kamerasını tutanda şəklin üstündə sizin videonuz oynayır. Tətbiq lazım deyil, QR kodu oxutmaq kifayətdir.'],
        ],
        'products' => ['love-story-vol-1', 'love-story-vol-2', 'love-story-vol-3', 'i-love', 'love-is-red', 'love-is-orange', 'love-is-blue', 'dark-spotify', 'frame-player', 'netflix', 'milka', 'chocolate-puppin'],
    ],
    [
        'slug' => '14-fevral',
        'menu_label' => '14 Fevral',
        'emoji' => '❤️',
        'title' => '14 Fevral, Sevgililər günü hədiyyəsi',
        'meta_title' => '14 Fevral hədiyyəsi, Sevgililər günü üçün | Nefis',
        'meta_description' => 'Sevgililər günü üçün fərdi hədiyyə: birgə şəkliniz və sevgi sözlərinizlə şokolad qutusu. 14 Fevral hədiyyəsini onlayn sifariş edin, Bakıda çatdırılma.',
        'eyebrow' => 'Sevgililər günü',
        'intro' => '14 Fevralda gül və ürək formalı şokolad hər yerdədir. Sizin hədiyyəniz isə yeganə olacaq, üzərində ikinizin şəkli və sizin sözləriniz.',
        'body' => <<<'MD'
## Sevgililər gününə orijinal hədiyyə

Sevgililər günündə hədiyyənin dəyəri qiymətində deyil, arxasındakı düşüncədədir. Birgə şəkliniz olan şokolad qutusu həm şirin sürprizdir, həm də illər sonra baxılacaq xatirə.

## Hədiyyəni tamamlayın

- Qutunun içinə **polaroid məktub** qoyun, ilk görüşünüzün şəkli və bir neçə səmimi söz.
- **Canlı şəkil** əlavə edin: qutudakı şəkil telefonda sizin videonuzla canlanır.
- Qutunu **hədiyyə kağızına** büküb lentlə bağlayırıq.

## Vaxtında çatsın

Fevralın ortasında sifarişlər çox olur. Hədiyyənin 14 Fevrala çatması üçün sifarişi ən azı bir həftə əvvəl verməyi məsləhət görürük.
MD,
        'faq' => [
            ['q' => '14 Fevral hədiyyəsini nə vaxt sifariş etməliyəm?', 'a' => 'Ən azı bir həftə əvvəl. Bayram ərəfəsində sifarişlər çoxalır, erkən sifariş hədiyyənin vaxtında çatmasına kömək edir.'],
            ['q' => 'Oğlan üçün də uyğun dizayn varmı?', 'a' => 'Bəli. Netflix, Spotify və Google üslubunda dizaynlar oğlanlar üçün də çox uyğundur, öz şəkliniz və zarafatlı sözlərinizlə.'],
            ['q' => 'Hədiyyəni sevgilimin ünvanına göndərə bilərsinizmi?', 'a' => 'Bəli, Bakıda qapıya çatdırırıq, bölgələrə isə poçtla göndəririk.'],
        ],
        'products' => ['love-story-vol-1', 'love-story-vol-2', 'love-story-vol-3', 'i-love', 'love-is-red', 'love-is-orange', 'love-is-blue', 'dark-spotify', 'frame-player', 'netflix', 'google'],
    ],
    [
        'slug' => '8-mart',
        'menu_label' => '8 Mart',
        'emoji' => '🌷',
        'title' => '8 Mart hədiyyəsi, fərdi şokolad qutusu',
        'meta_title' => '8 Mart hədiyyəsi, anaya, həyat yoldaşına, rəfiqəyə | Nefis',
        'meta_description' => 'Qadınlar günü üçün fərdi hədiyyə: şəkil və təbrik sözləri ilə şokolad qutusu. Anaya, həyat yoldaşına, bacıya, rəfiqəyə və iş yoldaşlarına. Bakıda çatdırılma.',
        'eyebrow' => 'Qadınlar günü',
        'intro' => '8 Martda ananıza, həyat yoldaşınıza, bacınıza və ya iş yoldaşlarınıza gül dəstəsindən uzun yaşayan hədiyyə verin, onların öz şəkli ilə şokolad qutusu.',
        'body' => <<<'MD'
## 8 Martda nə hədiyyə etmək olar?

Qadınlar günündə gül və şokolad klassikdir. Biz bu klassikanı xatirəyə çeviririk: qutunun üzərində onun şəkli və sizin təbrikiniz, içində isə sevdiyi şokolad olur.

## Kimə hansı dizayn?

- **Anaya və nənəyə**, ailə şəkli ilə "Family Frame".
- **Həyat yoldaşına və sevgiliyə**, "Love story" və "Love is…" dizaynları.
- **Bacıya və rəfiqəyə**, Milka, Alyonka və Barbie üslubunda şən dizaynlar.
- **İş yoldaşlarına**, eyni dizaynda, hər birinin adı ilə ayrıca qutu.

## Vaxtında sifariş edin

Mart ayının əvvəlində sifarişlər çoxalır. Hədiyyənin bayrama çatması üçün sifarişi bir həftə əvvəl verin.
MD,
        'faq' => [
            ['q' => 'Bir neçə iş yoldaşım üçün sifariş verə bilərəmmi?', 'a' => 'Bəli. Eyni dizaynı hər biri üçün ayrıca ad və şəkillə fərdiləşdirib səbətə əlavə edin, hamısını bir sifarişdə göndəririk.'],
            ['q' => 'Qutunu hədiyyə kağızına bükürsünüz?', 'a' => 'Bəli, istəsəniz qutunu naxışlı hədiyyə kağızına büküb lentlə bağlayırıq. Kağızlara "Qablaşdırma" bölməsində baxa bilərsiniz.'],
            ['q' => 'Gül əvəzinə şokolad qutusu uyğun olarmı?', 'a' => 'Çoxları məhz buna görə seçir: gül bir neçə günə solur, şəkilli qutu isə xatirə kimi saxlanılır. İstəsəniz onu gül dəstəsinə əlavə kimi də verə bilərsiniz.'],
        ],
        'products' => ['family-frame', 'love-story-vol-1', 'love-story-vol-2', 'love-story-vol-3', 'milka', 'alyonka-aze-sytle-vol-1', 'alyonka-aze-sytle-vol-2', 'barbie-vol-1', 'love-is-red', 'love-is-orange', 'love-is-blue', 'frame-player', 'i-love'],
    ],
    [
        'slug' => 'anaya',
        'menu_label' => 'Anaya',
        'emoji' => '👩‍👧',
        'title' => 'Anaya hədiyyə, ailə şəkli ilə şokolad qutusu',
        'meta_title' => 'Anaya hədiyyə, ailə şəkli ilə fərdi şokolad qutusu | Nefis',
        'meta_description' => 'Ananın ad günü, 8 Mart və bayramlar üçün fərdi hədiyyə: ailə şəkliniz və sevgi dolu sözlərinizlə şokolad qutusu. Onlayn sifariş, Bakıda və bölgələrə çatdırılma.',
        'eyebrow' => 'Anaya hədiyyə',
        'intro' => 'Ana üçün ən qiymətli hədiyyə ailəsinin şəklidir. Onu sevdiyi şokoladla birlikdə, ürəkdən gələn sözlərlə təqdim edin.',
        'body' => <<<'MD'
## Anaya nə hədiyyə etmək olar?

Analar üçün hədiyyənin qiyməti deyil, diqqəti önəmlidir. Uşaqlarının və nəvələrinin şəkli olan şokolad qutusunu çox vaxt atmırlar, şokolad yeyilir, qutu isə rəfdə xatirə kimi qalır.

## Hədiyyəni daha səmimi edin

- **Ailə şəkli**, "Family Frame" dizaynında.
- **Polaroid məktub**, qutunun içinə şəkil və sözlərinizlə kiçik məktub.
- **Canlı şəkil**, nəvələrin videosu qutudakı şəklin üstündə oynayır.

## Hansı münasibətlərə?

Ananın ad günü, 8 Mart, Novruz bayramı və ya sadəcə "səni sevirəm" demək üçün.
MD,
        'faq' => [
            ['q' => 'Başqa şəhərdə yaşayan anama göndərə bilərsinizmi?', 'a' => 'Bəli, Azərbaycanın bölgələrinə poçtla göndəririk. Sifarişdə ananızın adını, telefonunu və poçt indeksini yazın.'],
            ['q' => 'Qutuya bir neçə şəkil qoymaq olar?', 'a' => 'Bəzi dizaynlarda bir neçə şəkil yeri var. Dizaynı açanda neçə şəkil yükləyə biləcəyinizi görəcəksiniz.'],
            ['q' => 'Şəkil keyfiyyətsizdirsə nə olar?', 'a' => 'Ən yaxşı nəticə üçün aydın, işıqlı şəkil yükləyin. Şəklin qutuda necə görünəcəyini sifarişdən əvvəl önizləmədə görürsünüz.'],
        ],
        'products' => ['family-frame', 'baby-vol-1', 'baby-vol-2', 'cici-bebe-sari', 'milka', 'alpen-gold', 'alyonka-aze-sytle-vol-1', 'alyonka-aze-sytle-vol-2', 'frame-player', 'love-story-vol-2'],
    ],
    [
        'slug' => 'korpeye',
        'menu_label' => 'Körpəyə',
        'emoji' => '👶',
        'title' => 'Körpə doğumu üçün hədiyyə',
        'meta_title' => 'Körpə doğumu hədiyyəsi, şəkilli şokolad qutusu | Nefis',
        'meta_description' => 'Yeni doğulan körpə üçün fərdi hədiyyə və qonaqlara paylamaq üçün şokolad: körpənin şəkli və adı ilə şokolad qutuları. Bakıda və bölgələrə çatdırılma.',
        'eyebrow' => 'Doğum hədiyyəsi',
        'intro' => 'Körpənin ilk şəkli və adı ilə şokolad qutusu, həm valideynlərə hədiyyə, həm də qonaqlara paylamaq üçün şirin xatirə.',
        'body' => <<<'MD'
## Yeni doğulan körpəyə nə hədiyyə edilir?

Doğum hədiyyəsi əslində valideynlər üçün seçilir. Körpənin şəkli olan şokolad qutusu ailənin illər sonra da saxladığı ilk xatirələrdən biri olur.

## Qonaqlar üçün şokolad

Bir çox ailə doğum münasibətilə qohum və dostlara körpənin şəkli və adı olan şokolad paylayır. Eyni dizaynı bir neçə ədəd sifariş edə bilərsiniz.

## Dizaynlar

"Baby" və "Cici bebe" dizaynları körpənin şəkli və adı üçün hazırlanıb, "Family Frame" isə bütün ailənin şəkli üçün.
MD,
        'faq' => [
            ['q' => 'Körpənin adını və doğum tarixini yazmaq olar?', 'a' => 'Bəli, yazı sahələrinə ad, tarix və istədiyiniz sözləri yazırsınız.'],
            ['q' => 'Qonaqlar üçün çoxlu qutu sifariş etmək olar?', 'a' => 'Bəli. Sayı çox olan sifarişlər üçün Instagramda bizə yazın, hazırlanma müddətini birlikdə dəqiqləşdirək.'],
            ['q' => 'Qutunun içində nə olur?', 'a' => 'Seçdiyiniz 90–105 qramlıq şokolad plitkası: Milka, Alpen Gold və digər brendlər.'],
        ],
        'products' => ['baby-vol-1', 'baby-vol-2', 'cici-bebe-sari', 'family-frame', 'kinder-vol-1', 'kinder-vol-2'],
    ],
    [
        'slug' => 'usaga',
        'menu_label' => 'Uşağa',
        'emoji' => '🧸',
        'title' => 'Uşaq üçün hədiyyə, öz şəkli ilə şokolad',
        'meta_title' => 'Uşaq üçün hədiyyə, öz şəkli ilə şokolad qutusu | Nefis',
        'meta_description' => 'Uşağın ad günü və bayramlar üçün hədiyyə: Kinder, Barbie və maşın dizaynlarında, uşağın öz şəkli və adı ilə şokolad qutusu. Bakıda çatdırılma.',
        'eyebrow' => 'Uşaqlar üçün',
        'intro' => 'Uşaqlar öz şəkillərini sevimli şokoladın qutusunda görəndə çox sevinirlər. Kinder, Barbie və maşın dizaynlarından birini seçin.',
        'body' => <<<'MD'
## Uşağı hansı hədiyyə sevindirir?

Oyuncaq tez unudulur, amma "öz Kinder"i olan uşaq onu bütün dostlarına göstərir. Fərdi şokolad qutusu həm şirin hədiyyədir, həm də uşağın adı ilə hazırlanan xüsusi bir şey.

## Uşaq ad günü üçün

Uşağın ad günündə qonaqlara onun şəkli olan şokolad paylamaq da gözəl ideyadır, bayram bitəndən sonra da hamı onu xatırlayır.

## Dizaynlar

- **Kinder**, tanış üslubda, uşağın şəkli ilə.
- **Barbie**, qızlar üçün çəhrayı dizayn.
- **Avtomobil**, maşın sevən oğlanlar üçün.
- **Alyonka**, Azərbaycan üslubunda sevimli dizayn.
MD,
        'faq' => [
            ['q' => 'Uşağın adını qutuya yaza bilərəmmi?', 'a' => 'Bəli, yazı sahələrinə uşağın adını və təbrikinizi yazırsınız.'],
            ['q' => 'Uşaq ad günü üçün bir neçə qutu sifariş etmək olar?', 'a' => 'Bəli. Çox sayda sifariş üçün Instagramda bizə yazın.'],
            ['q' => 'İçinə hansı şokoladı qoyursunuz?', 'a' => 'Şokoladı özünüz seçirsiniz, uşağın sevdiyi brendi seçə bilərsiniz.'],
        ],
        'products' => ['kinder-vol-1', 'kinder-vol-2', 'barbie-vol-1', 'avtomobil', 'alyonka-aze-sytle-vol-1', 'alyonka-aze-sytle-vol-2', 'cici-bebe-sari', 'baby-vol-1', 'baby-vol-2'],
    ],
    [
        'slug' => 'qiza',
        'menu_label' => 'Qıza',
        'emoji' => '🎀',
        'title' => 'Qıza hədiyyə, şəkilli fərdi şokolad qutusu',
        'meta_title' => 'Qıza hədiyyə, sevgiliyə, bacıya, rəfiqəyə | Nefis',
        'meta_description' => 'Qıza orijinal hədiyyə: onun şəkli və sizin sözlərinizlə şokolad qutusu, polaroid məktub və hədiyyə qablaşdırması. Sevgiliyə, bacıya, rəfiqəyə.',
        'eyebrow' => 'Qıza hədiyyə',
        'intro' => 'Sevgilinizə, bacınıza və ya rəfiqənizə, onun şəkli, adı və sizin sözlərinizlə hazırlanan, ilk baxışdan sevindirən hədiyyə.',
        'body' => <<<'MD'
## Qıza nə hədiyyə etmək olar?

Qızlar hədiyyədəki diqqəti hər şeydən çox qiymətləndirir. Şəkli və adı olan şokolad qutusu, içində polaroid məktub və naxışlı qablaşdırma, bunların hamısını bir sifarişdə seçə bilərsiniz.

## Dizaynlar

- **Love story və I love**, sevgiliyə romantik hədiyyə.
- **Milka və Barbie**, incə, çəhrayı və bənövşəyi tonlarda.
- **Love is…**, qısa, şirin sözlər üçün.
- **Frame & Player və Spotify**, "onun mahnısı" ilə.
MD,
        'faq' => [
            ['q' => 'Hədiyyəni qablaşdırırsınız?', 'a' => 'Bəli, istəsəniz qutunu hədiyyə kağızına büküb lentlə bağlayırıq.'],
            ['q' => 'Qutunun içinə məktub qoymaq olar?', 'a' => 'Bəli, şəkil və sözlərinizlə polaroid məktub əlavə edə bilərsiniz.'],
            ['q' => 'Neçə günə çatdırırsınız?', 'a' => 'Adətən 1–3 iş günü. Bakıda qapıya çatdırırıq, bölgələrə poçtla göndəririk.'],
        ],
        'products' => ['love-story-vol-1', 'love-story-vol-2', 'love-story-vol-3', 'i-love', 'milka', 'barbie-vol-1', 'love-is-red', 'love-is-orange', 'love-is-blue', 'frame-player', 'dark-spotify', 'alyonka-aze-sytle-vol-1'],
    ],
    [
        'slug' => 'kisiye',
        'menu_label' => 'Kişiyə',
        'emoji' => '🎩',
        'title' => 'Kişiyə hədiyyə, oğlana, həyat yoldaşına, dosta',
        'meta_title' => 'Kişiyə hədiyyə, oğlana, həyat yoldaşına şokolad | Nefis',
        'meta_description' => 'Oğlana, həyat yoldaşına, qardaşa və dosta orijinal hədiyyə: Netflix, Spotify, Google üslubunda, onun şəkli və sizin zarafatınızla şokolad qutusu.',
        'eyebrow' => 'Kişiyə hədiyyə',
        'intro' => 'Kişilərə hədiyyə seçmək çətindir. Onun şəkli ilə Netflix afişası, Spotify pleyeri və ya Google səhifəsi kimi görünən şokolad qutusu isə mütləq gülümsədəcək.',
        'body' => <<<'MD'
## Oğlana nə hədiyyə etmək olar?

Kişilərin çoxu "mənə heç nə lazım deyil" deyir. Ona görə hədiyyə faydalı olmaqdan çox yaddaqalan olmalıdır. Fərdi dizaynlı şokolad qutusunda onun şəkli və sizin zarafatınız olur, belə hədiyyəni hamıya göstərirlər.

## Dizaynlar

- **Netflix**, onun şəkli ilə film afişası.
- **Spotify və Frame & Player**, "sizin mahnınız" pleyerdə.
- **Google**, axtarış səhifəsi üslubunda zarafat.
- **Avtomobil**, maşın həvəskarları üçün.
- **"Qardaş demə, lazım olar"**, qardaşa və ən yaxın dosta.
MD,
        'faq' => [
            ['q' => 'Həyat yoldaşıma ad günü üçün hansı dizaynı seçim?', 'a' => 'Birgə şəkliniz üçün "Love story" və ya Spotify dizaynı, zarafat üçün isə Netflix və Google dizaynları uyğundur.'],
            ['q' => 'Kişilər üçün hansı şokoladı seçim?', 'a' => 'Qutunun içinə istədiyiniz plitkanı seçirsiniz, onun ən çox sevdiyi brendi götürün.'],
            ['q' => 'Hədiyyəni iş yerinə çatdırırsınız?', 'a' => 'Bəli, Bakıda istənilən ünvana çatdırırıq. Sifarişdə alıcının adını və telefonunu yazın.'],
        ],
        'products' => ['netflix', 'google', 'dark-spotify', 'frame-player', 'avtomobil', 'qardas-deme-lazim-olar-qarfield', 'alpen-gold', 'velizar', 'love-story-vol-1'],
    ],
    [
        'slug' => 'ildonumu',
        'menu_label' => 'İldönümü',
        'emoji' => '💍',
        'title' => 'İldönümü hədiyyəsi, evlilik və tanışlıq ildönümü',
        'meta_title' => 'İldönümü hədiyyəsi, evlilik və tanışlıq ildönümü | Nefis',
        'meta_description' => 'Evlilik və tanışlıq ildönümü üçün fərdi hədiyyə: toy və ya birgə şəkliniz, xüsusi tarixiniz və sevgi sözlərinizlə şokolad qutusu. Bakıda çatdırılma.',
        'eyebrow' => 'İldönümü',
        'intro' => 'Toy gününüz, tanış olduğunuz gün və ya ilk görüşünüz, sizə aid tarixi şəkliniz və sözlərinizlə şokolad qutusunda qeyd edin.',
        'body' => <<<'MD'
## İldönümünə nə hədiyyə etmək olar?

İldönümü birlikdə keçən illərin xatırlanmasıdır. Toy şəkliniz və ya ilk birgə şəkliniz olan qutu bu günə ən uyğun hədiyyələrdən biridir.

## Xatirəni tamamlayın

- **Tarix və sözlər**, tanış olduğunuz və ya evləndiyiniz günü qutuya yazın.
- **Polaroid məktub**, illər əvvəlki şəklinizlə.
- **Canlı şəkil**, toy videonuz qutudakı şəklin üstündə oynasın.
MD,
        'faq' => [
            ['q' => 'Qutuya köhnə şəkil qoymaq olar?', 'a' => 'Bəli. Köhnə şəkli telefonla aydın çəkib yükləyə bilərsiniz; önizləmədə necə görünəcəyini sifarişdən əvvəl görürsünüz.'],
            ['q' => 'Toy videosunu canlı şəkilə necə əlavə edim?', 'a' => '"Canlı şəkil" bölməsində videonu yükləyirsiniz. Qutudakı şəklə telefonu tutanda həmin video oynayır.'],
            ['q' => 'Hədiyyəni sürpriz kimi çatdıra bilərsinizmi?', 'a' => 'Bəli. Sifarişdə alıcının adını və ünvanını yazın, qutunu ona çatdırırıq.'],
        ],
        'products' => ['love-story-vol-1', 'love-story-vol-2', 'love-story-vol-3', 'love-is-red', 'love-is-orange', 'love-is-blue', 'i-love', 'family-frame', 'frame-player', 'dark-spotify'],
    ],
    [
        'slug' => 'gul-evezine',
        'menu_label' => 'Gül əvəzinə',
        'emoji' => '🌹',
        'title' => 'Gül əvəzinə nə hədiyyə etmək olar?',
        'meta_title' => 'Gül əvəzinə hədiyyə, solmayan şəkilli şokolad qutusu | Nefis',
        'meta_description' => 'Gül bir neçə günə solur. Gül əvəzinə və ya gül dəstəsinə əlavə kimi, şəkil və sözlərinizlə fərdi şokolad qutusu. Bakıda və bölgələrə çatdırılma.',
        'eyebrow' => 'Güllə yanaşı',
        'intro' => 'Gül gözəldir, amma bir neçə günə solur. Şəkilli şokolad qutusu isə şokolad yeyiləndən sonra da xatirə kimi qalır, gül əvəzinə və ya gül dəstəsi ilə birlikdə.',
        'body' => <<<'MD'
## Niyə gül əvəzinə şokolad qutusu?

- **Solmur**, qutu və üzərindəki şəkil illərlə saxlanılır.
- **Fərdidir**, hazır buketdən fərqli olaraq üzərində onun şəkli və sizin sözləriniz olur.
- **Şirindir**, içində onun sevdiyi şokolad var.

## Güllə birlikdə

Gül dəstəsi almaq qərarındasınızsa, şəkilli qutu ona gözəl əlavədir: buket gözü, qutu isə ürəyi sevindirir. Biz gül satmırıq, amma qutunu elə hazırlayırıq ki, istənilən buketin yanında yaraşsın.

## Hansı münasibətlərə?

Ad günü, 8 Mart, 14 Fevral, ildönümü, barışmaq və ya sadəcə "səni düşünürəm" demək üçün.
MD,
        'faq' => [
            ['q' => 'Siz gül də satırsınız?', 'a' => 'Xeyr, biz fərdi şokolad qutuları hazırlayırıq. Qutunu gül dəstəsinə əlavə kimi də hədiyyə edə bilərsiniz.'],
            ['q' => 'Qutu neçəyə başa gəlir?', 'a' => 'Qiymət dizayna və içinə seçdiyiniz şokolada görə dəyişir, dəqiq məbləği səbətdə görürsünüz.'],
            ['q' => 'Hədiyyəni qablaşdırırsınız?', 'a' => 'Bəli, istəsəniz qutunu hədiyyə kağızına büküb lentlə bağlayırıq.'],
        ],
        'products' => ['love-story-vol-1', 'love-story-vol-2', 'love-story-vol-3', 'i-love', 'love-is-red', 'love-is-orange', 'love-is-blue', 'family-frame', 'milka', 'frame-player'],
    ],
    [
        'slug' => 'dosta',
        'menu_label' => 'Dosta',
        'emoji' => '🤝',
        'title' => 'Dosta hədiyyə, zarafatlı fərdi şokolad',
        'meta_title' => 'Dosta hədiyyə, qardaşa, bacıya zarafatlı şokolad | Nefis',
        'meta_description' => 'Dosta, qardaşa və bacıya yaddaqalan hədiyyə: Netflix, Google, Spotify və Qarfield üslubunda, onun şəkli və sizin zarafatınızla şokolad qutusu.',
        'eyebrow' => 'Dosta hədiyyə',
        'intro' => 'Ən yaxın dostunuzu və ya qardaşınızı güldürmək istəyirsiniz? Onun şəkli ilə Netflix afişası və ya "Qardaş demə, lazım olar" qutusu, həm şirin, həm də zarafatlı hədiyyə.',
        'body' => <<<'MD'
## Dosta nə hədiyyə etmək olar?

Dostlar arasında ən yaxşı hədiyyə birlikdə güldüyünüz şeydir. Onun şəkli və aranızdakı zarafat yazılmış şokolad qutusu ad günü, bayram və ya səbəbsiz sürpriz üçün uyğundur.

## Dizaynlar

- **Netflix**, dostunuz öz filminin baş qəhrəmanı.
- **Google**, axtarış səhifəsi üslubunda zarafat.
- **Spotify**, "sizin mahnınız".
- **"Qardaş demə, lazım olar"**, qardaşa və qardaş qədər yaxın dosta.
MD,
        'faq' => [
            ['q' => 'Qutuya öz zarafatımızı yaza bilərəmmi?', 'a' => 'Bəli, dizayndakı yazı sahələrinə istədiyiniz sözləri yazırsınız və nəticəni dərhal görürsünüz.'],
            ['q' => 'Dostuma başqa şəhərə göndərə bilərsinizmi?', 'a' => 'Bəli, bölgələrə poçtla göndəririk.'],
            ['q' => 'Bir neçə dost üçün eyni dizaynı sifariş edə bilərəmmi?', 'a' => 'Bəli, hər birini ayrıca fərdiləşdirib səbətə əlavə edin.'],
        ],
        'products' => ['netflix', 'google', 'dark-spotify', 'qardas-deme-lazim-olar-qarfield', 'chocolate-puppin', 'velizar', 'avtomobil', 'alyonka-aze-sytle-vol-2', 'frame-player'],
    ],
    [
        'slug' => 'yeni-il',
        'menu_label' => 'Yeni il',
        'emoji' => '🎄',
        'title' => 'Yeni il hədiyyəsi, şəkilli şokolad qutusu',
        'meta_title' => 'Yeni il hədiyyəsi, fərdi şokolad qutusu | Nefis',
        'meta_description' => 'Yeni il üçün fərdi hədiyyə: ailəyə, dostlara və iş yoldaşlarına şəkil və təbriklə şokolad qutusu. Onlayn sifariş, Bakıda və bölgələrə çatdırılma.',
        'eyebrow' => 'Yeni il',
        'intro' => 'Yeni ildə sevdiklərinizə hazır hədiyyə dəsti əvəzinə onların şəkli və sizin təbrikinizlə hazırlanan şokolad qutusu verin.',
        'body' => <<<'MD'
## Yeni ilə nə hədiyyə etmək olar?

Yeni il hədiyyəsi çox vaxt tələsik alınır və bir-birinə bənzəyir. Şəkilli şokolad qutusu isə hər kəs üçün ayrıca hazırlanır: üzərində onun şəkli və adı, içində sevdiyi şokolad.

## Kimə?

- **Ailəyə**, "Family Frame" dizaynında ailə şəkli ilə.
- **Uşaqlara**, Kinder və Alyonka üslubunda.
- **Dostlara və iş yoldaşlarına**, hər birinin adı ilə eyni dizaynda.

## Erkən sifariş edin

Dekabrın sonunda sifarişlər çox olur. Hədiyyələrin bayramdan əvvəl çatması üçün sifarişi dekabrın ortasına qədər verin.
MD,
        'faq' => [
            ['q' => 'Yeni il hədiyyəsini nə vaxt sifariş etməliyəm?', 'a' => 'Dekabrın ortasına qədər. Bayram ərəfəsində sifarişlər çoxalır.'],
            ['q' => 'Çox sayda qutu sifariş edə bilərəmmi?', 'a' => 'Bəli. Çox sayda sifariş üçün Instagramda bizə yazın, müddəti birlikdə dəqiqləşdirək.'],
            ['q' => 'Qutunu hədiyyə kağızına bükürsünüz?', 'a' => 'Bəli, istəsəniz qutunu hədiyyə kağızına büküb lentlə bağlayırıq.'],
        ],
        'products' => ['family-frame', 'kinder-vol-1', 'kinder-vol-2', 'milka', 'alpen-gold', 'alyonka-aze-sytle-vol-1', 'alyonka-aze-sytle-vol-2', 'netflix', 'dark-spotify', 'frame-player'],
    ],
];
