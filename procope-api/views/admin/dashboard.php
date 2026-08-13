<?php
/**
 * Variables : $stats, $perDay, $byGender, $active, $activeSlots, $latest,
 *             $caTotal, $caActive, $resteAEncaisser, $archivedCount, $placesRestantes,
 *             $offresPubliees, $dossiersTotal, $soonestOffer, $candidatsRetenus
 */
use App\Models\Inscription;

$total = array_sum($stats);

$statutColors = [
    'preinscrit'       => '#6366f1',
    'preuve_recue'     => '#f59e0b',
    'valide'           => '#10b981',
    'refuse'           => '#ef4444',
    'liste_attente'    => '#94a3b8',
    'paiement_partiel' => '#8b5cf6',
];
$badgeClasses = [
    'preinscrit'       => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    'preuve_recue'     => 'bg-amber-50 text-amber-700 ring-amber-200',
    'valide'           => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    'refuse'           => 'bg-red-50 text-red-700 ring-red-200',
    'liste_attente'    => 'bg-slate-100 text-slate-600 ring-slate-300',
    'paiement_partiel' => 'bg-violet-50 text-violet-700 ring-violet-200',
];

$donutData = [];
foreach (Inscription::STATUT_LABELS as $key => $label) {
    $donutData[] = ['label' => $label, 'value' => (int) $stats[$key], 'color' => $statutColors[$key]];
}

$barsData = array_map(
    static fn (array $day): array => [
        'label' => date('d/m', strtotime($day['date'])),
        'value' => (int) $day['n'],
    ],
    $perDay
);

$genderTotal = $byGender['M'] + $byGender['F'];
$genderData = [
    ['label' => 'Hommes', 'value' => (int) $byGender['M'], 'color' => '#06A3DA'],
    ['label' => 'Femmes', 'value' => (int) $byGender['F'], 'color' => '#f5a623'],
];
?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-brand-navy">Tableau de bord</h1>
        <p class="mt-1 text-sm text-slate-500">
            Vue d'ensemble<?= $active ? ' — formation « ' . e($active['titre']) . ' »' : ' de toutes les inscriptions' ?>
        </p>
    </div>
    <a class="btn-primary" href="/admin/inscriptions">
        <?= icon('clipboard-list', 'h-5 w-5') ?> Voir les inscriptions
    </a>
</div>

<!-- Cartes stats -->
<div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
    <div class="card flex items-center gap-4 transition hover:-translate-y-0.5 hover:shadow-md">
        <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-blue/10 text-brand-blue">
            <?= icon('clipboard-list', 'h-6 w-6') ?>
        </span>
        <div>
            <div class="text-3xl font-bold leading-tight text-brand-navy"><?= $total ?></div>
            <div class="text-sm text-slate-500">Inscriptions<?= $active ? ' (formation active)' : ' (total)' ?></div>
        </div>
    </div>
    <div class="card flex items-center gap-4 transition hover:-translate-y-0.5 hover:shadow-md">
        <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">
            <?= icon('paper-clip', 'h-6 w-6') ?>
        </span>
        <div>
            <div class="text-3xl font-bold leading-tight text-brand-navy"><?= $stats['preuve_recue'] ?></div>
            <div class="text-sm text-slate-500">Preuves à vérifier</div>
        </div>
    </div>
    <div class="card flex items-center gap-4 transition hover:-translate-y-0.5 hover:shadow-md">
        <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500">
            <?= icon('check-circle', 'h-6 w-6') ?>
        </span>
        <div>
            <div class="text-3xl font-bold leading-tight text-brand-navy"><?= $stats['valide'] ?></div>
            <div class="text-sm text-slate-500">Validées</div>
        </div>
    </div>
    <div class="card flex items-center gap-4 transition hover:-translate-y-0.5 hover:shadow-md">
        <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-500">
            <?= icon('user', 'h-6 w-6') ?>
        </span>
        <div>
            <div class="text-3xl font-bold leading-tight text-brand-navy"><?= $stats['preinscrit'] ?></div>
            <div class="text-sm text-slate-500">Préinscrits (sans preuve)</div>
        </div>
    </div>
</div>

