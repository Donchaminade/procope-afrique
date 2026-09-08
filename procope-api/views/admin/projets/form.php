<?php
/** Variables : $project (null si création), $images + $recipients + $sourceApplication (page edit) */
use App\Models\IncubatedProject;

$isEdit = $project !== null;
$images = $images ?? [];
$recipients = (int) ($recipients ?? 0);
$sourceApplication = $sourceApplication ?? null;
$value = static fn (string $key, ?string $default = '') => e($project[$key] ?? $default);
?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-brand-navy">
            <?= $isEdit ? 'Modifier le projet' : 'Nouveau projet' ?>
        </h1>
        <?php if ($isEdit): ?>
            <p class="mt-1 text-sm text-slate-500"><?= e($project['title']) ?></p>
        <?php else: ?>
            <p class="mt-1 text-sm text-slate-500">
                Projet vitrine créé à la main, sans dépôt candidat. Aucun e-mail n'est envoyé au porteur.
            </p>
        <?php endif; ?>
    </div>
    <a class="btn-ghost" href="/admin/projets">
        <?= icon('arrow-left', 'h-4 w-4') ?> Retour
    </a>
</div>

<form method="post" action="<?= $isEdit ? '/admin/projets/' . (int) $project['id'] : '/admin/projets' ?>"
      enctype="multipart/form-data" class="max-w-4xl space-y-6" data-loading-submit data-loading-label="Enregistrement…">
    <?= csrf_field() ?>

    <?php if ($sourceApplication): ?>
        <div class="rounded-xl bg-brand-blue/5 p-4 text-sm text-slate-700 ring-1 ring-inset ring-brand-blue/20">
            <p class="font-semibold text-brand-navy">Issu d'un dépôt retenu</p>
            <p class="mt-1">
                Porteur : <?= e($sourceApplication['full_name']) ?>
                (<?= e($sourceApplication['email']) ?>)
                — <?= e($sourceApplication['call_title'] ?: 'Candidature spontanée') ?>.
                <a class="font-medium text-brand-blue hover:underline"
                   href="/admin/projets/depots/<?= (int) $sourceApplication['id'] ?>">Voir le dossier</a>
            </p>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('rocket', 'h-5 w-5') ?></span>
            Informations du projet
        </h2>
        <div class="space-y-5">
            <div>
                <label class="label" for="p-title">Titre *</label>
                <input class="input" id="p-title" type="text" name="title" required maxlength="200"
                       placeholder="Ex. : AgriConnect"
                       value="<?= $value('title') ?>">
            </div>
            <div>
                <label class="label" for="p-pitch">Accroche <span class="hint">(cartes du site public)</span></label>
                <textarea class="input min-h-[90px]" id="p-pitch" name="pitch" maxlength="500"><?= $value('pitch') ?></textarea>
            </div>
            <div>
                <label class="label" for="p-description">Description complète</label>
                <textarea class="input min-h-[220px]" id="p-description" name="description"><?= $value('description') ?></textarea>
            </div>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="p-sector">Secteur</label>
                    <input class="input" id="p-sector" type="text" name="sector" maxlength="190"
                           placeholder="AgriTech, Santé, Éducation…" value="<?= $value('sector') ?>">
                </div>
                <div>
                    <label class="label" for="p-stage">Stade *</label>
                    <select class="input" id="p-stage" name="stage" required>
                        <?php foreach (IncubatedProject::STAGE_LABELS as $key => $label): ?>
                            <option value="<?= e($key) ?>"
                                <?= ($project['stage'] ?? 'idee') === $key ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="label" for="p-country">Pays</label>
                    <div class="input-icon-wrap">
                        <?= icon('map-pin') ?>
                        <input class="input" id="p-country" type="text" name="country" maxlength="120"
                               placeholder="Togo" value="<?= $value('country') ?>">
                    </div>
                </div>
                <div>
                    <label class="label" for="p-year">Année</label>
                    <input class="input" id="p-year" type="number" name="year" min="1990" max="2100"
                           placeholder="2026" value="<?= $value('year') ?>">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="p-website">Site web</label>
                    <input class="input" id="p-website" type="url" name="website" maxlength="255"
                           placeholder="https://" value="<?= $value('website') ?>">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="p-socials">Réseaux sociaux <span class="hint">(un lien par ligne)</span></label>
                    <textarea class="input min-h-[90px]" id="p-socials" name="socials"><?= $value('socials') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$isEdit): ?>
        <div class="card">
            <h2 class="card-title">
                <span class="card-title-icon"><?= icon('photo', 'h-5 w-5') ?></span>
                Affiches du projet <span class="hint">(optionnel)</span>
            </h2>
            <div>
                <label class="label" for="p-images">Images JPEG, PNG ou WebP <span class="hint">(sélection multiple)</span></label>
                <input class="input" id="p-images" type="file" name="images[]" multiple
                       accept="image/jpeg,image/png,image/webp">
                <p class="hint mt-1.5">
                    La première image devient l'affiche principale (modifiable ensuite).
                    Elle apparaît sur le site public et dans les e-mails d'annonce.
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
                <?= (int) ($project['is_published'] ?? 0) ? 'checked' : '' ?>>
            <span class="text-sm font-medium text-slate-700">
                Projet publié (visible sur le site)
            </span>
        </label>
    </div>

    <div class="flex flex-wrap gap-3">
        <button class="btn-primary" type="submit">
            <?= icon('check', 'h-5 w-5') ?> <?= $isEdit ? 'Enregistrer' : 'Créer le projet' ?>
        </button>
        <a class="btn-ghost" href="/admin/projets">Annuler</a>
    </div>
