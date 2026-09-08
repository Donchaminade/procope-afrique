/* JS admin PROCOPE (CSP : pas de JS inline, pas de CDN) */
(function () {
    'use strict';

    /* ---- Confirmation avant soumission (formulaires destructifs) ---- */
    document.querySelectorAll('form[data-confirm], form[data-confirm-count]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var countTpl = form.getAttribute('data-confirm-count');
            if (countTpl) {
                var n = form.querySelectorAll('input[name="ids[]"]:checked').length;
                if (n === 0) {
                    event.preventDefault();
                    window.alert('Aucun message sélectionné.');
                    return;
                }
                if (!window.confirm(countTpl.replace('{n}', String(n)))) {
                    event.preventDefault();
                }
                return;
            }
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    /* ---- Sélection groupée (liste des messages) ---- */
    var msgSelectAll = document.getElementById('msg-select-all');
    if (msgSelectAll) {
        msgSelectAll.addEventListener('change', function () {
            document.querySelectorAll('input[name="ids[]"]').forEach(function (cb) {
                cb.checked = msgSelectAll.checked;
            });
        });
    }

    /* ---- Galerie : bascule type photos / affiche ---- */
    var galleryKindHost = document.querySelector('[data-gallery-kind]');
    function syncGalleryKind() {
        if (!galleryKindHost) {
            return;
        }
        var selected = galleryKindHost.querySelector('input[name="kind"]:checked');
        var kind = selected ? selected.value : 'photos';
        var isAffiche = kind === 'affiche';
        document.querySelectorAll('[data-kind-photos]').forEach(function (el) {
            el.classList.toggle('hidden', isAffiche);
        });
        document.querySelectorAll('[data-kind-affiche]').forEach(function (el) {
            el.classList.toggle('hidden', !isAffiche);
        });
        var title = document.getElementById('g-title');
        if (title) {
            var nextPh = title.getAttribute(isAffiche ? 'data-placeholder-affiche' : 'data-placeholder-photos');
            if (nextPh) {
                title.setAttribute('placeholder', nextPh);
            }
        }
        var file = document.querySelector('[data-gallery-create-files]');
        if (file) {
            file.required = isAffiche;
        }
    }
    if (galleryKindHost) {
        galleryKindHost.addEventListener('change', syncGalleryKind);
        syncGalleryKind();
    }

    /* ---- Galerie : préremplir titre / année / mois depuis la formation ---- */
    var galleryFormation = document.getElementById('g-formation');
    if (galleryFormation) {
        galleryFormation.addEventListener('change', function () {
            var option = galleryFormation.options[galleryFormation.selectedIndex];
            if (!option || !option.value) {
                return;
            }
            var title = document.getElementById('g-title');
            var year = document.getElementById('g-year');
            var month = document.getElementById('g-month');
            var nextTitle = option.getAttribute('data-title') || '';
            var nextYear = option.getAttribute('data-year') || '';
            var nextMonth = option.getAttribute('data-month') || '';
            if (title && nextTitle) {
                title.value = nextTitle;
            }
            if (year && nextYear) {
                year.value = nextYear;
            }
            if (month) {
                month.value = nextMonth;
            }
        });
    }

    /* ---- Créneaux dynamiques (formulaire formation) ---- */
    var addButton = document.getElementById('add-slot');
    var container = document.getElementById('slots');
    if (addButton && container) {
        addButton.addEventListener('click', function () {
            var row = container.querySelector('.slot-row').cloneNode(true);
            row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
            container.appendChild(row);
        });
        container.addEventListener('click', function (event) {
            var removeBtn = event.target.closest('.remove-slot');
            if (!removeBtn) return;
            var rows = container.querySelectorAll('.slot-row');
            if (rows.length > 1) {
                removeBtn.closest('.slot-row').remove();
            } else {
                rows[0].querySelectorAll('input').forEach(function (input) { input.value = ''; });
            }
        });
    }

    /* ---- Sidebar mobile (off-canvas) ---- */
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebar-overlay');
    var toggle = document.getElementById('sidebar-toggle');
    if (sidebar && overlay && toggle) {
        var closeSidebar = function () {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        };
        toggle.addEventListener('click', function () {
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
            } else {
                closeSidebar();
            }
        });
        overlay.addEventListener('click', closeSidebar);
    }

    /* ---- Message flash : fermeture manuelle + auto-dismiss ---- */
    var flash = document.getElementById('flash');
    if (flash) {
        var hideFlash = function () {
            flash.classList.add('opacity-0', '-translate-y-3');
            window.setTimeout(function () { flash.remove(); }, 500);
        };
        var dismissBtn = flash.querySelector('[data-dismiss]');
        if (dismissBtn) dismissBtn.addEventListener('click', hideFlash);
        window.setTimeout(hideFlash, 6000);
    }

    /* ---- Onglets (page Automatisations) : data-tab / data-panel + #hash ---- */
    var tabButtons = document.querySelectorAll('[data-tab]');
    var tabPanels = document.querySelectorAll('[data-panel]');
    if (tabButtons.length && tabPanels.length) {
        var tabNames = [];
        tabButtons.forEach(function (btn) { tabNames.push(btn.getAttribute('data-tab')); });

        var activateTab = function (name) {
            if (tabNames.indexOf(name) === -1) name = tabNames[0];
            tabButtons.forEach(function (btn) {
                var active = btn.getAttribute('data-tab') === name;
                btn.classList.toggle('tab-btn-active', active);
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            tabPanels.forEach(function (panel) {
                panel.classList.toggle('hidden', panel.getAttribute('data-panel') !== name);
            });
        };

        tabButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var name = btn.getAttribute('data-tab');
                activateTab(name);
                /* Persistance : l'ancre survit aux rechargements et redirections */
                window.history.replaceState(null, '', '#' + name);
            });
        });

        /* Onglet initial : #hash, sinon les filtres du journal, sinon le premier */
        var initial = window.location.hash.replace('#', '');
        if (tabNames.indexOf(initial) === -1) {
            initial = /(?:^|[?&])(?:log_status|log_type|page)=/.test(window.location.search)
                ? 'journal' : tabNames[0];
        }
        activateTab(initial);
    }

    /* ---- Interrupteurs auto-soumis (toggles d'automatisation) ---- */
    document.querySelectorAll('input[data-autosubmit]').forEach(function (input) {
        input.addEventListener('change', function () {
            var form = input.form;
            if (!form) return;
            /* Conserve l'onglet actif après la redirection du POST */
            form.action = form.action.split('#')[0] + window.location.hash;
            form.submit();
        });
    });

    /* ---- Filtres auto-soumis (selects du journal) ---- */
    document.querySelectorAll('select[data-autofilter]').forEach(function (select) {
        select.addEventListener('change', function () {
            if (select.form) select.form.submit();
        });
    });

    /* ---- Blocs repliables (bouton play "Exécuter" des automatisations) ---- */
    document.querySelectorAll('[data-collapse-target]').forEach(function (btn) {
        var target = document.getElementById(btn.getAttribute('data-collapse-target'));
        if (!target) return;
        btn.addEventListener('click', function () {
            target.classList.toggle('hidden');
            btn.setAttribute('aria-expanded', target.classList.contains('hidden') ? 'false' : 'true');
        });
    });

    /* ---- Annonces manuelles : l'action dépend de l'élément choisi ----
       data-announce-form contient le gabarit d'URL ({id} = valeur du select). */
    document.querySelectorAll('[data-announce-form]').forEach(function (form) {
        var pattern = form.getAttribute('data-announce-form');
        form.addEventListener('submit', function (event) {
            var select = form.querySelector('select[data-announce-select]');
            var id = select ? select.value : '';
            if (!id || !pattern) {
                event.preventDefault();
                return;
            }
            form.action = pattern.replace('{id}', encodeURIComponent(id));
        });
    });

    /* ---- Panneau de prévisualisation des modèles (onglet Modèles) ---- */
    var previewPanel = document.getElementById('preview-panel');
    var previewFrame = document.getElementById('preview-frame');
    if (previewPanel && previewFrame) {
        var previewTitle = document.getElementById('preview-title');
        document.querySelectorAll('[data-preview-url]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                previewFrame.src = btn.getAttribute('data-preview-url');
                if (previewTitle) {
                    previewTitle.textContent = btn.getAttribute('data-preview-title') || 'Prévisualisation';
                }
                previewPanel.classList.remove('hidden');
                /* Sur écran étroit le panneau est sous les cartes : on l'amène en vue */
                previewPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        });
        var previewClose = document.getElementById('preview-close');
        if (previewClose) {
            previewClose.addEventListener('click', function () {
                previewPanel.classList.add('hidden');
                previewFrame.src = 'about:blank';
            });
        }
    }

    /* =====================================================================
       Éditeur visuel (modèles d'e-mail + réponse aux messages).
       Corps contenteditable ; mise en forme via document.execCommand
       (aucune librairie) ; textarea masqué synchronisé à la soumission.
       Sanitisation côté serveur.
       ===================================================================== */
    function bindRichEditor(options) {
        var editor = document.getElementById(options.editorId);
        var form = document.getElementById(options.formId);
        if (!editor || !form) return null;

        var body = document.getElementById(options.bodyId);
        var toolbar = options.toolbarId ? document.getElementById(options.toolbarId) : null;
        var sourceWrap = options.sourceWrapId ? document.getElementById(options.sourceWrapId) : null;
        var sourceToggle = options.sourceToggleId ? document.getElementById(options.sourceToggleId) : null;
        var cmdRoot = toolbar || document;
        var sourceMode = false;
        var savedRange = null;

        var saveSelection = function () {
            var selection = window.getSelection();
            if (selection.rangeCount > 0 && editor.contains(selection.anchorNode)) {
                savedRange = selection.getRangeAt(0).cloneRange();
            }
        };
        ['keyup', 'mouseup', 'focus', 'blur'].forEach(function (eventName) {
            editor.addEventListener(eventName, saveSelection);
        });

        var restoreSelection = function () {
            editor.focus();
            if (!savedRange) return;
            var selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(savedRange);
        };

        cmdRoot.querySelectorAll('[data-editor-cmd]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var cmd = btn.getAttribute('data-editor-cmd');
                restoreSelection();
                if (cmd === 'createLink') {
                    var url = window.prompt('Adresse du lien (doit commencer par https:// ou http://) :', 'https://');
                    if (!url) return;
                    url = url.trim();
                    if (!/^https?:\/\/.+/i.test(url)) {
                        window.alert('Adresse invalide : le lien doit commencer par https:// ou http://');
                        return;
                    }
                    document.execCommand('createLink', false, url);
                } else {
                    document.execCommand(cmd, false, null);
                }
                saveSelection();
            });
        });

        document.querySelectorAll('[data-editor-var]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                restoreSelection();
                document.execCommand('insertText', false, btn.getAttribute('data-editor-var'));
                saveSelection();
            });
        });

        if (body && sourceWrap && sourceToggle) {
            sourceToggle.addEventListener('click', function () {
                sourceMode = !sourceMode;
                if (sourceMode) {
                    body.value = editor.innerHTML;
                    sourceWrap.classList.remove('hidden');
                    editor.setAttribute('contenteditable', 'false');
                    editor.classList.add('opacity-50');
                    sourceToggle.textContent = 'Revenir au mode visuel';
                } else {
                    editor.innerHTML = body.value;
                    sourceWrap.classList.add('hidden');
                    editor.setAttribute('contenteditable', 'true');
                    editor.classList.remove('opacity-50');
                    sourceToggle.textContent = 'Mode texte (avancé)';
                }
            });
        }

        form.addEventListener('submit', function () {
            if (!sourceMode && body) {
                body.value = editor.innerHTML;
            }
        });

        return {
            sync: function () {
                if (body) body.value = editor.innerHTML;
                return editor.innerHTML;
            }
        };
    }

    bindRichEditor({
        editorId: 'tpl-editor',
        formId: 'tpl-form',
        bodyId: 'tpl-body',
        toolbarId: 'tpl-toolbar',
        sourceWrapId: 'tpl-source-wrap',
        sourceToggleId: 'tpl-source-toggle'
    });

    var replyEditorApi = bindRichEditor({
        editorId: 'reply-editor',
        formId: 'reply-form',
        bodyId: 'reply-body',
        toolbarId: 'reply-toolbar'
    });
    var replyPreviewBtn = document.getElementById('reply-preview-btn');
    var replyPreviewForm = document.getElementById('reply-preview-form');
    var replyPreviewBody = document.getElementById('reply-preview-body');
    var replyPreviewFrame = document.getElementById('reply-preview-frame');
    if (replyPreviewBtn && replyPreviewForm && replyEditorApi) {
        replyPreviewBtn.addEventListener('click', function () {
            var html = replyEditorApi.sync();
            if (replyPreviewBody) replyPreviewBody.value = html;
            if (replyPreviewFrame) replyPreviewFrame.classList.remove('hidden');
            if (typeof replyPreviewForm.requestSubmit === 'function') {
                replyPreviewForm.requestSubmit();
            } else {
                replyPreviewForm.submit();
            }
        });
    }

    /* ---- Soumission avec état « chargement » --------------------------------
       Cible : form[data-loading-submit] OU tout POST admin, sauf
       interrupteurs [data-autosubmit], filtres GET / [data-autofilter],
       et form[data-no-loading] (ex. déconnexion).
       data-confirm / data-announce-form s'exécutent avant (enregistrés plus
       haut) : si preventDefault, on n'active pas le loader.
       État uniquement en mémoire : un rechargement remet les boutons. */
    var LOADING_SPINNER = '<svg class="h-5 w-5 shrink-0 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">'
        + '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>'
        + '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>'
        + '</svg>';

    function loadingLabelFor(source) {
        var explicit = source.getAttribute('data-loading-label');
        if (explicit) return explicit;
        var raw = (source.textContent || source.getAttribute('title') || '').replace(/\s+/g, ' ').trim().toLowerCase();
        if (raw.indexOf('connecter') !== -1) return 'Connexion…';
        if (raw.indexOf('enregistrer') !== -1 || raw.indexOf('créer') !== -1 || raw.indexOf('mettre à jour') !== -1) {
            return 'Enregistrement…';
        }
        if (raw.indexOf('supprimer') !== -1 || raw.indexOf('purger') !== -1 || raw.indexOf('désactiver') !== -1) {
            return 'Suppression…';
        }
        if (raw.indexOf('archiver') !== -1) return 'Archivage…';
        if (raw.indexOf('restaurer') !== -1) return 'Restauration…';
        if (raw.indexOf('publier') !== -1 || raw.indexOf('dépublier') !== -1) return 'Publication…';
        if (raw.indexOf('valider') !== -1 || raw.indexOf('vérifier') !== -1 || raw.indexOf('retenir') !== -1) {
            return 'Validation…';
        }
        if (raw.indexOf('envoyer') !== -1 || raw.indexOf('exécuter') !== -1 || raw.indexOf('annoncer') !== -1
            || raw.indexOf('renvoyer') !== -1) {
            return 'Envoi…';
        }
        if (raw.indexOf('ajouter') !== -1) return 'Envoi…';
        if (raw.indexOf('export') !== -1 || raw.indexOf('excel') !== -1 || raw.indexOf('pdf') !== -1) {
            return 'Export…';
        }
        return 'Envoi…';
    }

    function applySubmitLoading(btn, form) {
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btn.classList.add('pointer-events-none', 'opacity-70');
        var idle = btn.querySelector('[data-loading-idle]');
        var busy = btn.querySelector('[data-loading-busy]');
        if (idle && busy) {
            idle.classList.add('hidden');
            busy.classList.remove('hidden');
            busy.removeAttribute('aria-hidden');
            return;
        }
        var iconOnly = btn.classList.contains('btn-icon') || btn.classList.contains('btn-icon-danger')
            || ((btn.textContent || '').replace(/\s+/g, '') === '');
        if (iconOnly) {
            btn.innerHTML = LOADING_SPINNER.replace('h-5 w-5', 'h-4 w-4');
            return;
        }
        var label = form.getAttribute('data-loading-label') || loadingLabelFor(btn);
        btn.innerHTML = LOADING_SPINNER + '<span>' + label + '</span>';
    }

    function bindLoadingSubmit(form) {
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) return;
            if (form.getAttribute('data-submitting') === '1') {
                event.preventDefault();
                return;
            }
            var buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
            var clicked = event.submitter || form.querySelector('[type="submit"]');
            if (clicked && (clicked.getAttribute('formtarget') || form.getAttribute('target'))) {
                return;
            }
            if (clicked && clicked.disabled) {
                event.preventDefault();
                return;
            }
            form.setAttribute('data-submitting', '1');
            buttons.forEach(function (btn) { applySubmitLoading(btn, form); });
        });
    }

    document.querySelectorAll('form').forEach(function (form) {
        if (form.hasAttribute('data-no-loading')) return;
        if (form.querySelector('[data-autosubmit], [data-autofilter]')) return;
        var method = (form.getAttribute('method') || 'get').toLowerCase();
        var explicit = form.hasAttribute('data-loading-submit');
        if (!explicit && method !== 'post') return;
        bindLoadingSubmit(form);
    });

    /* Exports longs (liens GET) : feedback visuel le temps de la génération. */
    document.querySelectorAll('a[data-loading-export], a[href*="/export"], a[href*="/pdf"]').forEach(function (link) {
        link.addEventListener('click', function () {
            if (link.getAttribute('aria-busy') === 'true') return;
            link.setAttribute('aria-busy', 'true');
            link.classList.add('pointer-events-none', 'opacity-70');
            var label = loadingLabelFor(link);
            link.innerHTML = LOADING_SPINNER + '<span>' + label + '</span>';
        });
    });

    /* ---- Afficher / masquer le mot de passe (bouton oeil) ---- */
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        var input = document.getElementById(btn.getAttribute('data-toggle-password'));
        if (!input) return;
        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            var iconShow = btn.querySelector('[data-icon-show]');
            var iconHide = btn.querySelector('[data-icon-hide]');
            if (iconShow) iconShow.classList.toggle('hidden', show);
            if (iconHide) iconHide.classList.toggle('hidden', !show);
            btn.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        });
    });

    /* =====================================================================
       Graphiques SVG/CSS maison, animés (compatibles CSP : aucune lib CDN).
       Les données arrivent du PHP via attributs data-* (JSON échappé).
       ===================================================================== */
    var SVG_NS = 'http://www.w3.org/2000/svg';

    function readJson(el, attr) {
        try {
            return JSON.parse(el.getAttribute(attr) || '[]');
        } catch (e) {
            return [];
        }
    }

    /* ---- Donut animé (stroke-dasharray) ---- */
    document.querySelectorAll('[data-chart-donut]').forEach(function (el) {
        var data = readJson(el, 'data-chart-donut').filter(function (d) { return d && d.value > 0; });
        var thickness = parseFloat(el.getAttribute('data-thickness') || '5');
        var svg = document.createElementNS(SVG_NS, 'svg');
        svg.setAttribute('viewBox', '0 0 42 42');
        svg.setAttribute('class', 'h-full w-full');

        var R = 15.91549430918954; /* rayon tel que circonférence = 100 */

        /* Piste de fond */
        var track = document.createElementNS(SVG_NS, 'circle');
        track.setAttribute('cx', '21');
        track.setAttribute('cy', '21');
        track.setAttribute('r', String(R));
        track.setAttribute('fill', 'none');
        track.setAttribute('stroke', '#e2e8f0');
        track.setAttribute('stroke-width', String(thickness));
        svg.appendChild(track);

        var total = data.reduce(function (sum, d) { return sum + d.value; }, 0);
        var segments = [];
        var cumulative = 0;

        data.forEach(function (d) {
            var pct = total > 0 ? (d.value / total) * 100 : 0;
            var seg = document.createElementNS(SVG_NS, 'circle');
            seg.setAttribute('cx', '21');
            seg.setAttribute('cy', '21');
            seg.setAttribute('r', String(R));
            seg.setAttribute('fill', 'none');
            seg.setAttribute('stroke', d.color || '#94a3b8');
            seg.setAttribute('stroke-width', String(thickness));
            seg.setAttribute('stroke-linecap', pct >= 99.9 ? 'butt' : 'round');
            /* Départ à 12 h : offset = 25 - cumul avant le segment */
            seg.setAttribute('stroke-dasharray', '0 100');
            seg.setAttribute('stroke-dashoffset', String(25 - cumulative));
            seg.style.transition = 'stroke-dasharray 900ms cubic-bezier(0.4, 0, 0.2, 1)';
            var tip = document.createElementNS(SVG_NS, 'title');
            tip.textContent = (d.label || '') + ' : ' + d.value;
            seg.appendChild(tip);
            svg.appendChild(seg);
            /* -0.4 : mini-espace visuel entre segments (sauf segment unique) */
            var visible = data.length > 1 ? Math.max(pct - 0.9, 0.4) : pct;
            segments.push({ node: seg, dash: visible + ' ' + (100 - visible) });
            cumulative += pct;
        });

        el.appendChild(svg);

        /* Déclenche l'animation une fois le DOM peint */
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                segments.forEach(function (s) {
                    s.node.setAttribute('stroke-dasharray', s.dash);
                });
            });
        });
    });

    /* ---- Diagramme en barres animé ---- */
    document.querySelectorAll('[data-chart-bars]').forEach(function (el) {
        var data = readJson(el, 'data-chart-bars');
        if (!data.length) return;
        var max = data.reduce(function (m, d) { return Math.max(m, d.value); }, 0);

        var chart = document.createElement('div');
        chart.className = 'flex h-44 items-end gap-1 sm:gap-1.5';

        var labels = document.createElement('div');
        labels.className = 'mt-2 flex gap-1 border-t border-slate-100 pt-2 sm:gap-1.5';

        var showEvery = data.length > 8 ? 2 : 1;

        data.forEach(function (d, i) {
            var col = document.createElement('div');
            col.className = 'group relative flex h-full flex-1 items-end justify-center';
            col.title = d.label + ' : ' + d.value + (d.value > 1 ? ' inscriptions' : ' inscription');

            var bubble = document.createElement('span');
            bubble.className = 'pointer-events-none absolute -top-1 left-1/2 z-10 -translate-x-1/2 rounded-md bg-brand-navy px-1.5 py-0.5 text-[10px] font-bold text-white opacity-0 shadow transition-opacity duration-200 group-hover:opacity-100';
            bubble.textContent = String(d.value);
            col.appendChild(bubble);

            var bar = document.createElement('div');
            bar.className = 'w-full max-w-[28px] rounded-t-md transition-all duration-700 ease-out ' +
                (d.value > 0
                    ? 'bg-gradient-to-t from-brand-navy2 to-brand-blue group-hover:from-brand-orange group-hover:to-amber-400'
                    : 'bg-slate-200');
            bar.style.height = '2%';
            bar.style.transitionDelay = (i * 35) + 'ms';
            col.appendChild(bar);
            chart.appendChild(col);

            var lab = document.createElement('span');
            lab.className = 'flex-1 text-center text-[10px] font-medium text-slate-400';
            lab.textContent = (i % showEvery === 0) ? d.label : '';
            labels.appendChild(lab);

            var target = max > 0 ? Math.max((d.value / max) * 100, d.value > 0 ? 6 : 2) : 2;
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    bar.style.height = target + '%';
                });
            });
        });

        el.appendChild(chart);
        el.appendChild(labels);
    });
})();