<!-- Cartes finances & formations -->
<div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
    <div class="card flex items-center gap-4 transition hover:-translate-y-0.5 hover:shadow-md">
        <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-teal-500/10 text-teal-600">
            <?= icon('banknotes', 'h-6 w-6') ?>
        </span>
        <div>
            <div class="text-2xl font-bold leading-tight text-brand-navy"><?= format_price($caTotal) ?></div>
            <div class="text-sm text-slate-500">CA total encaissé</div>
        </div>
    </div>
    <div class="card flex items-center gap-4 transition hover:-translate-y-0.5 hover:shadow-md">
        <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600">
            <?= icon('credit-card', 'h-6 w-6') ?>
        </span>
        <div>
            <div class="text-2xl font-bold leading-tight text-brand-navy">
                <?= $active ? format_price($caActive) : '—' ?>
            </div>
            <div class="text-sm text-slate-500">
                <?php if ($active): ?>
                    CA formation en cours
                    <span class="block text-xs text-slate-400">
                        Reste à encaisser : <?= format_price($resteAEncaisser) ?>
                    </span>
                <?php else: ?>
                    CA formation en cours (aucune active)
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card flex items-center gap-4 transition hover:-translate-y-0.5 hover:shadow-md">
        <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-violet-500/10 text-violet-500">
            <?= icon('archive-box', 'h-6 w-6') ?>
        </span>
        <div>
            <div class="text-3xl font-bold leading-tight text-brand-navy"><?= (int) $archivedCount ?></div>
            <div class="text-sm text-slate-500">Formations bouclées</div>
        </div>
    </div>
    <div class="card flex items-center gap-4 transition hover:-translate-y-0.5 hover:shadow-md">
        <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-rose-500/10 text-rose-500">
            <?= icon('user-plus', 'h-6 w-6') ?>
        </span>
        <div>
            <div class="text-3xl font-bold leading-tight text-brand-navy">
                <?php if (!$active): ?>
                    —
                <?php elseif ($placesRestantes === null): ?>
                    Illimité
                <?php else: ?>
                    <?= (int) $placesRestantes ?>
                <?php endif; ?>
            </div>
            <div class="text-sm text-slate-500">Places restantes (formation en cours)</div>
        </div>
    </div>
</div>

<!-- Cartes offres d'emploi & candidatures -->
<div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
    <div class="card flex items-center gap-4 transition hover:-translate-y-0.5 hover:shadow-md">
        <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-blue/10 text-brand-blue">
            <?= icon('briefcase', 'h-6 w-6') ?>
        </span>
        <div>
            <div class="text-3xl font-bold leading-tight text-brand-navy"><?= (int) $offresPubliees ?></div>
            <div class="text-sm text-slate-500">Offres publiées en cours</div>
        </div>
    </div>
    <div class="card flex items-center gap-4 transition hover:-translate-y-0.5 hover:shadow-md">
        <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600">
            <?= icon('document-text', 'h-6 w-6') ?>
        </span>
        <div>
            <div class="text-3xl font-bold leading-tight text-brand-navy"><?= (int) $dossiersTotal ?></div>
            <div class="text-sm text-slate-500">Dossiers déposés (toutes offres)</div>
        </div>
    </div>
    <div class="card flex items-center gap-4 transition hover:-translate-y-0.5 hover:shadow-md">
        <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">
            <?= icon('clock', 'h-6 w-6') ?>
        </span>
        <div>
            <div class="text-3xl font-bold leading-tight text-brand-navy">
                <?= $soonestOffer ? (int) $soonestOffer['nb_candidatures'] : '—' ?>
            </div>
            <div class="text-sm text-slate-500">
                <?php if ($soonestOffer): ?>
                    Dossiers — offre en cours
                    <span class="block truncate text-xs text-slate-400" title="<?= e($soonestOffer['title']) ?>">
                        <?= e($soonestOffer['title']) ?> (clôture <?= format_datetime($soonestOffer['closes_at']) ?>)
                    </span>
                <?php else: ?>
                    Dossiers — offre en cours (aucune offre ouverte)
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card flex items-center gap-4 transition hover:-translate-y-0.5 hover:shadow-md">
        <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500">
            <?= icon('star', 'h-6 w-6') ?>
        </span>
        <div>
            <div class="text-3xl font-bold leading-tight text-brand-navy"><?= (int) $candidatsRetenus ?></div>
            <div class="text-sm text-slate-500">Candidats retenus</div>
        </div>
    </div>
</div>

