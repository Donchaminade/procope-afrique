<?php
/** Variables : $inscription, $mails */
use App\Models\Inscription;

$modules = $inscription['modules'] ? (array) json_decode($inscription['modules'], true) : [];

$badgeClasses = [
    'preinscrit'       => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    'preuve_recue'     => 'bg-amber-50 text-amber-700 ring-amber-200',
    'valide'           => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    'refuse'           => 'bg-red-50 text-red-700 ring-red-200',
    'liste_attente'    => 'bg-slate-100 text-slate-600 ring-slate-300',
    'paiement_partiel' => 'bg-violet-50 text-violet-700 ring-violet-200',
];

$prixFormation = (float) $inscription['formation_prix'];
$deviseLabel = ($inscription['formation_devise'] ?? 'XOF') === 'XOF' ? 'F CFA' : $inscription['formation_devise'];
$proofIsImage = $inscription['payment_proof_mime']
    && str_starts_with((string) $inscription['payment_proof_mime'], 'image/');
$proofUrl = '/admin/inscriptions/' . (int) $inscription['id'] . '/proof';

$initials = strtoupper(mb_substr($inscription['full_name'], 0, 1));
if (preg_match('/^(\S)\S*\s+(\S)/u', $inscription['full_name'], $m)) {
    $initials = strtoupper($m[1] . $m[2]);
}

/** Petite ligne d'information (libellé / valeur). */
$row = static function (string $label, string $valueHtml): string {
    return '<div class="flex flex-col gap-0.5 py-2.5 sm:flex-row sm:items-start sm:gap-4">'
        . '<dt class="w-full shrink-0 text-xs font-semibold uppercase tracking-wide text-slate-400 sm:w-44 sm:pt-0.5">'
        . e($label) . '</dt>'
        . '<dd class="flex-1 text-sm text-slate-700">' . $valueHtml . '</dd></div>';
};
?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-4">
        <span class="avatar h-12 w-12 bg-gradient-to-br from-brand-navy2 to-brand-blue text-base">
            <?= e($initials) ?>
        </span>
        <div>
            <h1 class="flex flex-wrap items-center gap-3 text-2xl font-bold text-brand-navy">
                <?= e($inscription['full_name']) ?>
                <span class="badge <?= $badgeClasses[$inscription['statut']] ?? 'bg-slate-100 text-slate-600 ring-slate-300' ?>">
                    <?= e(Inscription::STATUT_LABELS[$inscription['statut']]) ?>
                </span>
            </h1>
            <p class="mt-0.5 text-sm text-slate-500">
                Inscrit le <?= format_datetime($inscription['created_at']) ?> — <?= e($inscription['formation_titre']) ?>
            </p>
        </div>
    </div>
    <a class="btn-ghost" href="/admin/inscriptions">
        <?= icon('arrow-left', 'h-4 w-4') ?> Retour à la liste
    </a>
