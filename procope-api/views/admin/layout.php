<?php
/** Layout admin. Variables : $title, $content (+ user courant). */
$currentUser = \App\Services\Auth::user();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isActive = static fn (string $prefix): bool =>
    $prefix === '/admin' ? $path === '/admin' : str_starts_with($path, $prefix);
$navClass = static fn (string $prefix): string =>
    'nav-link' . ($isActive($prefix) ? ' nav-link-active' : '');
$flashData = flash();

$userName = $currentUser['name'] ?? '';
$initials = strtoupper(mb_substr($userName, 0, 1));
if (preg_match('/^(\S)\S*\s+(\S)/u', $userName, $m)) {
    $initials = strtoupper($m[1] . $m[2]);
}
$roleKey = $currentUser['role'] ?? '';
$roleBadge = match ($roleKey) {
    'super_admin' => 'bg-brand-orange/15 text-brand-orange ring-brand-orange/30',
    'admin'       => 'bg-brand-blue/15 text-sky-300 ring-brand-blue/30',
    default       => 'bg-white/10 text-slate-300 ring-white/20',
};
$newMessagesCount = \App\Models\ContactMessage::countNew();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'Admin') ?> — PROCOPE Admin</title>
    <link rel="icon" type="image/png" href="/assets/logo.png">
    <link rel="stylesheet" href="/assets/admin.css">
    <script src="/assets/admin.js" defer></script>
</head>
<body class="min-h-screen">

<!-- Overlay mobile -->
<div id="sidebar-overlay" class="fixed inset-0 z-30 hidden bg-brand-navy/50 backdrop-blur-sm lg:hidden"></div>

