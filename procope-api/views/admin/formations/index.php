<?php /** Variables : $result (rows,total,page,pages) */ ?>
<?php $formations = $result['rows']; ?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-brand-navy">Formations</h1>
        <p class="mt-1 text-sm text-slate-500">Gérez les sessions de formation et leurs inscriptions.</p>
    </div>
    <?php if (\App\Services\Auth::isAtLeast('admin')): ?>
        <a class="btn-primary" href="/admin/formations/create">
            <?= icon('plus', 'h-5 w-5') ?> Nouvelle formation
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <?php if ($formations): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Titre</th><th>Lieu</th><th>Prix</th><th>Inscriptions</th><th>Statut</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($formations as $formation): ?>
                    <tr>
                        <td class="font-semibold text-brand-navy"><?= e($formation['titre']) ?></td>
                        <td>
                            <?php if (!empty($formation['lieu'])): ?>
                                <span class="inline-flex items-center gap-1.5 text-slate-600">
                                    <?= icon('map-pin', 'h-4 w-4 shrink-0 text-slate-400') ?><?= e($formation['lieu']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="whitespace-nowrap font-medium"><?= format_price($formation['prix']) ?></td>
                        <td>
                            <span class="font-semibold text-brand-navy"><?= (int) $formation['nb_inscriptions'] ?></span>
                            <?php if ($formation['places_max']): ?>
                                <span class="text-slate-400">/ <?= (int) $formation['places_max'] ?> places</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $formation['inscriptions_ouvertes']): ?>
                                <span class="badge bg-emerald-50 text-emerald-700 ring-emerald-200">
                                    <?= icon('lock-open', 'h-3.5 w-3.5') ?> Ouvertes
                                </span>
                            <?php else: ?>
                                <span class="badge bg-slate-100 text-slate-600 ring-slate-300">
                                    <?= icon('lock-closed', 'h-3.5 w-3.5') ?> Fermées
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                <a class="btn-icon" href="/admin/inscriptions?formation_id=<?= (int) $formation['id'] ?>"
                                   title="Voir les inscriptions">
                                    <?= icon('clipboard-list', 'h-4 w-4') ?>
                                </a>
                                <?php if (\App\Services\Auth::isAtLeast('admin')): ?>
                                    <a class="btn-icon" href="/admin/formations/<?= (int) $formation['id'] ?>/edit"
                                       title="Modifier">
                                        <?= icon('pencil', 'h-4 w-4') ?>
                                    </a>
                                    <form class="inline-flex" method="post"
                                          action="/admin/formations/<?= (int) $formation['id'] ?>/toggle">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn-icon"
                                                title="<?= (int) $formation['inscriptions_ouvertes'] ? 'Fermer les inscriptions' : 'Ouvrir les inscriptions' ?>">
                                            <?= (int) $formation['inscriptions_ouvertes']
                                                ? icon('lock-closed', 'h-4 w-4')
                                                : icon('lock-open', 'h-4 w-4') ?>
                                        </button>
                                    </form>
                                    <form class="inline-flex" method="post"
                                          action="/admin/formations/<?= (int) $formation['id'] ?>/archive"
                                          data-confirm="Archiver cette formation et ses inscriptions ?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn-icon" title="Archiver">
                                            <?= icon('archive-box', 'h-4 w-4') ?>
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
        <?= pagination((int) $result['page'], (int) $result['pages'], '/admin/formations') ?>
    <?php else: ?>
        <div class="flex flex-col items-center gap-3 py-12 text-center">
            <?= icon('academic-cap', 'h-10 w-10 text-slate-300') ?>
            <p class="text-sm text-slate-500">Aucune formation. Créez la première !</p>
        </div>
    <?php endif; ?>
</div>
