<?php /** Variables : $error */ ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Connexion — PROCOPE Admin</title>
    <link rel="icon" type="image/png" href="/assets/logo.png">
    <link rel="stylesheet" href="/assets/admin.css">
    <script src="/assets/admin.js" defer></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-brand-navy via-brand-navy2 to-[#04203a]">
<div class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-10">
    <!-- Halos décoratifs -->
    <div class="pointer-events-none absolute -left-32 -top-32 h-96 w-96 rounded-full bg-brand-blue/20 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-40 -right-32 h-[28rem] w-[28rem] rounded-full bg-brand-orange/15 blur-3xl"></div>

    <div class="relative w-full max-w-md">
        <div class="rounded-3xl bg-white p-8 shadow-2xl ring-1 ring-white/20 sm:p-10">
            <div class="mb-8 flex flex-col items-center text-center">
                <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-2xl bg-slate-50 p-2.5 shadow-md ring-1 ring-slate-200">
                    <img src="/assets/logo.png" alt="Logo PROCOPE" class="h-full w-full object-contain">
                </div>
                <h1 class="text-2xl font-bold text-brand-navy">
                    PROCOPE <span class="text-brand-orange">Admin</span>
                </h1>
                <p class="mt-1.5 text-sm text-slate-500">Espace réservé à l'équipe PROCOPE Afrique</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="mb-5 flex items-start gap-3 rounded-xl bg-red-50 px-4 py-3 ring-1 ring-inset ring-red-200">
                    <?= icon('x-circle', 'h-5 w-5 shrink-0 text-red-500') ?>
                    <p class="text-sm font-medium text-red-700"><?= e($error) ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="/admin/login" autocomplete="off" class="space-y-5">
                <?= csrf_field() ?>
                <div>
                    <label class="label" for="login-email">Adresse e-mail ou identifiant</label>
                    <div class="input-icon-wrap">
                        <?= icon('envelope') ?>
                        <input class="input" id="login-email" type="text" name="email"
                               placeholder="vous@procope.org" required autofocus autocomplete="username">
                    </div>
                </div>
                <div>
                    <label class="label" for="login-password">Mot de passe</label>
                    <div class="input-icon-wrap">
                        <?= icon('lock-closed') ?>
                        <input class="input pr-11" id="login-password" type="password" name="password"
                               placeholder="Votre mot de passe" required autocomplete="current-password">
                        <button type="button" data-toggle-password="login-password"
                                aria-label="Afficher le mot de passe"
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-slate-400 transition hover:text-brand-navy focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-blue">
                            <span data-icon-show><?= icon('eye', 'h-5 w-5') ?></span>
                            <span data-icon-hide class="hidden"><?= icon('eye-off', 'h-5 w-5') ?></span>
                        </button>
                    </div>
                </div>
                <button class="btn-primary w-full py-3 text-base" type="submit">
                    <?= icon('logout', 'h-5 w-5 -scale-x-100') ?> Se connecter
                </button>
            </form>
        </div>
        <p class="mt-6 text-center text-xs text-slate-400">
            &copy; <?= date('Y') ?> PROCOPE Afrique — Incubateur d'entreprises, Lomé (Togo)
        </p>
    </div>
</div>
</body>
</html>
