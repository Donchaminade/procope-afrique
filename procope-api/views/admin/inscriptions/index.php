<?php
/** Variables : $result (rows,total,page,pages), $filters, $formations */
use App\Models\Inscription;

$query = static function (array $overrides = []) use ($filters): string {
    return http_build_query(array_filter(array_merge($filters, $overrides), fn ($v) => $v !== null && $v !== ''));
};

$badgeClasses = [
    'preinscrit'       => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    'preuve_recue'     => 'bg-amber-50 text-amber-700 ring-amber-200',
    'valide'           => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    'refuse'           => 'bg-red-50 text-red-700 ring-red-200',
    'liste_attente'    => 'bg-slate-100 text-slate-600 ring-slate-300',
    'paiement_partiel' => 'bg-violet-50 text-violet-700 ring-violet-200',
];
?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-brand-navy">
            Inscriptions
            <span class="ml-1 rounded-full bg-brand-blue/10 px-3 py-1 align-middle text-sm font-semibold text-brand-blue">
                <?= (int) $result['total'] ?>
            </span>
        </h1>
        <p class="mt-1 text-sm text-slate-500">Filtrez, consultez et validez les candidatures.</p>
    </div>
    <?php if (\App\Services\Auth::isAtLeast('admin')): ?>
        <a class="btn-secondary" href="/admin/inscriptions/export?<?= e($query()) ?>">
            <?= icon('download', 'h-5 w-5') ?> Exporter Excel
        </a>
    <?php endif; ?>
</div>

<!-- Filtres -->
<form method="get" action="/admin/inscriptions" class="card mb-6 !p-5">
    <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <div>
            <label class="label" for="f-formation">Formation</label>
            <select class="input" id="f-formation" name="formation_id">
                <option value="">Toutes</option>
                <?php foreach ($formations as $formation): ?>
                    <option value="<?= (int) $formation['id'] ?>"
                        <?= (int) ($filters['formation_id'] ?? 0) === (int) $formation['id'] ? 'selected' : '' ?>>
                        <?= e($formation['titre']) ?><?= !empty($formation['archived_at']) ? ' (archivée)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="label" for="f-statut">Statut</label>
            <select class="input" id="f-statut" name="statut">
                <option value="">Tous</option>
                <?php foreach (Inscription::STATUT_LABELS as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($filters['statut'] ?? '') === $key ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="label" for="f-q">Recherche</label>
            <div class="input-icon-wrap">
                <?= icon('search') ?>
                <input class="input" id="f-q" type="text" name="q" placeholder="Nom, téléphone, e-mail"
                       value="<?= e($filters['q'] ?? '') ?>">
            </div>
        </div>
        <div>
            <label class="label" for="f-from">Du</label>
            <input class="input" id="f-from" type="date" name="from" value="<?= e($filters['from'] ?? '') ?>">
        </div>
        <div>
            <label class="label" for="f-to">Au</label>
            <input class="input" id="f-to" type="date" name="to" value="<?= e($filters['to'] ?? '') ?>">
        </div>
        <div class="flex gap-2">
            <button class="btn-secondary flex-1" type="submit">
                <?= icon('funnel', 'h-4 w-4') ?> Filtrer
            </button>
            <a class="btn-ghost" href="/admin/inscriptions" title="Réinitialiser les filtres">
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
                    <th>#</th><th>Candidat</th><th>Téléphone</th><th>Ville</th>
                    <th>Paiement</th><th>Preuve</th><th>Statut</th><th>Date</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['rows'] as $row): ?>
                    <tr>
                        <td class="text-slate-400"><?= (int) $row['id'] ?></td>
                        <td>
                            <div class="flex items-center gap-3">
                                <?php
                                $rowInitials = strtoupper(mb_substr($row['full_name'], 0, 1));
                                if (preg_match('/^(\S)\S*\s+(\S)/u', $row['full_name'], $m)) {
                                    $rowInitials = strtoupper($m[1] . $m[2]);
                                }
                                ?>
                                <span class="avatar bg-gradient-to-br from-brand-navy2 to-brand-blue">
                                    <?= e($rowInitials) ?>
                                </span>
                                <span class="font-semibold text-brand-navy"><?= e($row['full_name']) ?></span>
                            </div>
                        </td>
                        <td class="whitespace-nowrap"><?= e($row['phone']) ?></td>
                        <td><?= e($row['city'] ?? '—') ?></td>
                        <td class="whitespace-nowrap">
                            <?= $row['payment_method'] === 'mobile_money' ? 'Mobile Money'
                                : ($row['payment_method'] === 'ecobank' ? 'Ecobank' : '—') ?>
                        </td>
                        <td>
                            <?php if ($row['payment_proof_path']): ?>
                                <span class="inline-flex items-center gap-1 font-medium text-emerald-600">
                                    <?= icon('paper-clip', 'h-4 w-4') ?> Oui
                                </span>
                            <?php else: ?>
                                <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $badgeClasses[$row['statut']] ?? 'bg-slate-100 text-slate-600 ring-slate-300' ?>">
                                <?= e(Inscription::STATUT_LABELS[$row['statut']]) ?>
                            </span>
                        </td>
                        <td class="whitespace-nowrap text-slate-500"><?= format_datetime($row['created_at']) ?></td>
                        <td class="text-right">
                            <a class="btn-icon" href="/admin/inscriptions/<?= (int) $row['id'] ?>" title="Voir le détail">
                                <?= icon('eye', 'h-4 w-4') ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($result['pages'] > 1): ?>
            <?php
            $page = (int) $result['page'];
            $pages = (int) $result['pages'];
            $window = 2;
            ?>
            <nav class="mt-6 flex flex-wrap items-center justify-center gap-1.5" aria-label="Pagination">
                <a class="page-btn <?= $page <= 1 ? 'page-btn-disabled' : '' ?>"
                   href="/admin/inscriptions?<?= e($query(['page' => max(1, $page - 1)])) ?>" title="Page précédente">
                    <?= icon('chevron-left', 'h-4 w-4') ?>
                </a>
                <?php
                $shown = [];
                for ($p = 1; $p <= $pages; $p++) {
                    if ($p === 1 || $p === $pages || abs($p - $page) <= $window) {
                        $shown[] = $p;
                    }
                }
                $prev = 0;
                foreach ($shown as $p):
                    if ($p - $prev > 1): ?>
                        <span class="px-1 text-slate-400">…</span>
                    <?php endif; ?>
                    <?php if ($p === $page): ?>
                        <span class="page-btn page-btn-active" aria-current="page"><?= $p ?></span>
                    <?php else: ?>
                        <a class="page-btn" href="/admin/inscriptions?<?= e($query(['page' => $p])) ?>"><?= $p ?></a>
                    <?php endif; ?>
                    <?php $prev = $p; ?>
                <?php endforeach; ?>
                <a class="page-btn <?= $page >= $pages ? 'page-btn-disabled' : '' ?>"
                   href="/admin/inscriptions?<?= e($query(['page' => min($pages, $page + 1)])) ?>" title="Page suivante">
                    <?= icon('chevron-right', 'h-4 w-4') ?>
                </a>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <div class="flex flex-col items-center gap-3 py-12 text-center">
            <?= icon('inbox', 'h-10 w-10 text-slate-300') ?>
            <p class="text-sm text-slate-500">Aucune inscription ne correspond à ces critères.</p>
            <a class="btn-ghost btn-sm" href="/admin/inscriptions">
                <?= icon('arrow-path', 'h-4 w-4') ?> Réinitialiser les filtres
            </a>
        </div>
    <?php endif; ?>
</div>
