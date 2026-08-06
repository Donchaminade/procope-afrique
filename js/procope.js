/*
 * PROCOPE Afrique — interactive features (no backend required):
 *   - TikTok video-testimonials strip: step-by-step carousel (next card),
 *     pause on hover, drag / swipe with snap, click to play in a pop-up,
 *     first-visit "swipe" tutorial, and a "see more on TikTok" link.
 *   - Registration form that opens a pre-filled WhatsApp message.
 *   - Homepage contact form that opens a pre-filled WhatsApp message.
 *   - Candidature form that opens a pre-filled WhatsApp message
 *     (attachments must be sent manually in the chat — wa.me cannot attach files).
 *   - Candidature page dual panels (formation / incubation) with slide toggle.
 *   - Previous-training posters lightbox.
 *
 * ---> EDIT THIS CONFIG with your real values. <---
 */
(function () {
    "use strict";

    /* =========================== CONFIG =========================== */

    // WhatsApp number in international format, digits only (no +, no spaces).
    // Togo +228 96 45 76 95  ->  22896457695
    var WHATSAPP_NUMBER = "22896457695";

    // Public TikTok profile (the "Voir plus sur TikTok" button).
    var TIKTOK_PROFILE = "https://www.tiktok.com/@procope.afrique";

    // Auto-advance delay between cards (ms).
    var VT_STEP_MS = 3800;

    // Video testimonials. Paste the real TikTok video links in `url`.
    // The video ID is auto-extracted from a standard link:
    //   https://www.tiktok.com/@user/video/1234567890123456789
    // `thumb` = local cover (downloaded from TikTok oEmbed).
    // `name` / `role` = light labels (handle + short title from oEmbed) — not invented person names.
    var VIDEO_TESTIMONIALS = [
        {
            name: "@mathieukunyabor",
            role: "Entrepreneuriat étudiant",
            thumb: "img/vt-thumb-1.jpg",
            url: "https://www.tiktok.com/@mathieukunyabor/video/7646324253251374343"
        },
        {
            name: "@mathieukunyabor",
            role: "Feedbacks des participants",
            thumb: "img/vt-thumb-2.jpg",
            url: "https://www.tiktok.com/@mathieukunyabor/video/7609057601724124423"
        },
        {
            name: "@mathieukunyabor",
            role: "Récapitulatif de formation",
            thumb: "img/vt-thumb-3.jpg",
            url: "https://www.tiktok.com/@mathieukunyabor/video/7611656575941414162"
        },
        {
            name: "@mathieukunyabor",
            role: "Impact après formation",
            thumb: "img/vt-thumb-4.jpg",
            url: "https://www.tiktok.com/@mathieukunyabor/video/7618655760100134165"
        }
    ];

    /* ============================ HELPERS ============================ */

    function tiktokVideoId(url) {
        var m = /\/video\/(\d+)/.exec(url || "");
        return m ? m[1] : null;
    }

    // A real TikTok video id is a long number; the demo placeholders are 000...00X.
    function isRealTikTokId(id) {
        return !!id && id.length >= 16 && !/^0{8,}/.test(id);
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

        var n = VIDEO_TESTIMONIALS.length;
        if (!n) return;

        var cards = VIDEO_TESTIMONIALS.map(function (v, i) {
            var label = v.name || "Témoignage";
            var meta = "";
            if (v.name || v.role) {
                meta = '<div class="vt-meta">' +
                    (v.name ? "<h6>" + esc(v.name) + "</h6>" : "") +
                    (v.role ? "<small>" + esc(v.role) + "</small>" : "") +
                    "</div>";
            }
            return (
                '<div class="vt-card" data-url="' + esc(v.url) + '" data-thumb="' + esc(v.thumb) + '" role="button" tabindex="0" ' +
                'aria-label="Lire le témoignage vidéo ' + (i + 1) + " — " + esc(label) + '">' +
                '<img src="' + esc(v.thumb) + '" alt="Aperçu vidéo TikTok — ' + esc(label) + '" loading="lazy">' +
                '<span class="vt-tiktok-badge">' + TIKTOK_SVG + "</span>" +
                '<div class="vt-overlay"><span class="vt-play"><i class="fa fa-play"></i></span></div>' +
                meta +
                "</div>"
            );
        }).join("");

        // Duplicate the set so the step carousel can loop without a hard jump.
        scroller.innerHTML = cards + cards;
        scroller.classList.add("vt-step-carousel");

        var state = { hover: false, drag: false, modal: false, touchPause: false };
        var isDown = false, dragMoved = false, startX = 0, startScroll = 0, touchTimer = null;
        var index = 0;
        var stepTimer = null;
        var looping = false;

        function paused() { return state.hover || state.drag || state.modal || state.touchPause || looping; }

        function cardStep() {
            var card = scroller.querySelector(".vt-card");
            if (!card) return 0;
            var gap = parseFloat(getComputedStyle(scroller).columnGap || getComputedStyle(scroller).gap) || 20;
            return card.offsetWidth + gap;
        }

        function scrollToIndex(i, smooth) {
            var step = cardStep();
            if (!step) return;
            scroller.style.scrollBehavior = smooth ? "smooth" : "auto";
            scroller.scrollLeft = Math.round(i * step);
        }

        function nearestIndex() {
            var step = cardStep();
            if (!step) return 0;
            return Math.round(scroller.scrollLeft / step);
        }

        function snapToNearest(smooth) {
            index = nearestIndex();
            // Keep index in the first copy when possible (seamless loop).
            if (index >= n) {
                index = index % n;
                scrollToIndex(index + n, false);
                // Force layout, then settle on the first-copy index without animation.
                void scroller.scrollLeft;
                scrollToIndex(index, false);
                return;
            }
            scrollToIndex(index, smooth !== false);
        }

        function advance() {
            if (paused()) return;
            var step = cardStep();
            if (!step || scroller.scrollWidth <= scroller.clientWidth) return;

            index = nearestIndex();
            index += 1;
            scrollToIndex(index, true);

            // After entering the duplicate set, jump back to the matching card in the first set.
            if (index >= n) {
                looping = true;
                window.setTimeout(function () {
                    index = index - n;
                    scrollToIndex(index, false);
                    looping = false;
                }, 450);
            }
        }

        function startAuto() {
            if (stepTimer) clearInterval(stepTimer);
            stepTimer = setInterval(advance, VT_STEP_MS);
        }
        startAuto();

        scroller.addEventListener("mouseenter", function () { state.hover = true; });
        scroller.addEventListener("mouseleave", function () { state.hover = false; });

        scroller.addEventListener("pointerdown", function (e) {
            isDown = true; dragMoved = false; startX = e.clientX; startScroll = scroller.scrollLeft;
            if (e.pointerType === "mouse") {
                state.drag = true;
                scroller.style.scrollBehavior = "auto";
            } else {
                state.touchPause = true;
                if (touchTimer) clearTimeout(touchTimer);
            }
        });

        window.addEventListener("pointermove", function (e) {
            if (!isDown) return;
            var dx = e.clientX - startX;
            if (Math.abs(dx) > 6 && !dragMoved) {
                dragMoved = true;
                if (e.pointerType === "mouse") scroller.classList.add("is-dragging");
            }
            if (dragMoved && e.pointerType === "mouse") {
                scroller.scrollLeft = startScroll - dx;
                e.preventDefault();
            }
        });

        function endPointer(e) {
            if (!isDown) return;
            isDown = false;
            state.drag = false;
            scroller.classList.remove("is-dragging");
            if (dragMoved) {
                // Snap to the nearest card after a drag / swipe.
                snapToNearest(true);
            }
            if (e && e.pointerType !== "mouse") {
                touchTimer = setTimeout(function () { state.touchPause = false; }, 2500);
            }
        }
        window.addEventListener("pointerup", endPointer);
        window.addEventListener("pointercancel", endPointer);

        // Keep index in sync if the user scrolls with trackpad / touch native scroll.
        var scrollSyncTimer = null;
        scroller.addEventListener("scroll", function () {
            if (isDown || looping) return;
            if (scrollSyncTimer) clearTimeout(scrollSyncTimer);
            scrollSyncTimer = setTimeout(function () {
                index = nearestIndex() % n;
            }, 80);
        });

        window.addEventListener("resize", function () {
            scrollToIndex(index % n, false);
        });

        function openCard(card) {
            var url = card.getAttribute("data-url");
            var thumb = card.getAttribute("data-thumb");
            var id = tiktokVideoId(url);
            var frame = document.getElementById("vt-frame");
            var fallback = document.getElementById("vt-fallback");
            var openLink = document.getElementById("vt-open-tiktok");
            if (openLink) openLink.href = url || TIKTOK_PROFILE;
            if (isRealTikTokId(id) && frame) {
                var embed = "https://www.tiktok.com/player/v1/" + id + "?autoplay=1&loop=1&controls=1";
                frame.innerHTML = '<iframe src="' + embed + '" allow="autoplay; encrypted-media; fullscreen" allowfullscreen></iframe>';
                if (fallback) fallback.style.display = "none";
            } else if (frame) {
                // No real TikTok link yet: show the preview thumbnail + a hint instead of a broken embed.
                frame.innerHTML = '<img src="' + esc(thumb) + '" alt="" style="width:100%;height:100%;object-fit:cover;">';
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
                setTimeout(dismiss, 8000);
            }
        }
    }

    /* ==================== WHATSAPP HELPERS ==================== */

    function openWhatsapp(text) {
        var url = "https://wa.me/" + WHATSAPP_NUMBER + "?text=" + encodeURIComponent(text);
        window.open(url, "_blank");
    }

    function fieldValue(id) {
        var el = document.getElementById(id);
        return el ? el.value.trim() : "";
    }

    function fileLabel(id, label) {
        var el = document.getElementById(id);
        if (!el || !el.files || !el.files.length) return null;
        return label + " (" + el.files[0].name + ")";
    }

    /* ==================== WHATSAPP REGISTRATION FORM ==================== */

    function initWhatsappForm() {
        var form = document.getElementById("training-form");
        if (!form) return;
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            var name = fieldValue("tf-name"), phone = fieldValue("tf-phone"),
                email = fieldValue("tf-email"), training = fieldValue("tf-training"),
                msg = fieldValue("tf-message");

            var lines = [
                "Bonjour PROCOPE Afrique, je souhaite m'inscrire à la prochaine formation.",
                "",
                "Nom : " + name,
                "Téléphone : " + phone
            ];
            if (email) lines.push("Email : " + email);
            if (training) lines.push("Formation : " + training);
            if (msg) lines.push("Message : " + msg);

            openWhatsapp(lines.join("\n"));
            form.reset();
        });
    }

    /* ==================== WHATSAPP CONTACT FORM (homepage) ==================== */

    function initContactForm() {
        var form = document.getElementById("contact-form");
        if (!form) return;
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            var name = fieldValue("cf-name");
            var email = fieldValue("cf-email");
            var service = fieldValue("cf-service");
            var msg = fieldValue("cf-message");

            var lines = [
                "Bonjour PROCOPE Afrique, je souhaite vous contacter.",
                "",
                "Nom : " + name,
                "Email : " + email
            ];
            if (service) lines.push("Service : " + service);
            if (msg) lines.push("Message : " + msg);

            openWhatsapp(lines.join("\n"));
            form.reset();
        });
    }

    /* ==================== WHATSAPP CANDIDATURE FORM ==================== */

    function initCandidatureForm() {
        var form = document.getElementById("candidature-form");
        if (!form) return;
        form.addEventListener("submit", function (e) {
            e.preventDefault();

            var name = fieldValue("fullName");
            var email = fieldValue("email");
            var project = fieldValue("projectName");
            var description = fieldValue("projectDescription");

            var docs = [
                fileLabel("cv", "CV"),
                fileLabel("businessPlan", "Business Plan"),
                fileLabel("pitchDeck", "Pitch Deck")
            ].filter(Boolean);

            var lines = [
                "Bonjour PROCOPE Afrique, je souhaite déposer mon projet.",
                "",
                "Nom : " + name,
                "Email : " + email,
                "Projet : " + project,
                "Description : " + description,
                ""
            ];

            if (docs.length) {
                lines.push("Documents à envoyer ensuite :");
                docs.forEach(function (d) { lines.push("- " + d); });
                lines.push("");
                lines.push("(Je joindrai ces fichiers manuellement dans cette conversation.)");
            } else {
                lines.push("Documents à envoyer ensuite : CV / Business Plan / Pitch Deck");
                lines.push("(Je joindrai les pièces dans cette conversation WhatsApp.)");
            }

            if (docs.length) {
                window.alert(
                    "WhatsApp va s'ouvrir avec votre candidature préremplie.\n\n" +
                    "Les fichiers ne peuvent pas être joints automatiquement : " +
                    "pensez à les envoyer ensuite dans la conversation WhatsApp."
                );
            }

            openWhatsapp(lines.join("\n"));
            form.reset();
        });
    }

    /* ==================== PARTENAIRE FORM (WhatsApp) ==================== */

    function initPartenaireForm() {
        var form = document.getElementById("partenaire-form");
        if (!form) return;
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            var name = fieldValue("pf-name");
            var email = fieldValue("pf-email");
            var type = fieldValue("pf-type");
            var msg = fieldValue("pf-message");
            var lines = [
                "Bonjour PROCOPE Afrique, je souhaite devenir partenaire.",
                "",
                "Nom / Organisation : " + name,
                "Email : " + email
            ];
            if (type) lines.push("Type : " + type);
            if (msg) lines.push("Message : " + msg);
            openWhatsapp(lines.join("\n"));
            form.reset();
        });
    }

    /* ==================== CANDIDATURE PANEL TOGGLE (4 onglets + hash) ==================== */

    function initCandidaturePanels() {
        var slider = document.getElementById("cand-slider");
        var tabs = document.querySelectorAll("[data-cand-panel]");
        if (!slider || !tabs.length) return;

        var keys = ["former", "projet", "emploi", "partenaire"];
        // Legacy aliases from older links
        var aliases = {
            formation: "former",
            incubation: "projet",
            partner: "partenaire",
            partenaires: "partenaire",
            job: "emploi",
            jobs: "emploi"
        };

        var panels = {};
        keys.forEach(function (key) {
            panels[key] = document.getElementById("cand-panel-" + key);
        });

        function setPanel(panelKey, updateHash) {
            panelKey = aliases[panelKey] || panelKey;
            if (!panels[panelKey]) return;
            slider.setAttribute("data-panel", panelKey);

            tabs.forEach(function (btn) {
                var active = btn.getAttribute("data-cand-panel") === panelKey;
                btn.classList.toggle("is-active", active);
                btn.setAttribute("aria-selected", active ? "true" : "false");
                btn.setAttribute("tabindex", active ? "0" : "-1");
            });

            keys.forEach(function (key) {
                var panel = panels[key];
                if (!panel) return;
                var active = key === panelKey;
                panel.classList.toggle("is-active", active);
                panel.setAttribute("aria-hidden", active ? "false" : "true");
                if (active) {
                    panel.removeAttribute("hidden");
                    panel.removeAttribute("inert");
                } else {
                    panel.setAttribute("hidden", "");
                    panel.setAttribute("inert", "");
                }
            });

            if (updateHash !== false) {
                try {
                    if (history.replaceState) {
                        history.replaceState(null, "", "#" + panelKey);
                    } else {
                        window.location.hash = panelKey;
                    }
                } catch (err) { /* ignore */ }
            }
        }

        function panelFromHash() {
            var h = (window.location.hash || "").replace(/^#/, "").toLowerCase();
            if (!h) return null;
            return aliases[h] || h;
        }

        tabs.forEach(function (btn) {
            btn.addEventListener("click", function () {
                setPanel(btn.getAttribute("data-cand-panel"), true);
            });
            btn.addEventListener("keydown", function (e) {
                if (e.key !== "ArrowLeft" && e.key !== "ArrowRight") return;
                e.preventDefault();
                var cur = slider.getAttribute("data-panel") || "former";
                var idx = keys.indexOf(cur);
                if (idx < 0) idx = 0;
                if (e.key === "ArrowRight") idx = (idx + 1) % keys.length;
                else idx = (idx - 1 + keys.length) % keys.length;
                setPanel(keys[idx], true);
                var next = document.querySelector('[data-cand-panel="' + keys[idx] + '"]');
                if (next) next.focus();
            });
        });

        window.addEventListener("hashchange", function () {
            var fromHash = panelFromHash();
            if (fromHash && panels[fromHash]) setPanel(fromHash, false);
        });

        var initial = panelFromHash() || slider.getAttribute("data-panel") || "former";
        setPanel(initial, false);
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
        initContactForm();
        initCandidatureForm();
        initPartenaireForm();
        initCandidaturePanels();
        initPosters();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
