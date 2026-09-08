<?php
/**
 * Variables : $application, $history (audit_logs job_application.status)
 * Écran scindé : infos du dossier à gauche, CV affiché en simultané à droite.
 */
use App\Models\JobApplication;

$badgeClasses = [
    'nouvelle'  => 'bg-sky-50 text-sky-700 ring-sky-200',
    'en_examen' => 'bg-amber-50 text-amber-700 ring-amber-200',
    'retenue'   => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    'refusee'   => 'bg-red-50 text-red-700 ring-red-200',
];

$initials = strtoupper(mb_substr($application['full_name'], 0, 1));
if (preg_match('/^(\S)\S*\s+(\S)/u', $application['full_name'], $m)) {
    $initials = strtoupper($m[1] . $m[2]);
}
$phoneDigits = preg_replace('/[^\d+]/', '', (string) ($application['phone'] ?? ''));
$statusUrl = '/admin/emplois/candidatures/' . (int) $application['id'] . '/status';
?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-4">
        <span class="avatar h-12 w-12 bg-gradient-to-br from-brand-navy2 to-brand-blue text-base">
            <?= e($initials) ?>
        </span>
        <div>
            <h1 class="flex flex-wrap items-center gap-3 text-2xl font-bold text-brand-navy">
                <?= e($application['full_name']) ?>
                <span class="badge <?= $badgeClasses[$application['statut']] ?? 'bg-slate-100 text-slate-600 ring-slate-300' ?>">
                    <?= e(JobApplication::STATUT_LABELS[$application['statut']] ?? $application['statut']) ?>
                </span>
            </h1>
            <p class="mt-0.5 text-sm text-slate-500">
                Candidature reçue le <?= format_datetime($application['created_at']) ?>
                — Offre : <?= e($application['offer_title']) ?>
            </p>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <?php if ($application['statut'] !== 'retenue'): ?>
            <form method="post" action="<?= e($statusUrl) ?>"
                  data-loading-submit data-loading-label="Validation…"
                  data-confirm="Retenir <?= e($application['full_name']) ?> pour la prochaine phase du recrutement ?">
                <?= csrf_field() ?>
                <input type="hidden" name="statut" value="retenue">
                <button class="btn-primary" type="submit">
                    <?= icon('check-circle', 'h-4 w-4') ?> Retenir pour la prochaine phase
                </button>
            </form>
        <?php endif; ?>
        <?php if ($application['statut'] !== 'refusee'): ?>
            <form method="post" action="<?= e($statusUrl) ?>"
                  data-loading-submit data-loading-label="Enregistrement…"
                  data-confirm="Ne pas retenir la candidature de <?= e($application['full_name']) ?> ?">
                <?= csrf_field() ?>
                <input type="hidden" name="statut" value="refusee">
                <button class="btn-danger" type="submit">
                    <?= icon('x-circle', 'h-4 w-4') ?> Ne pas retenir
                </button>
            </form>
        <?php endif; ?>
        <a class="btn-ghost" href="/admin/emplois/<?= (int) $application['offer_id'] ?>/candidatures">
            <?= icon('arrow-left', 'h-4 w-4') ?> Retour
        </a>
    </div>
</div>

