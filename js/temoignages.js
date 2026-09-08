/**
 * PROCOPE — Témoignages écrits.
 *
 * - Charge GET /api/temoignages dans .testimonial-carousel[data-temoignages-api]
 *   (accueil, services). Les cartes Aïssatou / Moussa / Fatima / David
 *   étaient des placeholders fictifs : elles sont remplacées par l'API.
 *   Si l'API est vide ou indisponible : état vide + bouton Témoigner
 *   (pas de fallback vers les faux noms).
 * - Modal partagé #temoignageModal (injecté une fois) + POST multipart.
 * - Boutons / liens [data-temoigner] et hash #temoigner.
 *
 * Inclure après js/api-config.js (et après jQuery + Owl + Bootstrap).
 */
(function () {
    'use strict';

    var API_BASE = window.PROCOPE_API_BASE || 'http://127.0.0.1:8088';
    var SUCCESS_MSG = 'Merci, votre témoignage sera lu par l\'équipe avant publication.';

    function esc(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function initials(name) {
        var parts = String(name || '').trim().split(/\s+/);
        if (!parts[0]) { return '?'; }
        if (parts.length === 1) { return parts[0].charAt(0).toUpperCase(); }
        return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    }

    function ensureModal() {
        if (document.getElementById('temoignageModal')) { return; }
        var wrap = document.createElement('div');
        wrap.innerHTML =
            '<div class="modal fade" id="temoignageModal" tabindex="-1" aria-labelledby="temoignageModalTitle" aria-hidden="true">' +
                '<div class="modal-dialog modal-dialog-centered">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header border-0">' +
                            '<h5 class="modal-title" id="temoignageModalTitle">Partager un témoignage</h5>' +
                            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>' +
                        '</div>' +
                        '<div class="modal-body">' +
                            '<p class="text-muted small mb-3">L\'équipe lit chaque message avant publication.</p>' +
                            '<div id="tm-form-alert" class="alert d-none" role="alert"></div>' +
                            '<form id="temoignage-form" novalidate>' +
                                '<div class="pc-hp" aria-hidden="true">' +
                                    '<label for="tm-website">Site web</label>' +
                                    '<input type="text" id="tm-website" name="website" tabindex="-1" autocomplete="off">' +
                                '</div>' +
                                '<div class="mb-3">' +
                                    '<label class="form-label" for="tm-name">Nom *</label>' +
                                    '<input class="form-control" id="tm-name" name="name" type="text" required maxlength="150" autocomplete="name">' +
                                '</div>' +
                                '<div class="mb-3">' +
                                    '<label class="form-label" for="tm-role">Rôle / activité</label>' +
                                    '<input class="form-control" id="tm-role" name="role" type="text" maxlength="190" placeholder="Ex. : CEO de …, participante à la formation">' +
                                '</div>' +
                                '<div class="mb-3">' +
                                    '<label class="form-label" for="tm-email">E-mail <span class="text-muted">(pour l\'accusé de réception)</span></label>' +
                                    '<input class="form-control" id="tm-email" name="email" type="email" maxlength="190" autocomplete="email">' +
                                '</div>' +
                                '<div class="mb-3">' +
                                    '<label class="form-label" for="tm-quote">Témoignage *</label>' +
                                    '<textarea class="form-control" id="tm-quote" name="quote" rows="5" required maxlength="2000"></textarea>' +
                                '</div>' +
                                '<div class="mb-3">' +
                                    '<label class="form-label" for="tm-photo">Photo <span class="text-muted">(facultative, JPEG / PNG / WebP, 2 Mo max)</span></label>' +
                                    '<input class="form-control" id="tm-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">' +
                                '</div>' +
                                '<button class="btn btn-primary w-100" type="submit" id="tm-submit">Envoyer</button>' +
                            '</form>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
        document.body.appendChild(wrap.firstElementChild);
    }

    function showAlert(kind, message) {
        var el = document.getElementById('tm-form-alert');
        if (!el) { return; }
        el.className = 'alert alert-' + kind;
        el.textContent = message;
        el.classList.remove('d-none');
    }

    function hideAlert() {
        var el = document.getElementById('tm-form-alert');
        if (!el) { return; }
        el.className = 'alert d-none';
        el.textContent = '';
    }

    function openModal() {
        ensureModal();
        var modalEl = document.getElementById('temoignageModal');
        if (!modalEl || typeof bootstrap === 'undefined') { return; }
        hideAlert();
        var form = document.getElementById('temoignage-form');
        if (form) { form.classList.remove('d-none'); }
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function bindOpeners() {
        document.addEventListener('click', function (ev) {
            var trigger = ev.target.closest('[data-temoigner]');
            if (!trigger) { return; }
            ev.preventDefault();
            openModal();
        });
        if (location.hash === '#temoigner') {
            openModal();
        }
        window.addEventListener('hashchange', function () {
            if (location.hash === '#temoigner') { openModal(); }
        });
    }

    function bindForm() {
        document.addEventListener('submit', function (ev) {
            var form = ev.target;
            if (!form || form.id !== 'temoignage-form') { return; }
            ev.preventDefault();
            hideAlert();

            var name = (form.name.value || '').trim();
            var quote = (form.quote.value || '').trim();
            if (name.length < 2) {
                showAlert('danger', 'Veuillez indiquer votre nom (2 caractères minimum).');
                return;
            }
            if (quote.length < 20) {
                showAlert('danger', 'Votre témoignage est trop court (20 caractères minimum).');
                return;
            }

            var btn = document.getElementById('tm-submit');
            var prev = btn ? btn.textContent : '';
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Envoi…';
            }

            var data = new FormData(form);
            fetch(API_BASE + '/api/temoignages', {
                method: 'POST',
                body: data
            }).then(function (res) {
                return res.json().then(function (json) {
                    return { ok: res.ok, status: res.status, json: json };
                });
            }).then(function (out) {
                if (out.status === 429) {
                    showAlert('warning', out.json.error || 'Trop de tentatives. Réessayez plus tard.');
                    return;
                }
                if (!out.ok && out.status !== 200) {
                    var fields = out.json.fields || {};
                    var first = out.json.error || fields.quote || fields.name || fields.photo || fields.email || 'Envoi impossible.';
                    showAlert('danger', first);
                    return;
                }
                form.reset();
                form.classList.add('d-none');
                showAlert('success', (out.json && out.json.message) || SUCCESS_MSG);
            }).catch(function () {
                showAlert('danger', 'Réseau indisponible. Réessayez dans un instant.');
            }).finally(function () {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = prev || 'Envoyer';
                }
            });
        });
    }

    function cardHtml(item) {
        var name = item.name || '';
        var role = item.role || '';
        var quote = item.quote || '';
        var photo = item.photo;
        var avatar = photo
            ? '<img class="img-fluid rounded" src="' + esc(photo) + '" style="width: 60px; height: 60px; object-fit: cover;" alt="Portrait de ' + esc(name) + '">'
            : '<span class="tm-initials" aria-hidden="true">' + esc(initials(name)) + '</span>';
        return (
            '<div class="testimonial-item bg-light my-4">' +
                '<div class="d-flex align-items-center border-bottom pt-5 pb-4 px-5">' +
                    avatar +
                    '<div class="ps-4">' +
                        '<h4 class="text-primary mb-1">' + esc(name) + '</h4>' +
                        (role ? '<small class="text-uppercase">' + esc(role) + '</small>' : '') +
                    '</div>' +
                '</div>' +
                '<div class="pt-4 pb-5 px-5">' + esc(quote) + '</div>' +
            '</div>'
        );
    }

    function emptyHtml() {
        return (
            '<div class="tm-empty text-center py-5">' +
                '<p class="mb-4">Aucun témoignage publié pour le moment. Vous pouvez être le premier.</p>' +
                '<button type="button" class="btn btn-primary btn-temoigner py-3 px-5" data-temoigner>Témoigner</button>' +
            '</div>'
        );
    }

    function owlOptions() {
        return {
            autoplay: true,
            smartSpeed: 1500,
            dots: true,
            loop: true,
            center: true,
            responsive: {
                0: { items: 1 },
                576: { items: 1 },
                768: { items: 2 },
                992: { items: 3 }
            }
        };
    }

    function fillCarousel(root, items) {
        var $ = window.jQuery;
        var host = root.querySelector('[data-temoignages-host]') || root;
        var carousel = host.querySelector('.testimonial-carousel');
        var empty = host.querySelector('[data-temoignages-empty]');

        if (!items.length) {
            if (carousel && $ && $(carousel).data('owl.carousel')) {
                $(carousel).trigger('destroy.owl.carousel');
            }
            if (carousel) {
                carousel.classList.add('d-none');
                carousel.innerHTML = '';
            }
            if (empty) {
                empty.classList.remove('d-none');
                empty.innerHTML = emptyHtml();
            }
            return;
        }

        if (empty) {
            empty.classList.add('d-none');
            empty.innerHTML = '';
        }
        if (!carousel) { return; }
        carousel.classList.remove('d-none');

        if ($ && $(carousel).data('owl.carousel')) {
            $(carousel).trigger('destroy.owl.carousel');
            $(carousel).removeClass('owl-loaded owl-hidden');
            $(carousel).find('.owl-stage-outer').children().unwrap();
        }
        carousel.innerHTML = items.map(cardHtml).join('');
        if ($ && $.fn.owlCarousel) {
            var opts = owlOptions();
            if (items.length < 3) {
                opts.loop = false;
                opts.center = items.length === 1;
            }
            $(carousel).owlCarousel(opts);
        }
    }

    function loadCarousels() {
        var roots = document.querySelectorAll('[data-temoignages-host]');
        if (!roots.length) { return; }

        fetch(API_BASE + '/api/temoignages')
            .then(function (res) { return res.ok ? res.json() : Promise.reject(); })
            .then(function (json) {
                var items = (json && json.items) ? json.items : [];
                roots.forEach(function (root) { fillCarousel(root, items); });
            })
            .catch(function () {
                roots.forEach(function (root) { fillCarousel(root, []); });
            });
    }

    function boot() {
        ensureModal();
        bindOpeners();
        bindForm();
        loadCarousels();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
