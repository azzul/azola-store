"""
Membuat foto produk contoh (ilustrasi) untuk DemoStoreSeeder. Hanya perlu dijalankan ulang kalau ingin mengganti gambar demo:
    python3 database/seeders/demo-images/_generate.py
Hasilnya: <slug>-1.jpg (kemasan), <slug>-2.jpg (close-up), <slug>-3.jpg (semua ukuran). Ganti dengan foto asli toko Anda.
"""
import os, random
from PIL import Image, ImageDraw, ImageFilter, ImageFont

OUT = os.path.dirname(os.path.abspath(__file__))
S = 2          # supersampling
W = 1000 * S
FONT = "/usr/share/fonts/truetype/google-fonts/Poppins-Bold.ttf"
if not os.path.exists(FONT):
    FONT = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"

def font(size): return ImageFont.truetype(FONT, int(size * S))
def hexc(h): h = h.lstrip('#'); return tuple(int(h[i:i+2], 16) for i in (0, 2, 4))
def mix(a, b, t): return tuple(int(a[i] + (b[i] - a[i]) * t) for i in range(3))

def background(c1, c2, grain=True):
    img = Image.new("RGB", (W, W), c1)
    px = ImageDraw.Draw(img)
    for y in range(W):
        px.line([(0, y), (W, y)], fill=mix(c1, c2, y / W))
    # lantai lembut
    d = ImageDraw.Draw(img, "RGBA")
    d.rectangle([0, int(W * .72), W, W], fill=(255, 255, 255, 46))
    if grain:
        rnd = random.Random(7)
        for _ in range(9000):
            x, y = rnd.randrange(W), rnd.randrange(W)
            d.point((x, y), fill=(0, 0, 0, rnd.randrange(4, 14)))
    return img

def shadow(img, box, blur=26, alpha=90):
    sh = Image.new("RGBA", img.size, (0, 0, 0, 0))
    ImageDraw.Draw(sh).ellipse(box, fill=(20, 30, 25, alpha))
    sh = sh.filter(ImageFilter.GaussianBlur(blur * S))
    img.paste(sh, (0, 0), sh)

def shine(d, x0, y0, x1, y1, r):
    d.rounded_rectangle([x0 + (x1 - x0) * .08, y0 + (y1 - y0) * .06, x0 + (x1 - x0) * .2, y1 - (y1 - y0) * .06], radius=r, fill=(255, 255, 255, 54))

def label(d, cx, cy, w, h, title, sub, accent, ink=(255, 255, 255), fill=(255, 255, 255, 235), tcolor=(24, 35, 28)):
    d.rounded_rectangle([cx - w / 2, cy - h / 2, cx + w / 2, cy + h / 2], radius=18 * S, fill=fill)
    d.rectangle([cx - w / 2, cy - h / 2, cx - w / 2 + 14 * S, cy + h / 2], fill=accent)
    d.text((cx + 6 * S, cy - h * .16), title, font=font(h * .26 / S), fill=tcolor, anchor="mm")
    d.text((cx + 6 * S, cy + h * .2), sub, font=font(h * .15 / S), fill=mix(tcolor, (130, 140, 133), .4), anchor="mm")

def bag(img, cx, base, w, h, color, title, sub, accent):
    shadow(img, [cx - w * .62, base - 24 * S, cx + w * .62, base + 34 * S])
    d = ImageDraw.Draw(img, "RGBA")
    x0, x1, y0, y1 = cx - w / 2, cx + w / 2, base - h, base
    d.rounded_rectangle([x0, y0, x1, y1], radius=36 * S, fill=color)
    d.polygon([(x0, y0 + 6 * S), (x1, y0 + 6 * S), (x1 - 10 * S, y0 - 44 * S), (x0 + 10 * S, y0 - 44 * S)], fill=mix(color, (255, 255, 255), .12))
    for i in range(10):
        xx = x0 + 14 * S + i * (w - 28 * S) / 9
        d.line([(xx, y0 - 40 * S), (xx, y0 + 6 * S)], fill=(0, 0, 0, 36), width=3 * S)
    shine(d, x0, y0, x1, y1, 20 * S)
    label(d, cx, y0 + h * .46, w * .78, h * .3, title, sub, accent)

