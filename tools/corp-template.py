# -*- coding: utf-8 -*-
r"""The sheet a company's own designer works on.

Two things come out of this: the template itself, blank panels with the cut,
bleed and safe lines drawn on them, and beside it a worked example of the
same two panels filled in — because a designer who has never held the box
cannot tell from a measurement how small the front really is, or that the
QR code on the back has to sit on white with room around it.
"""
import io
import os

import segno
from PIL import Image, ImageDraw, ImageFont

# Run from the project root:  python tools/corp-template.py
OUT = 'public/images/corporate/'

DPI = 300
MM = DPI / 25.4

W, H = 2480, 3508                      # A4 at 300 dpi, so he can print it

PAPER = (255, 253, 250)
INK = (36, 26, 20)
GREY = (122, 112, 104)
FAINT = (228, 220, 212)
ORANGE = (240, 84, 31)
CUT = (36, 26, 20)
BLEED = (210, 59, 46)
SAFE = (47, 111, 179)
NAVY = (27, 58, 107)
WHITE = (255, 255, 255)

# Windows' own faces; they carry the Azerbaijani letters.
F = 'C:/Windows/Fonts/'


def font(name, size):
    return ImageFont.truetype(F + name, size)


BOLD = lambda s: font('seguisb.ttf', s)
REG = lambda s: font('segoeui.ttf', s)
HEAVY = lambda s: font('arialbd.ttf', s)


def mm(v):
    return int(round(v * MM))


def dashed(d, box, colour, width=3, dash=22, gap=16):
    """A dashed rectangle. PIL has no dash, so the line is walked by hand."""
    x0, y0, x1, y1 = box
    for (ax, ay, bx, by) in ((x0, y0, x1, y0), (x1, y0, x1, y1),
                             (x1, y1, x0, y1), (x0, y1, x0, y0)):
        length = max(abs(bx - ax), abs(by - ay))
        if not length:
            continue
        dx, dy = (bx - ax) / length, (by - ay) / length
        at = 0
        while at < length:
            to = min(at + dash, length)
            d.line([ax + dx * at, ay + dy * at, ax + dx * to, ay + dy * to],
                   fill=colour, width=width)
            at = to + gap


def centre(d, text, f, y, box, colour=INK):
    x0, _, x1, _ = box
    w = d.textlength(text, font=f)
    d.text(((x0 + x1 - w) / 2, y), text, font=f, fill=colour)


def qr_image(text, size, dark=INK, light=WHITE):
    """A real code, so the designer can point a phone at the example."""
    code = segno.make(text, error='h')
    tmp = io.BytesIO()
    code.save(tmp, kind='png', scale=20, border=0,
              dark='#%02x%02x%02x' % dark, light='#%02x%02x%02x' % light)
    tmp.seek(0)
    return Image.open(tmp).convert('RGB').resize((size, size), Image.LANCZOS)


# --------------------------------------------------------------- the panels
PANEL_W, PANEL_H = mm(45), mm(90)
BLEED_MM, SAFE_MM = mm(3), mm(4)