<!-- Écran scindé : dossier à gauche, CV à droite -->
<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <!-- GAUCHE : toutes les informations du dossier -->
    <div class="space-y-6">
        <div class="card">
            <h2 class="card-title">
                <span class="card-title-icon"><?= icon('identification', 'h-5 w-5') ?></span>
                Informations du candidat
            </h2>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">E-mail</dt>
                    <dd class="mt-1">
                        <a class="inline-flex items-center gap-1.5 font-medium text-brand-blue transition hover:underline"
                           href="mailto:<?= e($application['email']) ?>">
                            <?= icon('envelope', 'h-4 w-4') ?> <?= e($application['email']) ?>
                        </a>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Téléphone</dt>
                    <dd class="mt-1">
                        <?php if ($application['phone']): ?>
                            <a class="inline-flex items-center gap-1.5 font-medium text-brand-blue transition hover:underline"
                               href="tel:<?= e($phoneDigits) ?>">
                                <?= icon('phone', 'h-4 w-4') ?> <?= e($application['phone']) ?>
                            </a>
                        <?php else: ?>
                            <span class="text-slate-400">Non renseigné</span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Offre liée</dt>
                    <dd class="mt-1 font-medium text-slate-700"><?= e($application['offer_title']) ?></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Déposée le</dt>
                    <dd class="mt-1 text-slate-700"><?= format_datetime($application['created_at']) ?></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Adresse IP</dt>
                    <dd class="mt-1 text-slate-700"><?= e($application['ip'] ?? '—') ?></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Navigateur</dt>
                    <dd class="mt-1 break-all text-xs text-slate-500"><?= e($application['user_agent'] ?? '—') ?></dd>
                </div>
            </dl>
        </div>

        <div class="card">
            <h2 class="card-title">
                <span class="card-title-icon"><?= icon('document-text', 'h-5 w-5') ?></span>
                Message / motivation
            </h2>
            <?php if (trim((string) $application['message']) !== ''): ?>
                <div class="whitespace-pre-line rounded-xl bg-slate-50 p-5 text-sm leading-relaxed text-slate-700 ring-1 ring-inset ring-slate-200">
                    <?= e($application['message']) ?>
                </div>
            <?php else: ?>
                <p class="rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-400 ring-1 ring-inset ring-slate-200">
                    Aucun message fourni par le candidat.
                </p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 class="card-title">
                <span class="card-title-icon"><?= icon('arrow-path', 'h-5 w-5') ?></span>
                Statut de la candidature
            </h2>
            <form method="post" action="<?= e($statusUrl) ?>" class="flex flex-col gap-3 sm:flex-row" data-loading-submit data-loading-label="Enregistrement…">
                <?= csrf_field() ?>
                <select class="input flex-1" name="statut" aria-label="Nouveau statut">
                    <?php foreach (JobApplication::STATUT_LABELS as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $application['statut'] === $key ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-secondary" type="submit">
                    <?= icon('check', 'h-4 w-4') ?> Mettre à jour
                </button>
            </form>
            <p class="mt-3 flex items-start gap-2 text-xs text-slate-400">
                <?= icon('information-circle', 'h-4 w-4 shrink-0') ?>
                Une candidature « Nouvelle » passe automatiquement « En examen » dès sa première ouverture.
                Les e-mails « retenue » / « non retenue » ne partent que si l'automatisation correspondante
                est activée et que le statut change réellement.
            </p>

            <?php if ($history): ?>
                <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-400">Historique de statut</h3>
                <ol class="mt-2 space-y-2">
                    <?php foreach ($history as $entry): ?>
                        <?php $meta = $entry['meta'] ? (array) json_decode((string) $entry['meta'], true) : []; ?>
                        <li class="flex flex-wrap items-center gap-2 text-sm text-slate-600">
                            <?= icon('clock', 'h-3.5 w-3.5 shrink-0 text-slate-400') ?>
                            <span class="text-xs text-slate-400"><?= format_datetime($entry['created_at']) ?></span>
                            <span>
                                <?= e(JobApplication::STATUT_LABELS[$meta['from'] ?? ''] ?? ($meta['from'] ?? '?')) ?>
                                →
                                <strong><?= e(JobApplication::STATUT_LABELS[$meta['to'] ?? ''] ?? ($meta['to'] ?? '?')) ?></strong>
                            </span>
                            <span class="text-xs text-slate-400">
                                <?= !empty($meta['mode']) ? '(' . e($meta['mode']) . ')' : 'par ' . e($entry['user_name'] ?? 'Système') ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </div>

    <!-- DROITE : CV affiché en simultané -->
    <div class="card flex flex-col">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="card-title mb-0">
                <span class="card-title-icon"><?= icon('paper-clip', 'h-5 w-5') ?></span>
                CV du candidat
            </h2>
            <?php if ($application['cv_path']): ?>
                <a class="btn-ghost btn-sm"
                   href="/admin/emplois/candidatures/<?= (int) $application['id'] ?>/cv">
                    <?= icon('eye', 'h-4 w-4') ?> Plein écran
                </a>
            <?php endif; ?>
        </div>
        <?php if ($application['cv_path']): ?>
            <p class="mb-3 truncate text-xs text-slate-400"><?= e($application['cv_name'] ?: 'CV.pdf') ?></p>
            <iframe src="/admin/emplois/candidatures/<?= (int) $application['id'] ?>/cv/fichier"
                    title="CV de <?= e($application['full_name']) ?>"
                    class="min-h-[70vh] w-full flex-1 rounded-xl bg-slate-100 ring-1 ring-inset ring-slate-200"></iframe>
        <?php else: ?>
            <div class="flex min-h-[70vh] flex-1 flex-col items-center justify-center gap-3 rounded-xl bg-slate-50 text-center ring-1 ring-inset ring-slate-200">
                <?= icon('document-text', 'h-12 w-12 text-slate-300') ?>
                <p class="text-sm font-medium text-slate-500">Aucun CV joint à cette candidature</p>
                <p class="max-w-xs text-xs text-slate-400">
                    Le candidat n'a pas déposé de CV. Vous pouvez le contacter par e-mail ou téléphone
                    pour compléter le dossier.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>
