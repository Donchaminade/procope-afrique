<?php
/** Variables : $formation (archivée), $slots, $inscriptions */
use App\Models\Inscription;

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
        <h1 class="text-2xl font-bold text-brand-navy"><?= e($formation['titre']) ?></h1>
        <p class="mt-1 flex items-center gap-1.5 text-sm text-slate-500">
            <?= icon('archive-box', 'h-4 w-4') ?>
            Formation archivée le <?= format_datetime($formation['archived_at']) ?>
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <a class="btn-ghost" href="/admin/archives">
            <?= icon('arrow-left', 'h-4 w-4') ?> Retour aux archives
        </a>
        <?php if (\App\Services\Auth::isAtLeast('admin')): ?>
            <form method="post" action="/admin/archives/<?= (int) $formation['id'] ?>/restore"
                  data-confirm="Restaurer cette formation ? Elle réapparaîtra dans la liste des formations (inscriptions fermées).">
                <?= csrf_field() ?>
                <button class="btn-primary" type="submit">
                    <?= icon('arrow-path', 'h-4 w-4') ?> Restaurer
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="card lg:col-span-2">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('academic-cap', 'h-5 w-5') ?></span>
            Détails de la formation
        </h2>
        <div class="flex flex-col gap-5 sm:flex-row">
            <?php if (!empty($formation['affiche_path'])): ?>
                <img src="/uploads/affiches/<?= e($formation['affiche_path']) ?>"
                     alt="Affiche de la formation"
                     class="h-40 w-auto shrink-0 self-start rounded-xl object-cover ring-1 ring-slate-200 shadow-soft">
            <?php endif; ?>
            <dl class="grid flex-1 grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="font-semibold text-slate-500">Lieu</dt>
                    <dd class="mt-0.5 text-slate-700"><?= e($formation['lieu'] ?? '—') ?></dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500">Frais de participation</dt>
                    <dd class="mt-0.5 font-medium text-slate-700"><?= format_price($formation['prix']) ?></dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500">Places max</dt>
                    <dd class="mt-0.5 text-slate-700">
                        <?= $formation['places_max'] ? (int) $formation['places_max'] : 'Illimité' ?>
                    </dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500">Téléphone de contact</dt>
                    <dd class="mt-0.5 text-slate-700"><?= e($formation['contact_phone'] ?? '—') ?></dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500">Créée le</dt>
                    <dd class="mt-0.5 text-slate-700"><?= format_datetime($formation['created_at']) ?></dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500">Archivée le</dt>
                    <dd class="mt-0.5 text-slate-700"><?= format_datetime($formation['archived_at']) ?></dd>
                </div>
                <?php if (!empty($formation['intro'])): ?>
                    <div class="sm:col-span-2">
                        <dt class="font-semibold text-slate-500">Introduction</dt>
                        <dd class="mt-0.5 whitespace-pre-line text-slate-700"><?= e($formation['intro']) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('calendar', 'h-5 w-5') ?></span>
            Créneaux
        </h2>
        <?php if ($slots): ?>
            <ul class="space-y-3">
                <?php foreach ($slots as $slot): ?>
                    <li class="rounded-xl bg-slate-50 p-3 ring-1 ring-inset ring-slate-200">
                        <div class="text-sm font-semibold text-slate-700"><?= e($slot['label']) ?></div>
                        <div class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500">
                            <?= icon('clock', 'h-3.5 w-3.5') ?>
                            de <?= date('H\hi', strtotime($slot['starts_at'])) ?>
                            à <?= date('H\hi', strtotime($slot['ends_at'])) ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-sm text-slate-500">Aucun créneau enregistré.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2 class="card-title">
        <span class="card-title-icon"><?= icon('clipboard-list', 'h-5 w-5') ?></span>
        Inscrits
        <span class="ml-1 rounded-full bg-brand-blue/10 px-2.5 py-0.5 text-sm font-semibold text-brand-blue">
            <?= count($inscriptions) ?>
        </span>
    </h2>
    <?php if ($inscriptions): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Candidat</th><th>Téléphone</th><th>E-mail</th><th>Statut</th>
                    <th>Montant payé</th><th>Date</th>
                    <th class="text-right">Fiche</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($inscriptions as $row): ?>
                    <tr>
                        <td class="font-semibold text-brand-navy"><?= e($row['full_name']) ?></td>
                        <td class="whitespace-nowrap"><?= e($row['phone']) ?></td>
                        <td><?= e($row['email'] ?? '—') ?></td>
                        <td>
                            <span class="badge <?= $badgeClasses[$row['statut']] ?? 'bg-slate-100 text-slate-600 ring-slate-300' ?>">
                                <?= e(Inscription::STATUT_LABELS[$row['statut']] ?? $row['statut']) ?>
                            </span>
                        </td>
                        <td class="whitespace-nowrap">
                            <?php if ($row['amount_received'] !== null): ?>
                                <span class="font-medium text-emerald-700"><?= format_price($row['amount_received']) ?></span>
                            <?php elseif ($row['amount_declared'] !== null): ?>
                                <span class="text-slate-600"><?= format_price($row['amount_declared']) ?></span>
                                <span class="text-xs text-slate-400">(déclaré)</span>
                            <?php else: ?>
                                <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="whitespace-nowrap text-slate-500"><?= format_datetime($row['created_at']) ?></td>
                        <td class="text-right">
                            <a class="btn-icon" href="/admin/inscriptions/<?= (int) $row['id'] ?>"
                               title="Voir la fiche d'inscription">
                                <?= icon('eye', 'h-4 w-4') ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="flex flex-col items-center gap-3 py-12 text-center">
            <?= icon('inbox', 'h-10 w-10 text-slate-300') ?>
            <p class="text-sm text-slate-500">Aucun inscrit pour cette formation archivée.</p>
        </div>
    <?php endif; ?>
</div>
