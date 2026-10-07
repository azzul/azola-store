"""Sampul artikel contoh (ilustrasi). Jalankan: python3 database/seeders/demo-images/_articles.py"""
import os, math, random
from PIL import Image, ImageDraw, ImageFont
OUT = os.path.dirname(os.path.abspath(__file__))
S = 2; W, H = 1600 * S, 900 * S
F = "/usr/share/fonts/truetype/google-fonts/Poppins-Bold.ttf"
if not os.path.exists(F): F = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
def hexc(h): h=h.lstrip('#'); return tuple(int(h[i:i+2],16) for i in (0,2,4))
def mix(a,b,t): return tuple(int(a[i]+(b[i]-a[i])*t) for i in range(3))
COVERS = {
  "cover-stok":   ("#0f5c46", "#1f8f6b", "#ffd43b", "circles"),
  "cover-kopi":   ("#3b2a20", "#8a5a3c", "#f2c230", "waves"),
  "cover-dapur":  ("#c8742b", "#f0b15a", "#fff3d6", "tiles"),
  "cover-sekolah":("#2a4d9b", "#6c8fe0", "#ffd43b", "lines"),
}
for name,(c1,c2,acc,kind) in COVERS.items():
    a,b,ac = hexc(c1),hexc(c2),hexc(acc)
    img = Image.new("RGB",(W,H),a); d = ImageDraw.Draw(img,"RGBA")
    for x in range(W): d.line([(x,0),(x,H)], fill=mix(a,b,x/W))
    rnd = random.Random(name)
    if kind=="circles":
        for i in range(9):
            r=rnd.randrange(120,420)*S; x=rnd.randrange(W//3,W); y=rnd.randrange(0,H)
            d.ellipse([x-r,y-r,x+r,y+r], fill=(255,255,255,rnd.randrange(14,40)))
        d.ellipse([W*.62,H*.18,W*.62+360*S,H*.18+360*S], fill=ac+(255,))
        d.rounded_rectangle([W*.66,H*.32,W*.66+300*S,H*.32+230*S], radius=40*S, fill=(255,255,255,235))
    elif kind=="waves":
        for k in range(8):
            pts=[(x, H*(.45+.06*k)+math.sin(x/(180*S)+k)*60*S) for x in range(0,W+20,20)]
            d.line(pts, fill=(255,255,255,40+k*8), width=14*S)
        d.ellipse([W*.66,H*.22,W*.66+400*S,H*.22+400*S], fill=ac+(255,))
    elif kind=="tiles":
        for i in range(8):
            for j in range(5):
                if (i+j)%2==0: d.rounded_rectangle([W*.45+i*150*S, 60*S+j*150*S, W*.45+i*150*S+130*S, 60*S+j*150*S+130*S], radius=24*S, fill=(255,255,255,64))
        d.ellipse([W*.7,H*.55,W*.7+330*S,H*.55+330*S], fill=ac+(230,))
    else:
        for i in range(14):
            y=H*.15+i*52*S; d.line([(W*.5,y),(W*.95,y)], fill=(255,255,255,70), width=5*S)
        d.rounded_rectangle([W*.56,H*.2,W*.56+380*S,H*.2+520*S], radius=30*S, fill=ac+(255,))
    img.resize((1600,900), Image.LANCZOS).save(os.path.join(OUT,name+".jpg"),"JPEG",quality=84,optimize=True,progressive=True)
    print("ok",name)
