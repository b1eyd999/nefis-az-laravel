/*
  Nefis: xonça üçün mini şokolad qutuları.
  Biz xonça satmırıq. Kataloqda yalnız xonçaya qoyulan şokolad qutuları var,
  xonça şəkilləri (EXAMPLES) yalnız nümunədir, qiyməti və səhifəsi yoxdur.
  Kataloq (index.html) və sifariş səhifəsi (xonca.html) bu faylı oxuyur.
  Qiymət standartdır: 1 qutu 1.50 ₼, ən az 20 qutu (30 ₼).
  Dizaynlar Pinterest araşdırmasına əsaslanır, mənbələr: dizayn-arasdirma.md
*/
window.XONCA = (function () {
  // Şokolad qutusu kateqoriyaları
  var CATS = {
    klassik:  "Klassik",
    monoqram: "Monoqram",
    cicekli:  "Çiçəkli",
    fiyonklu: "Fiyonklu",
    tund:     "Tünd rəngli",
    sekilli:  "Şəkilli"
  };

  // Qutunun üstündəki başlıq. two: iki ad yazılır
  var OCCASIONS = [
    { id: "nisan",  name: "Nişan",     label: "NİŞAN",     two: true },
    { id: "soz",    name: "Söz kəsmə", label: "SÖZ KƏSMƏ", two: true },
    { id: "toy",    name: "Toy",       label: "TOY",       two: true },
    { id: "xina",   name: "Xına",      label: "XINA",      two: true },
    { id: "adgunu", name: "Ad günü",   label: "AD GÜNÜ",   two: false }
  ];

  // Qutu rəngləri (c: rəng nümunəsi, fill: qutunun rəngi, back: arxadakı qutular, dark: yazı qızılı olur)
  var COLORS = {
    ag:      { name: "Ağ",          c: "#FBFAF7", fill: "#FFFDF9", back: "#EDE6DA" },
    krem:    { name: "Krem",        c: "#EADCC4", fill: "#F2E8D6", back: "#E2D3BA" },
    pudra:   { name: "Pudra",       c: "#EBCFC8", fill: "#F5E3DE", back: "#E6CBC3" },
    lavanda: { name: "Lavanda",     c: "#DCD3EA", fill: "#EEE9F5", back: "#DDD4EA" },
    bordo:   { name: "Bordo",       c: "#6E1F2B", fill: "#6E1F2B", back: "#55171F", dark: true },
    gece:    { name: "Gecə mavisi", c: "#1F2A44", fill: "#1F2A44", back: "#172036", dark: true }
  };

  var MIN = 20;                          // ən az sifariş: 20 qutu (30 ₼), bütün dizaynlarda standart
  var MAX = 300;
  var PRESETS = [20, 30, 40, 50, 60];    // tez seçim
  var LEAD = 3;                          // hazırlanma, gün (nefis.az: 1-3 iş günü)

  // Xonçaya təxminən neçə qutu lazımdır (nümunə şəkillərə görə)
  var GUIDE = [
    { icon: "💐", name: "Buket",            n: "7-15 qutu" },
    { icon: "🎁", name: "Şüşə qutu",        n: "24-56 qutu" },
    { icon: "🏛️", name: "Stend və vitrin",  n: "24-54 qutu" },
    { icon: "✨", name: "Böyük dəst",       n: "60-80 qutu" }
  ];

  // Nümunə xonça şəkilləri: satılmır, qiyməti yoxdur
  var EXAMPLES = [
    { img: "img/qutu-monoqram.jpg",      alt: "Nümunə xonça: qızılı çərçivəli şüşə qutuda sıra-sıra ağ şokolad qutuları" },
    { img: "img/buket-pastel.jpg",       alt: "Nümunə xonça: şaftalı güllərdən buket, güllərin arasında ağ şokolad qutuları" },
    { img: "img/qutu-qizili-draje.jpg",  alt: "Nümunə xonça: qızılı şüşə qutuda ağ şokolad qutuları və qızılı draje" },
    { img: "img/vitrin-qizilgul.jpg",    alt: "Nümunə xonça: qızılı ayaqlı vitrində üç sıra krem şokolad qutusu" },
    { img: "img/buket-hortenziya.jpg",   alt: "Nümunə xonça: çəhrayı hortenziya buketində ağ şokolad qutuları" },
    { img: "img/stend-barokko.jpg",      alt: "Nümunə xonça: qızılı stend üstündə akril qapaqlı qutuda şokoladlar" },
    { img: "img/qutu-lale.jpg",          alt: "Nümunə xonça: üstündə ağ lalələr olan şüşə qutuda krem şokolad qutuları" },
    { img: "img/buket-cobanyastigi.jpg", alt: "Nümunə xonça: güllər və çobanyastığı buketində ağ şokolad qutuları" },
    { img: "img/buket-cehrayi-bulud.jpg",alt: "Nümunə xonça: çəhrayı kağızlı buketdə ağ şokolad qutuları" },
    { img: "img/dest-gelin.jpg",         alt: "Nümunə xonça dəsti: güzgü sinidə şokolad qutuları, stend və ağ gül buketi" }
  ];

  /*
    Mini qutu dizaynları.
    look.center: names (adlar alt-alta), mono (baş hərflər), olive (zeytun budağı),
                 wreath (çələng), heart (ürək), photo (şəkil)
    look.frame:  line (nazik xətt) və ya gold (qızılı ikiqat çərçivə)
    look.deco:   flowers, lavender, eucalyptus (çiçək bəzəyi)
    look.bow:    navy və ya cream (yuxarıda fiyonk)
    look.goldText: yazı qızılı rəngdə
    examples: bu qutuya oxşar qutuların göründüyü nümunə xonça şəkilləri
    photos:   qutunun real fotosu (kataloq kartı və sifariş səhifəsinin ilk şəkli).
              SVG önizləmə yalnız redaktə üçündür: ad yazanda, rəng seçəndə görünür.

    img/qutular/*.jpg: mockup fotolar, Azərbaycan adları ilə (mockup/qutu-foto.html,
    yenidən çəkmək üçün: sh mockup/render.sh). Nefisin öz qutu fotoları olanda
    eyni adla bu qovluğa qoyulur. Üslub mənbələri: dizayn-arasdirma.md.
  */

  var ITEMS = [
    {
      id: "kalliqrafiya", cat: "klassik", name: "Kalliqrafiya", badge: "HOT", price: 1.5,
      look: { center: "names", frame: "line" }, colors: ["ag", "krem", "pudra"],
      short: "Adlar kalliqrafiya ilə, altında tarix.",
      desc: "Ən çox seçilən xonça şokoladı. Ağ qutunun üstündə mərasimin adı, kalliqrafiya ilə adlarınız və tarix. İstənilən xonçaya, buketə və ya şüşə qutuya yaraşır.",
      photos: ["img/qutular/kalliqrafiya.jpg"],
      examples: ["img/buket-pastel.jpg", "img/dest-gelin.jpg"]
    },
    {
      id: "qizili-cerceve", cat: "klassik", name: "Qızılı çərçivə", price: 1.5,
      look: { center: "names", frame: "gold", goldText: true }, colors: ["ag", "krem"],
      short: "Qızılı kənar, yazı qızılı rəngdə.",
      desc: "Ağ qutunun kənarında qızılı ikiqat çərçivə, adlar və tarix qızılı rəngdə. Qızılı şüşə qutu və stendlərlə dəst kimi görünür.",
      photos: ["img/qutular/qizili-cerceve.jpg"],
      examples: ["img/qutu-qizili-draje.jpg", "img/stend-barokko.jpg"]
    },
    {
      id: "qizili-monoqram", cat: "monoqram", name: "Qızılı monoqram", badge: "YENİ", price: 1.5,
      look: { center: "mono", frame: "gold" }, colors: ["ag", "krem", "pudra"],
      short: "İri qızılı baş hərflər, altında adlar.",
      desc: "Qutunun ortasında iri qızılı baş hərfləriniz, aralarında incə xətt, altında adlar və tarix. Monoqramlı xonçalarla eyni üslubu saxlayır.",
      photos: ["img/qutular/qizili-monoqram.jpg"],
      examples: ["img/qutu-monoqram.jpg"]
    },
    {
      id: "zeytun", cat: "monoqram", name: "Zeytun budağı", price: 1.5,
      look: { center: "olive", frame: "line" }, colors: ["ag", "krem"],
      short: "Baş hərflərin arasında zeytun budağı.",
      desc: "Baş hərflərin arasında yaşıl zeytun budağı, altında adlar və tarix. Ağ güllü və yaşıl bəzəkli xonçalara yaraşır.",
      photos: ["img/qutular/zeytun.jpg"],
      examples: []
    },
    {
      id: "celeng", cat: "monoqram", name: "Qızılı çələng", price: 1.5,
      look: { center: "wreath", frame: "line" }, colors: ["ag", "krem", "pudra"],
      short: "Baş hərflər qızılı yarpaq çələngində.",
      desc: "Baş hərfləriniz qızılı yarpaqlardan çələngin içində, altında adlar və tarix. Təntənəli, amma sakit görünür.",
      photos: ["img/qutular/celeng.jpg"],
      examples: ["img/vitrin-qizilgul.jpg"]
    },
    {
      id: "ag-cicek", cat: "cicekli", name: "Ağ çiçək", price: 1.5,
      look: { center: "mono", frame: "line", deco: "flowers" }, colors: ["ag", "krem", "pudra"],
      short: "Akvarel ağ çiçəklər və baş hərflər.",
      desc: "Küncdə akvarel üslubunda ağ çiçəklər və yarpaqlar, ortada baş hərfləriniz, altında adlar. Ağ güllü buket və gipsofilalı xonçalara uyğundur.",
      photos: ["img/qutular/ag-cicek.jpg"],
      examples: ["img/buket-cobanyastigi.jpg"]
    },
    {
      id: "lavanda", cat: "cicekli", name: "Lavanda", price: 1.5,
      look: { center: "names", frame: "line", deco: "lavender" }, colors: ["ag", "lavanda"],
      short: "Lavanda budaqları, adlar kalliqrafiya ilə.",
      desc: "Qutunun iki tərəfində bənövşəyi lavanda budaqları, ortada kalliqrafiya ilə adlarınız və tarix. Yaz və yay mərasimləri üçün.",
      photos: ["img/qutular/lavanda.jpg"],
      examples: []
    },
    {
      id: "evkalipt", cat: "cicekli", name: "Evkalipt", price: 1.5,
      look: { center: "names", frame: "line", deco: "eucalyptus" }, colors: ["ag", "krem"],
      short: "Yaşıl evkalipt yarpaqları, ortada adlar.",
      desc: "Yuxarıda və aşağıda yaşıl evkalipt yarpaqları, ortada adlar və tarix. Evkaliptli və laləli xonçalarla bir dəst kimi görünür.",
      photos: ["img/qutular/evkalipt.jpg"],
      examples: ["img/qutu-lale.jpg"]
    },
    {
      id: "goy-fiyonk", cat: "fiyonklu", name: "Göy fiyonk", price: 1.5,
      look: { center: "mono", frame: "line", bow: "navy" }, colors: ["ag", "krem"],
      short: "Tünd göy fiyonk, altında monoqram.",
      desc: "Qutunun başında tünd göy fiyonk, altında baş hərflər, adlar və tarix. Ağ-göy rəngli mərasimlər üçün.",
      photos: ["img/qutular/goy-fiyonk.jpg"],
      examples: []
    },
    {
      id: "krem-fiyonk", cat: "fiyonklu", name: "Krem fiyonk", price: 1.5,
      look: { center: "names", frame: "line", bow: "cream" }, colors: ["ag", "pudra", "krem"],
      short: "Krem atlas fiyonk, adlar kalliqrafiya ilə.",
      desc: "Qutunun başında krem atlas fiyonk, altında kalliqrafiya ilə adlarınız və tarix. Pastel buketlərə yaraşır.",
      photos: ["img/qutular/krem-fiyonk.jpg"],
      examples: ["img/buket-hortenziya.jpg"]
    },
    {
      id: "bordo-urek", cat: "tund", name: "Bordo ürək", price: 1.5,
      look: { center: "heart", frame: "gold" }, colors: ["bordo", "gece"],
      short: "Bordo qutu, qızılı ürəkdə baş hərflər.",
      desc: "Bordo qutu, qızılı ürəyin içində baş hərfləriniz, altında adlar və tarix qızılı rəngdə. Payız və qış mərasimləri üçün.",
      photos: ["img/qutular/bordo-urek.jpg"],
      examples: []
    },
    {
      id: "gece-mavisi", cat: "tund", name: "Gecə mavisi", price: 1.5,
      look: { center: "mono", frame: "gold" }, colors: ["gece", "bordo"],
      short: "Tünd göy qutu, qızılı monoqram.",
      desc: "Tünd göy qutu, qızılı baş hərflər, adlar və tarix. Ağ və qızılı xonçaların yanında seçilir.",
      photos: ["img/qutular/gece-mavisi.jpg"],
      examples: []
    },
    {
      id: "cutluk-sekli", cat: "sekilli", name: "Cütlük şəkli", price: 1.5,
      look: { center: "photo", frame: "line" }, photo: true, colors: ["ag", "krem", "pudra"],
      short: "Sizin şəkliniz və adlarınız.",
      desc: "Qutunun üstündə sizin şəkliniz, altında adlarınız və tarix. Şəkli sifariş zamanı yükləyirsiniz.",
      photos: ["img/qutular/cutluk-sekli.jpg"],
      examples: []
    }
  ];

  function get(id) {
    for (var i = 0; i < ITEMS.length; i++) if (ITEMS[i].id === id) return ITEMS[i];
    return null;
  }
  function occ(id) {
    for (var i = 0; i < OCCASIONS.length; i++) if (OCCASIONS[i].id === id) return OCCASIONS[i];
    return OCCASIONS[0];
  }

  return {
    CATS: CATS, OCCASIONS: OCCASIONS, COLORS: COLORS, MIN: MIN, MAX: MAX, PRESETS: PRESETS,
    LEAD: LEAD, GUIDE: GUIDE, EXAMPLES: EXAMPLES, ITEMS: ITEMS, get: get, occ: occ
  };
})();
