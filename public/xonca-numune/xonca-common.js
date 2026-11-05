/* Nefis xonça şokoladı: ortaq kod (köməkçilər, header, kart, qutu önizləməsi). */
window.NF = (function () {
  var X = window.XONCA;

  function $(s, r) { return (r || document).querySelector(s); }
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }
  // 1.5 -> "1.50 ₼", 1200 -> "1 200 ₼" (nefis.az kimi nöqtə ilə)
  function money(n) {
    var r = Math.round(n * 100) / 100;
    var p = (r % 1 ? r.toFixed(2) : String(r)).split(".");
    p[0] = p[0].replace(/\B(?=(\d{3})+(?!\d))/g, " ");
    return p.join(".") + " ₼";
  }
  function pad(n) { return String(n).padStart(2, "0"); }
  function fmtDate(d) { return pad(d.getDate()) + "." + pad(d.getMonth() + 1) + "." + d.getFullYear(); }
  function isoDate(d) { return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate()); }
  function addDays(n) { var d = new Date(); d.setHours(0, 0, 0, 0); d.setDate(d.getDate() + n); return d; }
  function first(n) { return n.charAt(0).toLocaleUpperCase("az"); }
  function initials(names) { return names.map(first).join(" | "); }
  var reduced = !!(window.matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches);

  /* ================= Mini qutunun önizləməsi (SVG) =================
     t = { names:[], sampleNames:[], sample:bool, date:"dd.mm.yyyy" | "", label:"NİŞAN", color:"ag", photo:url|null }
     it.look: center, frame, deco, bow, goldText (xonca-data.js-də izah olunur) */
  var uid = 0;
  var SERIF = "Georgia,'Times New Roman',serif", SANS = "-apple-system,Inter,sans-serif";
  function fit(len, fs, max) { return len * fs * 0.52 > max ? ' textLength="' + max + '" lengthAdjust="spacingAndGlyphs"' : ''; }
  function script(y, s, fill, max) {
    var len = Math.max(s.length, 1);
    var fs = Math.max(14, Math.min(28, Math.floor(260 / len)));
    return '<text x="200" y="' + y + '" text-anchor="middle" font-family="' + SERIF + '" font-style="italic" font-size="' + fs + '" fill="' + fill + '"' + fit(len, fs, max) + '>' + esc(s) + '</text>';
  }
  function caps(y, s, fill) {
    s = s.toLocaleUpperCase("az");
    return '<text x="200" y="' + y + '" text-anchor="middle" font-family="' + SANS + '" font-size="11" font-weight="600" letter-spacing="2" fill="' + fill + '"' + fit(s.length, 13, 136) + '>' + esc(s) + '</text>';
  }
  function serif(x, y, s, size, fill, extra) {
    return '<text x="' + x + '" y="' + y + '" text-anchor="middle" font-family="' + SERIF + '" font-size="' + size + '" fill="' + fill + '"' + (extra || '') + '>' + esc(s) + '</text>';
  }
  function leaf(cx, cy, len, rot, fill) {
    return '<ellipse cx="' + cx + '" cy="' + cy + '" rx="' + len + '" ry="' + (len * 0.4).toFixed(1) + '" transform="rotate(' + rot + ' ' + cx + ' ' + cy + ')" fill="' + fill + '"/>';
  }
  function flower(cx, cy, r, rot) {
    var s = '';
    for (var i = 0; i < 5; i++) {
      s += '<ellipse cx="' + cx + '" cy="' + (cy - r * 0.9).toFixed(1) + '" rx="' + (r * 0.62).toFixed(1) + '" ry="' + r + '" transform="rotate(' + (rot + i * 72) + ' ' + cx + ' ' + cy + ')" fill="#FFFFFF" fill-opacity=".96" stroke="#C9BFAF" stroke-width=".8"/>';
    }
    return s + '<circle cx="' + cx + '" cy="' + cy + '" r="' + (r * 0.35).toFixed(1) + '" fill="#E7C97A"/>';
  }
  // Bəzəklər
  var DECO = {
    bow: function (kind) {
      var f = kind === "navy" ? "#22305C" : "#EADBC3", st = kind === "navy" ? "#141C3A" : "#C2AC86";
      return '<g fill="' + f + '" stroke="' + st + '" stroke-width=".8" stroke-linejoin="round">' +
        '<path d="M197 168 L182 198 L190 195 L193 203 L200 171 Z"/><path d="M203 168 L218 198 L210 195 L207 203 L200 171 Z"/>' +
        '<path d="M200 166 C188 146 160 146 165 166 C160 184 188 186 200 166 Z"/><path d="M200 166 C212 146 240 146 235 166 C240 184 212 186 200 166 Z"/>' +
        '<ellipse cx="200" cy="166" rx="6" ry="7.5"/></g>';
    },
    flowers: function () {
      return leaf(176, 172, 12, -25, "#A9BC9C") + leaf(224, 172, 12, 25, "#A9BC9C") +
        leaf(160, 182, 9, 15, "#C3D0B8") + leaf(240, 182, 9, -15, "#C3D0B8") +
        flower(180, 178, 7, 30) + flower(220, 178, 7, 10) + flower(200, 166, 11, 0);
    },
    lavender: function () {
      function sprig(x, flip) {
        var s = '<path d="M' + x + ' 384 Q ' + (x + (flip ? -7 : 7)) + ' 300 ' + x + ' 206" fill="none" stroke="#8FA07E" stroke-width="1.4"/>';
        for (var y = 206; y < 290; y += 8) {
          s += '<ellipse cx="' + (x - 3) + '" cy="' + y + '" rx="3" ry="4.6" fill="#9C8AC4"/><ellipse cx="' + (x + 3) + '" cy="' + (y + 4) + '" rx="3" ry="4.6" fill="#B9AADA"/>';
        }
        return s + leaf(x + (flip ? -6 : 6), 330, 9, flip ? 60 : -60, "#A9BC9C");
      }
      return sprig(140, false) + sprig(260, true);
    },
    eucalyptus: function () {
      function branch(y, dir) {
        var s = '<path d="M138 ' + y + ' Q 200 ' + (y + dir * 8) + ' 262 ' + y + '" fill="none" stroke="#7F9483" stroke-width="1.2"/>';
        for (var x = 146; x <= 254; x += 12) {
          var yy = y + dir * Math.sin((x - 138) / 124 * Math.PI) * 6;
          s += '<circle cx="' + x + '" cy="' + (yy - 5).toFixed(1) + '" r="5" fill="#A7BAA9"/><circle cx="' + (x + 6) + '" cy="' + (yy + 5).toFixed(1) + '" r="4.5" fill="#8FA595"/>';
        }
        return s;
      }
      return branch(147, 1) + branch(381, -1);
    }
  };

  function preview(it, t, label, crop) {
    var u = "p" + (++uid), C = X.COLORS[t.color] || X.COLORS.ag, L = it.look || {};
    var names = t.names.length ? t.names : t.sampleNames;
    var gold = "url(#" + u + "g)", dark = !!C.dark;
    var ink = (dark || L.goldText) ? gold : (t.sample ? "#9A8774" : "#3A2617");
    var op = t.sample ? ' opacity=".8"' : '';
    var lab = dark ? "#E2BE6E" : "#9C6C2A", soft = dark ? "#E9D9B4" : "#6B4630";
    var top = !!(L.bow || L.deco === "flowers");         // yuxarıda bəzək varsa, mətn aşağı düşür
    var nameMax = L.deco === "lavender" ? 104 : 140;
    var Y = top
      ? { label: 218, n1: 254, amp: 278, n2: 306, one: 284, mono: 276, monoFs: 42, line: 304, orn: 334, date: 362 }
      : { label: 174, n1: 236, amp: 264, n2: 296, one: 270, mono: 262, monoFs: 50, line: 298, orn: 328, date: 360 };

    var face = crop === "face";                          // yalnız qutunun üzü (foto mockup üçün)
    var s = '<svg viewBox="' + (face ? "115 130 170 270" : crop ? "55 96 290 362.5" : "0 0 400 500") + '" preserveAspectRatio="xMidYMid slice" ' +
      (label ? 'role="img" aria-label="' + esc(label) + '"' : 'aria-hidden="true" focusable="false"') + '>' +
      '<defs>' +
        '<radialGradient id="' + u + 'bg" cx="50%" cy="35%" r="80%"><stop offset="0" style="stop-color:var(--paper)"/><stop offset=".6" style="stop-color:var(--band)"/><stop offset="1" style="stop-color:var(--band-mid)"/></radialGradient>' +
        '<linearGradient id="' + u + 'g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#A8772E"/><stop offset=".5" stop-color="#E6C676"/><stop offset="1" stop-color="#B0823A"/></linearGradient>' +
        '<clipPath id="' + u + 'c"><circle cx="200" cy="226" r="46"/></clipPath>' +
      '</defs>' +
      (face ? '' : '<rect width="400" height="500" fill="url(#' + u + 'bg)"/>');
    // arxadakı qutular (eyni dizayn, bir neçə ədəd olduğunu göstərir)
    if (!face) s += '<g transform="rotate(-12 135 270)"><rect x="70" y="160" width="130" height="210" rx="6" fill="' + C.back + '"/><rect x="78" y="168" width="114" height="194" rx="3" fill="none" stroke="#C9A04A" stroke-opacity=".45"/></g>';
    if (!face) s += '<g transform="rotate(11 275 290)"><rect x="210" y="180" width="130" height="210" rx="6" fill="' + C.back + '"/><rect x="218" y="188" width="114" height="194" rx="3" fill="none" stroke="#C9A04A" stroke-opacity=".45"/></g>';
    // ön qutu
    s += (face ? '<g>' : '<g transform="rotate(-3 200 265)">' +
      '<rect x="121" y="138" width="170" height="270" rx="8" fill="#3A2617" opacity=".18"/>') +
      '<rect x="115" y="130" width="170" height="270" rx="' + (face ? 0 : 8) + '" fill="' + C.fill + '"/>';
    if (L.frame === "gold" || dark) {
      s += '<rect x="122" y="137" width="156" height="256" rx="5" fill="none" stroke="' + gold + '" stroke-width="2.4"/>' +
        '<rect x="129" y="144" width="142" height="242" rx="3" fill="none" stroke="' + gold + '" stroke-width=".9"/>';
    } else {
      s += '<rect x="125" y="140" width="150" height="250" rx="4" fill="none" stroke="#C9A04A" stroke-opacity=".6" stroke-width="1.2"/>';
    }
    if (L.deco && DECO[L.deco]) s += DECO[L.deco]();
    if (L.bow) s += DECO.bow(L.bow);
    s += '<text x="200" y="' + Y.label + '" text-anchor="middle" font-family="' + SANS + '" font-size="10.5" font-weight="600" letter-spacing="3.5" fill="' + lab + '">' + esc(t.label) + '</text>';

    var c = L.center || "names", orn = Y.orn, dateY = Y.date, pair = names.join(" & ");
    if (c === "mono") {
      s += serif(200, Y.mono, initials(names), Y.monoFs, gold, ' letter-spacing="4"' + op) + caps(Y.line, pair, ink);
    } else if (c === "olive") {
      var letters = names.map(first);
      s += '<path d="M200 284 Q 204 254 200 222" fill="none" stroke="#6F8150" stroke-width="1.4"/>';
      for (var i = 0; i < 6; i++) s += leaf(200 + (i % 2 ? 6 : -6), 230 + i * 9, 7, i % 2 ? -35 : 35, i % 2 ? "#7A8B5A" : "#93A372");
      s += serif(letters.length > 1 ? 168 : 200, 270, letters[0], 46, ink, op) + (letters.length > 1 ? serif(232, 270, letters[1], 46, ink, op) : '');
      s += caps(306, pair, ink); orn = 332;
    } else if (c === "wreath") {
      for (var k = 0; k < 24; k++) {
        var a = k / 24 * Math.PI * 2, lx = (200 + 44 * Math.cos(a)).toFixed(1), ly = (244 + 44 * Math.sin(a)).toFixed(1);
        s += leaf(lx, ly, 7, (a * 180 / Math.PI + 120).toFixed(0), gold);
      }
      s += serif(200, 254, names.map(first).join(" & "), 26, dark ? gold : ink, op) + caps(310, pair, ink); orn = 334;
    } else if (c === "heart") {
      s += '<path d="M200 292 C148 258 150 206 200 226 C250 206 252 258 200 292 Z" fill="none" stroke="' + gold + '" stroke-width="2"/>';
      s += serif(200, 262, names.map(first).join("&"), 26, gold, op) + caps(316, pair, ink); orn = 338; dateY = 364;
    } else if (c === "photo") {
      if (t.photo) {
        s += '<image href="' + esc(t.photo) + '" x="154" y="180" width="92" height="92" preserveAspectRatio="xMidYMid slice" clip-path="url(#' + u + 'c)"/>';
      } else {
        s += '<circle cx="200" cy="226" r="46" fill="#E6DCCD"/>' +
          '<path d="M184 216h7l3-5h12l3 5h7v20h-32z" fill="none" stroke="#9A8774" stroke-width="2" stroke-linejoin="round"/><circle cx="200" cy="225" r="6" fill="none" stroke="#9A8774" stroke-width="2"/>';
      }
      s += '<circle cx="200" cy="226" r="46" fill="none" stroke="#C9A04A" stroke-width="1.5"/>' + script(306, pair, ink, nameMax);
      orn = 330; dateY = 362;
    } else if (names.length > 1) {
      s += script(Y.n1, names[0], ink, nameMax) +
        '<text x="200" y="' + Y.amp + '" text-anchor="middle" font-family="' + SERIF + '" font-style="italic" font-size="18" fill="' + lab + '">&amp;</text>' +
        script(Y.n2, names[1], ink, nameMax);
    } else {
      s += script(Y.one, names[0], ink, nameMax);
    }
    s += '<path d="M168 ' + orn + 'h22M210 ' + orn + 'h22" stroke="#C9A04A" stroke-width="1.2"/><path d="M200 ' + (orn - 6) + 'l4 6-4 6-4-6z" fill="#C9A04A"/>';
    if (t.date) s += '<text x="200" y="' + dateY + '" text-anchor="middle" font-family="' + SANS + '" font-size="13" letter-spacing="2" fill="' + soft + '">' + esc(t.date) + '</text>';
    return s + '</g></svg>';
  }

  /* Real foto: yüklənməsə şəkil silinir, altdakı SVG görünür */
  function photoHTML(src, alt, lazy) {
    return '<img src="' + src + '" alt="' + esc(alt) + '"' + (lazy ? ' loading="lazy"' : '') +
      ' referrerpolicy="no-referrer" decoding="async" onerror="this.remove()">';
  }

  /* Kataloq kartı: real foto, foto yoxdursa SVG dizayn */
  var SAMPLE_DATE = fmtDate(addDays(30));
  function cardHTML(it) {
    var t = { names: ["Aynur", "Elvin"], sampleNames: [], sample: false, date: SAMPLE_DATE, label: "NİŞAN", color: it.colors[0], photo: null };
    var ph = it.photos && it.photos[0];
    return '<a class="pcard" href="xonca.html?x=' + it.id + '">' +
      '<div class="media' + (ph ? ' has-photo' : '') + '">' + preview(it, t, "", true) +
        (ph ? photoHTML(ph, "Nümunə: " + it.name + " dizaynında şokolad qutuları", true) : '') +
        (it.badge ? '<span class="tag">' + esc(it.badge) + '</span>' : '') + '</div>' +
      '<div class="body"><p class="eyebrow">' + esc(X.CATS[it.cat]) + '</p>' +
        '<h3>' + esc(it.name) + '</h3>' +
        '<p class="desc">' + esc(it.short) + '</p>' +
        '<p class="meta">Ən az ' + X.MIN + ' qutu, ' + money(X.MIN * it.price) + '</p>' +
        '<div class="foot"><span class="from">' + money(it.price) + ' <small>/ qutu</small></span>' +
        '<span class="btn btn-ghost btn-sm go" aria-hidden="true">Seç</span></div></div></a>';
  }

  /* Nümunə xonça şəkli: qiymətsiz, linksiz */
  function exampleHTML(e) {
    return '<figure class="ex"><img src="' + e.img + '" alt="' + esc(e.alt) + '" loading="lazy" width="1200" height="1500"></figure>';
  }

  /* Toast */
  var tt;
  function toast(msg) {
    var t = $("#toast");
    t.innerHTML = '<span aria-hidden="true">✓</span>' + esc(msg);
    t.classList.add("show");
    clearTimeout(tt);
    tt = setTimeout(function () { t.classList.remove("show"); }, 3000);
  }

  /* Header: tema və mobil menyu */
  function initHeader() {
    var tb = $("#themeBtn"), ti = $("#themeIc");
    function sync() {
      var dark = document.documentElement.getAttribute("data-theme") === "dark";
      tb.setAttribute("aria-label", dark ? "İşıqlı rejimə keç" : "Qaranlıq rejimə keç");
      ti.innerHTML = dark
        ? '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>'
        : '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>';
    }
    tb.addEventListener("click", function () {
      var dark = document.documentElement.getAttribute("data-theme") !== "dark";
      if (dark) document.documentElement.setAttribute("data-theme", "dark");
      else document.documentElement.removeAttribute("data-theme");
      try { localStorage.setItem("nefis-theme", dark ? "dark" : "light"); } catch (e) {}
      sync();
    });
    sync();

    var mb = $("#menuBtn"), mn = $("#mnav"), mi = $("#menuIc");
    mb.addEventListener("click", function () {
      var open = mn.hidden;
      mn.hidden = !open;
      mb.setAttribute("aria-expanded", String(open));
      mb.setAttribute("aria-label", open ? "Menyunu bağla" : "Menyunu aç");
      mi.innerHTML = open ? '<path d="M6 6l12 12M18 6 6 18"/>' : '<path d="M4 7h16M4 12h16M4 17h16"/>';
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !mn.hidden) { mb.click(); mb.focus(); }
    });
  }

  return {
    $: $, esc: esc, money: money, fmtDate: fmtDate, isoDate: isoDate, addDays: addDays, initials: initials,
    reduced: reduced, preview: preview, photoHTML: photoHTML, cardHTML: cardHTML, exampleHTML: exampleHTML,
    toast: toast, initHeader: initHeader
  };
})();