def blank_panel(label, back=False):
    """One face of the box as the designer receives it: the paper, the three
    lines that matter, and on the back the square the QR code must keep."""
    w, h = PANEL_W + BLEED_MM * 2, PANEL_H + BLEED_MM * 2
    im = Image.new('RGB', (w, h), PAPER)
    d = ImageDraw.Draw(im)

    cut = (BLEED_MM, BLEED_MM, w - BLEED_MM - 1, h - BLEED_MM - 1)
    safe = (BLEED_MM + SAFE_MM, BLEED_MM + SAFE_MM,
            w - BLEED_MM - SAFE_MM - 1, h - BLEED_MM - SAFE_MM - 1)

    d.rectangle(cut, fill=(252, 249, 245))
    dashed(d, (1, 1, w - 2, h - 2), BLEED, width=4)
    d.rectangle(cut, outline=CUT, width=4)
    dashed(d, safe, SAFE, width=3, dash=18, gap=14)

    if back:
        # The QR square, dead centre, the size the printer needs it.
        q = mm(20)
        qx, qy = (w - q) // 2, (h - q) // 2 - mm(4)
        d.rectangle((qx, qy, qx + q, qy + q), fill=WHITE, outline=SAFE, width=3)
        im.paste(qr_image('https://nefis.az', q - mm(3), dark=(190, 186, 182)),
                 (qx + mm(1.5), qy + mm(1.5)))
        small = REG(26)
        centre(d, 'QR kod', small, qy + q + mm(3), (0, 0, w, 0), SAFE)
        centre(d, '20 × 20 mm', small, qy + q + mm(3) + 34, (0, 0, w, 0), SAFE)
        centre(d, 'ağ fonda', small, qy + q + mm(3) + 68, (0, 0, w, 0), SAFE)
    else:
        f = REG(30)
        centre(d, 'LOQO', BOLD(44), h // 2 - mm(14), (0, 0, w, 0), FAINT)
        centre(d, 'şüar', f, h // 2 + mm(1), (0, 0, w, 0), FAINT)
        centre(d, '+994 __ ___ __ __', f, h // 2 + mm(9), (0, 0, w, 0), FAINT)

    return im


def filled_panel(back=False):
    """The same face with a design on it — what we are asking for."""
    w, h = PANEL_W + BLEED_MM * 2, PANEL_H + BLEED_MM * 2
    im = Image.new('RGB', (w, h), NAVY)
    d = ImageDraw.Draw(im)

    if back:
        q = mm(20)
        qx, qy = (w - q) // 2, (h - q) // 2 - mm(6)
        pad = mm(3)
        d.rectangle((qx - pad, qy - pad, qx + q + pad, qy + q + pad),
                    fill=WHITE)
        im.paste(qr_image('https://nefis.az', q), (qx, qy))
        centre(d, 'instagram.com/nefis.az', REG(30), qy + q + mm(8),
               (0, 0, w, 0), (225, 228, 236))
        centre(d, 'Menyunu skan edin', REG(26), qy + q + mm(8) + 44,
               (0, 0, w, 0), (160, 172, 196))
    else:
        centre(d, 'LOQO', HEAVY(96), h // 2 - mm(17), (0, 0, w, 0), WHITE)
        d.line([(w // 2 - mm(9), h // 2 + mm(2)), (w // 2 + mm(9), h // 2 + mm(2))],
               fill=ORANGE, width=6)
        centre(d, 'Həyata güvənlə baxın', REG(32), h // 2 + mm(6),
               (0, 0, w, 0), (225, 228, 236))
        centre(d, '+994 50 123 45 67', REG(30), h // 2 + mm(13),
               (0, 0, w, 0), (160, 172, 196))

    return im


# ----------------------------------------------------------------- the sheet
sheet = Image.new('RGB', (W, H), PAPER)
d = ImageDraw.Draw(sheet)

M = 150
y = 150

d.text((M, y), 'nefis', font=HEAVY(78), fill=ORANGE)
d.text((M + 230, y + 26), 'şirkət şokoladı', font=REG(44), fill=GREY)
y += 130

d.text((M, y), 'Dizayn şablonu', font=BOLD(96), fill=INK)
y += 125
d.text((M, y), 'Qutu: 45 × 90 × 15 mm   ·   Çap sahəsi: 45 × 90 mm   ·   300 dpi   ·   CMYK',
       font=REG(42), fill=GREY)
y += 60
d.line([(M, y + 30), (W - M, y + 30)], fill=FAINT, width=3)
y += 90

# --- 1. the template ------------------------------------------------------
d.text((M, y), '1.  ŞABLON — dizaynı bu sahələrin üstündə qurun', font=BOLD(52), fill=INK)
y += 95

pw = PANEL_W + BLEED_MM * 2
gap = 260
left = (W - pw * 2 - gap) // 2
labels = ('ÖN ÜZ', 'ARXA ÜZ')
for i, (lab, panel) in enumerate(zip(labels, (blank_panel('', False), blank_panel('', True)))):
    x = left + i * (pw + gap)
    centre(d, lab, BOLD(42), y, (x, 0, x + pw, 0), INK)
    sheet.paste(panel, (x, y + 70))

ph = PANEL_H + BLEED_MM * 2
y += 70 + ph + 40

# the legend, between the two panels' feet
legend = [
    (BLEED, '— — —', 'Kəsim payı (bleed) 3 mm — fon bura qədər uzansın'),
    (CUT, '———', 'Kəsim xətti — qutunun əsl ölçüsü'),
    (SAFE, '— — —', 'Təhlükəsiz sahə — yazı və loqo bunun içində qalsın'),
]
for colour, dash, text in legend:
    d.text((M, y), dash, font=BOLD(34), fill=colour)
    d.text((M + 130, y - 2), text, font=REG(38), fill=GREY)
    y += 52

y += 20

# --- 2. the example -------------------------------------------------------
d.text((M, y), '2.  NÜMUNƏ — hazır dizayn belə görünür', font=BOLD(52), fill=INK)
y += 95

scale = 0.50
sw, sh = int(pw * scale), int(ph * scale)
left2 = (W - sw * 2 - gap) // 2
for i, panel in enumerate((filled_panel(False), filled_panel(True))):
    x = left2 + i * (sw + gap)
    centre(d, labels[i], BOLD(42), y, (x, 0, x + sw, 0), INK)
    sheet.paste(panel.resize((sw, sh), Image.LANCZOS), (x, y + 70))

y += 70 + sh + 45

# --- 3. the rules ---------------------------------------------------------
d.line([(M, y), (W - M, y)], fill=FAINT, width=3)
y += 50
d.text((M, y), 'Qaydalar', font=BOLD(48), fill=INK)
y += 80

rules = [
    'Fayl: PDF, AI, EPS — yoxdursa PNG və ya JPG, 300 dpi, ölçü 51 × 96 mm (kəsim payı ilə).',
    'Rəng: CMYK. RGB göndərsəniz, çapda rəng bir az dəyişə bilər.',
    'Şrift: hərfləri əyriyə çevirin (outline) və ya şrift faylını da göndərin.',
    'QR kod: ən azı 20 × 20 mm, ağ fonda, ətrafında 4 mm boş yer. Tünd fonda işləmir.',
    'Ən kiçik yazı 6 pt — qutu kiçikdir, ondan aşağısı oxunmur.',
    'Vacib heç nəyi kəsim xəttinə yaxın qoymayın: mavi xəttin içində qalsın.',
]
for line in rules:
    d.ellipse((M + 8, y + 16, M + 22, y + 30), fill=ORANGE)
    d.text((M + 50, y), line, font=REG(38), fill=(70, 60, 54))
    y += 58

y += 18
d.text((M, y), 'Hazır faylı saytdakı müraciət formasına əlavə edin: nefis.az/sirketler-ucun',
       font=BOLD(40), fill=ORANGE)

os.makedirs(OUT, exist_ok=True)
sheet.save(OUT + 'dizayn-sablonu.jpg', quality=90, dpi=(DPI, DPI), optimize=True)
print('sheet', os.path.getsize(OUT + 'dizayn-sablonu.jpg') // 1024, 'KB', sheet.size, 'y-end', y)

# ---------------------------------------------- the back of the box, on its own
# The same back panel the designer is shown, but standing on the page as an
# object: rounded corners and a soft shadow, so it reads as a box lying on a
# surface rather than a navy rectangle.
from PIL import ImageFilter

CARD = (1200, 980)
card = Image.new('RGB', CARD, (247, 242, 234))

back = filled_panel(True)
bh = 780
bw = int(back.width * bh / back.height)
back = back.resize((bw, bh), Image.LANCZOS)

radius = 34
mask = Image.new('L', (bw, bh), 0)
ImageDraw.Draw(mask).rounded_rectangle((0, 0, bw - 1, bh - 1), radius, fill=255)

bx, by = (CARD[0] - bw) // 2, (CARD[1] - bh) // 2 - 10

shadow = Image.new('L', CARD, 0)
ImageDraw.Draw(shadow).rounded_rectangle(
    (bx + 10, by + 26, bx + bw - 10, by + bh + 20), radius + 6, fill=90)
shadow = shadow.filter(ImageFilter.GaussianBlur(26))
card.paste(Image.new('RGB', CARD, (120, 96, 72)), (0, 0), shadow)

card.paste(back, (bx, by), mask)
card.save(OUT + 'qutunun-arxasi.jpg', quality=90, optimize=True)
print('back', os.path.getsize(OUT + 'qutunun-arxasi.jpg') // 1024, 'KB', CARD)
