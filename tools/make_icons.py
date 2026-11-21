#!/usr/bin/env python3
"""Génère les icônes du plugin (plugin_info + PWA) — glyphe maison ambre."""
import os
from PIL import Image, ImageDraw

S = 2048          # rendu suréchantillonné
BG = (19, 19, 21, 255)
AMBER = (245, 158, 11, 255)


def draw_house(draw, cx, cy, scale, line_w, color):
    """Dessine le glyphe maison (traits arrondis) centré en (cx, cy).
    Les coordonnées sont exprimées dans un repère 512x512."""
    unit = (S / 512.0) * scale

    def P(x, y):
        return (cx + (x - 256) * unit, cy + (y - 256) * unit)

    w = max(1, int(line_w))
    roof = [P(96, 252), P(256, 124), P(416, 252)]
    left = [P(140, 218), P(140, 402)]
    right = [P(372, 218), P(372, 402)]
    base = [P(140, 402), P(372, 402)]
    door = [P(222, 402), P(222, 318), P(290, 318), P(290, 402)]

    for pts in (roof, left, right, base, door):
        draw.line(pts, fill=color, width=w, joint="curve")
        for p in pts:
            r = w / 2.0
            draw.ellipse([p[0] - r, p[1] - r, p[0] + r, p[1] + r], fill=color)


def make_icon(path, size, maskable=False):
    img = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    if maskable:
        # fond plein (le système applique son propre masque)
        d.rectangle([0, 0, S, S], fill=BG)
        scale = 0.62
    else:
        radius = int(S * 0.22)
        d.rounded_rectangle([0, 0, S - 1, S - 1], radius=radius, fill=BG)
        scale = 0.78
    draw_house(d, S / 2, S / 2, scale, int(S * 0.052), AMBER)
    img = img.resize((size, size), Image.LANCZOS)
    img.save(path, "PNG", optimize=True)
    print("écrit", path, os.path.getsize(path), "octets")


base = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
os.makedirs(os.path.join(base, "pwa", "icons"), exist_ok=True)
os.makedirs(os.path.join(base, "plugin_info"), exist_ok=True)

make_icon(os.path.join(base, "plugin_info", "homeboard_icon.png"), 256)
make_icon(os.path.join(base, "pwa", "icons", "icon-192.png"), 192)
make_icon(os.path.join(base, "pwa", "icons", "icon-512.png"), 512)
make_icon(os.path.join(base, "pwa", "icons", "icon-maskable-512.png"), 512, maskable=True)
