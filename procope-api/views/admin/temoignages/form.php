<?php
/** Variables : $testimonial (null si création) */
use App\Models\Testimonial;

$isEdit = $testimonial !== null;
$value = static fn (string $key, ?string $default = '') => e($testimonial[$key] ?? $default);
$photoUrl = $isEdit ? Testimonial::photoUrl($testimonial['photo_path'] ?? null) : null;
$defaultStatut = $isEdit ? ($testimonial['statut'] ?? 'en_attente') : 'publie';
?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-brand-navy">
            <?= $isEdit ? 'Modifier le témoignage' : 'Nouveau témoignage' ?>
        </h1>
        <p class="mt-1 text-sm text-slate-500">
            <?= $isEdit
                ? e($testimonial['author_name'])
                : 'Créé par l\'équipe : publié sur le site par défaut. Photo facultative.' ?>
        </p>
    </div>
    <a class="btn-ghost" href="<?= $isEdit ? '/admin/temoignages/' . (int) $testimonial['id'] : '/admin/temoignages' ?>">
        <?= icon('arrow-left', 'h-4 w-4') ?> Retour
    </a>
</div>

<form method="post"
      action="<?= $isEdit ? '/admin/temoignages/' . (int) $testimonial['id'] : '/admin/temoignages' ?>"
      enctype="multipart/form-data" class="max-w-3xl space-y-6"
      data-loading-submit data-loading-label="Enregistrement…">
    <?= csrf_field() ?>

    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('chat-bubble', 'h-5 w-5') ?></span>
            Contenu
        </h2>
        <div class="space-y-5">
            <div>
                <label class="label" for="t-name">Nom *</label>
                <input class="input" id="t-name" type="text" name="author_name" required maxlength="150"
                       placeholder="Ex. : Afi Mensah" value="<?= $value('author_name') ?>">
            </div>
            <div>
                <label class="label" for="t-role">Rôle / activité</label>
                <input class="input" id="t-role" type="text" name="role_title" maxlength="190"
                       placeholder="Ex. : CEO de …, participante à la formation"
                       value="<?= $value('role_title') ?>">
            </div>
            <div>
                <label class="label" for="t-email">E-mail <span class="hint">(facultatif)</span></label>
                <input class="input" id="t-email" type="email" name="author_email" maxlength="190"
                       value="<?= $value('author_email') ?>">
            </div>
            <div>
                <label class="label" for="t-quote">Témoignage *</label>
                <textarea class="input min-h-[160px]" id="t-quote" name="quote" required maxlength="2000"
                          placeholder="Texte du témoignage"><?= $value('quote') ?></textarea>
            </div>
            <div>
                <label class="label" for="t-statut">Statut</label>
                <select class="input" id="t-statut" name="statut">
                    <?php foreach (Testimonial::STATUT_LABELS as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $defaultStatut === $key ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('photo', 'h-5 w-5') ?></span>
            Photo <span class="hint font-normal">(facultative, JPEG / PNG / WebP, 2 Mo max)</span>
        </h2>
        <?php if ($photoUrl): ?>
            <div class="mb-4 flex items-center gap-4">
                <img src="<?= e($photoUrl) ?>" alt="" class="h-20 w-20 rounded-full object-cover ring-1 ring-slate-200">
                <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remove_photo" value="1"
                           class="h-4 w-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue">
                    Retirer la photo actuelle
                </label>
            </div>
        <?php endif; ?>
        <input class="input" id="t-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
    </div>

    <div class="flex flex-wrap gap-3">
        <button class="btn-primary" type="submit">
            <?= icon('check', 'h-4 w-4') ?> Enregistrer
        </button>
        <a class="btn-ghost" href="/admin/temoignages">Annuler</a>
    </div>
</form>
