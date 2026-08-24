<?php /** Variables : $values */ ?>
<div class="mb-8">
    <h1 class="text-2xl font-bold text-brand-navy">Réglages</h1>
    <p class="mt-1 text-sm text-slate-500">Informations publiques du site et destinataires des alertes internes.</p>
</div>

<form method="post" action="/admin/settings" class="max-w-3xl space-y-6" data-loading-submit data-loading-label="Enregistrement…">
    <?= csrf_field() ?>

    <!-- Informations du site -->
    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('information-circle', 'h-5 w-5') ?></span>
            Informations du site
        </h2>
        <div class="space-y-5">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="s-site-email">E-mail public</label>
                    <div class="input-icon-wrap">
                        <?= icon('envelope') ?>
                        <input class="input" id="s-site-email" type="email" name="site_email"
                               placeholder="procopeafrique@gmail.com" value="<?= e($values['site_email']) ?>">
                    </div>
                </div>
                <div>
                    <label class="label" for="s-site-phone">Téléphone principal</label>
                    <div class="input-icon-wrap">
                        <?= icon('phone') ?>
                        <input class="input" id="s-site-phone" type="text" name="site_phone"
                               placeholder="+228 96 45 76 95" value="<?= e($values['site_phone']) ?>">
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="s-site-whatsapp">Téléphone WhatsApp</label>
                    <div class="input-icon-wrap">
                        <?= icon('phone') ?>
                        <input class="input" id="s-site-whatsapp" type="text" name="site_whatsapp"
                               placeholder="+228 96 45 76 95" value="<?= e($values['site_whatsapp']) ?>">
                    </div>
                </div>
                <div>
                    <label class="label" for="s-site-address">Adresse</label>
                    <div class="input-icon-wrap">
                        <?= icon('map-pin') ?>
                        <input class="input" id="s-site-address" type="text" name="site_address"
                               placeholder="Lomé, Togo" value="<?= e($values['site_address']) ?>">
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-100 pt-5">
                <h3 class="mb-4 flex items-center gap-2 text-sm font-semibold text-slate-600">
                    <?= icon('paper-airplane', 'h-4 w-4 text-slate-400') ?> Réseaux sociaux
                </h3>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label class="label" for="s-facebook">Facebook</label>
                        <input class="input" id="s-facebook" type="url" name="social_facebook"
                               placeholder="https://facebook.com/procopeafrique"
                               value="<?= e($values['social_facebook']) ?>">
                    </div>
                    <div>
                        <label class="label" for="s-instagram">Instagram</label>
                        <input class="input" id="s-instagram" type="url" name="social_instagram"
                               placeholder="https://instagram.com/procopeafrique"
                               value="<?= e($values['social_instagram']) ?>">
                    </div>
                    <div>
                        <label class="label" for="s-tiktok">TikTok</label>
                        <input class="input" id="s-tiktok" type="url" name="social_tiktok"
                               placeholder="https://tiktok.com/@procopeafrique"
                               value="<?= e($values['social_tiktok']) ?>">
                    </div>
                    <div>
                        <label class="label" for="s-linkedin">LinkedIn</label>
                        <input class="input" id="s-linkedin" type="url" name="social_linkedin"
                               placeholder="https://linkedin.com/company/procopeafrique"
                               value="<?= e($values['social_linkedin']) ?>">
                    </div>
                    <div>
                        <label class="label" for="s-youtube">YouTube</label>
                        <input class="input" id="s-youtube" type="url" name="social_youtube"
                               placeholder="https://youtube.com/@procopeafrique"
                               value="<?= e($values['social_youtube']) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- E-mails (destinataires internes uniquement — le SMTP est configuré dans le .env) -->
    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('envelope', 'h-5 w-5') ?></span>
            E-mails
        </h2>
        <div class="space-y-5">
            <div>
                <label class="label" for="s-notify">
                    Destinataires des alertes internes <span class="hint">(virgules ou points-virgules)</span>
                </label>
                <input class="input" id="s-notify" type="text" name="mail_notify"
                       placeholder="procopeafrique@gmail.com, autre@exemple.com"
                       value="<?= e($values['mail_notify']) ?>">
                <p class="mt-1.5 text-xs text-slate-400">
                    Reçoivent les alertes internes (nouvelle inscription, message de contact, candidature emploi).
                    Plusieurs adresses : séparées par des virgules ou des points-virgules.
                    Indépendant du compte SMTP d'authentification et du From visible
                    (<span class="font-mono">MAIL_FROM</span> / <span class="font-mono">MAIL_REPLY_TO</span> dans le <span class="font-mono">.env</span>).
                </p>
            </div>
            <div class="flex items-start gap-2.5 rounded-xl bg-slate-50 p-4 text-xs text-slate-500 ring-1 ring-inset ring-slate-200">
                <?= icon('information-circle', 'h-4 w-4 shrink-0 text-slate-400') ?>
                <span>
                    La configuration du serveur SMTP (hôte, identifiants, expéditeur) se fait dans le fichier
                    <span class="font-mono font-semibold">.env</span> du serveur.
                    L'activation des e-mails automatiques se gère sur la page
                    <a class="font-semibold text-brand-blue hover:underline" href="/admin/automations">Automatisations</a>.
                </span>
            </div>
        </div>
    </div>

    <button class="btn-primary" type="submit">
        <?= icon('check', 'h-5 w-5') ?> Enregistrer les réglages
    </button>
</form>
