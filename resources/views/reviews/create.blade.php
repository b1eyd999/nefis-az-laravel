@extends('layouts.app')

@section('title', __('Rəy yazın') . ' | Nefis')
@section('robots', 'noindex, nofollow')

@section('content')
<section class="page-hero">
  <div class="wrap-narrow">
    <span class="eyebrow">{{ __('Rəylər') }}</span>
    <h1>{{ __('Sifariş #:id barədə rəyiniz', ['id' => $order->id]) }}</h1>
    <p class="lede">{{ __('Qutu necə çıxdı, şəkil necə göründü, nə vaxt çatdı — bir-iki cümlə bizə çox kömək edir.') }}</p>
  </div>
</section>

<section style="padding-top:0;">
  <div class="wrap-narrow">
    <div class="auth-card">
      <form method="POST" action="{{ lroute('orders.review.store', $order) }}" enctype="multipart/form-data">
        @csrf

        <div class="field">
          <label>{{ __('Neçə ulduz verərdiniz?') }}</label>
          {{-- Radio buttons, not a script: five labels a finger can hit, and
               the choice survives a page that came back with an error. --}}
          <div class="rv-pick">
            @for($s = \App\Models\Review::MOST; $s >= 1; $s--)
              <label>
                <input type="radio" name="stars" value="{{ $s }}" required
                       @checked((int) old('stars') === $s)>
                <span>{{ str_repeat('★', $s) }}</span>
              </label>
            @endfor
          </div>
          @error('stars')<p class="guest-bad">{{ $message }}</p>@enderror
        </div>

        <div class="field">
          <label for="rv-body">{{ __('Yazmaq istədikləriniz') }}</label>
          <textarea id="rv-body" name="body" rows="5" maxlength="2000"
                    placeholder="{{ __('Məs. şəkil çox aydın çıxdı, qutu da möhkəm gəldi.') }}">{{ old('body') }}</textarea>
          @error('body')<p class="guest-bad">{{ $message }}</p>@enderror
        </div>

        <div class="field">
          <label for="rv-photo">{{ __('Qutunun şəkli (istəyə bağlı)') }}</label>
          <input type="file" id="rv-photo" name="photo" accept="image/*">
          <p class="guest-note">{{ __('Öz çəkdiyiniz şəkil bizim üçün hər reklamdan dəyərlidir.') }}</p>
          @error('photo')<p class="guest-bad">{{ $message }}</p>@enderror
        </div>

        <div class="field">
          <label for="rv-name">{{ __('Saytda hansı adla görünsün?') }}</label>
          <input type="text" id="rv-name" name="shown_name" maxlength="60"
                 value="{{ old('shown_name') }}"
                 placeholder="{{ \Illuminate\Support\Str::of(auth()->user()->name)->trim()->explode(' ')->first() }}">
          <p class="guest-note">{{ __('Boş qoysanız, yalnız adınız görünür — soyadınız yox.') }}</p>
        </div>

        <p class="guest-note" style="margin-bottom:1rem;">{{ __('Rəyi oxuyub saytda yerləşdiririk. Problem olsa, əvvəlcə sizinlə əlaqə saxlayırıq.') }}</p>

        <button type="submit" class="btn btn-primary btn-block">{{ __('Rəyi göndər') }}</button>
      </form>
    </div>
  </div>
</section>
@endsection
