/**
 * PROCOPE — Galeries Actualités (photos + affiches).
 *
 * GET /api/galeries?kind=photos  → #fg-host (bandeau Owl + grille)
 * GET /api/galeries?kind=affiche → #fp-host (carousel Owl vertical)
 * Lightbox partagée #galleryModal (ne touche pas #posterModal).
 *
 * Inclure après js/api-config.js (et jQuery + Owl).
 */
(function () {
    'use strict';

    var API_BASE = window.PROCOPE_API_BASE || 'http://127.0.0.1:8088';

    var lightbox = {
        items: [],
        index: 0,
        resumes: []
    };

    function esc(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function prefersReducedMotion() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    function shuffle(list) {
        var copy = list.slice();
        var i;
        var j;
        var tmp;
        for (i = copy.length - 1; i > 0; i--) {
            j = Math.floor(Math.random() * (i + 1));
            tmp = copy[i];
            copy[i] = copy[j];
            copy[j] = tmp;
        }
        return copy;
    }

    function photoLabel(item) {
        var bits = [];
        if (item.caption) {
            bits.push(item.caption);
        }
        if (item.title) {
            bits.push(item.title);
        }
        return bits.join(' — ');
    }

    function periodLabel(item) {
        var bits = [];
        if (item.monthLabel) {
            bits.push(item.monthLabel.charAt(0).toUpperCase() + item.monthLabel.slice(1));
        }
        if (item.year) {
            bits.push(String(item.year));
        }
        return bits.join(' ');
    }

    function flattenPhotos(albums) {
        var photos = [];
        albums.forEach(function (album) {
            (album.images || []).forEach(function (image) {
                if (!image || !image.url) {
                    return;
                }
                photos.push({
                    url: image.url,
                    caption: image.caption || '',
                    year: parseInt(album.year, 10) || 0,
                    title: album.title || '',
                    albumId: album.id,
                    month: album.month,
                    monthLabel: album.month_label || ''
                });
            });
        });
        return shuffle(photos);
    }

    function flattenAffiches(albums) {
        var cards = [];
        albums.forEach(function (album) {
            var images = (album.images || []).filter(function (image) {
                return image && image.url;
            });
            var cover = album.cover || (images[0] && images[0].url);
            if (!cover) {
                return;
            }
            cards.push({
                url: cover,
                caption: album.description || '',
                year: parseInt(album.year, 10) || 0,
                title: album.title || '',
                albumId: album.id,
                month: album.month,
                monthLabel: album.month_label || '',
                images: images.length
                    ? images
                    : [{ url: cover, caption: album.description || '' }]
            });
        });
        return shuffle(cards);
    }

    function uniqueYears(items) {
        var seen = {};
        var years = [];
        items.forEach(function (item) {
            if (item.year && !seen[item.year]) {
                seen[item.year] = true;
                years.push(item.year);
            }
        });
        years.sort(function (a, b) { return b - a; });
        return years;
    }

    function uniqueFormations(items) {
        var seen = {};
        var titles = [];
        items.forEach(function (item) {
            if (item.title && !seen[item.title]) {
                seen[item.title] = true;
                titles.push(item.title);
            }
        });
        titles.sort(function (a, b) { return a.localeCompare(b, 'fr'); });
        return titles;
    }

    function owlOptions(count, reduced) {
        return {
            autoplay: !reduced && count > 1,
            autoplayTimeout: 5000,
            autoplayHoverPause: true,
            autoplaySpeed: reduced ? 0 : 900,
            smartSpeed: reduced ? 0 : 900,
            dots: count > 1,
            loop: !reduced && count >= 3,
            center: true,
            margin: 16,
            mouseDrag: count > 1,
            touchDrag: count > 1,
            nav: false,
            responsive: {
                0: { items: 1 },
                576: { items: 1 },
                768: { items: Math.min(2, count) },
                992: { items: Math.min(3, count) }
            }
        };
    }

    function showLightbox() {
        var img = document.getElementById('gallery-modal-img');
        var cap = document.getElementById('gallery-modal-caption');
        var prev = document.getElementById('gallery-modal-prev');
        var next = document.getElementById('gallery-modal-next');
        var item = lightbox.items[lightbox.index];
        if (!img || !item) {
            return;
        }
        img.src = item.url;
        img.alt = photoLabel(item) || 'Visuel PROCOPE Afrique';
        if (cap) {
            var label = photoLabel(item);
            cap.textContent = label;
            cap.classList.toggle('d-none', !label);
        }
        var many = lightbox.items.length > 1;
        if (prev) {
            prev.classList.toggle('d-none', !many);
        }
        if (next) {
            next.classList.toggle('d-none', !many);
        }
    }

    function stepLightbox(delta) {
        if (!lightbox.items.length) {
            return;
        }
        var n = lightbox.items.length;
        lightbox.index = (lightbox.index + delta + n) % n;
        showLightbox();
    }

    function openLightbox(items, index, onPause) {
        lightbox.items = items || [];
        lightbox.index = index || 0;
        showLightbox();
        var modalEl = document.getElementById('galleryModal');
        if (modalEl && window.bootstrap) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
        if (onPause) {
            onPause(true);
        }
    }

    function bindLightbox(onHidden) {
        if (typeof onHidden === 'function') {
            lightbox.resumes.push(onHidden);
        }
        var prev = document.getElementById('gallery-modal-prev');
        var next = document.getElementById('gallery-modal-next');
        if (prev && !prev.dataset.fgBound) {
            prev.dataset.fgBound = '1';
            prev.addEventListener('click', function () { stepLightbox(-1); });
        }
        if (next && !next.dataset.fgBound) {
            next.dataset.fgBound = '1';
            next.addEventListener('click', function () { stepLightbox(1); });
        }

        var modalEl = document.getElementById('galleryModal');
        if (modalEl && !modalEl.dataset.fgBound) {
            modalEl.dataset.fgBound = '1';
            modalEl.addEventListener('hidden.bs.modal', function () {
                lightbox.resumes.forEach(function (fn) { fn(); });
            });
            document.addEventListener('keydown', function (ev) {
                if (!modalEl.classList.contains('show')) {
                    return;
                }
                if (ev.key === 'ArrowLeft') {
                    ev.preventDefault();
                    stepLightbox(-1);
                }
                if (ev.key === 'ArrowRight') {
                    ev.preventDefault();
                    stepLightbox(1);
                }
            });
        }
    }

    function createSection(config) {
        var state = {
            items: [],
            year: 'all',
            formation: 'all',
            reducedMotion: false
        };

        function host() {
            return document.getElementById(config.hostId);
        }

        function filteredItems() {
            return state.items.filter(function (item) {
                if (state.year !== 'all' && item.year !== state.year) {
                    return false;
                }
                if (state.formation !== 'all' && item.title !== state.formation) {
                    return false;
                }
                return true;
            });
        }

        function loadingHtml() {
            return (
                '<div class="text-center py-5">' +
                    '<div class="spinner-border text-primary" role="status" aria-hidden="true"></div>' +
                    '<p class="mt-3 mb-0 text-muted">' + esc(config.loadingText) + '</p>' +
                '</div>'
            );
        }

        function emptyBox(title, text) {
            return (
                '<div class="fg-empty bg-light rounded text-center py-5 px-4">' +
                    '<i class="fa fa-images text-primary mb-3" style="font-size: 42px;" aria-hidden="true"></i>' +
                    '<h3 class="h4 mb-2">' + esc(title) + '</h3>' +
                    '<p class="mb-0 text-muted">' + esc(text) + '</p>' +
                '</div>'
            );
        }

        function filtersHtml() {
            var years = uniqueYears(state.items);
            var yearScope = state.year === 'all'
                ? state.items
                : state.items.filter(function (item) { return item.year === state.year; });
            var formations = uniqueFormations(yearScope);
            if (state.formation !== 'all' && formations.indexOf(state.formation) === -1) {
                state.formation = 'all';
                formations = uniqueFormations(yearScope);
            }

            var yearPills = '<button type="button" class="fg-filter-btn' + (state.year === 'all' ? ' is-active' : '') + '"' +
                ' data-fg-year="all" aria-pressed="' + (state.year === 'all' ? 'true' : 'false') + '">Tout</button>';
            years.forEach(function (year) {
                var active = state.year === year;
                yearPills += '<button type="button" class="fg-filter-btn' + (active ? ' is-active' : '') + '"' +
                    ' data-fg-year="' + year + '" aria-pressed="' + (active ? 'true' : 'false') + '">' +
                    year + '</button>';
            });

            var formationOptions = '<option value="all">' + esc(config.allFormationsLabel) + '</option>';
            formations.forEach(function (title) {
                formationOptions += '<option value="' + esc(title) + '"' +
                    (state.formation === title ? ' selected' : '') + '>' + esc(title) + '</option>';
            });

            return (
                '<div class="fg-toolbar mb-4">' +
                    '<div class="fg-filters" role="group" aria-label="Filtrer par année">' +
                        yearPills +
                    '</div>' +
                    (formations.length > 1
                        ? '<label class="fg-formation-label">' +
                            '<span class="visually-hidden">' + esc(config.allFormationsLabel) + '</span>' +
                            '<select class="fg-formation-select" data-fg-formation aria-label="' + esc(config.allFormationsLabel) + '">' +
                                formationOptions +
                            '</select>' +
                          '</label>'
                        : '') +
                '</div>'
            );
        }

        function slideHtml(item, index) {
            var label = photoLabel(item) || config.itemLabel;
            var period = periodLabel(item);
            var caption = item.title
                ? '<span class="fg-slide-caption">' + esc(item.title) +
                    (period ? ' · ' + esc(period) : '') + '</span>'
                : '';
            var extras = '';
            if (config.kind === 'affiche') {
                extras =
                    (item.title ? '<h5 class="fg-slide-title">' + esc(item.title) + '</h5>' : '') +
                    (period || item.caption
                        ? '<small class="fg-slide-kicker">' + esc(item.caption || period) + '</small>'
                        : '');
            }
            return (
                '<div class="fg-slide-item">' +
                    '<button type="button" class="fg-slide' + (config.kind === 'affiche' ? ' fg-slide--poster' : '') + '"' +
                        ' data-fg-photo="' + index + '"' +
                        ' aria-label="' + esc(label || 'Agrandir') + '">' +
                        '<img src="' + esc(item.url) + '" alt="' + esc(label) + '" loading="lazy">' +
                        '<span class="fg-slide-overlay" aria-hidden="true">' +
                            '<span class="fg-slide-zoom"><i class="fa fa-search-plus" aria-hidden="true"></i></span>' +
                            caption +
                        '</span>' +
                    '</button>' +
                    extras +
                '</div>'
            );
        }

        function carouselHtml(items) {
            if (!items.length) {
                return '';
            }
            return (
                '<div class="fg-carousel-wrap mb-4">' +
                    '<div class="owl-carousel fg-carousel' + (config.kind === 'affiche' ? ' fg-carousel--posters' : '') + '" data-fg-carousel>' +
                        items.map(slideHtml).join('') +
                    '</div>' +
                '</div>'
            );
        }

        function gridHtml(items) {
            return (
                '<div class="row g-3">' +
                    items.map(function (item, index) {
                        return (
                            '<div class="col-6 col-md-4 col-lg-3">' +
                                '<button type="button" class="fg-photo" data-fg-photo="' + index + '"' +
                                    ' aria-label="' + esc(photoLabel(item) || 'Agrandir') + '">' +
                                    '<img src="' + esc(item.url) + '" alt="' + esc(photoLabel(item) || config.itemLabel) + '" loading="lazy">' +
                                    '<span class="fg-photo-zoom"><i class="fa fa-search-plus" aria-hidden="true"></i></span>' +
                                '</button>' +
                            '</div>'
                        );
                    }).join('') +
                '</div>'
            );
        }

        function destroyCarousel() {
            var $ = window.jQuery;
            var root = host();
            if (!root || !$) {
                return;
            }
            var carousel = root.querySelector('[data-fg-carousel]');
            if (carousel && $(carousel).data('owl.carousel')) {
                $(carousel).trigger('destroy.owl.carousel');
                $(carousel).removeClass('owl-loaded owl-hidden');
                $(carousel).find('.owl-stage-outer').children().unwrap();
            }
        }

        function initCarousel(count) {
            var $ = window.jQuery;
            var root = host();
            if (!root) {
                return;
            }
            var carousel = root.querySelector('[data-fg-carousel]');
            if (!carousel) {
                return;
            }
            if ($ && $.fn.owlCarousel) {
                $(carousel).owlCarousel(owlOptions(count, state.reducedMotion));
                return;
            }
            carousel.classList.add('fg-carousel-static');
        }

        function pauseCarousel(paused) {
            var $ = window.jQuery;
            var root = host();
            var carousel = root ? root.querySelector('[data-fg-carousel]') : null;
            if (!carousel || !$ || !$(carousel).data('owl.carousel')) {
                return;
            }
            if (paused) {
                $(carousel).trigger('stop.owl.autoplay');
                return;
            }
            if (!state.reducedMotion) {
                $(carousel).trigger('play.owl.autoplay', [5000]);
            }
        }

        function render() {
            var root = host();
            if (!root) {
                return;
            }
            destroyCarousel();
            if (!state.items.length) {
                root.innerHTML = emptyBox(config.emptyTitle, config.emptyText);
                return;
            }

            state.reducedMotion = prefersReducedMotion();
            var items = filteredItems();
            var html = filtersHtml();
            if (!items.length) {
                html += emptyBox(config.emptyFilterTitle, config.emptyFilterText);
            } else {
                html += carouselHtml(items);
                if (config.showGrid) {
                    html += gridHtml(items);
                }
            }
            root.innerHTML = html;
            if (items.length) {
                initCarousel(items.length);
            }
        }

        function bind() {
            var root = host();
            if (!root) {
                return;
            }

            root.addEventListener('click', function (ev) {
                var yearBtn = ev.target.closest('[data-fg-year]');
                if (yearBtn) {
                    var raw = yearBtn.getAttribute('data-fg-year');
                    state.year = raw === 'all' ? 'all' : parseInt(raw, 10);
                    render();
                    return;
                }
                var photoBtn = ev.target.closest('[data-fg-photo]');
                if (photoBtn) {
                    var idx = parseInt(photoBtn.getAttribute('data-fg-photo'), 10) || 0;
                    var items = filteredItems();
                    if (config.kind === 'affiche') {
                        var card = items[idx];
                        openLightbox(card && card.images ? card.images : items, 0, pauseCarousel);
                    } else {
                        openLightbox(items, idx, pauseCarousel);
                    }
                }
            });

            root.addEventListener('change', function (ev) {
                var select = ev.target.closest('[data-fg-formation]');
                if (!select) {
                    return;
                }
                state.formation = select.value || 'all';
                render();
            });

            if (window.matchMedia) {
                var mq = window.matchMedia('(prefers-reduced-motion: reduce)');
                var onMotion = function () {
                    if (state.items.length) {
                        render();
                    }
                };
                if (mq.addEventListener) {
                    mq.addEventListener('change', onMotion);
                } else if (mq.addListener) {
                    mq.addListener(onMotion);
                }
            }
        }

        function load() {
            var root = host();
            if (!root) {
                return;
            }
            root.innerHTML = loadingHtml();
            fetch(API_BASE + '/api/galeries?kind=' + encodeURIComponent(config.kind))
                .then(function (res) { return res.ok ? res.json() : Promise.reject(); })
                .then(function (json) {
                    var albums = (json && json.galleries) ? json.galleries : [];
                    state.items = config.kind === 'affiche'
                        ? flattenAffiches(albums)
                        : flattenPhotos(albums);
                    state.year = 'all';
                    state.formation = 'all';
                    render();
                })
                .catch(function () {
                    root.innerHTML = emptyBox(config.errorTitle, config.errorText);
                });
        }

        bindLightbox(function () {
            pauseCarousel(false);
        });
        bind();
        load();
    }

    function boot() {
        if (document.getElementById('fg-host')) {
            createSection({
                hostId: 'fg-host',
                kind: 'photos',
                showGrid: true,
                itemLabel: 'Photo de formation',
                loadingText: 'Chargement de la galerie…',
                emptyTitle: 'Pas encore de photos publiées',
                emptyText: 'Les photos des prochaines formations apparaîtront ici.',
                emptyFilterTitle: 'Aucune photo pour ce filtre',
                emptyFilterText: 'Essayez une autre année ou une autre formation.',
                errorTitle: 'Galerie momentanément indisponible',
                errorText: 'Impossible de charger les photos. Réessayez dans quelques instants.',
                allFormationsLabel: 'Toutes les formations'
            });
        }
        if (document.getElementById('fp-host')) {
            createSection({
                hostId: 'fp-host',
                kind: 'affiche',
                showGrid: false,
                itemLabel: 'Affiche de formation',
                loadingText: 'Chargement des affiches…',
                emptyTitle: 'Pas encore d\'affiche publiée',
                emptyText: 'Les affiches des formations précédentes apparaîtront ici.',
                emptyFilterTitle: 'Aucune affiche pour ce filtre',
                emptyFilterText: 'Essayez une autre année ou un autre événement.',
                errorTitle: 'Affiches momentanément indisponibles',
                errorText: 'Impossible de charger les affiches. Réessayez dans quelques instants.',
                allFormationsLabel: 'Tous les événements'
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