def bottle(img, cx, base, w, h, color, title, sub, accent, cap=(30, 40, 35)):
    shadow(img, [cx - w * .7, base - 22 * S, cx + w * .7, base + 30 * S])
    d = ImageDraw.Draw(img, "RGBA")
    x0, x1, y1 = cx - w / 2, cx + w / 2, base
    neck_w, neck_h = w * .34, h * .14
    y0 = base - h
    d.rounded_rectangle([cx - neck_w / 2, y0, cx + neck_w / 2, y0 + neck_h * 1.6], radius=10 * S, fill=mix(color, (255, 255, 255), .25))
    d.rounded_rectangle([cx - neck_w / 2 - 6 * S, y0 - 26 * S, cx + neck_w / 2 + 6 * S, y0 + 10 * S], radius=8 * S, fill=cap)
    d.rounded_rectangle([x0, y0 + neck_h, x1, y1], radius=int(w * .22), fill=color)
    shine(d, x0, y0 + neck_h, x1, y1, 24 * S)
    label(d, cx, y0 + h * .6, w * .88, h * .3, title, sub, accent)

def pouch(img, cx, base, w, h, color, title, sub, accent):
    shadow(img, [cx - w * .62, base - 22 * S, cx + w * .62, base + 30 * S])
    d = ImageDraw.Draw(img, "RGBA")
    x0, x1, y0, y1 = cx - w / 2, cx + w / 2, base - h, base
    d.polygon([(x0, y0 + 24 * S), (x1, y0 + 24 * S), (x1 + 6 * S, y1 - 10 * S), (x0 - 6 * S, y1 - 10 * S)], fill=color)
    d.rounded_rectangle([x0 - 4 * S, y0, x1 + 4 * S, y0 + 40 * S], radius=8 * S, fill=mix(color, (0, 0, 0), .12))
    for i in range(7):
        d.line([(x0, y0 + 8 * S + i * 4 * S), (x1, y0 + 8 * S + i * 4 * S)], fill=(255, 255, 255, 40), width=S)
    shine(d, x0, y0 + 20 * S, x1, y1, 16 * S)
    label(d, cx, y0 + h * .55, w * .8, h * .3, title, sub, accent)

def box(img, cx, base, w, h, color, title, sub, accent):
    shadow(img, [cx - w * .66, base - 22 * S, cx + w * .66, base + 30 * S])
    d = ImageDraw.Draw(img, "RGBA")
    x0, x1, y0, y1 = cx - w / 2, cx + w / 2, base - h, base
    side = w * .16
    d.polygon([(x1, y0), (x1 + side, y0 - side * .5), (x1 + side, y1 - side * .5), (x1, y1)], fill=mix(color, (0, 0, 0), .22))
    d.polygon([(x0, y0), (x1, y0), (x1 + side, y0 - side * .5), (x0 + side, y0 - side * .5)], fill=mix(color, (255, 255, 255), .22))
    d.rectangle([x0, y0, x1, y1], fill=color)
    shine(d, x0, y0, x1, y1, 6 * S)
    label(d, cx, y0 + h * .5, w * .8, h * .34, title, sub, accent)

def eggs(img, cx, base, w, h, color, title, sub, accent):
    shadow(img, [cx - w * .62, base - 30 * S, cx + w * .62, base + 36 * S])
    d = ImageDraw.Draw(img, "RGBA")
    d.rounded_rectangle([cx - w / 2, base - h * .5, cx + w / 2, base], radius=34 * S, fill=(196, 170, 128))
    rows = [(cx - w * .3, base - h * .42), (cx, base - h * .5), (cx + w * .3, base - h * .42)]
    for ex, ey in rows + [(ex - w * .15, ey - h * .26) for ex, ey in rows[:2]] + [(cx + w * .15 + w * .02, base - h * .7)]:
        rw, rh = w * .26, h * .42
        d.ellipse([ex - rw / 2, ey - rh * .85, ex + rw / 2, ey + rh * .15], fill=(232, 205, 160))
        d.ellipse([ex - rw * .3, ey - rh * .75, ex - rw * .05, ey - rh * .35], fill=(255, 255, 255, 80))
    d.rounded_rectangle([cx - w / 2, base - h * .22, cx + w / 2, base], radius=34 * S, fill=(176, 148, 106))
    label(d, cx, base - h * .11, w * .7, h * .17, title, sub, accent)

