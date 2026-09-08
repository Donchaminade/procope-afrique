/**
 * PROCOPE — Configuration centralisée de l'URL de l'API back-office.
 *
 * Ce fichier est LE SEUL ENDROIT à modifier lors du déploiement de l'API.
 * Il doit être inclus AVANT tout script qui appelle l'API
 * (js/procope.js, js/inscription-formation.js, js/offres-emploi.js,
 *  js/projets-incubes.js, js/hero-formation.js, js/candidature-formation.js,
 *  js/temoignages.js, js/galeries.js).
 *
 * Les scripts consommateurs lisent window.PROCOPE_API_BASE (avec un
 * fallback local si ce fichier venait à manquer).
 */
(function () {
    'use strict';

    // ============================================================
    // URL DE PRODUCTION DE L'API PHP (Hostinger).
    // Sous-domaine prévu : https://api.procopeafrique.org
    // Tant que Hostinger n'est pas déployé, les appels depuis
    // procopeafrique.org / www.procopeafrique.org / *.vercel.app
    // échoueront (formations, offres, inscriptions, dépôts…).
    // C'est ATTENDU. Jour J : confirmer cette URL ou coller
    // l'URL Hostinger exacte ici — un seul endroit à éditer.
    // Ne plus utiliser l'ancien placeholder api.procopeafrique.com.
    // ============================================================
    var PROD_API_BASE = 'https://api.procopeafrique.org';
    var LOCAL_API_BASE = 'http://127.0.0.1:8088';

    var host = location.hostname || '';
    var isLocal = host === 'localhost' || host === '127.0.0.1';
    // Fronts de production reconnus : même constante PROD_API_BASE.
    var isProdFront = host === 'procopeafrique.org'
        || host === 'www.procopeafrique.org'
        || /\.vercel\.app$/.test(host);

    if (isLocal) {
        window.PROCOPE_API_BASE = LOCAL_API_BASE;
    } else if (isProdFront) {
        window.PROCOPE_API_BASE = PROD_API_BASE;
    } else {
        // Autres hôtes déployés (préviews, etc.) : même API unique.
        window.PROCOPE_API_BASE = PROD_API_BASE;
    }
})();
