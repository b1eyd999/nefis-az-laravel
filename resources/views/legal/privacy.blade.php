@extends('layouts.app')

@section('title', __('Məxfilik siyasəti') . ' | Nefis')
@section('meta_description', __('Nefis.az sifariş üçün hansı məlumatı toplayır, harada saxlayır, kim görür və necə silinir.'))

@section('content')
<section class="page-hero">
  <div class="wrap-narrow">
    <span class="eyebrow">{{ __('Məxfilik') }}</span>
    <h1>{{ __('Məxfilik siyasəti') }}</h1>
    <p class="lede">{{ __('Sifariş üçün nə toplayırıq, harada saxlayırıq, kim görür və necə silinir — hamısı olduğu kimi.') }}</p>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap-narrow legal">
    @include('legal.who')

    <h2>{{ __('Nə toplayırıq') }}</h2>
    <ul>
      <li>{{ __('Hesab üçün: ad, e-poçt, telefon və şifrənin şifrələnmiş izi. Şifrənin özü heç yerdə saxlanmır.') }}</li>
      <li>{{ __('Sifariş üçün: çatdırılma ünvanı və ya poçt indeksi, metro stansiyası, xəritədə seçdiyiniz nöqtə, telefon, çatdırılma günü və vaxtı, qeydiniz.') }}</li>
      <li>{{ __('Qutu üçün: yüklədiyiniz şəkillər və yazdığınız sözlər. Ulduz xəritəsi seçmisinizsə — tarix, saat, koordinat, yerin adı və seçdiyiniz görünüş açarları.') }}</li>
      <li>{{ __('Polaroid məktub üçün: şəkil və mətn. Canlı şəkil üçün: video.') }}</li>
      <li>{{ __('Köçürmə ilə ödəyəndə: yüklədiyiniz qəbz. Qəbzin içində adınız və kartınızın son rəqəmləri görünə bilər.') }}</li>
      <li>{{ __('Söhbətə yazsanız: mesajınız, adınız, telefonunuz və göndərdiyiniz şəkil.') }}</li>
    </ul>
    <p>{{ __('Şəkil serverə olduğu kimi gedir: telefonun şəklin içinə yazdığı məlumat (çəkiliş tarixi, bəzən yer) da faylın içində qalır.') }}</p>

    <h2>{{ __('Texniki məlumat') }}</h2>
    <p>{{ __('Saytın işləməsi üçün sessiya saxlanılır: bazada onun yanında IP ünvanınız və brauzerinizin adı da yazılır. Sessiyanın içində səbətiniz olur.') }}</p>
    <p>{{ __('Brauzerinizdə saxlananlar: sessiya kukisi, forma qorunması üçün kuki, seçdiyiniz dil və işıqlı/qaranlıq rejim. Sayğac qoşulubsa, Google Analytics öz kukilərini də qoyur.') }}</p>
    <p>{{ __('Girişdə, şifrə bərpasında və söhbətdə cəhdlər qısa müddətə sayılır — bu, saytı avtomatik hücumlardan qoruyur.') }}</p>

    <h2>{{ __('Kim görür') }}</h2>
    <ul>
      <li>{{ __('Mağazanın işçiləri — sifarişi hazırlamaq və çatdırmaq üçün.') }}</li>
      <li>{{ __('epoint.az — kartla ödəniş onların səhifəsində aparılır. Kartın nömrəsi bizə heç vaxt gəlmir; bizdə yalnız ödənişin nömrəsi, məbləği və vaxtı qalır.') }}</li>
      <li>{{ __('Kuryer və ya poçt — çatdırılma üçün ad, telefon və ünvan.') }}</li>
      <li>{{ __('Telegram — sifarişlər, söhbət və kuryer bildirişləri mağazanın botlarına düşür. Bunlar ayrı-ayrı botlardır.') }}</li>
      <li>{{ __('Yandex Disk — canlı şəklin videosu sahibin diskinə köçürülür.') }}</li>
      <li>{{ __('Google — xəritə açarı qoşulubsa xəritə və ünvan axtarışı Google-dan gedir; sayğac qoşulubsa ziyarət məlumatı Analytics-ə gedir; dizayn səhifəsində üzü kəsmək üçün model faylları Google və jsDelivr serverlərindən yüklənir.') }}</li>
      <li>{{ __('WhatsApp — sizinlə oradan yazışsaq, mesaj Meta-nın xidmətindən keçir.') }}</li>
    </ul>
    <p>{{ __('Məlumatınızı satmırıq və reklam üçün üçüncü şəxslərə vermirik.') }}</p>

    <h2>{{ __('Nə qədər saxlanılır') }}</h2>
    <p>{{ __('Sifariş və onun şəkilləri sifariş tarixçəsi və mühasibat üçün saxlanılır; avtomatik silən bir şey yoxdur. Canlı şəklin videosu mümkün olan kimi Yandex Diskə köçürülür və saytdan silinir; köçürmə alınmasa, video yenidən cəhd edilənə qədər saytda qalır.') }}</p>
    <p>{{ __('Silinməsini istəsəniz, bizə yazın — əl ilə silirik. Sifarişin özü ilə bağlı bəzi rəqəmlər mühasibat üçün qala bilər.') }}</p>

    <h2>{{ __('Canlı şəkil barədə ayrıca') }}</h2>
    <p>{{ __('Canlı şəklin linki qısa koddur və giriş tələb etmir: kodu bilən hər kəs videonu aça bilər. Qutunu əlinə alan adam onu görəcək — məhz bunun üçün düzəldilib. Video başqa yerdə paylaşılsa, biz ona nəzarət edə bilmirik.') }}</p>

    <h2>{{ __('Uşaqlar') }}</h2>
    <p>{{ __('Sayt uşaqlar üçün deyil. Uşaq şəkli yükləyirsinizsə, bunu valideyn və ya qanuni nümayəndə kimi edirsiniz.') }}</p>

    <h2>{{ __('Hüquqlarınız') }}</h2>
    <p>{{ __('Haqqınızda nə saxladığımızı soruşa, düzəltməyimizi və ya silməyimizi istəyə bilərsiniz. Bunun üçün aşağıdakı əlaqə yolları ilə yazın; hesabınızı özünüz silən düymə saytda yoxdur.') }}</p>

    <h2>{{ __('Əlaqə') }}</h2>
    @include('legal.contact')
    <p class="legal-note">{{ __('Siyasət dəyişə bilər; saytda həmişə son variant göstərilir.') }}</p>
  </div>
</section>
@endsection
