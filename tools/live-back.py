# -*- coding: utf-8 -*-
r"""The back of the box, for the live-photo page.

Run from the project root:  python tools/live-back.py

The page explains that the camera is pointed at the printed picture and the
video plays on top of it, but it never showed where the QR code that starts
all that actually is. It is on the back of the box, and a sentence saying so
is worth less than the thing itself.
"""
import io
import os

import segno
from PIL import Image, ImageDraw, ImageFilter, ImageFont

OUT = 'public/images/live/'

# The box's printed face is 969 x 1895 in the shop's own templates.
W, H = 620, 1212

PAPER = (251, 244, 234)
CREAM2 = (243, 228, 208)
COCOA = (58, 38, 23)
SOFT = (122, 103, 86)
ORANGE = (240, 84, 31)
WHITE = (255, 255, 255)

F = 'C:/Windows/Fonts/'


def font(name, size):
    return ImageFont.truetype(F + name, size)


def centre(d, text, f, y, width, colour):
    w = d.textlength(text, font=f)
    d.text(((width - w) / 2, y), text, font=f, fill=colour)


def qr(text, size):
    code = segno.make(text, error='h')
    tmp = io.BytesIO()
    code.save(tmp, kind='png', scale=20, border=0,
              dark='#%02x%02x%02x' % COCOA, light='#%02x%02x%02x' % WHITE)
    tmp.seek(0)
    return Image.open(tmp).convert('RGB').resize((size, size), Image.LANCZOS)


# ------------------------------------------------------------- the box back
box = Image.new('RGB', (W, H), PAPER)
d = ImageDraw.Draw(box)

# A hairline inside the edge, the way the shop's boxes are printed.
d.rounded_rectangle((18, 18, W - 19, H - 19), 26, outline=CREAM2, width=3)

centre(d, 'ŞƏKLİ CANLANDIRIN', font('seguisb.ttf', 30), 196, W, ORANGE)

q = 300
qx, qy = (W - q) // 2, 322
pad = 34
d.rounded_rectangle((qx - pad, qy - pad, qx + q + pad, qy + q + pad), 18, fill=WHITE)
box.paste(qr('https://nefis.az/canli', q), (qx, qy))

y = qy + q + pad + 60
centre(d, 'QR kodu oxuyun', font('seguisb.ttf', 34), y, W, COCOA)
centre(d, 'telefonu şəklə tutun —', font('segoeui.ttf', 30), y + 56, W, SOFT)
centre(d, 'video şəklin üstündə oynayır', font('segoeui.ttf', 30), y + 98, W, SOFT)

centre(d, 'nefis', font('arialbd.ttf', 44), H - 130, W, ORANGE)
centre(d, 'nefis.az', font('segoeui.ttf', 26), H - 76, W, SOFT)

# ---------------------------------------------- standing on a surface
CARD = (W + 180, H + 200)
card = Image.new('RGB', CARD, (247, 242, 234))

radius = 26
mask = Image.new('L', (W, H), 0)
ImageDraw.Draw(mask).rounded_rectangle((0, 0, W - 1, H - 1), radius, fill=255)

bx, by = (CARD[0] - W) // 2, (CARD[1] - H) // 2 - 14

shadow = Image.new('L', CARD, 0)
ImageDraw.Draw(shadow).rounded_rectangle(
    (bx + 14, by + 34, bx + W - 14, by + H + 26), radius + 8, fill=86)
shadow = shadow.filter(ImageFilter.GaussianBlur(30))
card.paste(Image.new('RGB', CARD, (120, 96, 72)), (0, 0), shadow)
card.paste(box, (bx, by), mask)

os.makedirs(OUT, exist_ok=True)
card.save(OUT + 'qutunun-arxasi.jpg', quality=88, optimize=True)
print('back', os.path.getsize(OUT + 'qutunun-arxasi.jpg') // 1024, 'KB', card.size)
