#!/usr/bin/env python3
"""Point the whole site at a new production domain in one command.

Most SEO tags (canonical, og:url, og:image, twitter:image) already adapt to the
live domain automatically at runtime via js/seo.js, so you usually do NOT need
this. Run it only to update the static-only pieces that crawlers read without
executing JavaScript: the JS fallback origin, the static canonical/OG/Twitter
fallbacks and JSON-LD in the HTML, robots.txt and sitemap.xml.

Usage:
    python3 scripts/set-domain.py https://www.procope-afrique.org

The scheme is optional (https:// is assumed) and a trailing slash is ignored.
"""
import glob
import io
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SEO_JS = os.path.join(ROOT, "js", "seo.js")


def current_origin():
    text = io.open(SEO_JS, encoding="utf-8").read()
    m = re.search(r'FALLBACK_ORIGIN\s*=\s*"([^"]+)"', text)
    if not m:
        sys.exit("Could not read FALLBACK_ORIGIN from js/seo.js")
    return m.group(1).rstrip("/")


def normalize(new):
    new = new.strip()
    if not re.match(r"^https?://", new):
        new = "https://" + new
    return new.rstrip("/")


def main():
    if len(sys.argv) != 2:
        sys.exit("Usage: python3 scripts/set-domain.py https://your-domain")
    old = current_origin()
    new = normalize(sys.argv[1])
    if old == new:
        print("Domain already set to", new)
        return

    targets = sorted(glob.glob(os.path.join(ROOT, "*.html")))
    targets += [os.path.join(ROOT, "robots.txt"),
                os.path.join(ROOT, "sitemap.xml"),
                SEO_JS]

    changed = 0
    for path in targets:
        if not os.path.exists(path):
            continue
        s = io.open(path, encoding="utf-8").read()
        if old in s:
            io.open(path, "w", encoding="utf-8").write(s.replace(old, new))
            changed += 1
            print("updated", os.path.relpath(path, ROOT))
    print(f"\nDone. {changed} file(s) updated: {old} -> {new}")


if __name__ == "__main__":
    main()
