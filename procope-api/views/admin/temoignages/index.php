<?php
/** Variables : $result (rows,total,page,pages), $filters */
use App\Models\Testimonial;

$query = static function (array $overrides = []) use ($filters): string {
    return http_build_query(array_filter(array_merge($filters, $overrides), fn ($v) => $v !== null && $v !== ''));
};

$badgeClasses = [
    'en_attente' => 'bg-amber-50 text-amber-700 ring-amber-200',
    'publie'     => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    'refuse'     => 'bg-red-50 text-red-700 ring-red-200',
];
$sourceClasses = [
    'public' => 'bg-sky-50 text-sky-700 ring-sky-200',
    'admin'  => 'bg-slate-100 text-slate-600 ring-slate-300',
];
?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-brand-navy">
            Témoignages
            <span class="ml-1 rounded-full bg-brand-blue/10 px-3 py-1 align-middle text-sm font-semibold text-brand-blue">
                <?= (int) $result['total'] ?>
            </span>
        </h1>
        <p class="mt-1 text-sm text-slate-500">Dépôts publics à valider, et témoignages publiés sur le site.</p>
    </div>
    <a class="btn-primary" href="/admin/temoignages/create">
        <?= icon('plus', 'h-4 w-4') ?> Nouveau témoignage
    </a>
</div>

