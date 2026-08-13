<?php
/** Variables : $formation (null si création), $slots, $pastParticipants (page edit) */
$isEdit = $formation !== null;
$pastParticipants = (int) ($pastParticipants ?? 0);
$isArchived = $isEdit && !empty($formation['archived_at']);
$value = static fn (string $key, ?string $default = '') => e($formation[$key] ?? $default);
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
            <?= $isEdit ? 'Modifier la formation' : 'Nouvelle formation' ?>
        </h1>
        <?php if ($isEdit): ?>
            <p class="mt-1 text-sm text-slate-500"><?= e($formation['titre']) ?></p>
        <?php endif; ?>
    </div>
    <a class="btn-ghost" href="/admin/formations">
        <?= icon('arrow-left', 'h-4 w-4') ?> Retour
    </a>
</div>

<form method="post" action="<?= $isEdit ? '/admin/formations/' . (int) $formation['id'] : '/admin/formations' ?>"
      class="max-w-4xl space-y-6" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('document-text', 'h-5 w-5') ?></span>
            Informations affichées à côté du formulaire
        </h2>
        <div class="space-y-5">
            <div>
                <label class="label" for="f-titre">Titre *</label>
                <input class="input" id="f-titre" type="text" name="titre" required maxlength="200"
                       value="<?= $value('titre') ?>">
            </div>
            <div>
                <label class="label" for="f-intro">
                    Introduction <span class="hint">(texte au-dessus du formulaire public)</span>
                </label>
                <textarea class="input min-h-[96px]" id="f-intro" name="intro"><?= $value('intro') ?></textarea>
            </div>
            <div>
                <label class="label" for="f-programme">Programme <span class="hint">(un point par ligne)</span></label>
                <textarea class="input min-h-[96px]" id="f-programme" name="programme"><?= $value('programme') ?></textarea>
            </div>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                <div>
                    <label class="label" for="f-lieu">Lieu</label>
                    <div class="input-icon-wrap">
                        <?= icon('map-pin') ?>
                        <input class="input" id="f-lieu" type="text" name="lieu" maxlength="255"
                               value="<?= $value('lieu') ?>">
                    </div>
                </div>
                <div>
                    <label class="label" for="f-prix">Prix (frais de participation)</label>
                    <div class="input-icon-wrap">
                        <?= icon('banknotes') ?>
                        <input class="input" id="f-prix" type="number" name="prix" min="0" step="1"
                               value="<?= e((string) (int) ($formation['prix'] ?? 25000)) ?>">
                    </div>
                </div>
                <div>
                    <label class="label" for="f-devise">Devise</label>
                    <input class="input" id="f-devise" type="text" name="devise" maxlength="10"
                           value="<?= $value('devise', 'XOF') ?>">
                </div>
            </div>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="f-places">Places max <span class="hint">(vide = illimité)</span></label>
                    <input class="input" id="f-places" type="number" name="places_max" min="1"
                           value="<?= e($formation['places_max'] ?? '') ?>">
                </div>
                <div>
                    <label class="label" for="f-contact">Téléphone de contact</label>
                    <div class="input-icon-wrap">
                        <?= icon('phone') ?>
                        <input class="input" id="f-contact" type="text" name="contact_phone" maxlength="30"
                               value="<?= $value('contact_phone', '+228 96 45 76 95') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('sparkles', 'h-5 w-5') ?></span>
            Affiche de la formation
        </h2>
        <div class="space-y-4">
            <?php if ($isEdit && !empty($formation['affiche_path'])): ?>
                <div class="flex items-start gap-4">
                    <img src="/uploads/affiches/<?= e($formation['affiche_path']) ?>"
                         alt="Affiche actuelle de la formation"
                         class="h-40 w-auto rounded-xl object-cover ring-1 ring-slate-200 shadow-soft">
                    <p class="text-sm text-slate-500">
                        Affiche actuelle. Choisissez un nouveau fichier ci-dessous pour la remplacer
                        (l'ancienne sera supprimée).
                    </p>
                </div>
            <?php else: ?>
                <p class="text-sm text-slate-500">
                    Aucune affiche pour le moment. Elle sera visible publiquement sur la page d'inscription
                    et l'espace candidature.
                </p>
            <?php endif; ?>
            <div>
                <label class="label" for="f-affiche">
                    <?= $isEdit && !empty($formation['affiche_path']) ? 'Remplacer l\'affiche' : 'Ajouter une affiche' ?>
                    <span class="hint">(JPEG, PNG ou WebP — 5 Mo max)</span>
                </label>
                <input class="input" id="f-affiche" type="file" name="affiche"
                       accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
            </div>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('calendar', 'h-5 w-5') ?></span>
            Créneaux (dates et heures)
        </h2>
        <div id="slots" class="space-y-3">
            <?php $slotList = $slots ?: [['label' => '', 'starts_at' => '', 'ends_at' => '']]; ?>
            <?php foreach ($slotList as $slot): ?>
                <div class="slot-row">
                    <div>
                        <label class="label">Libellé</label>
                        <input class="input" type="text" name="slot_label[]" maxlength="150"
                               placeholder="Samedi 30 janvier 2027" value="<?= e($slot['label']) ?>">
                    </div>
                    <div>
                        <label class="label">Début</label>
                        <input class="input" type="datetime-local" name="slot_start[]"
                               value="<?= $toLocal($slot['starts_at']) ?>">
                    </div>
                    <div>
                        <label class="label">Fin</label>
                        <input class="input" type="datetime-local" name="slot_end[]"
                               value="<?= $toLocal($slot['ends_at']) ?>">
                    </div>
                    <button type="button" class="btn-icon-danger remove-slot mb-1" title="Retirer ce créneau">
                        <?= icon('trash', 'h-4 w-4 pointer-events-none') ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn-ghost btn-sm mt-4" id="add-slot">
            <?= icon('plus', 'h-4 w-4') ?> Ajouter un créneau
        </button>
    </div>

    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('lock-open', 'h-5 w-5') ?></span>
            Ouverture des inscriptions
        </h2>
        <div class="space-y-5">
            <label class="flex cursor-pointer items-center gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-100/70">
                <input type="checkbox" class="checkbox" name="inscriptions_ouvertes" value="1"
                    <?= (int) ($formation['inscriptions_ouvertes'] ?? 0) ? 'checked' : '' ?>>
                <span class="text-sm font-medium text-slate-700">
                    Formulaire d'inscription actif (visible sur le site)
                </span>
            </label>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="f-du">Ouvre automatiquement le <span class="hint">(optionnel)</span></label>
                    <input class="input" id="f-du" type="datetime-local" name="ouverte_du"
                           value="<?= $toLocal($formation['ouverte_du'] ?? null) ?>">
                </div>
                <div>
                    <label class="label" for="f-au">Ferme automatiquement le <span class="hint">(optionnel)</span></label>
                    <input class="input" id="f-au" type="datetime-local" name="ouverte_au"
                           value="<?= $toLocal($formation['ouverte_au'] ?? null) ?>">
                </div>
            </div>
            <div>
                <label class="label" for="f-fermeture">Message affiché quand les inscriptions sont fermées</label>
                <textarea class="input min-h-[72px]" id="f-fermeture"
                          name="message_fermeture"><?= $value('message_fermeture') ?></textarea>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button class="btn-primary" type="submit">
            <?= icon('check', 'h-5 w-5') ?> <?= $isEdit ? 'Enregistrer' : 'Créer la formation' ?>
        </button>
        <a class="btn-ghost" href="/admin/formations">Annuler</a>
    </div>
</form>

<?php if ($isEdit && !$isArchived): ?>
    <div class="mt-8 max-w-4xl">
        <div class="card">
            <h2 class="card-title">
                <span class="card-title-icon"><?= icon('megaphone', 'h-5 w-5') ?></span>
                Annoncer la formation
            </h2>
            <p class="mb-4 text-sm text-slate-500">
                Envoie un e-mail d'annonce de cette formation (affiche, dates, prix et bouton d'inscription)
                aux anciens participants des autres formations et aux candidats aux offres d'emploi
                (<?= $pastParticipants ?> destinataire<?= $pastParticipants > 1 ? 's' : '' ?> avec e-mail, dédupliqués).
                L'envoi manuel ignore les interrupteurs d'automatisation.
            </p>
            <form method="post" action="/admin/formations/<?= (int) $formation['id'] ?>/announce"
                  data-confirm="Envoyer l'annonce de cette formation à <?= $pastParticipants ?> destinataire<?= $pastParticipants > 1 ? 's' : '' ?> (anciens participants + candidats aux offres) ?">
                <?= csrf_field() ?>
                <button class="btn-secondary" type="submit" <?= $pastParticipants === 0 ? 'disabled' : '' ?>>
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
                La suppression est définitive et retire aussi les créneaux associés.
            </p>
            <form method="post" action="/admin/formations/<?= (int) $formation['id'] ?>/delete"
                  data-confirm="Supprimer définitivement cette formation ?">
                <?= csrf_field() ?>
                <button class="btn-danger" type="submit">
                    <?= icon('trash', 'h-4 w-4') ?> Supprimer la formation
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>
