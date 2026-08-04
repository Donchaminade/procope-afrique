/*
 * Domain-agnostic SEO.
 *
 * Rewrites the canonical URL and the social-share URLs/images from the domain
 * that is actually serving the page (window.location.origin). This means the
 * site works correctly on ANY domain — the current Vercel URL, or a future
 * .org / .com / .tg — without editing every page again.
 *
 * The static <link rel="canonical"> / og:* / twitter:* tags in the HTML act as
 * a fallback for crawlers that do not execute JavaScript (e.g. some social
 * scrapers). Keep FALLBACK_ORIGIN in sync with the primary production domain
 * (or run scripts/set-domain.py, which also updates robots.txt / sitemap.xml).
 */
(function () {
    "use strict";

    var FALLBACK_ORIGIN = "https://procopeafrique.vercel.app";

    var loc = window.location || {};
    var origin = (loc.origin && loc.origin.indexOf("http") === 0) ? loc.origin : FALLBACK_ORIGIN;

    // Canonical path: current path without a trailing "index.html" and without query/hash.
    var path = (loc.pathname || "/").replace(/index\.html$/, "");
    if (path.charAt(0) !== "/") { path = "/" + path; }

    var pageUrl = origin + path;
    var heroImage = origin + "/img/carousel-1.jpg";

    function upsertLink(rel, href) {
        var el = document.head.querySelector('link[rel="' + rel + '"]');
        if (!el) {
            el = document.createElement("link");
            el.setAttribute("rel", rel);
            document.head.appendChild(el);
        }
        el.setAttribute("href", href);
    }

    function upsertMeta(attrName, key, content) {
        var el = document.head.querySelector("meta[" + attrName + '="' + key + '"]');
        if (!el) {
            el = document.createElement("meta");
            el.setAttribute(attrName, key);
            document.head.appendChild(el);
        }
        el.setAttribute("content", content);
    }

    upsertLink("canonical", pageUrl);
    upsertMeta("property", "og:url", pageUrl);
    upsertMeta("property", "og:image", heroImage);
    upsertMeta("name", "twitter:image", heroImage);
})();
