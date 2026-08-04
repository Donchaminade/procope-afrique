# AGENTS.md

## Cursor Cloud specific instructions

This repository is a **fully static website** (the PROCOPE Afrique showcase/incubator site). It is plain HTML5 + CSS3 (Bootstrap 5) + JavaScript (jQuery, Owl Carousel, WOW.js, etc.). See `README.md` for the page list and file structure.

Key facts for future agents:

- **No build step, no package manager, no tests, no lint.** There is no `package.json`, lockfile, `Makefile`, or CI config. All third-party libraries are vendored (committed) under `lib/`, and CSS under `css/`. Nothing needs to be installed to develop or run the site, so the environment update script is intentionally a no-op.
- **Run it as a static server (development mode).** Serve the repo root over HTTP rather than opening files via `file://` (relative asset paths and the search modal behave best over HTTP). Any static server works; a simple option already available in this environment is `python3 -m http.server 5501` from the repo root. The `.vscode/settings.json` uses VS Code Live Server on port `5501`, so `5501` is the conventional dev port.
- **Entry point:** `index.html` (browser tab title "Accueil - PROCOPE"). Other pages are the top-level `*.html` files (e.g. `candidature.html`, `contact.html`, `service.html`).
- **Forms are front-end only.** The candidature/contact forms have no backend `action`; submitting reloads the page (query string appended) and clears the fields. This is expected — there is no server-side handler to run or configure.
- **Editing content:** text lives directly in the `*.html` files, custom styles in `css/style.css`, and site JS in `js/main.js`. Changes are picked up on browser refresh (no compilation).
- **Dark mode / theming (non-obvious):** the active theme is stored on `<html data-theme="light|dark">`. Each page has a tiny inline `<script>` in `<head>` that applies the saved theme early (from `localStorage` key `procope-theme`, falling back to the OS `prefers-color-scheme`) to avoid a flash. The toggle button is `#theme-toggle` in the navbar; its click handler lives in `js/main.js`. Dark styles are scoped under `html[data-theme="dark"] ...` at the end of `css/style.css` and mostly override Bootstrap utility classes (`.bg-light`, `.bg-white`, `.bg-dark`, `.form-control`, etc.). When adding a new page or section, keep the `<head>` theme-init script and reuse those utility classes so dark mode keeps working automatically.
- **SEO conventions:** per-page `<title>`, meta description/keywords, canonical, Open Graph/Twitter tags and JSON-LD live in each page's `<head>`; `robots.txt` and `sitemap.xml` are at the repo root. Absolute URLs use the base `https://donchaminade.github.io/procope-afrique/` — update that base (in every page's canonical/OG/Twitter tags, `robots.txt` and `sitemap.xml`) if the site is deployed on a different domain.
- **Images:** photos in `img/` are AI-generated and web-optimized (resized + compressed). If you regenerate images, keep them compressed (they were reduced from ~26 MB to <1 MB total) so page speed / SEO is preserved.
