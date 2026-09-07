<?php /** Variables : $user (null si création) */
use App\Models\User;

$isEdit = $user !== null;
?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-brand-navy">
            <?= $isEdit ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur' ?>
        </h1>
        <?php if ($isEdit): ?>
            <p class="mt-1 text-sm text-slate-500"><?= e($user['name']) ?></p>
        <?php endif; ?>
    </div>
    <a class="btn-ghost" href="/admin/users">
        <?= icon('arrow-left', 'h-4 w-4') ?> Retour
    </a>
</div>

<form method="post" action="<?= $isEdit ? '/admin/users/' . (int) $user['id'] : '/admin/users' ?>" class="max-w-xl" data-loading-submit data-loading-label="Enregistrement…">
    <?= csrf_field() ?>
    <div class="card space-y-5">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('user', 'h-5 w-5') ?></span>
            Informations du compte
        </h2>
        <div>
            <label class="label" for="u-name">Nom complet *</label>
            <div class="input-icon-wrap">
                <?= icon('user') ?>
                <input class="input" id="u-name" type="text" name="name" required maxlength="150"
                       value="<?= e($user['name'] ?? '') ?>">
            </div>
        </div>
        <div>
            <label class="label" for="u-email">Adresse e-mail *</label>
            <div class="input-icon-wrap">
                <?= icon('envelope') ?>
                <input class="input" id="u-email" type="email" name="email" required maxlength="190"
                       value="<?= e($user['email'] ?? '') ?>">
            </div>
        </div>
        <div>
            <label class="label" for="u-role">Rôle</label>
            <select class="input" id="u-role" name="role">
                <?php foreach (User::ROLE_LABELS as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($user['role'] ?? 'operator') === $key ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="label" for="u-password">
                Mot de passe <?= $isEdit ? '<span class="hint">(laisser vide pour conserver)</span>' : '* <span class="hint">(min 10 caractères)</span>' ?>
            </label>
            <div class="input-icon-wrap">
                <?= icon('lock-closed') ?>
                <input class="input pr-11" id="u-password" type="password" name="password"
                       <?= $isEdit ? '' : 'required' ?> minlength="10" autocomplete="new-password">
                <button type="button" data-toggle-password="u-password"
                        aria-label="Afficher le mot de passe"
                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-slate-400 transition hover:text-brand-navy focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-blue">
                    <span data-icon-show><?= icon('eye', 'h-5 w-5') ?></span>
                    <span data-icon-hide class="hidden"><?= icon('eye-off', 'h-5 w-5') ?></span>
                </button>
            </div>
        </div>
        <?php if ($isEdit): ?>
            <label class="flex cursor-pointer items-center gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-100/70">
                <input type="checkbox" class="checkbox" name="is_active" value="1"
                    <?= (int) $user['is_active'] ? 'checked' : '' ?>>
                <span class="text-sm font-medium text-slate-700">Compte actif</span>
            </label>
        <?php else: ?>
            <input type="hidden" name="is_active" value="1">
        <?php endif; ?>
        <button class="btn-primary w-full" type="submit">
            <?= icon('check', 'h-5 w-5') ?> <?= $isEdit ? 'Enregistrer' : 'Créer l\'utilisateur' ?>
        </button>
    </div>
</form>