<!-- Graphiques -->
<div class="mt-6 grid grid-cols-1 gap-5 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('chart-bar', 'h-5 w-5') ?></span>
            Inscriptions — 14 derniers jours
        </h2>
        <div data-chart-bars="<?= e(json_encode($barsData, JSON_UNESCAPED_UNICODE)) ?>"></div>
    </div>

    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('sparkles', 'h-5 w-5') ?></span>
            Répartition par statut
        </h2>
        <div class="relative mx-auto h-44 w-44"
             data-chart-donut="<?= e(json_encode($donutData, JSON_UNESCAPED_UNICODE)) ?>" data-thickness="5">
            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-3xl font-bold text-brand-navy"><?= $total ?></span>
                <span class="text-[11px] uppercase tracking-wide text-slate-400">au total</span>
            </div>
        </div>
        <ul class="mt-5 space-y-2">
            <?php foreach (Inscription::STATUT_LABELS as $key => $label): ?>
                <li class="flex items-center gap-2.5 text-sm">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background:<?= e($statutColors[$key]) ?>"></span>
                    <span class="flex-1 text-slate-600"><?= e($label) ?></span>
                    <span class="font-semibold text-brand-navy"><?= (int) $stats[$key] ?></span>
                    <span class="w-11 text-right text-xs text-slate-400">
                        <?= $total > 0 ? round($stats[$key] / $total * 100) : 0 ?> %
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="mt-6 grid grid-cols-1 gap-5 xl:grid-cols-3">
    <!-- Formation active -->
    <div class="card xl:col-span-2">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('academic-cap', 'h-5 w-5') ?></span>
            Formation active
        </h2>
        <?php if ($active): ?>
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-lg font-bold text-brand-navy"><?= e($active['titre']) ?></span>
                <span class="badge bg-emerald-50 text-emerald-700 ring-emerald-200">
                    <?= icon('lock-open', 'h-3.5 w-3.5') ?> Inscriptions ouvertes
                </span>
            </div>
            <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2.5 text-sm text-slate-600">
                <?php if (!empty($active['lieu'])): ?>
                    <span class="inline-flex items-center gap-1.5">
                        <?= icon('map-pin', 'h-4 w-4 text-slate-400') ?> <?= e($active['lieu']) ?>
                    </span>
                <?php endif; ?>
                <span class="inline-flex items-center gap-1.5">
                    <?= icon('banknotes', 'h-4 w-4 text-slate-400') ?> <?= format_price($active['prix']) ?>
                </span>
                <?php foreach ($activeSlots as $slot): ?>
                    <span class="inline-flex items-center gap-1.5">
                        <?= icon('calendar', 'h-4 w-4 text-slate-400') ?> <?= e($slot['label']) ?>
                    </span>
                <?php endforeach; ?>
            </div>
            <?php if (\App\Services\Auth::isAtLeast('admin')): ?>
                <a class="btn-ghost btn-sm mt-5" href="/admin/formations/<?= (int) $active['id'] ?>/edit">
                    <?= icon('pencil', 'h-4 w-4') ?> Modifier la formation
                </a>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-sm text-slate-500">Aucune formation avec inscriptions ouvertes actuellement.</p>
            <a class="btn-ghost btn-sm mt-4" href="/admin/formations">
                <?= icon('academic-cap', 'h-4 w-4') ?> Gérer les formations
            </a>
        <?php endif; ?>
    </div>

    <!-- Mini donut hommes / femmes -->
    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('users', 'h-5 w-5') ?></span>
            Hommes / Femmes
        </h2>
        <div class="flex items-center gap-6">
            <div class="relative h-28 w-28 shrink-0"
                 data-chart-donut="<?= e(json_encode($genderData, JSON_UNESCAPED_UNICODE)) ?>" data-thickness="6">
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <span class="text-xl font-bold text-brand-navy"><?= $genderTotal ?></span>
                </div>
            </div>
            <ul class="flex-1 space-y-3">
                <?php foreach ($genderData as $g): ?>
                    <li class="flex items-center gap-2.5 text-sm">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background:<?= e($g['color']) ?>"></span>
                        <span class="flex-1 text-slate-600"><?= e($g['label']) ?></span>
                        <span class="font-semibold text-brand-navy"><?= (int) $g['value'] ?></span>
                        <span class="w-11 text-right text-xs text-slate-400">
                            <?= $genderTotal > 0 ? round($g['value'] / $genderTotal * 100) : 0 ?> %
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<!-- Dernières inscriptions -->
<div class="card mt-6">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="card-title mb-0">
            <span class="card-title-icon"><?= icon('clock', 'h-5 w-5') ?></span>
            Dernières inscriptions
        </h2>
        <a href="/admin/inscriptions" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-blue transition hover:text-brand-navy">
            Tout voir <?= icon('chevron-right', 'h-4 w-4') ?>
        </a>
    </div>
    <?php if ($latest): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Candidat</th><th>Téléphone</th><th>Formation</th><th>Statut</th><th>Date</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($latest as $row): ?>
                    <tr>
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
                        <td><?= e($row['phone']) ?></td>
                        <td><?= e($row['formation_titre']) ?></td>
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
    <?php else: ?>
        <div class="flex flex-col items-center gap-3 py-10 text-center">
            <?= icon('inbox', 'h-10 w-10 text-slate-300') ?>
            <p class="text-sm text-slate-500">Aucune inscription pour le moment.</p>
        </div>
    <?php endif; ?>
</div>
