/**
 * PROCOPE — Page d'inscription formation (inscription-formation.html)
 * Charge la config depuis procope-api et soumet le formulaire en multipart.
 * Aucune donnée sensible côté front : tout est validé côté serveur.
 */
(function () {
    'use strict';

    // URL de l'API centralisée dans js/api-config.js (window.PROCOPE_API_BASE),
    // à inclure avant ce script ; fallback local si la config manque.
    var API_BASE = window.PROCOPE_API_BASE || 'http://127.0.0.1:8088';

    var els = {
        loading: document.getElementById('if-loading'),
        closed: document.getElementById('if-closed'),
        closedMessage: document.getElementById('if-closed-message'),
        error: document.getElementById('if-error'),
        success: document.getElementById('if-success'),
        successMessage: document.getElementById('if-success-message'),
        successRef: document.getElementById('if-success-ref'),
        content: document.getElementById('if-content'),
        form: document.getElementById('inscription-form'),
        formError: document.getElementById('if-form-error'),
        submit: document.getElementById('if-submit')
    };

    if (!els.form) { return; }

    function show(state) {
        ['loading', 'closed', 'error', 'success', 'content'].forEach(function (key) {
            els[key].classList.toggle('d-none', key !== state);
        });
        if (state !== 'loading') { els.loading.classList.add('d-none'); }
    }

    function esc(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function formatHour(datetime) {
        var d = new Date(datetime.replace(' ', 'T'));
        if (isNaN(d)) { return ''; }
        var h = String(d.getHours()).padStart(2, '0');
        var m = String(d.getMinutes()).padStart(2, '0');
        return h + 'h' + m;
    }

    function formatPrice(amount, devise) {
        var formatted = Number(amount).toLocaleString('fr-FR');
        return formatted + ' ' + (devise === 'XOF' || !devise ? 'F CFA' : devise);
    }

    // Prix de la formation active (pour valider le montant partiel côté front)
    var formationPrix = 0;
    var formationDevise = 'XOF';

    function renderFormation(data) {
        var f = data.formation;
        formationPrix = Number(f.prix) || 0;
        formationDevise = f.devise || 'XOF';
        document.getElementById('if-titre').textContent = f.titre;

        // Affiche de la formation (si configurée dans l'admin)
        var afficheWrap = document.getElementById('if-affiche');
        if (afficheWrap && f.affiche_url) {
            var img = document.createElement('img');
            img.src = f.affiche_url;
            img.alt = f.titre;
            img.loading = 'lazy';
            img.className = 'img-fluid rounded shadow w-100';
            afficheWrap.innerHTML = '';
            afficheWrap.appendChild(img);
            afficheWrap.classList.remove('d-none');
        }
        document.getElementById('if-intro').textContent = f.intro || '';
        document.getElementById('if-prix').textContent = formatPrice(f.prix, f.devise);
        document.getElementById('if-lieu').textContent = f.lieu || 'À confirmer';
        document.getElementById('if-phone').textContent = f.contact_phone || '+228 96 45 76 95';

        document.getElementById('if-slots').innerHTML = (f.slots || []).map(function (slot) {
            return '<li class="mb-2"><i class="fa fa-clock text-primary me-2" aria-hidden="true"></i>' +
                '<strong>' + esc(slot.label) + '</strong><br>' +
                '<span class="ms-4">De ' + formatHour(slot.starts_at) + ' à ' + formatHour(slot.ends_at) + '</span></li>';
        }).join('');

        document.getElementById('if-programme').innerHTML = (f.programme || []).map(function (item) {
            return '<li class="mb-2"><i class="fa fa-check text-primary me-2" aria-hidden="true"></i>' + esc(item) + '</li>';
        }).join('');

        // Modules (checkboxes)
        document.getElementById('if-modules').innerHTML = (data.options.modules || []).map(function (mod, index) {
            var id = 'if-module-' + index;
            return '<div class="form-check">' +
                '<input class="form-check-input" type="checkbox" name="modules[]" id="' + id + '" value="' + esc(mod) + '">' +
                '<label class="form-check-label" for="' + id + '">' + esc(mod) + '</label></div>';
        }).join('');

        // Modalités de paiement (radios)
        document.getElementById('if-payments').innerHTML = (data.options.payment_methods || []).map(function (pm, index) {
            var id = 'if-payment-' + index;
            return '<div class="form-check">' +
                '<input class="form-check-input" type="radio" name="payment_method" id="' + id + '" value="' + esc(pm.value) + '" required>' +
                '<label class="form-check-label" for="' + id + '">' + esc(pm.label) + '</label></div>';
        }).join('');

        // Sources (radios + champ "Autre")
        document.getElementById('if-sources').innerHTML = (data.options.sources || []).map(function (src, index) {
            var id = 'if-source-' + index;
            return '<div class="form-check form-check-inline">' +
                '<input class="form-check-input" type="radio" name="acquisition_source" id="' + id + '" value="' + esc(src) + '">' +
                '<label class="form-check-label" for="' + id + '">' + esc(src) + '</label></div>';
        }).join('');

        var otherInput = document.getElementById('if-source-other');
        els.form.querySelectorAll('input[name="acquisition_source"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                otherInput.classList.toggle('d-none', radio.value !== 'Autre');
            });
        });

        // Paiement total / partiel
        document.getElementById('if-paytype-prix').textContent = formatPrice(formationPrix, formationDevise);
        var amountWrap = document.getElementById('if-amount-wrap');
        var amountInput = document.getElementById('if-amount');
        document.getElementById('if-amount-hint').textContent =
            'Montant supérieur à 0 et inférieur à ' + formatPrice(formationPrix, formationDevise) +
            '. Le solde sera à compléter avant la formation.';
        amountInput.max = String(Math.max(1, formationPrix - 1));
        els.form.querySelectorAll('input[name="payment_type"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                var partiel = radio.value === 'partiel' && radio.checked;
                amountWrap.classList.toggle('d-none', !partiel);
                amountInput.required = partiel;
                if (!partiel) { amountInput.value = ''; }
            });
        });
    }

    function showFormError(message) {
        els.formError.textContent = message;
        els.formError.classList.remove('d-none');
        els.formError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function submitForm(event) {
        event.preventDefault();
        els.formError.classList.add('d-none');

        if (!els.form.reportValidity()) { return; }

        // Validation du montant partiel (le serveur re-vérifie de toute façon)
        var paytype = els.form.querySelector('input[name="payment_type"]:checked');
        if (paytype && paytype.value === 'partiel') {
            var amount = Number(document.getElementById('if-amount').value);
            if (!amount || amount <= 0 || amount >= formationPrix) {
                showFormError('Pour un paiement partiel, indiquez un montant supérieur à 0 et inférieur à '
                    + formatPrice(formationPrix, formationDevise) + '.');
                return;
            }
        }

        var formData = new FormData(els.form);
        els.submit.disabled = true;
        els.submit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Envoi en cours…';

        fetch(API_BASE + '/api/inscriptions', { method: 'POST', body: formData })
            .then(function (response) {
                return response.json().then(function (json) { return { status: response.status, json: json }; });
            })
            .then(function (result) {
                if (result.json.ok) {
                    els.successMessage.textContent = result.json.message;
                    els.successRef.textContent = result.json.reference || '';
                    show('success');
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return;
                }
                var message = result.json.error || 'Une erreur est survenue. Réessayez.';
                if (result.json.fields) {
                    var details = Object.keys(result.json.fields).map(function (key) {
                        return result.json.fields[key];
                    });
                    message += ' ' + details.join(' ');
                }
                showFormError(message);
            })
            .catch(function () {
                showFormError('Connexion impossible au serveur d\'inscription. Vérifiez votre connexion et réessayez.');
            })
            .finally(function () {
                els.submit.disabled = false;
                els.submit.innerHTML = '<i class="fa fa-paper-plane me-2" aria-hidden="true"></i>Confirmer mon inscription';
            });
    }

    function init() {
        fetch(API_BASE + '/api/formations/active')
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.open) {
                    els.closedMessage.textContent = data.message || 'Les inscriptions sont actuellement fermées.';
                    show('closed');
                    return;
                }
                renderFormation(data);
                show('content');
            })
            .catch(function () { show('error'); });

        els.form.addEventListener('submit', submitForm);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
