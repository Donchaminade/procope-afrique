<?php
/** Variables : $result (rows,total,page,pages) */
$offers = $result['rows'];

/** Badge d'échéance : « Clôturée » ou « J-x » (orange si ≤ 5 jours). */
$deadlineBadge = static function (array $offer): string {
    $closesTs = strtotime((string) $offer['closes_at']);
    if ($closesTs <= time()) {
        return '<span class="badge bg-red-50 text-red-700 ring-red-200">Clôturée</span>';
    }
    $days = (int) ceil(($closesTs - time()) / 86400);
    $classes = $days <= 5
        ? 'bg-amber-50 text-amber-700 ring-amber-200'
        : 'bg-slate-100 text-slate-600 ring-slate-300';
    return '<span class="badge ' . $classes . '">J-' . $days . '</span>';
};
?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-brand-navy">Offres d'emploi</h1>
        <p class="mt-1 text-sm text-slate-500">Publiez les offres et suivez les candidatures reçues.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="btn-secondary" href="/admin/emplois/candidatures">
            <?= icon('clipboard-list', 'h-5 w-5') ?> Voir les candidatures
        </a>
        <a class="btn-primary" href="/admin/emplois/create">
            <?= icon('plus', 'h-5 w-5') ?> Nouvelle offre
        </a>
    </div>
</div>

<div class="card">
    <?php if ($offers): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Titre</th><th>Contrat</th><th>Lieu</th><th>Clôture</th>
                    <th>Publiée</th><th>Candidatures</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($offers as $offer): ?>
                    <tr>
                        <td class="font-semibold text-brand-navy"><?= e($offer['title']) ?></td>
                        <td>
                            <span class="badge bg-brand-blue/10 text-brand-blue ring-brand-blue/20">
                                <?= e($offer['contract_type']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($offer['location'])): ?>
                                <span class="inline-flex items-center gap-1.5 text-slate-600">
                                    <?= icon('map-pin', 'h-4 w-4 shrink-0 text-slate-400') ?><?= e($offer['location']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <span class="text-slate-500"><?= format_datetime($offer['closes_at']) ?></span>
                                <?= $deadlineBadge($offer) ?>
                            </div>
                        </td>
                        <td>
                            <?php if ((int) $offer['is_published']): ?>
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
                            <span class="font-semibold text-brand-navy"><?= (int) $offer['nb_candidatures'] ?></span>
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                <a class="btn-icon" href="/admin/emplois/<?= (int) $offer['id'] ?>/candidatures"
                                   title="Voir les candidatures">
                                    <?= icon('clipboard-list', 'h-4 w-4') ?>
                                </a>
                                <a class="btn-icon" href="/admin/emplois/<?= (int) $offer['id'] ?>/edit"
                                   title="Modifier">
                                    <?= icon('pencil', 'h-4 w-4') ?>
                                </a>
                                <form class="inline-flex" method="post"
                                      action="/admin/emplois/<?= (int) $offer['id'] ?>/publish">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn-icon"
                                            title="<?= (int) $offer['is_published'] ? 'Dépublier l\'offre' : 'Publier l\'offre' ?>">
                                        <?= (int) $offer['is_published']
                                            ? icon('eye-off', 'h-4 w-4')
                                            : icon('eye', 'h-4 w-4') ?>
                                    </button>
                                </form>
                                <form class="inline-flex" method="post"
                                      action="/admin/emplois/<?= (int) $offer['id'] ?>/archive"
                                      data-confirm="Archiver cette offre ? Elle sera dépubliée et disparaîtra des listes actives, mais restera consultable (avec ses candidatures) dans les Archives.">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn-icon" title="Archiver l'offre">
                                        <?= icon('archive-box', 'h-4 w-4') ?>
                                    </button>
                                </form>
                                <form class="inline-flex" method="post"
                                      action="/admin/emplois/<?= (int) $offer['id'] ?>/delete"
                                      data-confirm="Supprimer définitivement cette offre, ses candidatures et les CV associés ?">
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
        <?= pagination((int) $result['page'], (int) $result['pages'], '/admin/emplois') ?>
    <?php else: ?>
        <div class="flex flex-col items-center gap-3 py-12 text-center">
            <?= icon('briefcase', 'h-10 w-10 text-slate-300') ?>
            <p class="text-sm text-slate-500">Aucune offre d'emploi. Créez la première !</p>
        </div>
    <?php endif; ?>
</div>
