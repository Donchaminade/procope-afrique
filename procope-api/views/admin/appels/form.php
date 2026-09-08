<?php
/** Variables : $call (null si création) */
$isEdit = $call !== null;
$value = static fn (string $key, ?string $default = '') => e($call[$key] ?? $default);
$toLocal = static function (?string $datetime): string {
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    return $ts ? date('Y-m-d\TH:i', $ts) : '';
};
?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-brand-navy">
            <?= $isEdit ? 'Modifier l\'appel' : 'Nouvel appel' ?>
        </h1>
        <?php if ($isEdit): ?>
            <p class="mt-1 text-sm text-slate-500"><?= e($call['title']) ?></p>
        <?php endif; ?>
    </div>
    <a class="btn-ghost" href="/admin/appels">
        <?= icon('arrow-left', 'h-4 w-4') ?> Retour
    </a>
</div>

<form method="post" action="<?= $isEdit ? '/admin/appels/' . (int) $call['id'] : '/admin/appels' ?>"
      class="max-w-4xl space-y-6" data-loading-submit data-loading-label="Enregistrement…">
    <?= csrf_field() ?>

    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('megaphone', 'h-5 w-5') ?></span>
            Informations de l'appel
        </h2>
        <div class="space-y-5">
            <div>
                <label class="label" for="c-title">Titre *</label>
                <input class="input" id="c-title" type="text" name="title" required maxlength="200"
                       placeholder="Ex. : Appel AgriTech 2026"
                       value="<?= $value('title') ?>">
            </div>
            <div>
                <label class="label" for="c-sector">Secteur</label>
                <input class="input" id="c-sector" type="text" name="sector" maxlength="190"
                       placeholder="AgriTech, Santé, Éducation…"
                       value="<?= $value('sector') ?>">
            </div>
            <div>
                <label class="label" for="c-description">Description</label>
                <textarea class="input min-h-[220px]" id="c-description"
                          name="description"><?= $value('description') ?></textarea>
            </div>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('calendar', 'h-5 w-5') ?></span>
            Dates et publication
        </h2>
        <div class="space-y-5">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="c-opens">Ouverture *</label>
                    <input class="input" id="c-opens" type="datetime-local" name="opens_at" required
                           value="<?= $toLocal($call['opens_at'] ?? null) ?>">
                </div>
                <div>
                    <label class="label" for="c-closes">Clôture *</label>
                    <input class="input" id="c-closes" type="datetime-local" name="closes_at" required
                           value="<?= $toLocal($call['closes_at'] ?? null) ?>">
                    <p class="hint mt-1.5">
                        Passée cette date, l'appel disparaît du site public et les candidatures sont refusées.
                    </p>
                </div>
            </div>
            <label class="flex cursor-pointer items-center gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-100/70">
                <input type="checkbox" class="checkbox" name="is_published" value="1"
                    <?= (int) ($call['is_published'] ?? 0) ? 'checked' : '' ?>>
                <span class="text-sm font-medium text-slate-700">
                    Appel publié (visible sur le site s'il est dans ses dates)
                </span>
            </label>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button class="btn-primary" type="submit">
            <?= icon('check', 'h-5 w-5') ?> <?= $isEdit ? 'Enregistrer' : 'Créer l\'appel' ?>
        </button>
        <a class="btn-ghost" href="/admin/appels">Annuler</a>
    </div>
</form>
