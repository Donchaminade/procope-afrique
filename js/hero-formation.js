/**
 * PROCOPE — Notification "nouvelle formation" sur le hero (index.html).
 * Interroge l'API (GET /api/formations/active) et, si les inscriptions sont
 * ouvertes, injecte UNE pilule cliquable superposée au carrousel du hero,
 * au-dessus du titre des slides. Clic → candidature.html#former.
 * Aucune trace visuelle si l'API est fermée ou injoignable.
 * Vanilla JS, aucune dépendance.
 */
(function () {
    'use strict';

    // URL de l'API centralisée dans js/api-config.js (window.PROCOPE_API_BASE),
    // à inclure avant ce script ; fallback local si la config manque.
    var API_BASE = window.PROCOPE_API_BASE || 'http://127.0.0.1:8088';

    var MAX_TITLE_LENGTH = 60;

    function truncate(text, max) {
        text = String(text || '').trim();
        if (text.length <= max) { return text; }
        return text.slice(0, max - 1).replace(/\s+\S*$/, '') + '\u2026';
    }

    function buildNotice(titre) {
        var notice = document.createElement('a');
        notice.id = 'hero-formation-notice';
        notice.className = 'hero-formation-notice';
        notice.href = 'candidature.html#former';
        notice.setAttribute('aria-label',
            'Nouvelle formation, inscriptions ouvertes : ' + titre + '. Accéder à l\'espace candidature.');

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
        // Masqué sur mobile via CSS pour raccourcir la pilule.
        leadExtra.textContent = ' \u2014 Inscriptions ouvertes';
        lead.appendChild(leadExtra);

        var title = document.createElement('span');
        title.className = 'hfn-title';
        title.textContent = truncate(titre, MAX_TITLE_LENGTH);

        var chevron = document.createElement('i');
        chevron.className = 'fa fa-chevron-right hfn-chevron';
        chevron.setAttribute('aria-hidden', 'true');

        notice.appendChild(dot);
        notice.appendChild(icon);
        notice.appendChild(lead);
        notice.appendChild(title);
        notice.appendChild(chevron);
        return notice;
    }

    function insertNotice(titre) {
        var carousel = document.getElementById('header-carousel');
        if (!carousel || document.getElementById('hero-formation-notice')) { return; }

        var notice = buildNotice(titre);
        // Enfant direct du carrousel (position:relative), hors des items :
        // un seul élément superposé à toutes les slides, sous la navbar
        // (z-index 999) et au-dessus des captions (z-index 1).
        carousel.appendChild(notice);

        // Entrée douce (fade + slide down) au frame suivant.
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                notice.classList.add('hfn-visible');
            });
        });
    }

    function init() {
        fetch(API_BASE + '/api/formations/active')
            .then(function (response) {
                if (!response.ok) { throw new Error('http ' + response.status); }
                return response.json();
            })
            .then(function (data) {
                if (data && data.open && data.formation && data.formation.titre) {
                    insertNotice(data.formation.titre);
                }
            })
            .catch(function () { /* API fermée ou injoignable : rien à afficher */ });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