def notebook(img, cx, base, w, h, color, title, sub, accent):
    shadow(img, [cx - w * .65, base - 18 * S, cx + w * .65, base + 28 * S])
    d = ImageDraw.Draw(img, "RGBA")
    x0, x1, y0, y1 = cx - w / 2, cx + w / 2, base - h, base
    d.rounded_rectangle([x0 + 10 * S, y0 + 8 * S, x1 + 10 * S, y1 + 8 * S], radius=14 * S, fill=(255, 255, 255))
    d.rounded_rectangle([x0, y0, x1, y1], radius=14 * S, fill=color)
    d.rectangle([x0, y0, x0 + 26 * S, y1], fill=mix(color, (0, 0, 0), .2))
    for i in range(9):
        d.ellipse([x0 + 6 * S, y0 + 40 * S + i * (h - 80 * S) / 8 - 5 * S, x0 + 18 * S, y0 + 40 * S + i * (h - 80 * S) / 8 + 7 * S], fill=(255, 255, 255, 190))
    shine(d, x0, y0, x1, y1, 12 * S)
    label(d, cx + 12 * S, y0 + h * .42, w * .68, h * .26, title, sub, accent)

def pen(img, cx, base, w, h, color, title, sub, accent):
    d0 = ImageDraw.Draw(img, "RGBA")
    shadow(img, [cx - h * .52, base - 30 * S, cx + h * .52, base + 24 * S], 18, 70)
    pen_img = Image.new("RGBA", (int(h * 1.1), int(w * .5)), (0, 0, 0, 0))
    d = ImageDraw.Draw(pen_img, "RGBA")
    L, T = pen_img.size
    body_y0, body_y1 = T * .22, T * .78
    d.rounded_rectangle([L * .18, body_y0, L * .86, body_y1], radius=int(T * .28), fill=color)
    d.rectangle([L * .18, body_y0, L * .3, body_y1], fill=mix(color, (255, 255, 255), .25))
    d.polygon([(L * .18, body_y0 + 3 * S), (L * .18, body_y1 - 3 * S), (L * .04, T * .5)], fill=(40, 40, 40))
    d.rectangle([L * .6, body_y0 + 3 * S, L * .66, body_y1 - 3 * S], fill=(255, 255, 255, 120))
    d.rounded_rectangle([L * .86, body_y0 + 6 * S, L * .96, body_y1 - 6 * S], radius=8 * S, fill=mix(color, (0, 0, 0), .25))
    d.text((L * .44, T * .5), "GEL PEN 0.5", font=font(h * .045 / S), fill=(255, 255, 255, 235), anchor="mm")
    pen_img = pen_img.rotate(32, expand=True, resample=Image.BICUBIC)
    img.paste(pen_img, (int(cx - pen_img.width / 2), int(base - pen_img.height * .9)), pen_img)

def ream(img, cx, base, w, h, color, title, sub, accent):
    shadow(img, [cx - w * .64, base - 22 * S, cx + w * .64, base + 30 * S])
    d = ImageDraw.Draw(img, "RGBA")
    x0, x1, y0, y1 = cx - w / 2, cx + w / 2, base - h, base
    d.rounded_rectangle([x0, y0 + 14 * S, x1, y1], radius=10 * S, fill=(250, 250, 248))
    for i in range(14):
        d.line([(x0 + 10 * S, y0 + 22 * S + i * 3 * S), (x1 - 10 * S, y0 + 22 * S + i * 3 * S)], fill=(0, 0, 0, 14), width=S)
    d.rounded_rectangle([x0 - 6 * S, y0 + h * .2, x1 + 6 * S, y1 - h * .12], radius=10 * S, fill=color)
    shine(d, x0 - 6 * S, y0 + h * .2, x1 + 6 * S, y1 - h * .12, 8 * S)
    label(d, cx, y0 + h * .52, w * .78, h * .34, title, sub, accent)

def sugar(img, cx, base, w, h, color, title, sub, accent):
    bag(img, cx, base, w, h, color, title, sub, accent)

