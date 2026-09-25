<!DOCTYPE html>
<html lang="az">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ __('Fayl çox böyükdür') }}, Nefis</title>
<style>
  body{ margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:1.5rem; box-sizing:border-box;
    background:#FBF6EF; color:#2A1D15; font-family:-apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", Inter, "Segoe UI", Roboto, sans-serif; text-align:center; }
  @media (prefers-color-scheme: dark){ body{ background:#17110D; color:#F3E6D6; } }
  .box{ max-width:26rem; }
  h1{ font-family:-apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", Inter, "Segoe UI", Roboto, sans-serif; font-size:1.6rem; margin:.6rem 0; }
  p{ line-height:1.6; opacity:.85; }
  a{ display:inline-block; margin-top:1rem; padding:.8rem 1.6rem; border-radius:999px; background:#D6A35A; color:#17110D; font-weight:600; text-decoration:none; }
</style>
</head>
<body>
  <div class="box">
    <img src="/images/logo.svg" alt="Nefis" style="height:3rem;">
    <h1>{{ __('Fayllar çox böyükdür') }}</h1>
    <p>{{ __('Bir göndərişdə 30 MB-a qədər fayl yükləmək olar. Videonu qısaldın və ya sıxın, sonra yenidən cəhd edin.') }}</p>
    <a href="javascript:history.back()">← {{ __('Geri qayıt') }}</a>
  </div>
</body>
</html>