</div>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
    <!-- Colonne principale : candidat -->
    <div class="card xl:col-span-2">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('identification', 'h-5 w-5') ?></span>
            Candidat
        </h2>
        <dl class="divide-y divide-slate-100">
            <?= $row('Référence', '<span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs">' . e($inscription['uuid']) . '</span>') ?>
            <?= $row('Formation', '<span class="font-semibold text-brand-navy">' . e($inscription['formation_titre']) . '</span>') ?>
            <?= $row('Sexe', e($inscription['gender'] === 'M' ? 'Masculin' : ($inscription['gender'] === 'F' ? 'Féminin' : '—'))) ?>
            <?= $row('Téléphone (WhatsApp)', '<span class="inline-flex items-center gap-1.5">' . icon('phone', 'h-4 w-4 text-slate-400') . e($inscription['phone']) . '</span>') ?>
            <?= $row('E-mail', $inscription['email']
                ? '<span class="inline-flex items-center gap-1.5">' . icon('envelope', 'h-4 w-4 text-slate-400') . e($inscription['email']) . '</span>'
                : '—') ?>
            <?= $row('Ville / Quartier', e($inscription['city'] ?? '—')) ?>
            <?= $row('Situation professionnelle', e($inscription['professional_status'] ?? '—')) ?>
            <?= $row('Déjà entrepreneur', $inscription['is_entrepreneur'] === null
                ? '—'
                : ((int) $inscription['is_entrepreneur']
                    ? '<span class="badge bg-emerald-50 text-emerald-700 ring-emerald-200">Oui</span>'
                    : '<span class="badge bg-slate-100 text-slate-600 ring-slate-300">Non</span>')) ?>
            <?= $row('Entreprise', e($inscription['company_name'] ?? '—')) ?>
            <?= $row("Secteur d'activité", e($inscription['sector'] ?? '—')) ?>
            <?= $row('Motivation', nl2br(e($inscription['motivation'] ?? '—'))) ?>
            <?= $row('Modules choisis', $modules
                ? implode(' ', array_map(
                    static fn ($mod) => '<span class="badge mb-1 mr-1 bg-sky-50 text-sky-700 ring-sky-200">' . e($mod) . '</span>',
                    $modules
                ))
                : '—') ?>
            <?= $row('Source', e(trim(($inscription['acquisition_source'] ?? '—')
                . ($inscription['acquisition_other'] ? ' — ' . $inscription['acquisition_other'] : '')))) ?>
            <?= $row('Autorisation images', (int) $inscription['consent_image'] ? 'Oui' : 'Non') ?>
            <?= $row('Adresse IP', e($inscription['ip'] ?? '—')) ?>
        </dl>
    </div>

    <!-- Colonne latérale -->
    <div class="space-y-6">
        <div class="card">
            <h2 class="card-title">
                <span class="card-title-icon"><?= icon('credit-card', 'h-5 w-5') ?></span>
                Paiement
            </h2>
            <div class="space-y-4 text-sm">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Modalité choisie</div>
                    <div class="mt-1 font-medium text-slate-700">
                        <?= $inscription['payment_method'] === 'mobile_money' ? 'Mobile Money (TMoney / Flooz)'
                            : ($inscription['payment_method'] === 'ecobank' ? 'Ecobank' : '—') ?>
                    </div>
                </div>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Type de paiement déclaré</div>
                    <div class="mt-1 font-medium text-slate-700">
                        <?= ($inscription['payment_type'] ?? 'total') === 'partiel'
                            ? 'Paiement partiel'
                            : 'Paiement de la totalité' ?>
                        <?php if ($inscription['amount_declared'] !== null): ?>
                            — <?= format_price($inscription['amount_declared'], $deviseLabel) ?> déclarés
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Preuve de dépôt / paiement</div>
                    <?php if ($inscription['payment_proof_path']): ?>
                        <?php if ($proofIsImage): ?>
                            <a href="<?= e($proofUrl) ?>" target="_blank" title="Ouvrir la preuve dans un nouvel onglet"
                               class="mt-2 block w-fit overflow-hidden rounded-xl ring-1 ring-slate-200 transition hover:ring-brand-blue">
                                <img src="<?= e($proofUrl) ?>" alt="Miniature de la preuve de paiement"
                                     class="max-h-56 w-auto max-w-full object-contain">
                            </a>
                        <?php else: ?>
                            <div class="mt-2 flex items-center gap-3 rounded-xl bg-slate-50 p-3 ring-1 ring-inset ring-slate-200">
                                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-500">
                                    <?= icon('document-text', 'h-5 w-5') ?>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-medium text-slate-700">Document PDF</div>
                                    <div class="truncate text-xs text-slate-400"><?= e($inscription['payment_proof_name'] ?? '') ?></div>
                                </div>
                                <a class="btn-secondary btn-sm shrink-0" href="<?= e($proofUrl) ?>" target="_blank">
                                    <?= icon('eye', 'h-4 w-4') ?> Ouvrir le PDF
                                </a>
                            </div>
                            <div class="mt-1.5 truncate text-xs text-slate-400">
                                <?= e($inscription['payment_proof_name'] ?? '') ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="mt-1 text-slate-400">Aucune preuve envoyée</div>
                    <?php endif; ?>
                </div>
                <?php if ($inscription['validated_at']): ?>
                    <div class="rounded-xl bg-slate-50 p-3 text-xs text-slate-500 ring-1 ring-inset ring-slate-200">
                        <span class="inline-flex items-center gap-1.5 font-semibold text-slate-600">
                            <?= icon('clock', 'h-3.5 w-3.5') ?> Dernier changement de statut
                        </span><br>
                        <?= format_datetime($inscription['validated_at']) ?>
                        par <?= e($inscription['validator_name'] ?? '—') ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h2 class="card-title">
                <span class="card-title-icon"><?= icon('shield-check', 'h-5 w-5') ?></span>
                Valider le paiement
            </h2>
            <dl class="mb-4 space-y-2 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Prix de la formation</dt>
                    <dd class="font-semibold text-brand-navy"><?= format_price($prixFormation, $deviseLabel) ?></dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Type de paiement choisi</dt>
                    <dd class="font-medium text-slate-700">
                        <?= ($inscription['payment_type'] ?? 'total') === 'partiel' ? 'Partiel' : 'Totalité' ?>
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Montant déclaré</dt>
                    <dd class="font-medium text-slate-700">
                        <?= $inscription['amount_declared'] !== null
                            ? format_price($inscription['amount_declared'], $deviseLabel) : '—' ?>
                    </dd>
                </div>
                <?php if ($inscription['amount_received'] !== null): ?>
                    <div class="flex items-center justify-between gap-3 rounded-lg bg-violet-50 px-2 py-1.5 ring-1 ring-inset ring-violet-200">
                        <dt class="font-medium text-violet-700">Montant déjà reçu</dt>
                        <dd class="font-semibold text-violet-700">
                            <?= format_price($inscription['amount_received'], $deviseLabel) ?>
                        </dd>
                    </div>
                    <?php if ((float) $inscription['amount_received'] < $prixFormation): ?>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Reste à payer</dt>
                            <dd class="font-semibold text-red-600">
                                <?= format_price($prixFormation - (float) $inscription['amount_received'], $deviseLabel) ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </dl>
            <form method="post" action="/admin/inscriptions/<?= (int) $inscription['id'] ?>/validate-payment"
                  class="space-y-3" data-loading-submit data-loading-label="Validation…">
                <?= csrf_field() ?>
                <div>
                    <label class="label" for="vp-amount">Montant reçu (<?= e($deviseLabel) ?>) *</label>
                    <input class="input" id="vp-amount" type="number" name="amount_received" min="1" step="1" required
                           value="<?= $inscription['amount_received'] !== null
                               ? e((string) (int) $inscription['amount_received'])
                               : ($inscription['amount_declared'] !== null ? e((string) (int) $inscription['amount_declared']) : '') ?>">
                </div>
                <button class="btn-primary w-full" type="submit">
                    <?= icon('shield-check', 'h-4 w-4') ?> Vérifier et valider
                </button>
            </form>
            <p class="mt-3 flex items-start gap-2 text-xs text-slate-400">
                <?= icon('information-circle', 'h-4 w-4 shrink-0') ?>
                Montant reçu ≥ prix : statut « Validé » + mail de confirmation.
                Montant inférieur : statut « Paiement partiel » + mail avec le reste à payer.
            </p>
        </div>

        <div class="card">
            <h2 class="card-title">
                <span class="card-title-icon"><?= icon('arrow-path', 'h-5 w-5') ?></span>
                Changer le statut
            </h2>
            <form method="post" action="/admin/inscriptions/<?= (int) $inscription['id'] ?>/status" class="space-y-3" data-loading-submit data-loading-label="Enregistrement…">
                <?= csrf_field() ?>
                <select class="input" name="statut">
                    <?php foreach (Inscription::STATUT_LABELS as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $inscription['statut'] === $key ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-primary w-full" type="submit">
                    <?= icon('check', 'h-4 w-4') ?> Mettre à jour
                </button>
            </form>
            <p class="mt-3 flex items-start gap-2 text-xs text-slate-400">
                <?= icon('information-circle', 'h-4 w-4 shrink-0') ?>
                « Validé » ou « Refusé » envoie automatiquement un e-mail au candidat (si e-mail renseigné).
                Pour valider un paiement, utilisez plutôt la carte « Valider le paiement » ci-dessus.
            </p>
        </div>

        <div class="card">
            <h2 class="card-title">
                <span class="card-title-icon"><?= icon('paper-airplane', 'h-5 w-5') ?></span>
                Envois manuels
            </h2>
            <?php if ($inscription['email']): ?>
                <div class="space-y-3">
                    <form method="post" action="/admin/inscriptions/<?= (int) $inscription['id'] ?>/send-mail" data-loading-submit data-loading-label="Envoi…">
                        <?= csrf_field() ?>
                        <input type="hidden" name="type" value="renvoi_confirmation">
                        <button class="btn-secondary w-full" type="submit">
                            <?= icon('arrow-path', 'h-4 w-4') ?> Renvoyer la confirmation d'inscription
                        </button>
                    </form>
                    <?php if ($inscription['statut'] === 'paiement_partiel'): ?>
                        <form method="post" action="/admin/inscriptions/<?= (int) $inscription['id'] ?>/send-mail" data-loading-submit data-loading-label="Envoi…">
                            <?= csrf_field() ?>
                            <input type="hidden" name="type" value="rappel_paiement">
                            <button class="btn-secondary w-full" type="submit">
                                <?= icon('banknotes', 'h-4 w-4') ?> Envoyer le rappel de paiement (reste à payer)
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                <p class="mt-3 flex items-start gap-2 text-xs text-slate-400">
                    <?= icon('information-circle', 'h-4 w-4 shrink-0') ?>
                    Envoi immédiat à <?= e($inscription['email']) ?>, même si les automatisations sont désactivées.
                    Chaque envoi est journalisé ci-dessous.
                </p>
            <?php else: ?>
                <p class="flex items-start gap-2 text-sm text-slate-400">
                    <?= icon('exclamation-triangle', 'h-4 w-4 shrink-0 text-amber-400') ?>
                    Aucune adresse e-mail renseignée pour ce candidat : envoi impossible.
                </p>
            <?php endif; ?>
        </div>

        <?php if ($mails): ?>
            <div class="card">
                <h2 class="card-title">
                    <span class="card-title-icon"><?= icon('envelope', 'h-5 w-5') ?></span>
                    E-mails envoyés
                </h2>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($mails as $mail): ?>
                        <li class="flex items-center gap-3 py-2.5 text-sm">
                            <?= $mail['status'] === 'sent'
                                ? icon('check-circle', 'h-5 w-5 shrink-0 text-emerald-500')
                                : icon('x-circle', 'h-5 w-5 shrink-0 text-red-500') ?>
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-slate-700"><?= e($mail['type']) ?></div>
                                <div class="truncate text-xs text-slate-400"><?= e($mail['to_email']) ?></div>
                            </div>
                            <span class="whitespace-nowrap text-xs text-slate-400">
                                <?= format_datetime($mail['sent_at']) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
