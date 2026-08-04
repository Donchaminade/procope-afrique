/*
 * PROCOPE Afrique — interactive features (no backend required):
 *   - TikTok video-testimonials strip: smooth right-to-left auto-scroll,
 *     pause on hover, drag / swipe to browse, click to play in a pop-up,
 *     first-visit "swipe" tutorial, and a "see more on TikTok" link.
 *   - Registration form that opens a pre-filled WhatsApp message.
 *   - Previous-training posters lightbox.
 *
 * ---> EDIT THIS CONFIG with your real values. <---
 */
(function () {
    "use strict";

    /* =========================== CONFIG =========================== */

    // WhatsApp number in international format, digits only (no +, no spaces).
    // Togo +228 7014 57 73  ->  22870145773
    var WHATSAPP_NUMBER = "22870145773";

    // Public TikTok profile (the "Voir plus sur TikTok" button).
    var TIKTOK_PROFILE = "https://www.tiktok.com/@procope.afrique";

    // Video testimonials. Paste the real TikTok video links in `url`.
    // The video ID is auto-extracted from a standard link:
    //   https://www.tiktok.com/@user/video/1234567890123456789
    // `thumb` is the local preview image shown in the strip.
    var VIDEO_TESTIMONIALS = [
        { name: "Aïssatou Diop", role: "Fondatrice, AgriConnect", thumb: "img/vtestim-1.jpg", url: "https://www.tiktok.com/@procope.afrique/video/0000000000000000001" },
        { name: "Moussa Traoré", role: "CEO, MobiSanté", thumb: "img/vtestim-2.jpg", url: "https://www.tiktok.com/@procope.afrique/video/0000000000000000002" },
        { name: "Fatima Diallo", role: "Co-fondatrice, EduTech", thumb: "img/vtestim-3.jpg", url: "https://www.tiktok.com/@procope.afrique/video/0000000000000000003" },
        { name: "David Okoro", role: "Fondateur, PayeFacile", thumb: "img/vtestim-4.jpg", url: "https://www.tiktok.com/@procope.afrique/video/0000000000000000004" },
        { name: "Grace Mensah", role: "CEO, GreenBox", thumb: "img/vtestim-5.jpg", url: "https://www.tiktok.com/@procope.afrique/video/0000000000000000005" },
        { name: "Kofi Boateng", role: "Fondateur, LogiTrans", thumb: "img/vtestim-6.jpg", url: "https://www.tiktok.com/@procope.afrique/video/0000000000000000006" }
    ];

    /* ============================ HELPERS ============================ */

    function tiktokVideoId(url) {
        var m = /\/video\/(\d+)/.exec(url || "");
        return m ? m[1] : null;
    }

    function tiktokEmbed(url) {
        var id = tiktokVideoId(url);
        return id ? "https://www.tiktok.com/player/v1/" + id + "?autoplay=1&loop=1&controls=1" : null;
    }

    function esc(s) {
        return String(s == null ? "" : s).replace(/[&<>"]/g, function (c) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c];
        });
    }

    // Inline TikTok glyph (Font Awesome 5.10 in this template has no fa-tiktok).
    var TIKTOK_SVG = '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" aria-hidden="true">' +
        '<path d="M16.5 3c.3 2.1 1.5 3.6 3.5 3.9v2.4c-1.2.1-2.4-.2-3.5-.8v5.6c0 3.2-2.5 5.6-5.6 5.6S5.3 17.3 5.3 14.1s2.5-5.6 5.7-5.5v2.5c-.2-.1-.5-.1-.7-.1-1.7 0-3.1 1.4-3.1 3.1s1.4 3.1 3.1 3.1 3-1.4 3-3.1V3h3.2z"/></svg>';

    /* ==================== VIDEO TESTIMONIALS STRIP ==================== */

    function initVideoStrip() {
        var scroller = document.getElementById("vt-scroller");
        if (!scroller) return;

        var cards = VIDEO_TESTIMONIALS.map(function (v) {
            return (
                '<div class="vt-card" data-url="' + esc(v.url) + '" role="button" tabindex="0" ' +
                'aria-label="Lire le témoignage vidéo de ' + esc(v.name) + '">' +
                '<img src="' + esc(v.thumb) + '" alt="Témoignage vidéo de ' + esc(v.name) + '" loading="lazy">' +
                '<span class="vt-tiktok-badge">' + TIKTOK_SVG + "</span>" +
                '<div class="vt-overlay"><span class="vt-play"><i class="fa fa-play"></i></span></div>' +
                '<div class="vt-meta"><h6>' + esc(v.name) + '</h6><small>' + esc(v.role) + "</small></div>" +
                "</div>"
            );
        }).join("");

        // Duplicate the set so the auto-scroll can loop seamlessly.
        scroller.innerHTML = cards + cards;

        var state = { hover: false, drag: false, modal: false, touchPause: false };
        var isDown = false, dragMoved = false, startX = 0, startScroll = 0, touchTimer = null;
        var SPEED = 0.5;

        function paused() { return state.hover || state.drag || state.modal || state.touchPause; }

        function tick() {
            if (!paused() && scroller.scrollWidth > scroller.clientWidth) {
                scroller.scrollLeft += SPEED;
                var half = scroller.scrollWidth / 2;
                if (scroller.scrollLeft >= half) { scroller.scrollLeft -= half; }
            }
            requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);

        scroller.addEventListener("mouseenter", function () { state.hover = true; });
        scroller.addEventListener("mouseleave", function () { state.hover = false; });

        scroller.addEventListener("pointerdown", function (e) {
            isDown = true; dragMoved = false; startX = e.clientX; startScroll = scroller.scrollLeft;
            if (e.pointerType === "mouse") {
                state.drag = true;
                scroller.classList.add("is-dragging");
            } else {
                state.touchPause = true;
                if (touchTimer) clearTimeout(touchTimer);
            }
        });

        window.addEventListener("pointermove", function (e) {
            if (!isDown) return;
            var dx = e.clientX - startX;
            if (Math.abs(dx) > 6) dragMoved = true;
            if (e.pointerType === "mouse") {
                scroller.scrollLeft = startScroll - dx;
                e.preventDefault();
            }
        });

        function endPointer(e) {
            if (!isDown) return;
            isDown = false;
            state.drag = false;
            scroller.classList.remove("is-dragging");
            if (e && e.pointerType !== "mouse") {
                touchTimer = setTimeout(function () { state.touchPause = false; }, 2500);
            }
        }
        window.addEventListener("pointerup", endPointer);
        window.addEventListener("pointercancel", endPointer);

        function openCard(card) {
            var url = card.getAttribute("data-url");
            var embed = tiktokEmbed(url);
            var frame = document.getElementById("vt-frame");
            var fallback = document.getElementById("vt-fallback");
            var openLink = document.getElementById("vt-open-tiktok");
            if (openLink) openLink.href = url || TIKTOK_PROFILE;
            if (embed && frame) {
                frame.innerHTML = '<iframe src="' + embed + '" allow="autoplay; encrypted-media; fullscreen" allowfullscreen></iframe>';
                if (fallback) fallback.style.display = "none";
            } else {
                if (frame) frame.innerHTML = "";
                if (fallback) fallback.style.display = "block";
            }
            state.modal = true;
            if (window.bootstrap) new window.bootstrap.Modal(document.getElementById("videoModal")).show();
        }

        scroller.addEventListener("click", function (e) {
            if (dragMoved) { dragMoved = false; return; }
            var card = e.target.closest(".vt-card");
            if (card) openCard(card);
        });
        scroller.addEventListener("keydown", function (e) {
            if (e.key !== "Enter" && e.key !== " ") return;
            var card = e.target.closest(".vt-card");
            if (card) { e.preventDefault(); openCard(card); }
        });

        var modalEl = document.getElementById("videoModal");
        if (modalEl) {
            modalEl.addEventListener("hidden.bs.modal", function () {
                var frame = document.getElementById("vt-frame");
                if (frame) frame.innerHTML = "";  // stop playback
                state.modal = false;
            });
        }

        // First-visit swipe tutorial
        var hint = document.getElementById("vt-swipe-hint");
        if (hint) {
            var dismiss = function () {
                hint.classList.add("is-hidden");
                try { localStorage.setItem("procope-vt-hint", "seen"); } catch (err) { }
            };
            if (localStorage.getItem("procope-vt-hint") === "seen") {
                hint.classList.add("is-hidden");
            } else {
                hint.addEventListener("click", dismiss);
                scroller.addEventListener("pointerdown", dismiss, { once: true });
                setTimeout(dismiss, 6000);
            }
        }
    }

    /* ==================== WHATSAPP REGISTRATION FORM ==================== */

    function initWhatsappForm() {
        var form = document.getElementById("training-form");
        if (!form) return;
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            var get = function (id) { var el = document.getElementById(id); return el ? el.value.trim() : ""; };
            var name = get("tf-name"), phone = get("tf-phone"), email = get("tf-email"),
                training = get("tf-training"), msg = get("tf-message");

            var lines = [
                "Bonjour PROCOPE Afrique, je souhaite m'inscrire à la prochaine formation.",
                "",
                "Nom : " + name,
                "Téléphone : " + phone
            ];
            if (email) lines.push("Email : " + email);
            if (training) lines.push("Formation : " + training);
            if (msg) lines.push("Message : " + msg);

            var url = "https://wa.me/" + WHATSAPP_NUMBER + "?text=" + encodeURIComponent(lines.join("\n"));
            window.open(url, "_blank");
        });
    }

    /* ==================== POSTERS LIGHTBOX ==================== */

    function initPosters() {
        var imgEl = document.getElementById("poster-modal-img");
        var modalEl = document.getElementById("posterModal");
        if (!imgEl || !modalEl) return;
        document.querySelectorAll("[data-poster]").forEach(function (el) {
            el.addEventListener("click", function () {
                imgEl.src = this.getAttribute("data-poster");
                imgEl.alt = this.getAttribute("data-poster-alt") || "Affiche de formation PROCOPE Afrique";
                if (window.bootstrap) new window.bootstrap.Modal(modalEl).show();
            });
        });
    }

    function init() {
        initVideoStrip();
        initWhatsappForm();
        initPosters();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
