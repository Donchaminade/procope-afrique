/**
 * PROCOPE — Notification "nouvelle formation" sur le hero (index.html).
 * Interroge GET /api/formations/active. Si des inscriptions sont ouvertes,
 * injecte UNE pilule cliquable superposée au carrousel. 0 ouverte : rien.
 * 1 ouverte : badge statique. 2+ : rotation toutes les 5 s (titre + couleur),
 * avec crossfade (jamais de swap instantané, y compris prefers-reduced-motion).
 * Clic → candidature.html#former (id en query). Vanilla JS, aucune dépendance.
 */
(function () {
    'use strict';

    var API_BASE = window.PROCOPE_API_BASE || 'http://127.0.0.1:8088';
    var MAX_TITLE_LENGTH = 60;
    var ROTATE_MS = 5000;
    var FADE_MS = 450;
    var REDUCED_FADE_MS = 200;
    var TONE_COUNT = 6;

    function isDevHost() {
        var host = window.location && window.location.hostname;
        return host === 'localhost' || host === '127.0.0.1';
    }

    function devLog() {
        if (!isDevHost() || typeof console === 'undefined' || !console.info) { return; }
        var args = ['[PROCOPE hero]'];
        args.push.apply(args, arguments);
        console.info.apply(console, args);
    }

    function truncate(text, max) {
        text = String(text || '').trim();
        if (text.length <= max) { return text; }
        return text.slice(0, max - 1).replace(/\s+\S*$/, '') + '\u2026';
    }

    function prefersReducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function formationHref(item) {
        var id = item && item.id != null ? String(item.id) : '';
        return id
            ? 'candidature.html?formation=' + encodeURIComponent(id) + '#former'
            : 'candidature.html#former';
    }

    function normalizeItems(data) {
        var raw = [];
        if (data && Array.isArray(data.items) && data.items.length) {
            raw = data.items;
        } else if (data && Array.isArray(data.formations) && data.formations.length) {
            raw = data.formations;
        } else if (data && data.open && data.formation && data.formation.titre) {
            raw = [data.formation];
        }
        return raw.filter(function (item) {
            return item && String(item.titre || '').trim();
        });
    }

    function applyTone(notice, index) {
        var i;
        var tone = index % TONE_COUNT;
        for (i = 0; i < TONE_COUNT; i += 1) {
            notice.classList.toggle('hfn-tone-' + i, tone === i);
        }
        notice.setAttribute('data-hfn-tone', String(tone));
        notice.setAttribute('data-hfn-index', String(index));
    }

    function applyItem(notice, item, index) {
        var titre = String(item.titre || '').trim();
        var titleEl = notice.querySelector('.hfn-title');
        notice.href = formationHref(item);
        notice.setAttribute('aria-label',
            'Nouvelle formation, inscriptions ouvertes : ' + titre + '. Accéder à l\'espace candidature.');
        if (titleEl) {
            titleEl.textContent = truncate(titre, MAX_TITLE_LENGTH);
        }
        applyTone(notice, index);
    }

    function buildNotice(item, index) {
        var notice = document.createElement('a');
        notice.id = 'hero-formation-notice';
        notice.className = 'hero-formation-notice hfn-tone-' + (index % TONE_COUNT);

        var dot = document.createElement('span');
        dot.className = 'hfn-dot';
        dot.setAttribute('aria-hidden', 'true');

        var icon = document.createElement('i');
        icon.className = 'fa fa-bullhorn hfn-icon';
        icon.setAttribute('aria-hidden', 'true');

        var lead = document.createElement('span');
        lead.className = 'hfn-lead';
        lead.appendChild(document.createTextNode('Nouvelle formation'));

        var leadExtra = document.createElement('span');
        leadExtra.className = 'hfn-lead-extra';
        leadExtra.textContent = ' \u2014 Inscriptions ouvertes';
        lead.appendChild(leadExtra);

        var title = document.createElement('span');
        title.className = 'hfn-title';
        title.setAttribute('aria-live', 'polite');

        var chevron = document.createElement('i');
        chevron.className = 'fa fa-chevron-right hfn-chevron';
        chevron.setAttribute('aria-hidden', 'true');

        notice.appendChild(dot);
        notice.appendChild(icon);
        notice.appendChild(lead);
        notice.appendChild(title);
        notice.appendChild(chevron);
        applyItem(notice, item, index);
        return notice;
    }

    function startRotate(notice, items) {
        if (items.length < 2) { return; }
        if (notice.getAttribute('data-hfn-rotating') === '1') { return; }
        notice.setAttribute('data-hfn-rotating', '1');
        notice.setAttribute('data-hfn-count', String(items.length));

        var index = 0;
        var paused = false;
        var swapping = false;

        function fadeDuration() {
            return prefersReducedMotion() ? REDUCED_FADE_MS : FADE_MS;
        }

        function goTo(next) {
            if (swapping || next === index) { return; }
            swapping = true;
            var fadeMs = fadeDuration();
            notice.classList.add('hfn-fading');
            applyTone(notice, next);
            window.setTimeout(function () {
                index = next;
                applyItem(notice, items[index], index);
                void notice.offsetWidth;
                notice.classList.remove('hfn-fading');
                window.setTimeout(function () {
                    swapping = false;
                }, fadeMs);
            }, fadeMs);
        }

        window.setInterval(function () {
            if (paused || swapping) { return; }
            goTo((index + 1) % items.length);
        }, ROTATE_MS);

        notice.addEventListener('mouseenter', function () { paused = true; });
        notice.addEventListener('mouseleave', function () { paused = false; });
        notice.addEventListener('focusin', function () { paused = true; });
        notice.addEventListener('focusout', function () { paused = false; });
    }

    function insertNotice(items) {
        var carousel = document.getElementById('header-carousel');
        if (!carousel) { return; }

        var notice = document.getElementById('hero-formation-notice');
        if (!notice) {
            notice = buildNotice(items[0], 0);
            carousel.appendChild(notice);
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    notice.classList.add('hfn-visible');
                });
            });
        } else {
            applyItem(notice, items[0], 0);
            notice.classList.add('hfn-visible');
        }

        startRotate(notice, items);
    }

    function init() {
        fetch(API_BASE + '/api/formations/active')
            .then(function (response) {
                if (!response.ok) { throw new Error('http ' + response.status); }
                return response.json();
            })
            .then(function (data) {
                var items = normalizeItems(data);
                devLog('formations ouvertes:', items.length, items.map(function (item) {
                    return item.titre;
                }));
                if (items.length) {
                    insertNotice(items);
                }
            })
            .catch(function (err) {
                devLog('API injoignable, badge masqué', err && err.message ? err.message : err);
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