SHAPES = dict(bag=bag, bottle=bottle, pouch=pouch, box=box, eggs=eggs, notebook=notebook, pen=pen, ream=ream, sugar=sugar)

# slug: (bentuk, warna produk, warna latar 1, latar 2, judul label, sub label, aksen, ukuran-ukuran [(skala, teks)])
ITEMS = {
    "beras-premium": ("bag", "#d9b66b", "#f3ead6", "#e6d3a8", "BERAS", "Premium Pulen", "#2f7d4f", [(.62, "5 kg"), (.8, "10 kg"), (1.0, "25 kg")]),
    "minyak-goreng": ("bottle", "#e8b923", "#fbf1cf", "#f0dc8a", "MINYAK", "Goreng Jernih", "#c0392b", [(.6, "1 L"), (.8, "2 L"), (1.0, "5 L")]),
    "gula-pasir": ("sugar", "#f2efe7", "#e8f0f6", "#c9dbe8", "GULA", "Pasir 1 kg", "#2c6fb0", [(1.0, "1 kg")]),
    "telur-ayam": ("eggs", "#e6c99a", "#f6ebdc", "#e8d3b3", "TELUR", "Ayam Segar", "#b4541c", [(1.0, "kg")]),
    "kopi-bubuk-robusta": ("pouch", "#5b3a29", "#efe3d6", "#d9c1a9", "KOPI", "Robusta Bubuk", "#c9892b", [(.62, "100 g"), (.8, "200 g"), (1.0, "500 g")]),
    "teh-celup-melati": ("box", "#2f8f5b", "#e5f3e8", "#bfe0c8", "TEH", "Melati isi 25", "#f2c230", [(1.0, "25 kantong")]),
    "air-mineral": ("bottle", "#7cc3e8", "#e6f4fb", "#bfe0f2", "AIR", "Mineral 600 ml", "#1f78b4", [(1.0, "600 ml")]),
    "buku-tulis": ("notebook", "#3f6fd1", "#e8eefb", "#c6d4f3", "BUKU", "Tulis 38 lembar", "#f2c230", [(1.0, "38 lembar")]),
    "pulpen-gel": ("pen", "#1d2b3a", "#eceff3", "#cdd5de", "PULPEN", "Gel 0.5", "#1d2b3a", [(1.0, "0.5 mm")]),
    "kertas-hvs-a4": ("ream", "#d84a3a", "#f6f1ea", "#e4d9cb", "KERTAS", "HVS A4", "#2c6fb0", [(1.0, "500 lembar")]),
}

def save(img, name):
    img = img.resize((1000, 1000), Image.LANCZOS)
    img.save(os.path.join(OUT, name), "JPEG", quality=84, optimize=True, progressive=True)

for slug, (shape, color, bg1, bg2, title, sub, accent, sizes) in ITEMS.items():
    fn = SHAPES[shape]
    col, a = hexc(color), hexc(accent)
    c1, c2 = hexc(bg1), hexc(bg2)
    base = int(W * .8)
    size = (W * .36, W * .56)

    # 1. kemasan utama
    img = background(c1, c2)
    fn(img, W // 2, base, size[0], size[1], col, title, sub, a)
    save(img, f"{slug}-1.jpg")

    # 2. close-up dari tengah, latar lebih gelap
    img = background(mix(c2, (255, 255, 255), .1), mix(c2, (0, 0, 0), .18))
    fn(img, W // 2, int(W * .98), W * .62, W * .92, col, title, sub, a)
    save(img, f"{slug}-2.jpg")

    # 3. semua ukuran berjejer
    img = background(mix(c1, (255, 255, 255), .4), c1)
    n = len(sizes)
    for i, (k, txt) in enumerate(sizes):
        cx = W * (i + 1) / (n + 1) if n > 1 else W / 2
        fn(img, int(cx), int(W * .8), size[0] * (k if n > 1 else 1) * (.78 if n > 1 else 1), size[1] * (k if n > 1 else 1) * (.78 if n > 1 else 1), col, title, sub if n == 1 else txt, a)
    save(img, f"{slug}-3.jpg")
    print("ok", slug)