<form method="get" action="/admin/temoignages" class="card mb-6 !p-5">
    <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div>
            <label class="label" for="f-statut">Statut</label>
            <select class="input" id="f-statut" name="statut">
                <option value="">Tous</option>
                <?php foreach (Testimonial::STATUT_LABELS as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($filters['statut'] ?? '') === $key ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="label" for="f-source">Source</label>
            <select class="input" id="f-source" name="source">
                <option value="">Toutes</option>
                <?php foreach (Testimonial::SOURCE_LABELS as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($filters['source'] ?? '') === $key ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="label" for="f-q">Recherche</label>
            <div class="input-icon-wrap">
                <?= icon('search') ?>
                <input class="input" id="f-q" type="text" name="q" placeholder="Nom, rôle, texte, e-mail"
                       value="<?= e($filters['q'] ?? '') ?>">
            </div>
        </div>
        <div class="flex gap-2">
            <button class="btn-secondary flex-1" type="submit">
                <?= icon('funnel', 'h-4 w-4') ?> Filtrer
            </button>
            <a class="btn-ghost" href="/admin/temoignages" title="Réinitialiser les filtres">
                <?= icon('arrow-path', 'h-4 w-4') ?>
            </a>
        </div>
    </div>
</form>

<?php if ($result['rows']): ?>
    <?php foreach ($result['rows'] as $row): ?>
        <form id="tm-pub-<?= (int) $row['id'] ?>" method="post"
              action="/admin/temoignages/<?= (int) $row['id'] ?>/publish"
              data-loading-submit data-loading-label="Publication…">
            <?= csrf_field() ?>
            <input type="hidden" name="from" value="list">
        </form>
        <form id="tm-ref-<?= (int) $row['id'] ?>" method="post"
              action="/admin/temoignages/<?= (int) $row['id'] ?>/refuse"
              data-loading-submit data-loading-label="Refus…">
            <?= csrf_field() ?>
            <input type="hidden" name="from" value="list">
        </form>
        <form id="tm-del-<?= (int) $row['id'] ?>" method="post"
              action="/admin/temoignages/<?= (int) $row['id'] ?>/delete"
              data-loading-submit data-loading-label="Suppression…"
              data-confirm="Supprimer définitivement le témoignage de <?= e($row['author_name']) ?> ? Cette action est irréversible.">
            <?= csrf_field() ?>
        </form>
    <?php endforeach; ?>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Auteur</th>
                    <th>Extrait</th>
                    <th>Source</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['rows'] as $row): ?>
                    <?php
                    $rowInitials = strtoupper(mb_substr($row['author_name'], 0, 1));
                    if (preg_match('/^(\S)\S*\s+(\S)/u', $row['author_name'], $m)) {
                        $rowInitials = strtoupper($m[1] . $m[2]);
                    }
                    $photoUrl = Testimonial::photoUrl($row['photo_path'] ?? null);
                    ?>
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <?php if ($photoUrl): ?>
                                    <img src="<?= e($photoUrl) ?>" alt=""
                                         class="h-10 w-10 shrink-0 rounded-full object-cover ring-1 ring-slate-200">
                                <?php else: ?>
                                    <span class="avatar bg-gradient-to-br from-brand-navy2 to-brand-blue">
                                        <?= e($rowInitials) ?>
                                    </span>
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <div class="font-semibold text-brand-navy"><?= e($row['author_name']) ?></div>
                                    <div class="truncate text-xs text-slate-400">
                                        <?= e($row['role_title'] ?: '—') ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="max-w-xs">
                            <span class="block truncate text-slate-500">
                                <?= e(mb_substr($row['quote'], 0, 90)) ?><?= mb_strlen($row['quote']) > 90 ? '…' : '' ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $sourceClasses[$row['source']] ?? 'bg-slate-100 text-slate-600 ring-slate-300' ?>">
                                <?= e(Testimonial::SOURCE_LABELS[$row['source']] ?? $row['source']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $badgeClasses[$row['statut']] ?? 'bg-slate-100 text-slate-600 ring-slate-300' ?>">
                                <?= e(Testimonial::STATUT_LABELS[$row['statut']] ?? $row['statut']) ?>
                            </span>
                        </td>
                        <td class="whitespace-nowrap text-slate-500"><?= format_datetime($row['created_at']) ?></td>
                        <td class="text-right">
                            <div class="inline-flex items-center justify-end gap-1">
                                <a class="btn-icon" href="/admin/temoignages/<?= (int) $row['id'] ?>" title="Lire">
                                    <?= icon('eye', 'h-4 w-4') ?>
                                </a>
                                <a class="btn-icon" href="/admin/temoignages/<?= (int) $row['id'] ?>/edit" title="Modifier">
                                    <?= icon('pencil', 'h-4 w-4') ?>
                                </a>
                                <?php if ($row['statut'] !== 'publie'): ?>
                                    <button class="btn-icon" type="submit" form="tm-pub-<?= (int) $row['id'] ?>"
                                            title="Valider et publier">
                                        <?= icon('check', 'h-4 w-4') ?>
                                    </button>
                                <?php endif; ?>
                                <?php if ($row['statut'] !== 'refuse'): ?>
                                    <button class="btn-icon" type="submit" form="tm-ref-<?= (int) $row['id'] ?>"
                                            title="Refuser">
                                        <?= icon('x-mark', 'h-4 w-4') ?>
                                    </button>
                                <?php endif; ?>
                                <button class="btn-icon" type="submit" form="tm-del-<?= (int) $row['id'] ?>"
                                        title="Supprimer">
                                    <?= icon('trash', 'h-4 w-4') ?>
                                </button>
                            </div>
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
                   href="/admin/temoignages?<?= e($query(['page' => max(1, $page - 1)])) ?>" title="Page précédente">
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
                        <a class="page-btn" href="/admin/temoignages?<?= e($query(['page' => $p])) ?>"><?= $p ?></a>
                    <?php endif; ?>
                    <?php $prev = $p; ?>
                <?php endforeach; ?>
                <a class="page-btn <?= $page >= $pages ? 'page-btn-disabled' : '' ?>"
                   href="/admin/temoignages?<?= e($query(['page' => min($pages, $page + 1)])) ?>" title="Page suivante">
                    <?= icon('chevron-right', 'h-4 w-4') ?>
                </a>
            </nav>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="flex flex-col items-center gap-3 py-12 text-center">
            <?= icon('chat-bubble', 'h-10 w-10 text-slate-300') ?>
            <p class="text-sm text-slate-500">Aucun témoignage ne correspond à ces critères.</p>
            <a class="btn-ghost btn-sm" href="/admin/temoignages">
                <?= icon('arrow-path', 'h-4 w-4') ?> Réinitialiser les filtres
            </a>
        </div>
    </div>
<?php endif; ?>
