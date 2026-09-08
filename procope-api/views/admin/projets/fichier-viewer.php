<?php
$detailUrl = '/admin/projets/depots/' . (int) $application['id'];
$fileUrl = $detailUrl . '/fichier/fichier';
?>
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="flex min-w-0 items-center gap-3">
        <span class="card-title-icon"><?= icon('paper-clip', 'h-5 w-5') ?></span>
        <div class="min-w-0">
            <h1 class="truncate text-xl font-bold text-brand-navy">
                <?= e($application['full_name']) ?> — Pitch deck
            </h1>
            <p class="truncate text-xs text-slate-400">
                <?= e($application['file_name'] ?: 'pitch.pdf') ?>
                — <?= e($application['project_title'] ?: 'Candidature spontanée') ?>
            </p>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a class="btn-ghost" href="<?= e($fileUrl) ?>?download=1">
            <?= icon('download', 'h-4 w-4') ?> Télécharger
        </a>
        <a class="btn-secondary" href="<?= e($detailUrl) ?>">
            <?= icon('arrow-left', 'h-4 w-4') ?> Retour au dépôt
        </a>
    </div>
</div>

<iframe src="<?= e($fileUrl) ?>"
        title="Pitch deck de <?= e($application['full_name']) ?>"
        class="h-[calc(100vh-13rem)] min-h-[24rem] w-full rounded-xl bg-slate-100 ring-1 ring-inset ring-slate-200"></iframe>
