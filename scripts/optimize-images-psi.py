"""One-shot: recompress heavy JPEGs, emit WebP (+ hero srcset)."""
from __future__ import annotations

import io
import json
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
IMG = ROOT / "img"

HERO = {
    "carousel-1.jpg": (1920, (800, 1280)),
    "carousel-2.jpg": (1920, (800, 1280)),
    "photo-header.jpg": (1920, ()),
}

# Card / banner photos: cap width to cut unused pixels on mobile.
CONTENT_MAX = {
    "photo-accueil-pourquoi.jpg": 1400,
    "photo-about.jpg": 1400,
    "photo-candidature.jpg": 1400,
    "photo-contact.jpg": 1400,
    "photo-inscription.jpg": 1400,
    "photo-projets.jpg": 1400,
    "photo-actu-1.jpg": 1200,
    "photo-actu-2.jpg": 1200,
    "photo-actualites-1.jpg": 1200,
    "photo-actualites-2.jpg": 1200,
    "photo-actualites-3.jpg": 1200,
    "photo-partenaire-1.jpg": 1200,
    "photo-partenaire-2.jpg": 1200,
    "photo-partenaire-3.jpg": 1200,
    "photo-partenaire-4.jpg": 1200,
    "photo-partenaire-5.jpg": 1200,
    "photo-partenaire-6.jpg": 1200,
    "photo-service-accompagnement.jpg": 1200,
    "photo-service-financement.jpg": 1200,
    "photo-service-formations.jpg": 1200,
    "photo-service-incubation.jpg": 1200,
    "photo-service-partenariats.jpg": 1200,
}

TEAM = (
    "team-mathieu.jpg",
    "team-frederic.jpg",
    "team-raissa.jpg",
    "team-yedina.jpg",
)
OBJECTIFS = (
    "objectif-accompagnement.jpg",
    "objectif-formation.jpg",
    "objectif-financement.jpg",
    "objectif-ess.jpg",
    "objectif-reseau.jpg",
)
THUMBS = (
    "vt-thumb-1.jpg",
    "vt-thumb-2.jpg",
    "vt-thumb-3.jpg",
    "vt-thumb-4.jpg",
)

JPEG_Q = 78
WEBP_Q = 74
HERO_JPEG_Q = 80
HERO_WEBP_Q = 76


def _resize(im: Image.Image, max_w: int) -> Image.Image:
    w, h = im.size
    if w <= max_w:
        return im
    nh = max(1, round(h * max_w / w))
    return im.resize((max_w, nh), Image.Resampling.LANCZOS)


def _jpeg_bytes(im: Image.Image, quality: int) -> bytes:
    buf = io.BytesIO()
    im.convert("RGB").save(buf, format="JPEG", quality=quality, optimize=True, progressive=True)
    return buf.getvalue()


def _webp_bytes(im: Image.Image, quality: int) -> bytes:
    buf = io.BytesIO()
    im.convert("RGB").save(buf, format="WEBP", quality=quality, method=6)
    return buf.getvalue()


def _write_if_smaller(path: Path, data: bytes, min_gain: float = 0.02) -> bool:
    if path.exists() and path.stat().st_size <= len(data) * (1 + min_gain) and path.suffix.lower() != ".webp":
        # overwrite anyway when we resized (caller handles)
        pass
    path.write_bytes(data)
    return True


def process_jpeg(name: str, max_w: int, jpeg_q: int, webp_q: int, extra_widths: tuple[int, ...] = ()) -> dict:
    src = IMG / name
    with Image.open(src) as im:
        im = im.convert("RGB")
        full = _resize(im, max_w)
        jpg = _jpeg_bytes(full, jpeg_q)
        if len(jpg) < src.stat().st_size or full.size != im.size:
            src.write_bytes(jpg)
        webp_path = src.with_suffix(".webp")
        webp_path.write_bytes(_webp_bytes(full, webp_q))
        stem = src.stem
        variants = {}
        for w in extra_widths:
            sized = _resize(im, w)
            (IMG / f"{stem}-{w}.jpg").write_bytes(_jpeg_bytes(sized, jpeg_q))
            (IMG / f"{stem}-{w}.webp").write_bytes(_webp_bytes(sized, webp_q))
            variants[w] = sized.size
        return {
            "file": name,
            "size": full.size,
            "jpg_kb": round(src.stat().st_size / 1024, 1),
            "webp_kb": round(webp_path.stat().st_size / 1024, 1),
            "variants": {str(k): list(v) for k, v in variants.items()},
        }


def process_logo() -> dict:
    src = IMG / "logo.png"
    with Image.open(src) as im:
        im = im.convert("RGBA")
        im.thumbnail((384, 384), Image.Resampling.LANCZOS)
        buf = io.BytesIO()
        im.save(buf, format="PNG", optimize=True)
        src.write_bytes(buf.getvalue())
        webp = io.BytesIO()
        im.convert("RGBA").save(webp, format="WEBP", quality=80, method=6)
        src.with_suffix(".webp").write_bytes(webp.getvalue())
        return {
            "file": "logo.png",
            "size": im.size,
            "png_kb": round(src.stat().st_size / 1024, 1),
            "webp_kb": round(src.with_suffix(".webp").stat().st_size / 1024, 1),
        }


def main() -> None:
    report = []
    for name, (max_w, extras) in HERO.items():
        report.append(process_jpeg(name, max_w, HERO_JPEG_Q, HERO_WEBP_Q, extras))
    for name, max_w in CONTENT_MAX.items():
        report.append(process_jpeg(name, max_w, JPEG_Q, WEBP_Q))
    for name in TEAM + OBJECTIFS + THUMBS:
        report.append(process_jpeg(name, 800, JPEG_Q, WEBP_Q))
    report.append(process_logo())
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
