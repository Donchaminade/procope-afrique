<?php
/** Variables : $offer (null si création), $images + $recipients (page edit uniquement) */
use App\Models\JobOffer;

$isEdit = $offer !== null;
$images = $images ?? [];
$recipients = (int) ($recipients ?? 0);
$value = static fn (string $key, ?string $default = '') => e($offer[$key] ?? $default);
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
            <?= $isEdit ? "Modifier l'offre" : 'Nouvelle offre' ?>
        </h1>
        <?php if ($isEdit): ?>
            <p class="mt-1 text-sm text-slate-500"><?= e($offer['title']) ?></p>
        <?php endif; ?>
    </div>
    <a class="btn-ghost" href="/admin/emplois">
        <?= icon('arrow-left', 'h-4 w-4') ?> Retour
    </a>
</div>

<form method="post" action="<?= $isEdit ? '/admin/emplois/' . (int) $offer['id'] : '/admin/emplois' ?>"
      enctype="multipart/form-data" class="max-w-4xl space-y-6" data-loading-submit data-loading-label="Enregistrement…">
    <?= csrf_field() ?>

    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('briefcase', 'h-5 w-5') ?></span>
            Informations de l'offre
        </h2>
        <div class="space-y-5">
            <div>
                <label class="label" for="o-title">Titre du poste *</label>
                <input class="input" id="o-title" type="text" name="title" required maxlength="200"
                       placeholder="Ex. : Chargé(e) de communication digitale"
                       value="<?= $value('title') ?>">
            </div>
            <div>
                <label class="label" for="o-description">
                    Description complète <span class="hint">(missions, profil recherché, comment postuler…)</span>
                </label>
                <textarea class="input min-h-[220px]" id="o-description"
                          name="description"><?= $value('description') ?></textarea>
            </div>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                <div>
                    <label class="label" for="o-location">Lieu</label>
                    <div class="input-icon-wrap">
                        <?= icon('map-pin') ?>
                        <input class="input" id="o-location" type="text" name="location" maxlength="255"
                               placeholder="Lomé, Togo" value="<?= $value('location') ?>">
                    </div>
                </div>
                <div>
                    <label class="label" for="o-contract">Type de contrat *</label>
                    <select class="input" id="o-contract" name="contract_type" required>
                        <?php foreach (JobOffer::CONTRACT_TYPES as $type): ?>
                            <option value="<?= e($type) ?>"
                                <?= ($offer['contract_type'] ?? 'CDD') === $type ? 'selected' : '' ?>>
                                <?= e($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="label" for="o-salary">Salaire / indemnité <span class="hint">(optionnel)</span></label>
                    <div class="input-icon-wrap">
                        <?= icon('banknotes') ?>
                        <input class="input" id="o-salary" type="text" name="salary" maxlength="100"
                               placeholder="Ex. : 150 000 F CFA / mois" value="<?= $value('salary') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$isEdit): ?>
        <div class="card">
            <h2 class="card-title">
                <span class="card-title-icon"><?= icon('photo', 'h-5 w-5') ?></span>
                Affiches de l'offre <span class="hint">(optionnel)</span>
            </h2>
            <div>
                <label class="label" for="o-images">Images JPEG, PNG ou WebP <span class="hint">(sélection multiple)</span></label>
                <input class="input" id="o-images" type="file" name="images[]" multiple
                       accept="image/jpeg,image/png,image/webp">
                <p class="hint mt-1.5">
                    La première image devient l'affiche principale (modifiable ensuite depuis cette page).
                    Elle apparaît sur le site public et dans les e-mails d'annonce.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('calendar', 'h-5 w-5') ?></span>
            Publication et clôture
        </h2>
        <div class="space-y-5">
            <div class="max-w-xs">
                <label class="label" for="o-closes">Clôture des candidatures *</label>
                <input class="input" id="o-closes" type="datetime-local" name="closes_at" required
                       value="<?= $toLocal($offer['closes_at'] ?? null) ?>">
                <p class="hint mt-1.5">
                    Passée cette date, l'offre disparaît du site public et les candidatures sont refusées.
                    Repousser la date d'une offre publiée peut déclencher l'e-mail « offre prolongée »
                    (voir Automatisations).
                </p>
            </div>
            <label class="flex cursor-pointer items-center gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-100/70">
                <input type="checkbox" class="checkbox" name="is_published" value="1"
                    <?= (int) ($offer['is_published'] ?? 0) ? 'checked' : '' ?>>
                <span class="text-sm font-medium text-slate-700">
                    Offre publiée (visible sur le site et ouverte aux candidatures)
                </span>
            </label>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button class="btn-primary" type="submit">
            <?= icon('check', 'h-5 w-5') ?> <?= $isEdit ? 'Enregistrer' : "Créer l'offre" ?>
        </button>
        <a class="btn-ghost" href="/admin/emplois">Annuler</a>
    </div>
</form>

<?php if ($isEdit): ?>
    <div class="mt-8 max-w-4xl">
        <div class="card">
            <h2 class="card-title">
                <span class="card-title-icon"><?= icon('photo', 'h-5 w-5') ?></span>
                Affiches de l'offre
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
                                <img src="/uploads/offres/<?= e(rawurlencode($image['path'])) ?>" alt="Affiche de l'offre"
                                     class="absolute inset-0 h-full w-full object-cover">
                                <?php if ((int) $image['is_main']): ?>
                                    <span class="absolute left-2 top-2 inline-flex items-center gap-1 rounded-full bg-brand-orange px-2 py-0.5 text-[11px] font-bold text-white shadow">
                                        <?= icon('star', 'h-3 w-3') ?> Principale
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center justify-between gap-2 bg-white px-3 py-2">
                                <form method="post"
                                      action="/admin/emplois/<?= (int) $offer['id'] ?>/images/<?= (int) $image['id'] ?>/main">
                                    <?= csrf_field() ?>
                                    <label class="flex cursor-pointer items-center gap-1.5 text-xs font-medium text-slate-600">
                                        <input type="radio" class="radio" name="main_image" data-autosubmit
                                            <?= (int) $image['is_main'] ? 'checked disabled' : '' ?>>
                                        Principale
                                    </label>
                                </form>
                                <form method="post"
                                      action="/admin/emplois/<?= (int) $offer['id'] ?>/images/<?= (int) $image['id'] ?>/delete"
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
                    Aucune affiche pour le moment : l'offre s'affiche sans image sur le site public.
                </p>
            <?php endif; ?>

            <form method="post" action="/admin/emplois/<?= (int) $offer['id'] ?>/images"
                  enctype="multipart/form-data" class="flex flex-wrap items-end gap-3"
                  data-loading-submit data-loading-label="Envoi…">
                <?= csrf_field() ?>
                <div class="min-w-64 flex-1">
                    <label class="label" for="o-images">Ajouter des affiches <span class="hint">(JPEG, PNG ou WebP — sélection multiple)</span></label>
                    <input class="input" id="o-images" type="file" name="images[]" multiple required
                           accept="image/jpeg,image/png,image/webp">
                </div>
                <button class="btn-secondary" type="submit">
                    <?= icon('plus', 'h-4 w-4') ?> Ajouter
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($isEdit && (int) $offer['is_published'] && strtotime((string) $offer['closes_at']) > time()): ?>
    <div class="mt-8 max-w-4xl">
        <div class="card">
            <h2 class="card-title">
                <span class="card-title-icon"><?= icon('megaphone', 'h-5 w-5') ?></span>
                Annoncer l'offre par e-mail
            </h2>
            <p class="mb-4 text-sm text-slate-500">
                Envoie l'annonce de cette offre (titre, contrat, lieu, clôture et bouton « Postuler »)
                à toute la communauté PROCOPE : participants aux formations et candidats aux offres
                (<?= $recipients ?> destinataire<?= $recipients > 1 ? 's' : '' ?> avec e-mail).
                L'envoi manuel ignore les interrupteurs d'automatisation.
            </p>
            <form method="post" action="/admin/emplois/<?= (int) $offer['id'] ?>/announce"
                  data-loading-submit data-loading-label="Envoi…"
                  data-confirm="Envoyer l'annonce de cette offre à <?= $recipients ?> destinataire<?= $recipients > 1 ? 's' : '' ?> ?">
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
                La suppression est définitive et retire aussi les candidatures reçues et leurs CV.
            </p>
            <form method="post" action="/admin/emplois/<?= (int) $offer['id'] ?>/delete"
                  data-loading-submit data-loading-label="Suppression…"
                  data-confirm="Supprimer définitivement cette offre, ses candidatures et les CV associés ?">
                <?= csrf_field() ?>
                <button class="btn-danger" type="submit">
                    <?= icon('trash', 'h-4 w-4') ?> Supprimer l'offre
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>
