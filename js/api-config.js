/**
 * PROCOPE — Configuration centralisée de l'URL de l'API back-office.
 *
 * Ce fichier est LE SEUL ENDROIT à modifier lors du déploiement de l'API.
 * Il doit être inclus AVANT tout script qui appelle l'API
 * (js/procope.js, js/inscription-formation.js, js/offres-emploi.js,
 *  js/hero-formation.js, js/candidature-formation.js).
 *
 * Les scripts consommateurs lisent window.PROCOPE_API_BASE (avec un
 * fallback local si ce fichier venait à manquer).
 */
(function () {
    'use strict';

    // ============================================================
    // >>> URL DE PRODUCTION DE L'API — À CHANGER lors du
    // >>> déploiement Hostinger (un seul endroit à éditer).
    // ============================================================
    var PROD_API_BASE = 'https://api.procopeafrique.com';

    var isLocal = location.hostname === 'localhost' || location.hostname === '127.0.0.1';

    window.PROCOPE_API_BASE = isLocal ? 'http://127.0.0.1:8088' : PROD_API_BASE;
})();
