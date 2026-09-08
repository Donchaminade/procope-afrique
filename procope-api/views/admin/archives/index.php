<?php
/**
 * Variables : $result (rows,total,page,pages) — formations archivées avec nb_inscriptions,
 *             $archivedOffers — offres d'emploi archivées avec nb_candidatures,
 *             $archivedProjects — projets incubés archivés avec nb_depots,
 *             $archivedCalls — appels à incubation archivés
 */
?>
<?php
$formations = $result['rows'];
$archivedOffers = $archivedOffers ?? [];
$archivedProjects = $archivedProjects ?? [];
$archivedCalls = $archivedCalls ?? [];
?>
<div class="mb-8">
    <h1 class="text-2xl font-bold text-brand-navy">Archives</h1>
    <p class="mt-1 text-sm text-slate-500">
        Formations, offres d'emploi, appels et projets incubés archivés : ils n'apparaissent plus
        dans les listes actives, mais leurs inscriptions, candidatures et dépôts restent consultables.
    </p>
</div>

<h2 class="mb-3 flex items-center gap-2 text-lg font-bold text-brand-navy">
    <?= icon('academic-cap', 'h-5 w-5 text-slate-400') ?> Formations
</h2>
<div class="card">
    <?php if ($formations): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Titre</th><th>Créneaux</th><th>Lieu</th><th>Inscrits</th><th>Archivée le</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($formations as $formation): ?>
                    <?php $slots = \App\Models\Formation::slots((int) $formation['id']); ?>
                    <tr>
                        <td class="font-semibold text-brand-navy">
                            <a class="transition hover:text-brand-blue"
                               href="/admin/archives/<?= (int) $formation['id'] ?>">
                                <?= e($formation['titre']) ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($slots): ?>
                                <div class="space-y-0.5 text-sm text-slate-600">
                                    <?php foreach ($slots as $slot): ?>
                                        <div class="flex items-center gap-1.5">
                                            <?= icon('calendar', 'h-3.5 w-3.5 shrink-0 text-slate-400') ?>
                                            <?= e($slot['label']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($formation['lieu'])): ?>
                                <span class="inline-flex items-center gap-1.5 text-slate-600">
                                    <?= icon('map-pin', 'h-4 w-4 shrink-0 text-slate-400') ?><?= e($formation['lieu']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="font-semibold text-brand-navy"><?= (int) $formation['nb_inscriptions'] ?></span>
                        </td>
                        <td class="whitespace-nowrap text-slate-500"><?= format_datetime($formation['archived_at']) ?></td>
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                <a class="btn-icon" href="/admin/archives/<?= (int) $formation['id'] ?>"
                                   title="Voir les détails et les inscrits">
                                    <?= icon('eye', 'h-4 w-4') ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination((int) $result['page'], (int) $result['pages'], '/admin/archives') ?>
    <?php else: ?>
        <div class="flex flex-col items-center gap-3 py-12 text-center">
            <?= icon('archive-box', 'h-10 w-10 text-slate-300') ?>
            <p class="text-sm text-slate-500">
                Aucune formation archivée. Utilisez le bouton « Archiver » dans la liste des formations
                pour ranger une session terminée.
            </p>
        </div>
    <?php endif; ?>
</div>

<!-- Offres d'emploi archivées -->
<h2 class="mb-3 mt-8 flex items-center gap-2 text-lg font-bold text-brand-navy">
    <?= icon('briefcase', 'h-5 w-5 text-slate-400') ?> Offres d'emploi
</h2>
<div class="card">
    <?php if ($archivedOffers): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Titre</th><th>Contrat</th><th>Lieu</th><th>Clôturée le</th>
                    <th>Candidatures</th><th>Archivée le</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($archivedOffers as $offer): ?>
                    <tr>
                        <td class="font-semibold text-brand-navy">
                            <a class="transition hover:text-brand-blue"
                               href="/admin/archives/emplois/<?= (int) $offer['id'] ?>">
                                <?= e($offer['title']) ?>
                            </a>
                        </td>
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
                        <td class="whitespace-nowrap text-slate-500"><?= format_datetime($offer['closes_at']) ?></td>
                        <td>
                            <span class="font-semibold text-brand-navy"><?= (int) $offer['nb_candidatures'] ?></span>
                        </td>
                        <td class="whitespace-nowrap text-slate-500"><?= format_datetime($offer['archived_at']) ?></td>
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                <a class="btn-icon" href="/admin/archives/emplois/<?= (int) $offer['id'] ?>"
                                   title="Voir les détails et les candidats">
                                    <?= icon('eye', 'h-4 w-4') ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="flex flex-col items-center gap-3 py-12 text-center">
            <?= icon('briefcase', 'h-10 w-10 text-slate-300') ?>
            <p class="text-sm text-slate-500">
                Aucune offre d'emploi archivée. Utilisez le bouton « Archiver » dans la liste des offres
                pour ranger un recrutement terminé.
            </p>
        </div>
    <?php endif; ?>
</div>

<!-- Projets incubés archivés -->
<h2 class="mb-3 mt-8 flex items-center gap-2 text-lg font-bold text-brand-navy">
    <?= icon('rocket', 'h-5 w-5 text-slate-400') ?> Projets incubés
</h2>
<div class="card">
    <?php if ($archivedProjects): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Titre</th><th>Secteur</th><th>Stade</th><th>Pays</th>
                    <th>Dépôts</th><th>Archivé le</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($archivedProjects as $project): ?>
                    <tr>
                        <td class="font-semibold text-brand-navy">
                            <a class="transition hover:text-brand-blue"
                               href="/admin/archives/projets/<?= (int) $project['id'] ?>">
                                <?= e($project['title']) ?>
                            </a>
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
                        <td class="text-slate-600">
                            <?= e(\App\Models\IncubatedProject::STAGE_LABELS[$project['stage'] ?? ''] ?? ($project['stage'] ?: '—')) ?>
                        </td>
                        <td class="text-slate-600"><?= e($project['country'] ?: '—') ?></td>
                        <td>
                            <span class="font-semibold text-brand-navy"><?= (int) $project['nb_depots'] ?></span>
                        </td>
                        <td class="whitespace-nowrap text-slate-500"><?= format_datetime($project['archived_at']) ?></td>
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                <a class="btn-icon" href="/admin/archives/projets/<?= (int) $project['id'] ?>"
                                   title="Voir les détails et les dépôts">
                                    <?= icon('eye', 'h-4 w-4') ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="flex flex-col items-center gap-3 py-12 text-center">
            <?= icon('rocket', 'h-10 w-10 text-slate-300') ?>
            <p class="text-sm text-slate-500">
                Aucun projet incubé archivé. Utilisez le bouton « Archiver » dans la liste des projets
                pour ranger un projet retiré du portfolio public.
            </p>
        </div>
    <?php endif; ?>
</div>

<h2 class="mb-3 mt-8 flex items-center gap-2 text-lg font-bold text-brand-navy">
    <?= icon('megaphone', 'h-5 w-5 text-slate-400') ?> Appels à incubation
</h2>
<div class="card">
    <?php if ($archivedCalls): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Titre</th><th>Secteur</th><th>Dépôts</th><th>Archivé le</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($archivedCalls as $call): ?>
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
                        <td>
                            <span class="font-semibold text-brand-navy"><?= (int) $call['nb_depots'] ?></span>
                        </td>
                        <td class="whitespace-nowrap text-slate-500"><?= format_datetime($call['archived_at']) ?></td>
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                <a class="btn-icon" href="/admin/projets/depots?call_id=<?= (int) $call['id'] ?>"
                                   title="Voir les dépôts de cet appel">
                                    <?= icon('clipboard-list', 'h-4 w-4') ?>
                                </a>
                                <form class="inline-flex" method="post"
                                      action="/admin/archives/appels/<?= (int) $call['id'] ?>/restore"
                                      data-loading-submit
                                      data-confirm="Restaurer cet appel ? Il réapparaîtra non publié.">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn-icon" title="Restaurer">
                                        <?= icon('arrow-path', 'h-4 w-4') ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="flex flex-col items-center gap-3 py-12 text-center">
            <?= icon('megaphone', 'h-10 w-10 text-slate-300') ?>
            <p class="text-sm text-slate-500">
                Aucun appel archivé. Utilisez le bouton « Archiver » dans la liste des appels
                pour ranger un appel clos.
            </p>
        </div>
    <?php endif; ?>
</div>
