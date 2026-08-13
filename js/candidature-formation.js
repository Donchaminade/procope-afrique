/**
 * PROCOPE — Espace candidature, panneau « Se former » (candidature.html)
 * Charge la formation en cours depuis l'API et affiche une carte d'inscription,
 * ou un message sobre si aucune session n'est ouverte (ou API injoignable).
 */
(function () {
    'use strict';

    // URL de l'API centralisée dans js/api-config.js (window.PROCOPE_API_BASE),
    // à inclure avant ce script ; fallback local si la config manque.
    var API_BASE = window.PROCOPE_API_BASE || 'http://127.0.0.1:8088';

    var container = document.getElementById('cf-formation');
    if (!container) { return; }

    function esc(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function formatHour(datetime) {
        var d = new Date(datetime.replace(' ', 'T'));
        if (isNaN(d)) { return ''; }
        return String(d.getHours()).padStart(2, '0') + 'h' + String(d.getMinutes()).padStart(2, '0');
    }

    function formatPrice(amount, devise) {
        return Number(amount).toLocaleString('fr-FR') + ' ' + (devise === 'XOF' || !devise ? 'F CFA' : devise);
    }

    function renderClosed() {
        container.innerHTML =
            '<div class="bg-light rounded p-4 p-md-5 h-100 text-center d-flex flex-column justify-content-center">' +
            '<i class="fa fa-calendar-times text-primary mb-3" style="font-size: 42px;" aria-hidden="true"></i>' +
            '<h4 class="mb-3">Aucune formation en cours pour le moment</h4>' +
            '<p class="text-muted mb-4">Suivez nos actualit\u00e9s ou contactez-nous pour \u00eatre inform\u00e9 de la prochaine session.</p>' +
            '<div>' +
            '<a href="actualites.html" class="btn btn-primary py-2 px-4 me-2 mb-2">Voir nos actualit\u00e9s</a>' +
            '<a href="contact.html" class="btn btn-outline-primary py-2 px-4 mb-2">Nous contacter</a>' +
            '</div></div>';
    }

    function renderOpen(data) {
        var f = data.formation;
        var slots = (f.slots || []).map(function (slot) {
            return '<li class="mb-2"><i class="fa fa-clock text-primary me-2" aria-hidden="true"></i>' +
                '<strong>' + esc(slot.label) + '</strong>' +
                ' <span class="text-muted">(de ' + formatHour(slot.starts_at) + ' \u00e0 ' + formatHour(slot.ends_at) + ')</span></li>';
        }).join('');

        var affiche = f.affiche_url
            ? '<img src="' + esc(f.affiche_url) + '" alt="' + esc(f.titre) + '" loading="lazy" class="img-fluid rounded shadow w-100 mb-4">'
            : '';

        container.innerHTML =
            '<div class="bg-light rounded p-4 p-md-5 h-100">' +
            affiche +
            '<h5 class="fw-bold text-primary text-uppercase mb-2">Session en cours</h5>' +
            '<h3 class="mb-3">' + esc(f.titre) + '</h3>' +
            (slots ? '<ul class="list-unstyled mb-3">' + slots + '</ul>' : '') +
            '<p class="mb-2"><i class="fa fa-money-bill-wave text-primary me-2" aria-hidden="true"></i>' +
            '<strong>Frais de participation :</strong> ' + esc(formatPrice(f.prix, f.devise)) + '</p>' +
            (f.lieu ? '<p class="mb-4"><i class="fa fa-map-marker-alt text-primary me-2" aria-hidden="true"></i>' +
                '<strong>Lieu :</strong> ' + esc(f.lieu) + '</p>' : '') +
            '<a href="inscription-formation.html" class="btn btn-primary btn-lg w-100 py-3">' +
            '<i class="fa fa-calendar-check me-2" aria-hidden="true"></i>S\u2019inscrire maintenant</a>' +
            '</div>';
    }

    function init() {
        fetch(API_BASE + '/api/formations/active')
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data && data.open) {
                    renderOpen(data);
                } else {
                    renderClosed();
                }
            })
            .catch(renderClosed);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
