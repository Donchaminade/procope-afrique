/**
 * PROCOPE — Offres d'emploi.
 * 1. Page offres-emploi.html : liste les offres publiées depuis procope-api,
 *    affiche le détail d'une offre (#slug ou ?offre=slug) avec galerie
 *    d'affiches et soumet la candidature en multipart.
 * 2. Section #emploi de candidature.html : aperçu compact (max 5 offres)
 *    avec lien vers la page complète.
 * Aucune donnée sensible côté front : tout est validé côté serveur.
 */
(function () {
    'use strict';

    // URL de l'API centralisée dans js/api-config.js (window.PROCOPE_API_BASE),
    // à inclure avant ce script ; fallback local si la config manque.
    var API_BASE = window.PROCOPE_API_BASE || 'http://127.0.0.1:8088';

    /* ==================== HELPERS PARTAGÉS ==================== */

    function esc(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    /** "2026-08-28 18:00:00" -> "28/08/2026 à 18h00" */
    function formatDate(datetime) {
        var d = new Date(String(datetime).replace(' ', 'T'));
        if (isNaN(d)) { return String(datetime); }
        var pad = function (n) { return String(n).padStart(2, '0'); };
        return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear() +
            ' à ' + pad(d.getHours()) + 'h' + pad(d.getMinutes());
    }

    function daysLeftLabel(offer) {
        var days = Number(offer.days_left);
        if (!offer.open) { return 'Clôturée'; }
        if (days <= 1) { return 'Dernier jour'; }
        return 'Dans ' + days + ' jours';
    }

    /**
     * Carte d'une offre (liste complète et aperçu candidature).
     * options : { href: lien du bouton, colClass: classes de colonne, compact: bool }
     */
    function offerCardHtml(offer, options) {
        var href = options.href;
        var imageHtml = offer.image
            ? '<div class="ratio ratio-16x9">' +
              '<img src="' + esc(offer.image) + '" alt="Affiche — ' + esc(offer.title) + '"' +
              ' class="w-100 h-100" style="object-fit:cover;" loading="lazy"></div>'
            : '';

        return '<div class="' + options.colClass + '">' +
            '<div class="bg-light rounded h-100 d-flex flex-column overflow-hidden">' +
            imageHtml +
            '<div class="p-4 d-flex flex-column flex-grow-1">' +
            '<div class="d-flex align-items-center justify-content-between mb-3">' +
            '<span class="badge bg-primary py-2 px-3">' + esc(offer.contract_type) + '</span>' +
            '<small class="text-muted"><i class="fa fa-hourglass-half me-1" aria-hidden="true"></i>' + esc(daysLeftLabel(offer)) + '</small>' +
            '</div>' +
            '<h4 class="mb-3' + (options.compact ? ' h5' : '') + '">' + esc(offer.title) + '</h4>' +
            '<p class="mb-2"><i class="fa fa-map-marker-alt text-primary me-2" aria-hidden="true"></i>' + esc(offer.location || 'Lomé, Togo') + '</p>' +
            '<p class="mb-2 small text-muted"><i class="fa fa-calendar-times text-primary me-2" aria-hidden="true"></i>Clôture : ' + esc(formatDate(offer.closes_at)) + '</p>' +
            (!options.compact && offer.excerpt
                ? '<p class="mb-4 flex-grow-1">' + esc(offer.excerpt) + '</p>'
                : '<div class="flex-grow-1"></div>') +
            '<a class="btn btn-primary py-2 px-4 mt-auto align-self-start" href="' + href + '">' +
            'Voir les détails<i class="fa fa-arrow-right ms-2" aria-hidden="true"></i></a>' +
            '</div></div></div>';
    }

    /* ==================== PAGE OFFRES-EMPLOI.HTML ==================== */

    function initOffresPage() {
        var els = {
            loading: document.getElementById('oe-loading'),
            error: document.getElementById('oe-error'),
            listView: document.getElementById('oe-list-view'),
            list: document.getElementById('oe-list'),
            empty: document.getElementById('oe-empty'),
            detailView: document.getElementById('oe-detail-view'),
            detail: document.getElementById('oe-detail'),
            notfound: document.getElementById('oe-notfound'),
            applyCta: document.getElementById('oe-apply-cta'),
            applyToggle: document.getElementById('oe-apply-toggle'),
            applyWrap: document.getElementById('oe-apply-wrap'),
            applySuccess: document.getElementById('oe-apply-success'),
            applySuccessMessage: document.getElementById('oe-apply-success-message'),
            closed: document.getElementById('oe-closed'),
            form: document.getElementById('oe-apply-form'),
            formError: document.getElementById('oe-form-error'),
            submit: document.getElementById('oe-submit')
        };

        if (!els.listView) { return; }

        var currentSlug = '';
        var defaultTitle = document.title;

        function show(state) {
            ['loading', 'error', 'listView', 'detailView'].forEach(function (key) {
                els[key].classList.toggle('d-none', key !== state);
            });
        }

        /** Slug demandé : #slug prioritaire, sinon ?offre=slug. */
        function requestedSlug() {
            var hash = decodeURIComponent(location.hash.replace(/^#/, ''));
            if (hash) { return hash; }
            var params = new URLSearchParams(location.search);
            return (params.get('offre') || '').trim();
        }

        /* ---- Liste des offres ---- */

        function renderList(offers) {
            document.title = defaultTitle;
            if (!offers.length) {
                els.list.innerHTML = '';
                els.empty.classList.remove('d-none');
                show('listView');
                return;
            }
            els.empty.classList.add('d-none');

            els.list.innerHTML = offers.map(function (offer) {
                return offerCardHtml(offer, {
                    href: '#' + encodeURIComponent(offer.slug),
                    colClass: 'col-lg-4 col-md-6',
                    compact: false
                });
            }).join('');

            show('listView');
        }

        function loadList() {
            show('loading');
            fetch(API_BASE + '/api/offres')
                .then(function (response) { return response.json(); })
                .then(function (data) { renderList(data.offers || []); })
                .catch(function () { show('error'); });
        }

        /* ---- Galerie d'affiches (détail) ---- */

        function renderGallery(offer) {
            var gallery = document.getElementById('oe-gallery');
            var main = document.getElementById('oe-gallery-main');
            var thumbs = document.getElementById('oe-gallery-thumbs');
            if (!gallery || !main || !thumbs) { return; }

            var images = offer.images || [];
            if (!images.length) {
                gallery.classList.add('d-none');
                main.removeAttribute('src');
                thumbs.innerHTML = '';
                return;
            }

            main.src = images[0];
            main.alt = 'Affiche — ' + offer.title;
            thumbs.innerHTML = '';

            if (images.length > 1) {
                images.forEach(function (url, index) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn p-0 border rounded overflow-hidden oe-thumb' + (index === 0 ? ' border-primary' : '');
                    btn.setAttribute('aria-label', 'Afficher l\'affiche ' + (index + 1));
                    btn.style.width = '84px';
                    btn.style.height = '63px';
                    var img = document.createElement('img');
                    img.src = url;
                    img.alt = '';
                    img.loading = 'lazy';
                    img.style.width = '100%';
                    img.style.height = '100%';
                    img.style.objectFit = 'cover';
                    btn.appendChild(img);
                    btn.addEventListener('click', function () {
                        main.src = url;
                        thumbs.querySelectorAll('.oe-thumb').forEach(function (t) {
                            t.classList.toggle('border-primary', t === btn);
                        });
                    });
                    thumbs.appendChild(btn);
                });
            }

            gallery.classList.remove('d-none');
        }

        /* ---- Détail d'une offre ---- */

        function renderDetail(offer) {
            document.title = offer.title + ' | Offres d\'emploi PROCOPE Afrique';

            document.getElementById('oe-title').textContent = offer.title;
            document.getElementById('oe-contract').textContent = offer.contract_type;
            document.getElementById('oe-location').textContent = offer.location || 'Lomé, Togo';
            document.getElementById('oe-salary').textContent = offer.salary || 'À discuter';
            document.getElementById('oe-closes').textContent = formatDate(offer.closes_at);

            document.getElementById('oe-badges').innerHTML =
                '<span class="badge bg-primary py-2 px-3 me-2">' + esc(offer.contract_type) + '</span>' +
                (offer.open
                    ? '<span class="badge bg-success py-2 px-3"><i class="fa fa-hourglass-half me-1" aria-hidden="true"></i>' + esc(daysLeftLabel(offer)) + '</span>'
                    : '<span class="badge bg-secondary py-2 px-3">Clôturée</span>');

            renderGallery(offer);

            // Description complète : un <p> par paragraphe (texte échappé)
            var description = String(offer.description || '').trim();
            document.getElementById('oe-description').innerHTML = description
                ? description.split(/\n{2,}/).map(function (paragraph) {
                    return '<p>' + esc(paragraph).replace(/\n/g, '<br>') + '</p>';
                }).join('')
                : '<p class="text-muted">Contactez-nous pour plus de détails sur ce poste.</p>';

            // Boutons Partager
            var shareUrl = location.origin + location.pathname + '#' + encodeURIComponent(offer.slug);
            var shareText = 'Offre d\'emploi PROCOPE Afrique — ' + offer.title;
            document.getElementById('oe-share-whatsapp').href =
                'https://wa.me/?text=' + encodeURIComponent(shareText + '\n' + shareUrl);
            document.getElementById('oe-share-facebook').href =
                'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl);
            document.getElementById('oe-share-linkedin').href =
                'https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(shareUrl);
            document.getElementById('oe-share-twitter').href =
                'https://twitter.com/intent/tweet?text=' + encodeURIComponent(shareText) + '&url=' + encodeURIComponent(shareUrl);

            // Ouverte : bouton Postuler ; clôturée : encart dédié sans formulaire
            els.closed.classList.toggle('d-none', !!offer.open);
            els.applyCta.classList.toggle('d-none', !offer.open);
            els.applyWrap.classList.add('d-none');
            els.applySuccess.classList.add('d-none');
            els.formError.classList.add('d-none');
            els.form.reset();

            els.notfound.classList.add('d-none');
            els.detail.classList.remove('d-none');
            show('detailView');
        }

        function loadDetail(slug) {
            show('loading');
            fetch(API_BASE + '/api/offres/' + encodeURIComponent(slug))
                .then(function (response) {
                    return response.json().then(function (json) { return { status: response.status, json: json }; });
                })
                .then(function (result) {
                    if (result.status === 404 || !result.json.offer) {
                        document.title = defaultTitle;
                        els.detail.classList.add('d-none');
                        els.notfound.classList.remove('d-none');
                        show('detailView');
                        return;
                    }
                    renderDetail(result.json.offer);
                })
                .catch(function () { show('error'); });
        }

        /* ---- Candidature ---- */

        function showFormError(message) {
            els.formError.textContent = message;
            els.formError.classList.remove('d-none');
            els.formError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function submitApplication(event) {
            event.preventDefault();
            els.formError.classList.add('d-none');
            if (!els.form.reportValidity()) { return; }

            var formData = new FormData(els.form);
            els.submit.disabled = true;
            els.submit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Envoi en cours…';

            fetch(API_BASE + '/api/offres/' + encodeURIComponent(currentSlug) + '/postuler', { method: 'POST', body: formData })
                .then(function (response) {
                    return response.json().then(function (json) { return { status: response.status, json: json }; });
                })
                .then(function (result) {
                    if (result.json.ok) {
                        els.applySuccessMessage.textContent = result.json.message ||
                            'Votre candidature a bien été enregistrée.';
                        els.applyWrap.classList.add('d-none');
                        els.applyCta.classList.add('d-none');
                        els.applySuccess.classList.remove('d-none');
                        els.applySuccess.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return;
                    }
                    var message = result.json.error || 'Une erreur est survenue. Réessayez.';
                    if (result.json.fields) {
                        message += ' ' + Object.keys(result.json.fields).map(function (key) {
                            return result.json.fields[key];
                        }).join(' ');
                    }
                    showFormError(message);
                })
                .catch(function () {
                    showFormError('Connexion impossible au serveur. Vérifiez votre connexion et réessayez.');
                })
                .finally(function () {
                    els.submit.disabled = false;
                    els.submit.innerHTML = '<i class="fa fa-paper-plane me-2" aria-hidden="true"></i>Envoyer ma candidature';
                });
        }

        /* ---- Routage liste <-> détail ---- */

        function route() {
            var slug = requestedSlug();
            currentSlug = slug;
            if (slug) {
                loadDetail(slug);
            } else {
                loadList();
            }
        }

        function copyShareLink() {
            var url = location.origin + location.pathname + '#' + encodeURIComponent(currentSlug);
            var label = document.getElementById('oe-share-copy-label');
            var done = function () {
                label.textContent = 'Lien copié !';
                setTimeout(function () { label.textContent = 'Copier le lien'; }, 2500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(done).catch(function () { window.prompt('Copiez le lien :', url); });
            } else {
                window.prompt('Copiez le lien :', url);
            }
        }

        els.form.addEventListener('submit', submitApplication);
        els.applyToggle.addEventListener('click', function () {
            els.applyWrap.classList.toggle('d-none');
            if (!els.applyWrap.classList.contains('d-none')) {
                document.getElementById('oe-full-name').focus();
            }
        });
        document.getElementById('oe-share-copy').addEventListener('click', copyShareLink);
        // Le lien retour vide le hash sans recharger la page
        document.getElementById('oe-back').addEventListener('click', function (event) {
            event.preventDefault();
            if (location.hash) {
                history.pushState('', document.title, location.pathname);
            }
            route();
        });
        window.addEventListener('hashchange', route);
        route();
    }

    /* ==================== SECTION #EMPLOI DE CANDIDATURE.HTML ==================== */

    var CANDIDATURE_MAX_OFFERS = 5;

    function initCandidatureSection() {
        var list = document.getElementById('cand-offres-list');
        if (!list) { return; }

        var loading = document.getElementById('cand-offres-loading');
        var empty = document.getElementById('cand-offres-empty');
        var error = document.getElementById('cand-offres-error');

        function done() {
            if (loading) { loading.classList.add('d-none'); }
        }

        fetch(API_BASE + '/api/offres')
            .then(function (response) { return response.json(); })
            .then(function (data) {
                done();
                var offers = (data.offers || []).slice(0, CANDIDATURE_MAX_OFFERS);
                if (!offers.length) {
                    if (empty) { empty.classList.remove('d-none'); }
                    return;
                }
                list.innerHTML = offers.map(function (offer) {
                    return offerCardHtml(offer, {
                        href: 'offres-emploi.html#' + encodeURIComponent(offer.slug),
                        colClass: 'col-lg-4 col-md-6',
                        compact: true
                    });
                }).join('');
                list.classList.remove('d-none');
            })
            .catch(function () {
                done();
                if (error) { error.classList.remove('d-none'); }
            });
    }

    function init() {
        initOffresPage();
        initCandidatureSection();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
