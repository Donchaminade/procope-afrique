<?php
/** Variables : $result (rows,total,page,pages), $filters */
use App\Models\ContactMessage;

$query = static function (array $overrides = []) use ($filters): string {
    return http_build_query(array_filter(array_merge($filters, $overrides), fn ($v) => $v !== null && $v !== ''));
};

$badgeClasses = [
    'nouveau' => 'bg-sky-50 text-sky-700 ring-sky-200',
    'lu'      => 'bg-slate-100 text-slate-600 ring-slate-300',
    'traite'  => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
];
?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-brand-navy">
            Messages
            <span class="ml-1 rounded-full bg-brand-blue/10 px-3 py-1 align-middle text-sm font-semibold text-brand-blue">
                <?= (int) $result['total'] ?>
            </span>
        </h1>
        <p class="mt-1 text-sm text-slate-500">Messages reçus via le formulaire de contact du site public.</p>
    </div>
</div>

<!-- Filtres -->
<form method="get" action="/admin/messages" class="card mb-6 !p-5">
    <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label class="label" for="f-statut">Statut</label>
            <select class="input" id="f-statut" name="statut">
                <option value="">Tous</option>
                <?php foreach (ContactMessage::STATUT_LABELS as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($filters['statut'] ?? '') === $key ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="label" for="f-q">Recherche</label>
            <div class="input-icon-wrap">
                <?= icon('search') ?>
                <input class="input" id="f-q" type="text" name="q" placeholder="Nom, e-mail, téléphone, sujet, message"
                       value="<?= e($filters['q'] ?? '') ?>">
            </div>
        </div>
        <div class="flex gap-2">
            <button class="btn-secondary flex-1" type="submit">
                <?= icon('funnel', 'h-4 w-4') ?> Filtrer
            </button>
            <a class="btn-ghost" href="/admin/messages" title="Réinitialiser les filtres">
                <?= icon('arrow-path', 'h-4 w-4') ?>
            </a>
        </div>
    </div>
</form>

<?php if ($result['rows']): ?>
    <?php foreach ($result['rows'] as $row): ?>
        <form id="msg-del-<?= (int) $row['id'] ?>" method="post"
              action="/admin/messages/<?= (int) $row['id'] ?>/delete"
              data-loading-submit data-loading-label="Suppression…"
              data-confirm="Supprimer définitivement le message de <?= e($row['name']) ?> ? Cette action est irréversible.">
            <?= csrf_field() ?>
        </form>
    <?php endforeach; ?>

    <form method="post" action="/admin/messages/delete-batch" class="card"
          data-loading-submit data-loading-label="Suppression…"
          data-confirm-count="Supprimer définitivement {n} message(s) sélectionné(s) ? Cette action est irréversible.">
        <?= csrf_field() ?>
        <?php if (!empty($filters['statut'])): ?>
            <input type="hidden" name="statut" value="<?= e($filters['statut']) ?>">
        <?php endif; ?>
        <?php if (!empty($filters['q'])): ?>
            <input type="hidden" name="q" value="<?= e($filters['q']) ?>">
        <?php endif; ?>
        <?php if ((int) $result['page'] > 1): ?>
            <input type="hidden" name="page" value="<?= (int) $result['page'] ?>">
        <?php endif; ?>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-600">
                <input type="checkbox" id="msg-select-all"
                       class="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue">
                Tout sélectionner
            </label>
            <button class="btn-danger" type="submit">
                <?= icon('trash', 'h-4 w-4') ?> Supprimer la sélection
            </button>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th class="w-10"><span class="sr-only">Sélection</span></th>
                    <th>#</th><th>Expéditeur</th><th>Sujet</th><th>Message</th>
                    <th>Statut</th><th>Date</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($result['rows'] as $row): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="ids[]" value="<?= (int) $row['id'] ?>"
                                   class="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue"
                                   aria-label="Sélectionner le message de <?= e($row['name']) ?>">
                        </td>
                        <td class="text-slate-400"><?= (int) $row['id'] ?></td>
                        <td>
                            <div class="flex items-center gap-3">
                                <?php
                                $rowInitials = strtoupper(mb_substr($row['name'], 0, 1));
                                if (preg_match('/^(\S)\S*\s+(\S)/u', $row['name'], $m)) {
                                    $rowInitials = strtoupper($m[1] . $m[2]);
                                }
                                ?>
                                <span class="avatar bg-gradient-to-br from-brand-navy2 to-brand-blue">
                                    <?= e($rowInitials) ?>
                                </span>
                                <div class="min-w-0">
                                    <div class="font-semibold text-brand-navy"><?= e($row['name']) ?></div>
                                    <div class="truncate text-xs text-slate-400">
                                        <?= e($row['email'] ?: ($row['phone'] ?: '—')) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="whitespace-nowrap"><?= e($row['subject'] ?: '—') ?></td>
                        <td class="max-w-xs">
                            <span class="block truncate text-slate-500">
                                <?= e(mb_substr($row['message'], 0, 90)) ?><?= mb_strlen($row['message']) > 90 ? '…' : '' ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $badgeClasses[$row['statut']] ?? 'bg-slate-100 text-slate-600 ring-slate-300' ?>">
                                <?= e(ContactMessage::STATUT_LABELS[$row['statut']] ?? $row['statut']) ?>
                            </span>
                        </td>
                        <td class="whitespace-nowrap text-slate-500"><?= format_datetime($row['created_at']) ?></td>
                        <td class="text-right">
                            <div class="inline-flex items-center justify-end gap-1">
                                <a class="btn-icon" href="/admin/messages/<?= (int) $row['id'] ?>" title="Voir le message">
                                    <?= icon('eye', 'h-4 w-4') ?>
                                </a>
                                <?php if (!empty($row['email'])): ?>
                                    <a class="btn-icon" href="/admin/messages/<?= (int) $row['id'] ?>#reply"
                                       title="Répondre">
                                        <?= icon('paper-airplane', 'h-4 w-4') ?>
                                    </a>
                                <?php endif; ?>
                                <button class="btn-icon" type="submit" form="msg-del-<?= (int) $row['id'] ?>"
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
                   href="/admin/messages?<?= e($query(['page' => max(1, $page - 1)])) ?>" title="Page précédente">
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
                        <a class="page-btn" href="/admin/messages?<?= e($query(['page' => $p])) ?>"><?= $p ?></a>
                    <?php endif; ?>
                    <?php $prev = $p; ?>
                <?php endforeach; ?>
                <a class="page-btn <?= $page >= $pages ? 'page-btn-disabled' : '' ?>"
                   href="/admin/messages?<?= e($query(['page' => min($pages, $page + 1)])) ?>" title="Page suivante">
                    <?= icon('chevron-right', 'h-4 w-4') ?>
                </a>
            </nav>
        <?php endif; ?>
    </form>
<?php else: ?>
    <div class="card">
        <div class="flex flex-col items-center gap-3 py-12 text-center">
            <?= icon('inbox', 'h-10 w-10 text-slate-300') ?>
            <p class="text-sm text-slate-500">Aucun message ne correspond à ces critères.</p>
            <a class="btn-ghost btn-sm" href="/admin/messages">
                <?= icon('arrow-path', 'h-4 w-4') ?> Réinitialiser les filtres
            </a>
        </div>
    </div>
<?php endif; ?>
