<?php
/** Variables : $result (rows,total,page,pages) */
use App\Models\FormationGallery;

$galleries = $result['rows'];
$canWrite = \App\Services\Auth::isAtLeast('admin');
?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-brand-navy">
            Galeries
            <span class="ml-1 rounded-full bg-brand-blue/10 px-3 py-1 align-middle text-sm font-semibold text-brand-blue">
                <?= (int) $result['total'] ?>
            </span>
        </h1>
        <p class="mt-1 text-sm text-slate-500">
            Photos de formation et affiches d'événements passés, affichées sur Actualités.
        </p>
    </div>
    <?php if ($canWrite): ?>
        <a class="btn-primary" href="/admin/galeries/create">
            <?= icon('plus', 'h-5 w-5') ?> Nouvel album
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <?php if ($galleries): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Album</th>
                    <th>Type</th>
                    <th>Année</th>
                    <th>Formation liée</th>
                    <th>Photos</th>
                    <th>Publié</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($galleries as $gallery): ?>
                    <?php
                    $monthLabel = FormationGallery::monthLabel(
                        $gallery['month'] !== null ? (int) $gallery['month'] : null
                    );
                    $rowKind = FormationGallery::normalizeKind($gallery['kind'] ?? null);
                    $isAffiche = $rowKind === FormationGallery::KIND_AFFICHE;
                    ?>
                    <tr>
                        <td class="font-semibold text-brand-navy"><?= e($gallery['title']) ?></td>
                        <td>
                            <?php if ($isAffiche): ?>
                                <span class="badge bg-brand-orange/10 text-brand-orange ring-brand-orange/30">
                                    Affiche
                                </span>
                            <?php else: ?>
                                <span class="badge bg-brand-blue/10 text-brand-blue ring-brand-blue/20">
                                    Photos
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="whitespace-nowrap text-slate-600">
                                <?= $monthLabel ? e(ucfirst($monthLabel) . ' ') : '' ?><?= (int) $gallery['year'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($gallery['formation_titre'])): ?>
                                <span class="text-sm text-slate-600"><?= e($gallery['formation_titre']) ?></span>
                            <?php else: ?>
                                <span class="text-slate-400">Titre libre</span>
                            <?php endif; ?>
                        </td>
                        <td class="font-semibold text-brand-navy"><?= (int) $gallery['nb_images'] ?></td>
                        <td>
                            <?php if ((int) $gallery['is_published']): ?>
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
                            <div class="flex items-center justify-end gap-1.5">
                                <?php if ($canWrite): ?>
                                    <a class="btn-icon" href="/admin/galeries/<?= (int) $gallery['id'] ?>/edit"
                                       title="Modifier">
                                        <?= icon('pencil', 'h-4 w-4') ?>
                                    </a>
                                    <form class="inline-flex" method="post"
                                          action="/admin/galeries/<?= (int) $gallery['id'] ?>/publish"
                                          data-loading-submit>
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn-icon"
                                                title="<?= (int) $gallery['is_published'] ? 'Dépublier l\'album' : 'Publier l\'album' ?>">
                                            <?= (int) $gallery['is_published']
                                                ? icon('eye-off', 'h-4 w-4')
                                                : icon('eye', 'h-4 w-4') ?>
                                        </button>
                                    </form>
                                    <form class="inline-flex" method="post"
                                          action="/admin/galeries/<?= (int) $gallery['id'] ?>/delete"
                                          data-loading-submit
                                          data-confirm="Supprimer définitivement cet album et toutes ses photos ?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn-icon-danger" title="Supprimer">
                                            <?= icon('trash', 'h-4 w-4') ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination((int) $result['page'], (int) $result['pages'], '/admin/galeries') ?>
    <?php else: ?>
        <div class="flex flex-col items-center gap-3 py-12 text-center">
            <?= icon('photo', 'h-10 w-10 text-slate-300') ?>
            <p class="text-sm text-slate-500">Aucun album. Créez le premier pour afficher photos ou affiches sur Actualités.</p>
        </div>
    <?php endif; ?>
</div>
