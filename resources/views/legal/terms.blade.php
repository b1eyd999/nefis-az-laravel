@extends('layouts.app')

@section('title', __('İstifadə şərtləri') . ' | Nefis')
@section('meta_description', __('Nefis.az-da sifariş necə verilir, qiymət nədən ibarətdir, qutu nə vaxt hazır olur və çatdırılma necə işləyir.'))

@section('content')
<section class="page-hero">
  <div class="wrap-narrow">
    <span class="eyebrow">{{ __('Şərtlər') }}</span>
    <h1>{{ __('İstifadə şərtləri') }}</h1>
    <p class="lede">{{ __('Bu səhifə sifarişin necə verildiyini, qiymətin nədən ibarət olduğunu və qutunun nə vaxt hazır olduğunu izah edir. Sifarişi göndərməklə bu şərtləri qəbul etmiş olursunuz.') }}</p>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap-narrow legal">
    @include('legal.who')

    <h2>{{ __('Nə satırıq') }}</h2>
    <p>{{ __('Hər qutu sifariş üzrə hazırlanır: sizin şəkliniz, sizin sözlərinizlə. Qutunun içinə seçdiyiniz şokolad qoyulur. İstəyinizə görə hədiyyə qablaşdırması, polaroid məktub və canlı şəkil (telefonla açılan video) da əlavə olunur.') }}</p>
    <p>{{ __('Bəzi dizaynlarda şəklin yerinə gecə səmasını seçmək olur: seçdiyiniz tarixdə, seçdiyiniz yerin üstündəki səma çap olunur. Polaroid məktub və canlı şəkil ayrıca da sifariş edilə bilər.') }}</p>
    <p>{{ __('Şirkətlər üçün loqolu qutular ayrı qaydada, sorğu ilə hazırlanır.') }}</p>
    <p>{{ __('«Lokasiya» dizaynlarında seçdiyiniz yerin küçələri çap olunur. Xəritə məlumatları OpenStreetMap-dəndir və ODbL lisenziyası ilə paylaşılır; bu qeyd həm saytda, həm də qutunun üzərində göstərilir.') }}</p>

    <h2>{{ __('Sifariş necə verilir') }}</h2>
    <p>{{ __('Sifariş vermək üçün hesab lazımdır: ad, e-poçt, Azərbaycan nömrəsi və şifrə. Bir nömrə ilə bir hesab açılır.') }}</p>
    <ul>
      <li>{{ __('Dizaynı seçirsiniz, şəklinizi yükləyir və yazıları yazırsınız. Hər yazı sahəsinin öz simvol həddi var və nə yazılıbsa, o da çap olunur.') }}</li>
      <li>{{ __('Saytda aktiv şokolad varsa, qutunun içinə şokolad seçmək məcburidir.') }}</li>
      <li>{{ __('Bir dizaynı 1-dən 20 ədədə qədər sifariş etmək olar.') }}</li>
      <li>{{ __('Son səhifədə çatdırılma üsulunu, ünvanı, günü, vaxt aralığını və telefonu yazırsınız.') }}</li>
    </ul>
    <p>{{ __('Yekun məbləği sifarişi göndərməzdən əvvəl görürsünüz. Sifariş göndəriləndən sonra ödəniş səhifəsinə keçirsiniz.') }}</p>

    <h2>{{ __('Qiymət nədən ibarətdir') }}</h2>
    <ul>
      <li>{{ __('Dizaynın öz qiyməti.') }}</li>
      <li>{{ __('Seçdiyiniz şokolad — qiyməti seçim zamanı göstərilir.') }}</li>
      <li>{{ __('Hədiyyə qablaşdırması, polaroid məktub və canlı şəkil — yalnız seçsəniz.') }}</li>
    </ul>
    <p>{{ __('«Təcili hazırlansın» haqqı bütün sifariş üçün bir dəfə alınır, hər qutuya görə deyil. Çatdırılma ayrıca hesablanır. Bütün qiymətlər manatladır.') }}</p>

    @php $ways = \App\Models\DeliveryMethod::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(); @endphp
    <h2>{{ __('Çatdırılma') }}</h2>
    @if($ways->isNotEmpty())
      <ul>
        @foreach($ways as $way)
          <li><b>{{ $way->tr('name') }}</b> — {{ $way->price > 0 ? \App\Support\Price::format($way->price) : __('pulsuz') }}@if($way->tr('description')). {{ $way->tr('description') }}@endif</li>
        @endforeach
      </ul>
    @endif
    <p>{{ __('Qapıya çatdırılma yalnız Bakı daxilindədir və yeri xəritədə özünüz seçirsiniz. Sifariş verilən andakı üsul və qiymət sifarişin içində saxlanılır: sonra qiyməti dəyişsək, sizin sifarişinizə təsir etmir.') }}</p>

    <h2>{{ __('Nə vaxt hazır olur') }}</h2>
    <p>{{ __('Hər qutu əl ilə hazırlanır, ona görə həmin gün təhvil verilmir. Hazırlanma müddəti :days gündür; ən erkən mümkün tarix son səhifədə yazılır və təqvimdə ondan əvvəlki gün seçilmir.', ['days' => \App\Support\DeliveryTime::leadDays()]) }}</p>
    <p>{{ __('Günü və vaxt aralığını özünüz seçirsiniz. «Təcili hazırlansın» sifarişi növbədənkənar hazırlayır; daha erkən gün lazımdırsa, sifarişdən sonra bizə yazın.') }}</p>

    <h2>{{ __('Ödəniş') }}</h2>
    <p>{{ __('Hansı ödəniş üsullarının açıq olduğunu sifarişin ödəniş səhifəsində görürsünüz. Kartla ödəniş epoint.az-ın qorunan səhifəsində aparılır — kart məlumatları bizdə saxlanmır. Köçürmə ilə ödəyəndə qəbzi həmin səhifəyə yükləyirsiniz və biz onu yoxlayırıq.') }}</p>
    <p>{{ __('Ödənişi başlamış sifariş bir müddət «yoxlanılır» kimi qalır — bu vaxt ikinci dəfə ödəməyin. Ödənişi olmayan və qəbzi göndərilməyən sifariş bir gündən sonra ləğv edilə bilər; sifarişi yenidən vermək olar.') }}</p>

    <h2>{{ __('Sifarişin gedişi') }}</h2>
    <ul>
      @foreach(\App\Models\Order::STATUSES as $label)
        <li>{{ __($label) }}@if(isset(\App\Support\CustomerNotice::LINES[array_search($label, \App\Models\Order::STATUSES, true)])) — {{ __(\App\Support\CustomerNotice::LINES[array_search($label, \App\Models\Order::STATUSES, true)]) }}@endif</li>
      @endforeach
    </ul>
    <p>{{ __('Statusu hər zaman «Sifarişlərim» səhifəsində görürsünüz. Status dəyişəndə hesabınızdakı e-poçta məktub göndərilir; məktub gəlməyibsə, spam qutusunu yoxlayın və ya elə həmin səhifəyə baxın.') }}</p>

    <h2>{{ __('Dəyişiklik və ləğv') }}</h2>
    <p>{{ __('Saytda sifarişi özünüz dəyişmək və ya ləğv etmək düyməsi yoxdur. Nəyisə dəyişmək lazımdırsa — şəkil, yazı, gün, ünvan — bizə mümkün qədər tez yazın. İş başlamayıbsa, birlikdə həll edirik: ya dəyişirik, ya sifarişi ləğv edib yenisini veririk.') }}</p>
    <p>{{ __('Hazır fərdi qutu geri qaytarılmır — o yalnız sizin şəkliniz və sözlərinizlə mövcuddur. Pulun qaytarıldığı hallar «Ödəniş və qaytarma» səhifəsində yazılıb.') }}</p>

    <h2>{{ __('Müəllif hüququ') }}</h2>
    <p>{{ __('Saytdakı bütün dizaynlar, onların şəkilləri, mətnlər və saytın özü Nefis Şokolad Evinə məxsusdur və müəllif hüququ ilə qorunur. Dizaynlar bizim öz işimizdir.') }}</p>
    <p>{{ __('Qutunu alırsınız — dizaynın hüququnu yox. Dizaynlarımızı surət çıxarmaq, satmaq, başqa məhsulda, çapda və ya reklamda istifadə etmək, habelə onların əsasında oxşar məhsul buraxmaq icazəsiz olmaz.') }}</p>
    <p>{{ __('Bu, xüsusilə başqa mağazalara və onlayn satıcılara aiddir: dizaynlarımızı götürüb öz məhsulu kimi satmaq, kataloqunda yerləşdirmək və ya üzərində kiçik dəyişiklik edib çıxarmaq olmaz. Belə halda hüquqlarımızı qanun yolu ilə müdafiə edirik.') }}</p>
    <p>{{ __('Aldığınız qutunun şəklini sosial şəbəkədə paylaşmaq, hədiyyə etmək və göstərmək tamamilə sərbəstdir — buna sevinirik. Söhbət dizaynın özünün ticarətdə istifadəsindən gedir.') }}</p>
    <p>{{ __('Yüklədiyiniz şəkil isə sizindir: onu yalnız sizin sifarişinizi hazırlamaq üçün işlədirik və icazəniz olmadan reklamda göstərmirik.') }}</p>

    <h2>{{ __('Sizin öhdəliyiniz') }}</h2>
    <p>{{ __('Yüklədiyiniz şəkli istifadə etmək hüququ sizdə olmalıdır: başqasının şəkli, loqosu və ya müəllif işi üçün icazəniz olduğunu təsdiq edirsiniz. Yazıları yoxlayın — nə yazılıbsa, o çap olunur.') }}</p>

    <h2>{{ __('Əlaqə') }}</h2>
    @include('legal.contact')
    <p class="legal-note">{{ __('Şərtlər dəyişə bilər; saytda həmişə son variant göstərilir.') }}</p>
  </div>
</section>
@endsection
