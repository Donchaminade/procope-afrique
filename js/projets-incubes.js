/**
 * PROCOPE — Projets incubés.
 * 1. Page projets.html : projets publiés, appels ouverts + dépôt lié,
 *    candidature spontanée. Détail (#slug) avec « s'inspirer et déposer ».
 * 2. Section #projet de candidature.html : 5 projets, lien appels, dépôt spontané.
 */
(function () {
    'use strict';

    var API_BASE = window.PROCOPE_API_BASE || 'http://127.0.0.1:8088';

    function esc(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function projectCardHtml(project, options) {
        var href = options.href;
        var imageHtml = project.image
            ? '<div class="ratio ratio-16x9">' +
              '<img src="' + esc(project.image) + '" alt="Affiche — ' + esc(project.title) + '"' +
              ' class="w-100 h-100" style="object-fit:cover;" loading="lazy"></div>'
            : '';

        return '<div class="' + (options.colClass || 'col-lg-4 col-md-6') + '">' +
            '<div class="bg-light rounded h-100 d-flex flex-column overflow-hidden">' +
            imageHtml +
            '<div class="p-4 d-flex flex-column flex-grow-1">' +
            '<div class="d-flex align-items-center justify-content-between mb-3">' +
            '<span class="badge bg-primary py-2 px-3">' + esc(project.sector || 'Projet') + '</span>' +
            (project.stage_label
                ? '<small class="text-muted">' + esc(project.stage_label) + '</small>'
                : '') +
            '</div>' +
            '<h4 class="mb-3' + (options.compact ? ' h5' : '') + '">' + esc(project.title) + '</h4>' +
            (project.country
                ? '<p class="mb-2"><i class="fa fa-map-marker-alt text-primary me-2" aria-hidden="true"></i>' +
                  esc(project.country) + '</p>'
                : '') +
            (!options.compact && project.excerpt
                ? '<p class="mb-4 flex-grow-1">' + esc(project.excerpt) + '</p>'
                : '<div class="flex-grow-1"></div>') +
            '<a class="btn btn-primary py-2 px-4 mt-auto align-self-start" href="' + href + '">' +
            'Voir les détails<i class="fa fa-arrow-right ms-2" aria-hidden="true"></i></a>' +
            '</div></div></div>';
    }

    function formatCallDate(value) {
        if (!value) { return ''; }
        var date = new Date(String(value).replace(' ', 'T'));
        if (isNaN(date.getTime())) { return String(value); }
        return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
    }

    function callCardHtml(call, options) {
        options = options || {};
        var href = options.href || ('#appel-' + encodeURIComponent(call.slug));
        return '<div class="' + (options.colClass || 'col-lg-4 col-md-6') + '">' +
            '<div class="bg-light rounded h-100 d-flex flex-column p-4">' +
            '<div class="d-flex align-items-center justify-content-between mb-3">' +
            '<span class="badge bg-primary py-2 px-3">' + esc(call.sector || 'Appel') + '</span>' +
            '<small class="text-muted">Clôture le ' + esc(formatCallDate(call.closes_at)) + '</small>' +
            '</div>' +
            '<h4 class="mb-3' + (options.compact ? ' h5' : '') + '">' + esc(call.title) + '</h4>' +
            (call.excerpt
                ? '<p class="mb-4 flex-grow-1">' + esc(call.excerpt) + '</p>'
                : '<div class="flex-grow-1"></div>') +
            '<a class="btn btn-primary py-2 px-4 mt-auto align-self-start" href="' + href + '">' +
            'Postuler<i class="fa fa-arrow-right ms-2" aria-hidden="true"></i></a>' +
            '</div></div>';
    }

    function bindDepositForm(form, submitBtn, errorEl, successEl, successMsgEl, wrapEl, ctaEl, urlBuilder, options) {
        if (!form) { return; }
        options = options || {};
        var allowAnother = !!options.allowAnother;

        function showFormError(message) {
            errorEl.textContent = message;
            errorEl.classList.remove('d-none');
            errorEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function ensureAgainButton() {
            if (!allowAnother) { return; }
            var againBtn = successEl.querySelector('[data-deposit-again]');
            if (againBtn) { return; }
            againBtn = document.createElement('button');
            againBtn.type = 'button';
            againBtn.setAttribute('data-deposit-again', '1');
            againBtn.className = 'btn btn-outline-primary py-2 px-4 mt-3';
            againBtn.textContent = 'Déposer un autre projet';
            successEl.appendChild(againBtn);
            againBtn.addEventListener('click', function () {
                var kept = {
                    full_name: form.elements.namedItem('full_name'),
                    email: form.elements.namedItem('email'),
                    phone: form.elements.namedItem('phone')
                };
                var values = {
                    full_name: kept.full_name ? kept.full_name.value : '',
                    email: kept.email ? kept.email.value : '',
                    phone: kept.phone ? kept.phone.value : ''
                };
                form.reset();
                if (kept.full_name) { kept.full_name.value = values.full_name; }
                if (kept.email) { kept.email.value = values.email; }
                if (kept.phone) { kept.phone.value = values.phone; }
                successEl.classList.add('d-none');
                wrapEl.classList.remove('d-none');
                if (ctaEl) { ctaEl.classList.remove('d-none'); }
                var nameField = form.elements.namedItem('project_name');
                if (nameField && nameField.focus) { nameField.focus(); }
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            errorEl.classList.add('d-none');
            if (!form.reportValidity()) { return; }

            var formData = new FormData(form);
            submitBtn.disabled = true;
            submitBtn.setAttribute('aria-busy', 'true');
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Envoi…';

            fetch(urlBuilder(), { method: 'POST', body: formData })
                .then(function (response) {
                    return response.json().then(function (json) { return { status: response.status, json: json }; });
                })
                .then(function (result) {
                    if (result.json.ok) {
                        successMsgEl.textContent = result.json.message ||
                            'Votre dépôt a bien été enregistré.';
                        wrapEl.classList.add('d-none');
                        if (ctaEl) { ctaEl.classList.add('d-none'); }
                        ensureAgainButton();
                        successEl.classList.remove('d-none');
                        successEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
                    submitBtn.disabled = false;
                    submitBtn.removeAttribute('aria-busy');
                    submitBtn.innerHTML = '<i class="fa fa-paper-plane me-2" aria-hidden="true"></i>Envoyer mon dépôt';
                });
        });
    }

    function initProjetsPage() {
        var els = {
            loading: document.getElementById('pi-loading'),
            error: document.getElementById('pi-error'),
            listView: document.getElementById('pi-list-view'),
            list: document.getElementById('pi-list'),
            empty: document.getElementById('pi-empty'),
            detailView: document.getElementById('pi-detail-view'),
            detail: document.getElementById('pi-detail'),
            notfound: document.getElementById('pi-notfound'),
            applyCta: document.getElementById('pi-apply-cta'),
            applyToggle: document.getElementById('pi-apply-toggle'),
            applyWrap: document.getElementById('pi-apply-wrap'),
            applySuccess: document.getElementById('pi-apply-success'),
            applySuccessMessage: document.getElementById('pi-apply-success-message'),
            form: document.getElementById('pi-apply-form'),
            formError: document.getElementById('pi-form-error'),
            submit: document.getElementById('pi-submit'),
            openApply: document.getElementById('pi-open-apply'),
            openWrap: document.getElementById('pi-open-wrap'),
            openForm: document.getElementById('pi-open-form'),
            openError: document.getElementById('pi-open-error'),
            openSubmit: document.getElementById('pi-open-submit'),
            openSuccess: document.getElementById('pi-open-success'),
            openSuccessMessage: document.getElementById('pi-open-success-message'),
            calls: document.getElementById('pi-calls'),
            callsList: document.getElementById('pi-calls-list'),
            callApply: document.getElementById('pi-call-apply'),
            callApplyTitle: document.getElementById('pi-call-apply-title'),
            callApplyMeta: document.getElementById('pi-call-apply-meta'),
            callForm: document.getElementById('pi-call-form'),
            callError: document.getElementById('pi-call-error'),
            callSubmit: document.getElementById('pi-call-submit'),
            callSuccess: document.getElementById('pi-call-success'),
            callSuccessMessage: document.getElementById('pi-call-success-message')
        };

        if (!els.listView) { return; }

        var currentSlug = '';
        var currentCallSlug = '';
        var defaultTitle = document.title;
        var openCalls = [];

        function show(state) {
            ['loading', 'error', 'listView', 'detailView'].forEach(function (key) {
                els[key].classList.toggle('d-none', key !== state);
            });
        }

        function requestedSlug() {
            var hash = decodeURIComponent(location.hash.replace(/^#/, ''));
            if (hash.indexOf('appel-') === 0) { return ''; }
            if (hash && hash !== 'depot') { return hash; }
            var params = new URLSearchParams(location.search);
            return (params.get('projet') || '').trim();
        }

        function requestedCallSlug() {
            var hash = decodeURIComponent(location.hash.replace(/^#/, ''));
            if (hash.indexOf('appel-') === 0) {
                return hash.slice('appel-'.length);
            }
            return (new URLSearchParams(location.search).get('appel') || '').trim();
        }

        function renderList(projects) {
            document.title = defaultTitle;
            if (!projects.length) {
                els.list.innerHTML = '';
                els.empty.classList.remove('d-none');
                show('listView');
                return;
            }
            els.empty.classList.add('d-none');
            els.list.innerHTML = projects.map(function (project) {
                return projectCardHtml(project, {
                    href: '#' + encodeURIComponent(project.slug),
                    colClass: 'col-lg-4 col-md-6',
                    compact: false
                });
            }).join('');
            show('listView');
            renderCalls(openCalls);
        }

        function renderCalls(calls) {
            if (!els.calls || !els.callsList) { return; }
            openCalls = calls || [];
            if (!openCalls.length) {
                els.calls.classList.add('d-none');
                return;
            }
            els.calls.classList.remove('d-none');
            els.callsList.innerHTML = openCalls.map(function (call) {
                return callCardHtml(call, { href: '#appel-' + encodeURIComponent(call.slug) });
            }).join('');
            openSelectedCall();
        }

        function openSelectedCall() {
            if (!els.callApply) { return; }
            var slug = requestedCallSlug();
            currentCallSlug = slug;
            if (!slug) {
                els.callApply.classList.add('d-none');
                return;
            }
            var call = openCalls.filter(function (item) { return item.slug === slug; })[0];
            if (!call) {
                els.callApply.classList.add('d-none');
                return;
            }
            els.callApplyTitle.textContent = 'Candidater — ' + call.title;
            els.callApplyMeta.textContent = (call.sector ? call.sector + ' · ' : '') +
                'Clôture le ' + formatCallDate(call.closes_at);
            if (els.callForm) {
                var sectorInput = els.callForm.querySelector('[name="sector"]');
                if (sectorInput && !sectorInput.value) { sectorInput.value = call.sector || ''; }
            }
            els.callSuccess.classList.add('d-none');
            els.callApply.classList.remove('d-none');
            els.callApply.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function loadList() {
            show('loading');
            Promise.all([
                fetch(API_BASE + '/api/projets').then(function (response) { return response.json(); }),
                fetch(API_BASE + '/api/appels').then(function (response) { return response.json(); }).catch(function () { return { calls: [] }; })
            ]).then(function (results) {
                renderCalls(results[1].calls || []);
                renderList(results[0].projects || []);
            }).catch(function () { show('error'); });
        }

        function renderGallery(project) {
            var gallery = document.getElementById('pi-gallery');
            var main = document.getElementById('pi-gallery-main');
            var thumbs = document.getElementById('pi-gallery-thumbs');
            if (!gallery || !main || !thumbs) { return; }

            var images = project.images || [];
            if (!images.length) {
                gallery.classList.add('d-none');
                main.removeAttribute('src');
                thumbs.innerHTML = '';
                return;
            }

            main.src = images[0];
            main.alt = 'Affiche — ' + project.title;
            thumbs.innerHTML = '';

            if (images.length > 1) {
                images.forEach(function (url, index) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn p-0 border rounded overflow-hidden pi-thumb' + (index === 0 ? ' border-primary' : '');
                    btn.setAttribute('aria-label', 'Afficher l\'image ' + (index + 1));
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
                        thumbs.querySelectorAll('.pi-thumb').forEach(function (t) {
                            t.classList.toggle('border-primary', t === btn);
                        });
                    });
                    thumbs.appendChild(btn);
                });
            }

            gallery.classList.remove('d-none');
        }

        function renderDetail(project) {
            document.title = project.title + ' | Projets incubés PROCOPE Afrique';

            document.getElementById('pi-title').textContent = project.title;
            document.getElementById('pi-sector').textContent = project.sector || '—';
            document.getElementById('pi-stage').textContent = project.stage_label || '—';
            document.getElementById('pi-country').textContent = project.country || '—';
            document.getElementById('pi-year').textContent = project.year ? String(project.year) : '—';

            document.getElementById('pi-badges').innerHTML =
                (project.sector ? '<span class="badge bg-primary py-2 px-3 me-2">' + esc(project.sector) + '</span>' : '') +
                (project.stage_label ? '<span class="badge bg-secondary py-2 px-3">' + esc(project.stage_label) + '</span>' : '');

            renderGallery(project);

            var pitch = String(project.pitch || '').trim();
            var description = String(project.description || '').trim();
            var body = description || pitch;
            document.getElementById('pi-description').innerHTML = body
                ? body.split(/\n{2,}/).map(function (paragraph) {
                    return '<p>' + esc(paragraph).replace(/\n/g, '<br>') + '</p>';
                }).join('')
                : '<p class="text-muted">Contactez-nous pour plus de détails sur ce projet.</p>';

            var websiteEl = document.getElementById('pi-website');
            if (project.website) {
                websiteEl.classList.remove('d-none');
                websiteEl.querySelector('a').href = project.website;
                websiteEl.querySelector('a').textContent = project.website;
            } else {
                websiteEl.classList.add('d-none');
            }

            var socialsEl = document.getElementById('pi-socials');
            var socials = project.socials || [];
            if (socials.length) {
                socialsEl.classList.remove('d-none');
                socialsEl.querySelector('div').innerHTML = socials.map(function (url) {
                    return '<a class="d-block text-primary" href="' + esc(url) + '" target="_blank" rel="noopener">' +
                        esc(url) + '</a>';
                }).join('');
            } else {
                socialsEl.classList.add('d-none');
            }

            var shareUrl = location.origin + location.pathname + '#' + encodeURIComponent(project.slug);
            var shareText = 'Projet incubé PROCOPE Afrique — ' + project.title;
            document.getElementById('pi-share-whatsapp').href =
                'https://wa.me/?text=' + encodeURIComponent(shareText + '\n' + shareUrl);
            document.getElementById('pi-share-facebook').href =
                'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl);
            document.getElementById('pi-share-linkedin').href =
                'https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(shareUrl);
            document.getElementById('pi-share-twitter').href =
                'https://twitter.com/intent/tweet?text=' + encodeURIComponent(shareText) + '&url=' + encodeURIComponent(shareUrl);

            els.applyWrap.classList.add('d-none');
            els.applySuccess.classList.add('d-none');
            els.applyCta.classList.remove('d-none');
            els.formError.classList.add('d-none');
            els.form.reset();

            els.notfound.classList.add('d-none');
            els.detail.classList.remove('d-none');
            show('detailView');
        }

        function loadDetail(slug) {
            show('loading');
            fetch(API_BASE + '/api/projets/' + encodeURIComponent(slug))
                .then(function (response) {
                    return response.json().then(function (json) { return { status: response.status, json: json }; });
                })
                .then(function (result) {
                    if (result.status === 404 || !result.json.project) {
                        document.title = defaultTitle;
                        els.detail.classList.add('d-none');
                        els.notfound.classList.remove('d-none');
                        show('detailView');
                        return;
                    }
                    renderDetail(result.json.project);
                })
                .catch(function () { show('error'); });
        }

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
            var label = document.getElementById('pi-share-copy-label');
            var done = function () {
                label.textContent = 'Lien copié';
                setTimeout(function () { label.textContent = 'Copier le lien'; }, 2500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(done).catch(function () { window.prompt('Copiez le lien :', url); });
            } else {
                window.prompt('Copiez le lien :', url);
            }
        }

        bindDepositForm(
            els.form, els.submit, els.formError, els.applySuccess,
            els.applySuccessMessage, els.applyWrap, els.applyCta,
            function () { return API_BASE + '/api/projets/depot'; },
            { allowAnother: true }
        );

        if (els.callForm) {
            bindDepositForm(
                els.callForm, els.callSubmit, els.callError, els.callSuccess,
                els.callSuccessMessage, els.callApply, null,
                function () {
                    return API_BASE + '/api/appels/' + encodeURIComponent(currentCallSlug) + '/depot';
                }
            );
        }

        if (els.openForm) {
            bindDepositForm(
                els.openForm, els.openSubmit, els.openError, els.openSuccess,
                els.openSuccessMessage, els.openWrap, els.openApply,
                function () { return API_BASE + '/api/projets/depot'; },
                { allowAnother: true }
            );
        }

        if (els.applyToggle) {
            els.applyToggle.addEventListener('click', function () {
                els.applyWrap.classList.toggle('d-none');
                if (!els.applyWrap.classList.contains('d-none')) {
                    document.getElementById('pi-full-name').focus();
                }
            });
        }
        if (els.openApply) {
            els.openApply.addEventListener('click', function () {
                els.openWrap.classList.toggle('d-none');
                if (!els.openWrap.classList.contains('d-none')) {
                    document.getElementById('pi-open-full-name').focus();
                }
            });
        }
        document.getElementById('pi-share-copy').addEventListener('click', copyShareLink);
        document.getElementById('pi-back').addEventListener('click', function (event) {
            event.preventDefault();
            if (location.hash) {
                history.pushState('', document.title, location.pathname);
            }
            route();
        });
        window.addEventListener('hashchange', route);
        route();
    }

    var CANDIDATURE_MAX_PROJECTS = 5;

    function initCandidatureSection() {
        var list = document.getElementById('cand-projets-list');
        if (!list) { return; }

        var loading = document.getElementById('cand-projets-loading');
        var empty = document.getElementById('cand-projets-empty');
        var error = document.getElementById('cand-projets-error');
        var callsWrap = document.getElementById('cand-appels');
        var callsList = document.getElementById('cand-appels-list');
        var spontaneForm = document.getElementById('cand-spontane-form');
        var spontaneSubmit = document.getElementById('cand-spontane-submit');
        var spontaneError = document.getElementById('cand-spontane-error');
        var spontaneWrap = document.getElementById('cand-spontane-wrap');
        var spontaneSuccess = document.getElementById('cand-spontane-success');
        var spontaneSuccessMessage = document.getElementById('cand-spontane-success-message');
        var spontaneToggle = document.getElementById('cand-spontane-toggle');

        function done() {
            if (loading) { loading.classList.add('d-none'); }
        }

        fetch(API_BASE + '/api/projets')
            .then(function (response) { return response.json(); })
            .then(function (data) {
                done();
                var projects = (data.projects || []).slice(0, CANDIDATURE_MAX_PROJECTS);
                if (!projects.length) {
                    if (empty) { empty.classList.remove('d-none'); }
                    return;
                }
                list.innerHTML = projects.map(function (project) {
                    return projectCardHtml(project, {
                        href: 'projets.html#' + encodeURIComponent(project.slug),
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

        fetch(API_BASE + '/api/appels')
            .then(function (response) { return response.json(); })
            .then(function (data) {
                var calls = data.calls || [];
                if (!callsWrap || !callsList || !calls.length) { return; }
                callsList.innerHTML = calls.map(function (call) {
                    return callCardHtml(call, {
                        href: 'projets.html#appel-' + encodeURIComponent(call.slug),
                        colClass: 'col-lg-4 col-md-6',
                        compact: true
                    });
                }).join('');
                callsWrap.classList.remove('d-none');
            })
            .catch(function () { /* les projets restent visibles */ });

        if (spontaneToggle && spontaneWrap) {
            spontaneToggle.addEventListener('click', function () {
                spontaneWrap.classList.toggle('d-none');
            });
        }
        bindDepositForm(
            spontaneForm, spontaneSubmit, spontaneError, spontaneSuccess,
            spontaneSuccessMessage, spontaneWrap, spontaneToggle,
            function () { return API_BASE + '/api/projets/depot'; },
            { allowAnother: true }
        );
    }

    function init() {
        initProjetsPage();
        initCandidatureSection();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
