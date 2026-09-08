<?php
/** Variables : $filters (call_id, statut), $calls (select), $result */
use App\Models\ProjectApplication;

$badgeClasses = [
    'nouvelle'  => 'bg-sky-50 text-sky-700 ring-sky-200',
    'en_examen' => 'bg-amber-50 text-amber-700 ring-amber-200',
    'retenue'   => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    'refusee'   => 'bg-red-50 text-red-700 ring-red-200',
];
$calls = $calls ?? [];
$query = http_build_query(array_filter($filters, static fn ($v) => $v !== null && $v !== ''));
$query = $query !== '' ? '?' . $query : '';
?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="flex flex-wrap items-center gap-3 text-2xl font-bold text-brand-navy">
            Tous les dépôts
            <span class="rounded-full bg-brand-blue/10 px-3 py-1 align-middle text-sm font-semibold text-brand-blue">
                <?= (int) $result['total'] ?>
            </span>
        </h1>
        <p class="mt-1 text-sm text-slate-500">
            Dossiers d'un appel à incubation ou candidatures spontanées.
            Cliquez sur une ligne pour ouvrir le dossier complet (informations + pitch deck).
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <?php if ((int) $result['total'] > 0): ?>
            <a class="btn-secondary" href="/admin/projets/depots/export<?= e($query) ?>">
                <?= icon('download', 'h-4 w-4') ?> Excel / CSV
            </a>
            <a class="btn-secondary" href="/admin/projets/depots/pdf<?= e($query) ?>">
                <?= icon('document-text', 'h-4 w-4') ?> PDF
            </a>
        <?php endif; ?>
        <a class="btn-ghost" href="/admin/projets">
            <?= icon('arrow-left', 'h-4 w-4') ?> Retour aux projets
        </a>
    </div>
</div>

<form method="get" action="/admin/projets/depots" class="card mb-6 !p-5">
    <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label class="label" for="f-appel">Origine</label>
            <select class="input" id="f-appel" name="call_id">
                <option value="">Tous</option>
                <option value="-1" <?= (int) ($filters['call_id'] ?? 0) === -1 ? 'selected' : '' ?>>
                    Candidatures spontanées
                </option>
                <?php foreach ($calls as $call): ?>
                    <option value="<?= (int) $call['id'] ?>"
                        <?= (int) ($filters['call_id'] ?? 0) === (int) $call['id'] ? 'selected' : '' ?>>
                        <?= e($call['title']) ?><?= !empty($call['archived_at']) ? ' (archivé)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="label" for="f-statut">Statut</label>
            <select class="input" id="f-statut" name="statut">
                <option value="">Tous</option>
                <?php foreach (ProjectApplication::STATUT_LABELS as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($filters['statut'] ?? '') === $key ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button class="btn-secondary flex-1" type="submit">
                <?= icon('funnel', 'h-4 w-4') ?> Filtrer
            </button>
            <a class="btn-ghost" href="/admin/projets/depots" title="Réinitialiser les filtres">
                <?= icon('arrow-path', 'h-4 w-4') ?>
            </a>
        </div>
    </div>
</form>

<div class="card">
    <?php if ($result['rows']): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>#</th><th>Porteur</th><th>Origine</th><th>Téléphone</th><th>PDF</th>
                    <th>Statut</th><th>Date</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['rows'] as $row): ?>
                    <tr>
                        <td class="text-slate-400"><?= (int) $row['id'] ?></td>
                        <td>
                            <a class="flex items-center gap-3"
                               href="/admin/projets/depots/<?= (int) $row['id'] ?>">
                                <?php
                                $rowInitials = strtoupper(mb_substr($row['full_name'], 0, 1));
                                if (preg_match('/^(\S)\S*\s+(\S)/u', $row['full_name'], $m)) {
                                    $rowInitials = strtoupper($m[1] . $m[2]);
                                }
                                ?>
                                <span class="avatar bg-gradient-to-br from-brand-navy2 to-brand-blue">
                                    <?= e($rowInitials) ?>
                                </span>
                                <span class="min-w-0">
                                    <span class="block font-semibold text-brand-navy transition hover:text-brand-blue">
                                        <?= e($row['full_name']) ?>
                                    </span>
                                    <span class="block truncate text-xs text-slate-400"><?= e($row['email']) ?></span>
                                </span>
                            </a>
                        </td>
                        <td class="max-w-[220px]">
                            <span class="block truncate text-slate-600" title="<?= e($row['call_title'] ?: 'Spontanée') ?>">
                                <?= e($row['call_title'] ?: 'Candidature spontanée') ?>
                            </span>
                            <?php if (!empty($row['project_name'])): ?>
                                <span class="block truncate text-xs text-slate-400"><?= e($row['project_name']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="whitespace-nowrap text-slate-600"><?= e($row['phone'] ?: '—') ?></td>
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
                               title="Ouvrir le dossier (infos + PDF)">
                                <?= icon('eye', 'h-4 w-4') ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination((int) $result['page'], (int) $result['pages'], '/admin/projets/depots', $filters) ?>
    <?php else: ?>
        <div class="flex flex-col items-center gap-3 py-12 text-center">
            <?= icon('inbox', 'h-10 w-10 text-slate-300') ?>
            <p class="text-sm text-slate-500">
                <?= $filters
                    ? 'Aucun dépôt ne correspond aux filtres sélectionnés.'
                    : 'Aucun dépôt reçu pour le moment.' ?>
            </p>
        </div>
    <?php endif; ?>
</div>