</form>

<?php if ($isEdit): ?>
    <div class="mt-8 max-w-4xl">
        <div class="card">
            <h2 class="card-title">
                <span class="card-title-icon"><?= icon('photo', 'h-5 w-5') ?></span>
                Affiches du projet
            </h2>
            <p class="mb-4 text-sm text-slate-500">
                L'affiche <strong>principale</strong> apparaît sur les cartes du site public et en tête des
                e-mails d'annonce ; les autres forment la galerie de la page de détails.
            </p>

            <?php if ($images): ?>
                <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    <?php foreach ($images as $image): ?>
                        <div class="overflow-hidden rounded-xl ring-1 ring-inset <?= (int) $image['is_main'] ? 'ring-2 ring-brand-orange' : 'ring-slate-200' ?>">
                            <div class="relative aspect-[4/3] bg-slate-100">
                                <img src="/uploads/projets/<?= e(rawurlencode($image['path'])) ?>" alt="Affiche du projet"
                                     class="absolute inset-0 h-full w-full object-cover">
                                <?php if ((int) $image['is_main']): ?>
                                    <span class="absolute left-2 top-2 inline-flex items-center gap-1 rounded-full bg-brand-orange px-2 py-0.5 text-[11px] font-bold text-white shadow">
                                        <?= icon('star', 'h-3 w-3') ?> Principale
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center justify-between gap-2 bg-white px-3 py-2">
                                <form method="post"
                                      action="/admin/projets/<?= (int) $project['id'] ?>/images/<?= (int) $image['id'] ?>/main">
                                    <?= csrf_field() ?>
                                    <label class="flex cursor-pointer items-center gap-1.5 text-xs font-medium text-slate-600">
                                        <input type="radio" class="radio" name="main_image" data-autosubmit
                                            <?= (int) $image['is_main'] ? 'checked disabled' : '' ?>>
                                        Principale
                                    </label>
                                </form>
                                <form method="post"
                                      action="/admin/projets/<?= (int) $project['id'] ?>/images/<?= (int) $image['id'] ?>/delete"
                                      data-loading-submit data-loading-label="Suppression…"
                                      data-confirm="Supprimer définitivement cette affiche ?">
                                    <?= csrf_field() ?>
                                    <button class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-red-500 transition hover:bg-red-50"
                                            type="submit" title="Supprimer cette affiche">
                                        <?= icon('trash', 'h-3.5 w-3.5') ?> Supprimer
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="mb-6 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-400 ring-1 ring-inset ring-slate-200">
                    Aucune affiche pour le moment : le projet s'affiche sans image sur le site public.
                </p>
            <?php endif; ?>

            <form method="post" action="/admin/projets/<?= (int) $project['id'] ?>/images"
                  enctype="multipart/form-data" class="flex flex-wrap items-end gap-3"
                  data-loading-submit data-loading-label="Envoi…">
                <?= csrf_field() ?>
                <div class="min-w-64 flex-1">
                    <label class="label" for="p-images">Ajouter des affiches <span class="hint">(JPEG, PNG ou WebP — sélection multiple)</span></label>
                    <input class="input" id="p-images" type="file" name="images[]" multiple required
                           accept="image/jpeg,image/png,image/webp">
                </div>
                <button class="btn-secondary" type="submit">
                    <?= icon('plus', 'h-4 w-4') ?> Ajouter
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($isEdit && (int) $project['is_published'] && empty($project['archived_at'])): ?>
    <div class="mt-8 max-w-4xl">
        <div class="card">
            <h2 class="card-title">
                <span class="card-title-icon"><?= icon('megaphone', 'h-5 w-5') ?></span>
                Annoncer le projet par e-mail
            </h2>
            <p class="mb-4 text-sm text-slate-500">
                Envoie l'annonce de ce projet à toute la communauté PROCOPE
                (<?= $recipients ?> destinataire<?= $recipients > 1 ? 's' : '' ?> avec e-mail).
                L'envoi manuel ignore les interrupteurs d'automatisation.
            </p>
            <form method="post" action="/admin/projets/<?= (int) $project['id'] ?>/announce"
                  data-loading-submit data-loading-label="Envoi…"
                  data-confirm="Envoyer l'annonce de ce projet à <?= $recipients ?> destinataire<?= $recipients > 1 ? 's' : '' ?> ?">
                <?= csrf_field() ?>
                <button class="btn-secondary" type="submit" <?= $recipients === 0 ? 'disabled' : '' ?>>
                    <?= icon('megaphone', 'h-4 w-4') ?> Annoncer par e-mail
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($isEdit): ?>
    <div class="mt-8 max-w-4xl">
        <div class="card border border-red-100 !ring-red-100">
            <h2 class="card-title !text-red-600">
                <span class="card-title-icon !bg-red-50 !text-red-500"><?= icon('exclamation-triangle', 'h-5 w-5') ?></span>
                Zone dangereuse
            </h2>
            <p class="mb-4 text-sm text-slate-500">
                La suppression est définitive et retire les affiches. Les dépôts liés deviennent spontanés.
            </p>
            <form method="post" action="/admin/projets/<?= (int) $project['id'] ?>/delete"
                  data-loading-submit data-loading-label="Suppression…"
                  data-confirm="Supprimer définitivement ce projet et ses affiches ?">
                <?= csrf_field() ?>
                <button class="btn-danger" type="submit">
                    <?= icon('trash', 'h-4 w-4') ?> Supprimer le projet
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>
