/* JS admin PROCOPE (CSP : pas de JS inline, pas de CDN) */
(function () {
    'use strict';

    /* ---- Confirmation avant soumission (formulaires destructifs) ---- */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

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
       Éditeur visuel des modèles d'e-mail (page template-edit).
       Corps contenteditable dans le cadre réel de l'e-mail ; mise en forme
       via document.execCommand (aucune librairie) ; pastilles {{variables}}
       insérées à la position du curseur ; textarea masqué synchronisé à la
       soumission (mode texte avancé disponible). Sanitisation côté serveur.
       ===================================================================== */
    var tplEditor = document.getElementById('tpl-editor');
    var tplForm = document.getElementById('tpl-form');
    if (tplEditor && tplForm) {
        var tplBody = document.getElementById('tpl-body');
        var tplSourceWrap = document.getElementById('tpl-source-wrap');
        var tplSourceToggle = document.getElementById('tpl-source-toggle');
        var tplSourceMode = false;
        var tplSavedRange = null;

        /* La sélection est perdue quand on clique un bouton hors de la zone
           éditable : on la mémorise en continu pour la restaurer avant chaque
           commande ou insertion de variable. */
        var tplSaveSelection = function () {
            var selection = window.getSelection();
            if (selection.rangeCount > 0 && tplEditor.contains(selection.anchorNode)) {
                tplSavedRange = selection.getRangeAt(0).cloneRange();
            }
        };
        ['keyup', 'mouseup', 'focus', 'blur'].forEach(function (eventName) {
            tplEditor.addEventListener(eventName, tplSaveSelection);
        });

        var tplRestoreSelection = function () {
            tplEditor.focus();
            if (!tplSavedRange) return;
            var selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(tplSavedRange);
        };

        document.querySelectorAll('[data-editor-cmd]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var cmd = btn.getAttribute('data-editor-cmd');
                tplRestoreSelection();
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
                tplSaveSelection();
            });
        });

        document.querySelectorAll('[data-editor-var]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                tplRestoreSelection();
                document.execCommand('insertText', false, btn.getAttribute('data-editor-var'));
                tplSaveSelection();
            });
        });

        /* Mode texte (avancé) : bascule visuel <-> textarea brut */
        if (tplBody && tplSourceWrap && tplSourceToggle) {
            tplSourceToggle.addEventListener('click', function () {
                tplSourceMode = !tplSourceMode;
                if (tplSourceMode) {
                    tplBody.value = tplEditor.innerHTML;
                    tplSourceWrap.classList.remove('hidden');
                    tplEditor.setAttribute('contenteditable', 'false');
                    tplEditor.classList.add('opacity-50');
                    tplSourceToggle.textContent = 'Revenir au mode visuel';
                } else {
                    tplEditor.innerHTML = tplBody.value;
                    tplSourceWrap.classList.add('hidden');
                    tplEditor.setAttribute('contenteditable', 'true');
                    tplEditor.classList.remove('opacity-50');
                    tplSourceToggle.textContent = 'Mode texte (avancé)';
                }
            });
        }

        /* À la soumission, le HTML de l'éditeur alimente le champ body
           (sauf en mode texte, où le textarea fait foi). */
        tplForm.addEventListener('submit', function () {
            if (!tplSourceMode && tplBody) {
                tplBody.value = tplEditor.innerHTML;
            }
        });
    }

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