<!-- Sidebar -->
<aside id="sidebar"
       class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col bg-gradient-to-b from-brand-navy to-[#041d36] transition-transform duration-300 ease-in-out lg:translate-x-0">
    <div class="flex items-center gap-3 px-6 pb-6 pt-7">
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white p-1 shadow-md">
            <img src="/assets/logo.png" alt="Logo PROCOPE" class="h-full w-full object-contain">
        </div>
        <div class="leading-tight">
            <div class="text-lg font-bold tracking-wide text-white">PROCOPE</div>
            <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-brand-orange">Back-office</div>
        </div>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3">
        <a href="/admin" class="<?= $navClass('/admin') ?>">
            <?= icon('home', 'h-5 w-5 shrink-0') ?> Tableau de bord
        </a>
        <a href="/admin/formations" class="<?= $navClass('/admin/formations') ?>">
            <?= icon('academic-cap', 'h-5 w-5 shrink-0') ?> Formations
        </a>
        <a href="/admin/archives" class="<?= $navClass('/admin/archives') ?>">
            <?= icon('archive-box', 'h-5 w-5 shrink-0') ?> Archives
        </a>
        <a href="/admin/inscriptions" class="<?= $navClass('/admin/inscriptions') ?>">
            <?= icon('clipboard-list', 'h-5 w-5 shrink-0') ?> Inscriptions
        </a>
        <a href="/admin/messages" class="<?= $navClass('/admin/messages') ?>">
            <?= icon('envelope', 'h-5 w-5 shrink-0') ?> Messages
            <?php if ($newMessagesCount > 0): ?>
                <span class="ml-auto inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-brand-orange px-1.5 text-[11px] font-bold text-white">
                    <?= $newMessagesCount > 99 ? '99+' : (int) $newMessagesCount ?>
                </span>
            <?php endif; ?>
        </a>
        <?php if (\App\Services\Auth::isAtLeast('admin')): ?>
            <a href="/admin/emplois" class="<?= $navClass('/admin/emplois') ?>">
                <?= icon('briefcase', 'h-5 w-5 shrink-0') ?> Offres d'emploi
            </a>
            <a href="/admin/automations" class="<?= $navClass('/admin/automations') ?>">
                <?= icon('bolt', 'h-5 w-5 shrink-0') ?> Automatisations
            </a>
        <?php endif; ?>
        <?php if (\App\Services\Auth::isAtLeast('super_admin')): ?>
            <a href="/admin/users" class="<?= $navClass('/admin/users') ?>">
                <?= icon('users', 'h-5 w-5 shrink-0') ?> Utilisateurs
            </a>
            <a href="/admin/surveillance" class="<?= $navClass('/admin/surveillance') ?>">
                <?= icon('shield-check', 'h-5 w-5 shrink-0') ?> Surveillance
            </a>
        <?php endif; ?>
        <?php if (\App\Services\Auth::isAtLeast('admin')): ?>
            <a href="/admin/settings" class="<?= $navClass('/admin/settings') ?>">
                <?= icon('cog', 'h-5 w-5 shrink-0') ?> Réglages
            </a>
        <?php endif; ?>
    </nav>

    <div class="border-t border-white/10 p-4">
        <div class="flex items-center gap-3 rounded-xl bg-white/5 p-3">
            <span class="avatar bg-gradient-to-br from-brand-orange to-amber-600 ring-2 ring-white/20">
                <?= e($initials) ?>
            </span>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-semibold text-white"><?= e($userName) ?></div>
                <span class="badge mt-0.5 <?= $roleBadge ?>">
                    <?= e(\App\Models\User::ROLE_LABELS[$roleKey] ?? '') ?>
                </span>
            </div>
        </div>
        <form method="post" action="/admin/logout" class="mt-3">
            <?= csrf_field() ?>
            <button type="submit"
                    class="flex w-full items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-300 ring-1 ring-inset ring-white/15 transition hover:bg-white/10 hover:text-white">
                <?= icon('logout', 'h-5 w-5') ?> Se déconnecter
            </button>
        </form>
    </div>
</aside>

<!-- Contenu : colonne pleine hauteur, zone centrale scrollable, footer fixe en bas -->
<div class="flex h-screen flex-col lg:pl-72">
    <!-- Topbar mobile -->
    <header class="z-20 flex shrink-0 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 py-3 backdrop-blur lg:hidden">
        <button id="sidebar-toggle" type="button" aria-label="Ouvrir le menu"
                class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-brand-navy ring-1 ring-inset ring-slate-200 transition hover:bg-slate-100">
            <?= icon('menu', 'h-6 w-6') ?>
        </button>
        <img src="/assets/logo.png" alt="" class="h-8 w-8 object-contain">
        <span class="text-base font-bold text-brand-navy">PROCOPE <span class="text-brand-orange">Admin</span></span>
    </header>

    <main class="flex-1 overflow-y-auto px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
        <?php if ($flashData): ?>
            <div id="flash"
                 class="fixed right-4 top-4 z-50 flex w-[calc(100%-2rem)] max-w-sm items-start gap-3 rounded-xl px-4 py-3.5 shadow-lg ring-1 ring-inset transition-all duration-500
                        <?= ($flashData['type'] ?? '') === 'success'
                            ? 'bg-emerald-50 text-emerald-800 ring-emerald-200'
                            : 'bg-red-50 text-red-800 ring-red-200' ?>">
                <?= ($flashData['type'] ?? '') === 'success'
                    ? icon('check-circle', 'h-6 w-6 shrink-0 text-emerald-500')
                    : icon('x-circle', 'h-6 w-6 shrink-0 text-red-500') ?>
                <p class="flex-1 pt-0.5 text-sm font-medium"><?= e($flashData['message']) ?></p>
                <button type="button" data-dismiss="flash" aria-label="Fermer"
                        class="shrink-0 rounded-lg p-1 opacity-60 transition hover:opacity-100">
                    <?= icon('x-mark', 'h-4 w-4') ?>
                </button>
            </div>
        <?php endif; ?>

        <?= $content ?>
    </main>

    <footer class="flex shrink-0 flex-col items-center justify-between gap-1.5 border-t border-slate-200 bg-white px-4 py-2.5 text-xs text-slate-400 sm:flex-row sm:px-6 lg:px-10">
        <p>&copy; <?= date('Y') ?> PROCOPE Afrique — Tous droits réservés.</p>
        <p class="flex items-center gap-3">
            <span class="rounded-full bg-slate-200/70 px-2.5 py-0.5 font-semibold text-slate-500">Admin v1.0</span>
            <a href="https://procopeafrique.vercel.app" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1 font-medium transition hover:text-brand-blue">
                <?= icon('paper-airplane', 'h-3.5 w-3.5') ?> Voir le site public
            </a>
        </p>
    </footer>
</div>

</body>
</html>
