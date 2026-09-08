<?php
/** Variables : $result (rows,total,page,pages) */
$calls = $result['rows'];

$deadlineBadge = static function (array $call): string {
    $now = time();
    $opens = strtotime((string) $call['opens_at']);
    $closes = strtotime((string) $call['closes_at']);
    if ($opens > $now) {
        return '<span class="badge bg-slate-100 text-slate-600 ring-slate-300">Pas encore ouvert</span>';
    }
    if ($closes <= $now) {
        return '<span class="badge bg-red-50 text-red-700 ring-red-200">Clos</span>';
    }
    $days = (int) ceil(($closes - $now) / 86400);
    $classes = $days <= 5
        ? 'bg-amber-50 text-amber-700 ring-amber-200'
        : 'bg-slate-100 text-slate-600 ring-slate-300';
    return '<span class="badge ' . $classes . '">J-' . $days . '</span>';
};
?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-brand-navy">Appels à incubation</h1>
        <p class="mt-1 text-sm text-slate-500">
            Lancez un appel par secteur : les porteurs candidatent à cet appel.
            Les candidatures spontanées et les projets vitrine restent des chemins séparés.
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="btn-secondary" href="/admin/projets/depots">
            <?= icon('clipboard-list', 'h-5 w-5') ?> Voir les dépôts
        </a>
        <a class="btn-primary" href="/admin/appels/create">
            <?= icon('plus', 'h-5 w-5') ?> Nouvel appel
        </a>
    </div>
</div>

<div class="card">
    <?php if ($calls): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Titre</th><th>Secteur</th><th>Ouverture</th><th>Clôture</th>
                    <th>Publié</th><th>Dépôts</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($calls as $call): ?>
                    <tr>
                        <td class="font-semibold text-brand-navy"><?= e($call['title']) ?></td>
                        <td>
                            <?php if (!empty($call['sector'])): ?>
                                <span class="badge bg-brand-blue/10 text-brand-blue ring-brand-blue/20">
                                    <?= e($call['sector']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="whitespace-nowrap text-slate-500"><?= format_datetime($call['opens_at']) ?></td>
                        <td class="whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <span class="text-slate-500"><?= format_datetime($call['closes_at']) ?></span>
                                <?= $deadlineBadge($call) ?>
                            </div>
                        </td>
                        <td>
                            <?php if ((int) $call['is_published']): ?>
                                <span class="badge bg-emerald-50 text-emerald-700 ring-emerald-200">
                                    <?= icon('check-circle', 'h-3.5 w-3.5') ?> Oui
                                </span>
                            <?php else: ?>
                                <span class="badge bg-slate-100 text-slate-600 ring-slate-300">
                                    <?= icon('eye-off', 'h-3.5 w-3.5') ?> Non
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="font-semibold text-brand-navy"><?= (int) $call['nb_depots'] ?></span>
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                <a class="btn-icon" href="/admin/projets/depots?call_id=<?= (int) $call['id'] ?>"
                                   title="Voir les candidatures de cet appel">
                                    <?= icon('clipboard-list', 'h-4 w-4') ?>
                                </a>
                                <a class="btn-icon" href="/admin/appels/<?= (int) $call['id'] ?>/edit"
                                   title="Modifier">
                                    <?= icon('pencil', 'h-4 w-4') ?>
                                </a>
                                <form class="inline-flex" method="post"
                                      action="/admin/appels/<?= (int) $call['id'] ?>/publish"
                                      data-loading-submit>
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn-icon"
                                            title="<?= (int) $call['is_published'] ? 'Dépublier l\'appel' : 'Publier l\'appel' ?>">
                                        <?= (int) $call['is_published']
                                            ? icon('eye-off', 'h-4 w-4')
                                            : icon('eye', 'h-4 w-4') ?>
                                    </button>
                                </form>
                                <form class="inline-flex" method="post"
                                      action="/admin/appels/<?= (int) $call['id'] ?>/archive"
                                      data-loading-submit
                                      data-confirm="Archiver cet appel ? Il sera dépublié. Les dépôts restent consultables.">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn-icon" title="Archiver l'appel">
                                        <?= icon('archive-box', 'h-4 w-4') ?>
                                    </button>
                                </form>
                                <form class="inline-flex" method="post"
                                      action="/admin/appels/<?= (int) $call['id'] ?>/delete"
                                      data-loading-submit
                                      data-confirm="Supprimer définitivement cet appel ? Les dépôts liés deviennent spontanés.">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn-icon-danger" title="Supprimer">
                                        <?= icon('trash', 'h-4 w-4') ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination((int) $result['page'], (int) $result['pages'], '/admin/appels') ?>
    <?php else: ?>
        <div class="flex flex-col items-center gap-3 py-12 text-center">
            <?= icon('megaphone', 'h-10 w-10 text-slate-300') ?>
            <p class="text-sm text-slate-500">Aucun appel à incubation. Créez le premier !</p>
        </div>
    <?php endif; ?>
</div>
