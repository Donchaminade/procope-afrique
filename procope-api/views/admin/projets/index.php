<?php
/** Variables : $result (rows,total,page,pages) */
use App\Models\IncubatedProject;

$projects = $result['rows'];
?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-brand-navy">Projets incubés</h1>
        <p class="mt-1 text-sm text-slate-500">
            Portfolio public : projets créés à la main, ou fiches issues d'un dépôt retenu
            (appel ou spontané). Les appels à candidature se gèrent à part.
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="btn-secondary" href="/admin/projets/depots">
            <?= icon('clipboard-list', 'h-5 w-5') ?> Voir les dépôts
        </a>
        <a class="btn-primary" href="/admin/projets/create">
            <?= icon('plus', 'h-5 w-5') ?> Nouveau projet
        </a>
    </div>
</div>

<div class="card">
    <?php if ($projects): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Titre</th><th>Origine</th><th>Secteur</th><th>Stade</th><th>Pays</th>
                    <th>Publié</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($projects as $project): ?>
                    <tr>
                        <td class="font-semibold text-brand-navy"><?= e($project['title']) ?></td>
                        <td>
                            <?php if (!empty($project['application_id'])): ?>
                                <span class="badge bg-emerald-50 text-emerald-700 ring-emerald-200">Dépôt</span>
                            <?php else: ?>
                                <span class="badge bg-slate-100 text-slate-600 ring-slate-300">Manuel</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($project['sector'])): ?>
                                <span class="badge bg-brand-blue/10 text-brand-blue ring-brand-blue/20">
                                    <?= e($project['sector']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e(IncubatedProject::STAGE_LABELS[$project['stage']] ?? $project['stage']) ?></td>
                        <td>
                            <?php if (!empty($project['country'])): ?>
                                <span class="inline-flex items-center gap-1.5 text-slate-600">
                                    <?= icon('map-pin', 'h-4 w-4 shrink-0 text-slate-400') ?><?= e($project['country']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $project['is_published']): ?>
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
                                <?php if (!empty($project['application_id'])): ?>
                                    <a class="btn-icon" href="/admin/projets/depots/<?= (int) $project['application_id'] ?>"
                                       title="Voir le dépôt source">
                                        <?= icon('clipboard-list', 'h-4 w-4') ?>
                                    </a>
                                <?php endif; ?>
                                <a class="btn-icon" href="/admin/projets/<?= (int) $project['id'] ?>/edit"
                                   title="Modifier">
                                    <?= icon('pencil', 'h-4 w-4') ?>
                                </a>
                                <form class="inline-flex" method="post"
                                      action="/admin/projets/<?= (int) $project['id'] ?>/publish"
                                      data-loading-submit>
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn-icon"
                                            title="<?= (int) $project['is_published'] ? 'Dépublier le projet' : 'Publier le projet' ?>">
                                        <?= (int) $project['is_published']
                                            ? icon('eye-off', 'h-4 w-4')
                                            : icon('eye', 'h-4 w-4') ?>
                                    </button>
                                </form>
                                <form class="inline-flex" method="post"
                                      action="/admin/projets/<?= (int) $project['id'] ?>/archive"
                                      data-loading-submit
                                      data-confirm="Archiver ce projet ? Il sera dépublié et disparaîtra des listes actives, mais restera consultable (avec ses dépôts) dans les Archives.">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn-icon" title="Archiver le projet">
                                        <?= icon('archive-box', 'h-4 w-4') ?>
                                    </button>
                                </form>
                                <form class="inline-flex" method="post"
                                      action="/admin/projets/<?= (int) $project['id'] ?>/delete"
                                      data-loading-submit
                                      data-confirm="Supprimer définitivement ce projet et ses affiches ? Les dépôts liés deviennent spontanés.">
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
        <?= pagination((int) $result['page'], (int) $result['pages'], '/admin/projets') ?>
    <?php else: ?>
        <div class="flex flex-col items-center gap-3 py-12 text-center">
            <?= icon('rocket', 'h-10 w-10 text-slate-300') ?>
            <p class="text-sm text-slate-500">Aucun projet incubé. Créez le premier !</p>
        </div>
    <?php endif; ?>
</div>
