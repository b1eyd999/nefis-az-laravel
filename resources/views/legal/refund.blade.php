@extends('layouts.app')

@section('title', __('Ödəniş və qaytarma şərtləri') . ' | Nefis')
@section('meta_description', __('Nefis.az-da ödəniş necə aparılır, sifariş nə vaxt təsdiqlənir və hansı hallarda vəsait geri qaytarılır.'))

@section('content')
<section class="page-hero">
  <div class="wrap-narrow">
    <span class="eyebrow">{{ __('Ödəniş') }}</span>
    <h1>{{ __('Ödəniş və qaytarma şərtləri') }}</h1>
    <p class="lede">{{ __('Necə ödənilir, sifariş nə vaxt təsdiqlənir və hansı hallarda pul geri qayıdır.') }}</p>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap-narrow legal">
    @include('legal.who')

    <h2>{{ __('Necə ödənilir') }}</h2>
    <p>{{ __('Sifarişin ödəniş səhifəsində o an açıq olan üsullar göstərilir:') }}</p>
    <ul>
      <li>{{ __('Kartla onlayn — epoint.az-ın qorunan səhifəsində. Orada Visa və Mastercard, həmçinin telefonunuzdakı Google Pay və ya Apple Pay təklif olunur. Kart məlumatları bizə gəlmir.') }}</li>
      <li>{{ __('Köçürmə — kartdan-karta, M10 və ya bank hesabı. Məbləği köçürüb qəbzi həmin səhifəyə yükləyirsiniz, biz onu yoxlayırıq.') }}</li>
    </ul>
    <p>{{ __('Ödəniş üsullarının heç biri açıq olmayanda sifarişiniz qeydə alınır və biz sizinlə özümüz əlaqə saxlayırıq.') }}</p>

    <h2>{{ __('Sifariş nə vaxt təsdiqlənir') }}</h2>
    <p>{{ __('Kartla ödəyəndə təsdiq bankdan gəlir: bank cavab verən kimi sifarişin statusu «Təsdiqləndi» olur və qutu hazırlanmağa düşür. Bu bir neçə saniyə çəkir, bəzən bir az uzanır.') }}</p>
    <p>{{ __('Ödəniş başlayandan sonra səhifədə «yoxlanılır» yazısı görünür və kart düyməsi gizlədilir — ikinci dəfə ödəməyin. Bu gözləmə yarım saatdan sonra özü qurtarır və düymə yenidən açılır.') }}</p>
    <p>{{ __('Bankdan çıxan məbləğ sifarişin məbləği ilə üst-üstə düşməsə, sifariş avtomatik təsdiqlənmir: belə halı biz əl ilə yoxlayırıq və sizinlə əlaqə saxlayırıq.') }}</p>
    <p>{{ __('Köçürmə ilə ödəyəndə qəbzi yükləyəndən sonra status «Çek yoxlanılır» olur; yoxlayıb təsdiqləyirik.') }}</p>

    <h2>{{ __('Ödənilməmiş sifariş') }}</h2>
    <p>{{ __('Ödənişi olmayan və qəbzi göndərilməyən sifariş bir gündən sonra ləğv edilə bilər — pul çıxmadığı üçün qaytarılası bir şey olmur. Qəbz yüklənibsə, sifariş gözləyir: onu insan yoxlayır.') }}</p>

    <h2>{{ __('Nə vaxt qaytarmırıq') }}</h2>
    <p>{{ __('Hər qutu bir müştəri üçün hazırlanır: sizin şəkliniz və sizin sözlərinizlə çap olunur, başqasına satmaq mümkün deyil. Ona görə hazır fərdi qutu sadəcə fikir dəyişdiyi üçün geri qaytarılmır.') }}</p>

    <h2>{{ __('Nə vaxt qaytarırıq') }}</h2>
    <ul>
      <li>{{ __('İş hələ başlamayıbsa — yəni qutu hazırlanmağa düşməyibsə, sifarişi ləğv edib pulu qaytarırıq.') }}</li>
      <li>{{ __('Səhv bizdədirsə — başqa dizayn, başqa şəkil, pozulmuş çap, zədəli qutu.') }}</li>
      <li>{{ __('Sifarişi biz hazırlaya bilmiriksə.') }}</li>
    </ul>
    <p>{{ __('Səhv bizdə olanda çatdırılmanın haqqını da qaytarırıq. Zədəli qutunu qəbul edəndə onu açmadan şəkil çəkin — bu, məsələni tez həll edir.') }}</p>

    <h2>{{ __('Necə xəbər verirsiniz') }}</h2>
    <p>{{ __('Bizə yazın və sifarişin nömrəsini, nəyin səhv olduğunu və mümkünsə şəkli göndərin. Qutu əlinizə çatan kimi yoxlayın: problemi nə qədər tez bilsək, bir o qədər tez düzəldirik.') }}</p>

    <h2>{{ __('Pul necə qayıdır') }}</h2>
    <p>{{ __('Kartla ödəmisinizsə, pul ödədiyiniz karta qayıdır — bankdan asılı olaraq 1–7 iş günü çəkir. Köçürmə ilə ödəmisinizsə, pulu hansı karta və ya hesaba qaytaracağımızı sizdən soruşuruq: bizdə sizin hesab nömrəniz saxlanmır.') }}</p>
    <p>{{ __('Qaytarış tamamlananda sifarişin statusu «Vəsait qaytarıldı» olur və hesabınızdakı e-poçta məktub gedir.') }}</p>

    <h2>{{ __('Əlaqə') }}</h2>
    @include('legal.contact')
    <p class="legal-note">{{ __('Şərtlər dəyişə bilər; saytda həmişə son variant göstərilir.') }}</p>
  </div>
</section>
@endsection
