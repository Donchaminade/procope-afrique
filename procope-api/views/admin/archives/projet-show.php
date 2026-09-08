<?php
/** Variables : $project (archivé), $applications (avec project_title) */
use App\Models\IncubatedProject;
use App\Models\ProjectApplication;

$badgeClasses = [
    'nouvelle'  => 'bg-sky-50 text-sky-700 ring-sky-200',
    'en_examen' => 'bg-amber-50 text-amber-700 ring-amber-200',
    'retenue'   => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    'refusee'   => 'bg-red-50 text-red-700 ring-red-200',
];
$byStatut = array_fill_keys(ProjectApplication::STATUTS, 0);
foreach ($applications as $a) {
    $byStatut[$a['statut']] = ($byStatut[$a['statut']] ?? 0) + 1;
}
?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-brand-navy"><?= e($project['title']) ?></h1>
        <p class="mt-1 flex items-center gap-1.5 text-sm text-slate-500">
            <?= icon('archive-box', 'h-4 w-4') ?>
            Projet archivé le <?= format_datetime($project['archived_at']) ?>
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <a class="btn-ghost" href="/admin/archives">
            <?= icon('arrow-left', 'h-4 w-4') ?> Retour aux archives
        </a>
        <?php if (\App\Services\Auth::isAtLeast('admin')): ?>
            <form method="post" action="/admin/archives/projets/<?= (int) $project['id'] ?>/restore"
                  data-loading-submit data-loading-label="Restauration…"
                  data-confirm="Restaurer ce projet ? Il réapparaîtra dans la liste des projets (non publié).">
                <?= csrf_field() ?>
                <button class="btn-primary" type="submit">
                    <?= icon('arrow-path', 'h-4 w-4') ?> Restaurer
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="card lg:col-span-2">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('rocket', 'h-5 w-5') ?></span>
            Détails du projet
        </h2>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="font-semibold text-slate-500">Secteur</dt>
                <dd class="mt-0.5 text-slate-700"><?= e($project['sector'] ?: '—') ?></dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-500">Stade</dt>
                <dd class="mt-0.5 text-slate-700">
                    <?= e(IncubatedProject::STAGE_LABELS[$project['stage'] ?? ''] ?? ($project['stage'] ?: '—')) ?>
                </dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-500">Pays</dt>
                <dd class="mt-0.5 text-slate-700"><?= e($project['country'] ?: '—') ?></dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-500">Année</dt>
                <dd class="mt-0.5 text-slate-700"><?= e($project['year'] ?: '—') ?></dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-500">Créé le</dt>
                <dd class="mt-0.5 text-slate-700"><?= format_datetime($project['created_at']) ?></dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-500">Archivé le</dt>
                <dd class="mt-0.5 text-slate-700"><?= format_datetime($project['archived_at']) ?></dd>
            </div>
            <?php if (!empty($project['pitch'])): ?>
                <div class="sm:col-span-2">
                    <dt class="font-semibold text-slate-500">Pitch</dt>
                    <dd class="mt-0.5 whitespace-pre-line text-slate-700"><?= e($project['pitch']) ?></dd>
                </div>
            <?php endif; ?>
            <?php if (!empty($project['description'])): ?>
                <div class="sm:col-span-2">
                    <dt class="font-semibold text-slate-500">Description</dt>
                    <dd class="mt-0.5 whitespace-pre-line text-slate-700"><?= e($project['description']) ?></dd>
                </div>
            <?php endif; ?>
        </dl>
    </div>

    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('chart-bar', 'h-5 w-5') ?></span>
            Bilan des dépôts
        </h2>
        <ul class="space-y-2.5">
            <?php foreach (ProjectApplication::STATUT_LABELS as $key => $label): ?>
                <li class="flex items-center gap-2.5 text-sm">
                    <span class="badge <?= $badgeClasses[$key] ?>"><?= e($label) ?></span>
                    <span class="ml-auto font-semibold text-brand-navy"><?= (int) $byStatut[$key] ?></span>
                </li>
            <?php endforeach; ?>
            <li class="flex items-center gap-2.5 border-t border-slate-100 pt-2.5 text-sm">
                <span class="text-slate-500">Total</span>
                <span class="ml-auto font-bold text-brand-navy"><?= count($applications) ?></span>
            </li>
        </ul>
    </div>
</div>

<div class="card">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="card-title mb-0">
            <span class="card-title-icon"><?= icon('clipboard-list', 'h-5 w-5') ?></span>
            Dépôts
            <span class="ml-1 rounded-full bg-brand-blue/10 px-2.5 py-0.5 text-sm font-semibold text-brand-blue">
                <?= count($applications) ?>
            </span>
        </h2>
        <?php if ($applications): ?>
            <div class="flex flex-wrap gap-2">
                <a class="btn-secondary btn-sm" href="/admin/archives/projets/<?= (int) $project['id'] ?>/export">
                    <?= icon('download', 'h-4 w-4') ?> Excel / CSV
                </a>
                <a class="btn-secondary btn-sm" href="/admin/archives/projets/<?= (int) $project['id'] ?>/pdf">
                    <?= icon('document-text', 'h-4 w-4') ?> PDF
                </a>
                <a class="btn-secondary btn-sm"
                   href="/admin/archives/projets/<?= (int) $project['id'] ?>/pdf?statut=retenue">
                    <?= icon('star', 'h-4 w-4') ?> PDF des retenus
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($applications): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Porteur</th><th>Téléphone</th><th>E-mail</th><th>PDF</th>
                    <th>Statut</th><th>Date</th>
                    <th class="text-right">Fiche</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($applications as $row): ?>
                    <tr>
                        <td class="font-semibold text-brand-navy"><?= e($row['full_name']) ?></td>
                        <td class="whitespace-nowrap"><?= e($row['phone'] ?: '—') ?></td>
                        <td><?= e($row['email']) ?></td>
                        <td>
                            <?php if ($row['file_path']): ?>
                                <span class="inline-flex items-center gap-1.5 text-sm text-slate-600">
                                    <?= icon('paper-clip', 'h-4 w-4') ?> Oui
                                </span>
                            <?php else: ?>
                                <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $badgeClasses[$row['statut']] ?? 'bg-slate-100 text-slate-600 ring-slate-300' ?>">
                                <?= e(ProjectApplication::STATUT_LABELS[$row['statut']] ?? $row['statut']) ?>
                            </span>
                        </td>
                        <td class="whitespace-nowrap text-slate-500"><?= format_datetime($row['created_at']) ?></td>
                        <td class="text-right">
                            <a class="btn-icon" href="/admin/projets/depots/<?= (int) $row['id'] ?>"
                               title="Voir le dossier complet">
                                <?= icon('eye', 'h-4 w-4') ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="flex flex-col items-center gap-3 py-12 text-center">
            <?= icon('inbox', 'h-10 w-10 text-slate-300') ?>
            <p class="text-sm text-slate-500">Aucun dépôt reçu pour ce projet archivé.</p>
        </div>
    <?php endif; ?>
</div>
