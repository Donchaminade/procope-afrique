<?php
/** Variables : $gallery (null si création), $images, $formations */
use App\Models\FormationGallery;

$isEdit = $gallery !== null;
$images = $images ?? [];
$formations = $formations ?? [];
$kind = FormationGallery::normalizeKind($gallery['kind'] ?? null);
$isAffiche = $kind === FormationGallery::KIND_AFFICHE;
$value = static fn (string $key, ?string $default = '') => e($gallery[$key] ?? $default);
?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-brand-navy">
            <?= $isEdit ? ($isAffiche ? 'Modifier l\'affiche' : 'Modifier l\'album') : 'Nouvel album' ?>
        </h1>
        <?php if ($isEdit): ?>
            <p class="mt-1 text-sm text-slate-500"><?= e($gallery['title']) ?></p>
        <?php else: ?>
            <p class="mt-1 text-sm text-slate-500">
                Photos de formation ou affiche d'événement passé, puis visibles sur Actualités.
            </p>
        <?php endif; ?>
    </div>
    <a class="btn-ghost" href="/admin/galeries">
        <?= icon('arrow-left', 'h-4 w-4') ?> Retour
    </a>
</div>

<form method="post" action="<?= $isEdit ? '/admin/galeries/' . (int) $gallery['id'] : '/admin/galeries' ?>"
      enctype="multipart/form-data" class="max-w-4xl space-y-6" data-loading-submit data-loading-label="Enregistrement…">
    <?= csrf_field() ?>

    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('photo', 'h-5 w-5') ?></span>
            Album
        </h2>
        <div class="space-y-5">
            <fieldset>
                <legend class="label mb-2">Type *</legend>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2" data-gallery-kind>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-100/70">
                        <input type="radio" class="mt-0.5" name="kind" value="photos"
                            <?= $kind === FormationGallery::KIND_PHOTOS ? 'checked' : '' ?>>
                        <span>
                            <span class="block text-sm font-semibold text-brand-navy">Photos de formation</span>
                            <span class="mt-0.5 block text-xs text-slate-500">Galerie d'une vague : plusieurs photos, bandeau + grille.</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-100/70">
                        <input type="radio" class="mt-0.5" name="kind" value="affiche"
                            <?= $isAffiche ? 'checked' : '' ?>>
                        <span>
                            <span class="block text-sm font-semibold text-brand-navy">Affiche événement</span>
                            <span class="mt-0.5 block text-xs text-slate-500">Événement passé : 1 visuel principal, éventuellement quelques extras.</span>
                        </span>
                    </label>
                </div>
            </fieldset>
            <div>
                <label class="label" for="g-formation">Formation existante <span class="hint">(optionnel)</span></label>
                <select class="input" id="g-formation" name="formation_id" data-gallery-formation>
                    <option value="">Titre libre — sans lier une formation</option>
                    <?php foreach ($formations as $formation): ?>
                        <?php
                        $starts = $formation['first_starts_at'] ?? null;
                        $dataYear = '';
                        $dataMonth = '';
                        if ($starts) {
                            $ts = strtotime((string) $starts);
                            if ($ts) {
                                $dataYear = date('Y', $ts);
                                $dataMonth = (string) (int) date('n', $ts);
                            }
                        }
                        ?>
                        <option value="<?= (int) $formation['id'] ?>"
                                data-title="<?= e($formation['titre']) ?>"
                                data-year="<?= e($dataYear) ?>"
                                data-month="<?= e($dataMonth) ?>"
                            <?= (int) ($gallery['formation_id'] ?? 0) === (int) $formation['id'] ? 'selected' : '' ?>>
                            <?= e($formation['titre']) ?>
                            <?php if (!empty($formation['archived_at'])): ?>
                                (archivée)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="hint mt-1.5">
                    En choisissant une formation, le titre et l'année se préremplissent à partir du premier créneau.
                </p>
            </div>
            <div>
                <label class="label" for="g-title">Nom affiché *</label>
                <input class="input" id="g-title" type="text" name="title" required maxlength="200"
                       placeholder="Ex. : Formation PROCOPE 2027 — 1ère vague"
                       value="<?= $value('title') ?>"
                       data-placeholder-photos="Ex. : Formation PROCOPE 2027 — 1ère vague"
                       data-placeholder-affiche="Ex. : Bootcamp pitch">
            </div>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="g-year">Année *</label>
                    <input class="input" id="g-year" type="number" name="year" required min="1990" max="2100"
                           placeholder="2027" value="<?= $value('year') ?>">
                </div>
                <div>
                    <label class="label" for="g-month">Mois <span class="hint">(optionnel)</span></label>
                    <select class="input" id="g-month" name="month">
                        <option value="">Non précisé</option>
                        <?php foreach (FormationGallery::MONTH_LABELS as $num => $label): ?>
                            <option value="<?= (int) $num ?>"
                                <?= (int) ($gallery['month'] ?? 0) === $num ? 'selected' : '' ?>>
                                <?= e(ucfirst($label)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="label" for="g-description">Description <span class="hint">(optionnel)</span></label>
                <textarea class="input min-h-[90px]" id="g-description" name="description"><?= $value('description') ?></textarea>
            </div>
        </div>
    </div>

    <?php if (!$isEdit): ?>
        <div class="card">
            <h2 class="card-title">
                <span class="card-title-icon"><?= icon('photo', 'h-5 w-5') ?></span>
                <span data-kind-photos<?= $isAffiche ? ' class="hidden"' : '' ?>>Photos <span class="hint">(optionnel)</span></span>
                <span data-kind-affiche<?= $isAffiche ? '' : ' class="hidden"' ?>>Affiche principale *</span>
            </h2>
            <div>
                <label class="label" for="g-images">Images JPEG, PNG ou WebP <span class="hint">(sélection multiple)</span></label>
                <input class="input" id="g-images" type="file" name="images[]" multiple
                       accept="image/jpeg,image/png,image/webp" data-gallery-create-files>
                <p class="hint mt-1.5<?= $isAffiche ? ' hidden' : '' ?>" data-kind-photos>La première photo sert de couverture sur le site.</p>
                <p class="hint mt-1.5<?= $isAffiche ? '' : ' hidden' ?>" data-kind-affiche>
                    L'image principale est obligatoire. Les fichiers suivants deviennent des visuels extra.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('eye', 'h-5 w-5') ?></span>
            Publication
        </h2>
        <label class="flex cursor-pointer items-center gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-100/70">
            <input type="checkbox" class="checkbox" name="is_published" value="1"
                <?= (int) ($gallery['is_published'] ?? 0) ? 'checked' : '' ?>>
            <span class="text-sm font-medium text-slate-700<?= $isAffiche ? ' hidden' : '' ?>" data-kind-photos>
                Album publié (visible sur Actualités — galerie photos)
            </span>
            <span class="text-sm font-medium text-slate-700<?= $isAffiche ? '' : ' hidden' ?>" data-kind-affiche>
                Affiche publiée (visible sur Actualités — événements passés)
            </span>
        </label>
    </div>

    <div class="flex flex-wrap gap-3">
        <button class="btn-primary" type="submit">
            <?= icon('check', 'h-5 w-5') ?> <?= $isEdit ? 'Enregistrer' : 'Créer' ?>
        </button>
        <a class="btn-ghost" href="/admin/galeries">Annuler</a>
    </div>
</form>

<?php if ($isEdit): ?>
    <div class="mt-8 max-w-4xl">
        <div class="card">
            <h2 class="card-title">
                <span class="card-title-icon"><?= icon('photo', 'h-5 w-5') ?></span>
                <?= $isAffiche ? 'Affiche et visuels' : 'Photos de l\'album' ?>
            </h2>
            <p class="mb-4 text-sm text-slate-500">
                <?= $isAffiche
                    ? 'La première image est l\'affiche principale. Vous pouvez ajouter quelques visuels extra.'
                    : 'La première photo (ordre) est la couverture. Utilisez les flèches pour réordonner.' ?>
            </p>

            <?php if ($images): ?>
                <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    <?php foreach ($images as $i => $image): ?>
                        <div class="overflow-hidden rounded-xl ring-1 ring-inset ring-slate-200">
                            <div class="relative aspect-[4/3] bg-slate-100">
                                <img src="/uploads/galeries/<?= e(rawurlencode($image['path'])) ?>"
                                     alt="<?= e($image['caption'] ?: 'Photo de l\'album') ?>"
                                     class="absolute inset-0 h-full w-full object-cover">
                                <?php if ($i === 0): ?>
                                    <span class="absolute left-2 top-2 inline-flex items-center gap-1 rounded-full bg-brand-orange px-2 py-0.5 text-[11px] font-bold text-white shadow">
                                        <?= $isAffiche ? 'Principale' : 'Couverture' ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center justify-between gap-1 bg-white px-2 py-2">
                                <div class="flex items-center gap-1">
                                    <form method="post"
                                          action="/admin/galeries/<?= (int) $gallery['id'] ?>/images/<?= (int) $image['id'] ?>/up"
                                          data-loading-submit>
                                        <?= csrf_field() ?>
                                        <button class="btn-icon" type="submit" title="Monter"
                                            <?= $i === 0 ? 'disabled' : '' ?>>
                                            <?= icon('chevron-up', 'h-4 w-4') ?>
                                        </button>
                                    </form>
                                    <form method="post"
                                          action="/admin/galeries/<?= (int) $gallery['id'] ?>/images/<?= (int) $image['id'] ?>/down"
                                          data-loading-submit>
                                        <?= csrf_field() ?>
                                        <button class="btn-icon" type="submit" title="Descendre"
                                            <?= $i === count($images) - 1 ? 'disabled' : '' ?>>
                                            <?= icon('chevron-down', 'h-4 w-4') ?>
                                        </button>
                                    </form>
                                </div>
                                <form method="post"
                                      action="/admin/galeries/<?= (int) $gallery['id'] ?>/images/<?= (int) $image['id'] ?>/delete"
                                      data-loading-submit data-loading-label="Suppression…"
                                      data-confirm="Supprimer définitivement cette photo ?">
                                    <?= csrf_field() ?>
                                    <button class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-red-500 transition hover:bg-red-50"
                                            type="submit" title="Supprimer cette photo">
                                        <?= icon('trash', 'h-3.5 w-3.5') ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="mb-6 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-400 ring-1 ring-inset ring-slate-200">
                    Aucune photo pour le moment : l'album s'affiche sans couverture sur le site.
                </p>
            <?php endif; ?>

            <form method="post" action="/admin/galeries/<?= (int) $gallery['id'] ?>/images"
                  enctype="multipart/form-data" class="flex flex-wrap items-end gap-3"
                  data-loading-submit data-loading-label="Envoi…">
                <?= csrf_field() ?>
                <div class="min-w-64 flex-1">
                    <label class="label" for="g-images">Ajouter des photos <span class="hint">(JPEG, PNG ou WebP — sélection multiple)</span></label>
                    <input class="input" id="g-images" type="file" name="images[]" multiple required
                           accept="image/jpeg,image/png,image/webp">
                </div>
                <button class="btn-secondary" type="submit">
                    <?= icon('plus', 'h-4 w-4') ?> Ajouter
                </button>
            </form>
        </div>
    </div>

    <div class="mt-8 max-w-4xl">
        <div class="card border border-red-100 !ring-red-100">
            <h2 class="card-title !text-red-600">
                <span class="card-title-icon !bg-red-50 !text-red-500"><?= icon('exclamation-triangle', 'h-5 w-5') ?></span>
                Zone dangereuse
            </h2>
            <p class="mb-4 text-sm text-slate-500">
                La suppression est définitive et retire toutes les photos de l'album.
            </p>
            <form method="post" action="/admin/galeries/<?= (int) $gallery['id'] ?>/delete"
                  data-loading-submit data-loading-label="Suppression…"
                  data-confirm="Supprimer définitivement cet album et toutes ses photos ?">
                <?= csrf_field() ?>
                <button class="btn-danger" type="submit">
                    <?= icon('trash', 'h-4 w-4') ?> Supprimer l'album
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>
