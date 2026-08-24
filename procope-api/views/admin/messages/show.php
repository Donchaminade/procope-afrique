<?php
/** Variables : $message */
use App\Models\ContactMessage;

$badgeClasses = [
    'nouveau' => 'bg-sky-50 text-sky-700 ring-sky-200',
    'lu'      => 'bg-slate-100 text-slate-600 ring-slate-300',
    'traite'  => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
];

$initials = strtoupper(mb_substr($message['name'], 0, 1));
if (preg_match('/^(\S)\S*\s+(\S)/u', $message['name'], $m)) {
    $initials = strtoupper($m[1] . $m[2]);
}
$phoneDigits = preg_replace('/[^\d+]/', '', (string) ($message['phone'] ?? ''));
?>
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-4">
        <span class="avatar h-12 w-12 bg-gradient-to-br from-brand-navy2 to-brand-blue text-base">
            <?= e($initials) ?>
        </span>
        <div>
            <h1 class="flex flex-wrap items-center gap-3 text-2xl font-bold text-brand-navy">
                <?= e($message['name']) ?>
                <span class="badge <?= $badgeClasses[$message['statut']] ?? 'bg-slate-100 text-slate-600 ring-slate-300' ?>">
                    <?= e(ContactMessage::STATUT_LABELS[$message['statut']] ?? $message['statut']) ?>
                </span>
            </h1>
            <p class="mt-0.5 text-sm text-slate-500">
                Reçu le <?= format_datetime($message['created_at']) ?>
                <?= $message['subject'] ? ' — ' . e($message['subject']) : '' ?>
            </p>
        </div>
    </div>
    <a class="btn-ghost" href="/admin/messages">
        <?= icon('arrow-left', 'h-4 w-4') ?> Retour à la liste
    </a>
</div>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
    <!-- Colonne principale : message -->
    <div class="card xl:col-span-2">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('envelope', 'h-5 w-5') ?></span>
            Message
        </h2>
        <?php if ($message['subject']): ?>
            <p class="mb-3 text-sm">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Sujet :</span>
                <span class="ml-1 font-medium text-slate-700"><?= e($message['subject']) ?></span>
            </p>
        <?php endif; ?>
        <div class="whitespace-pre-line rounded-xl bg-slate-50 p-5 text-sm leading-relaxed text-slate-700 ring-1 ring-inset ring-slate-200">
            <?= e($message['message']) ?>
        </div>
        <dl class="mt-5 divide-y divide-slate-100 border-t border-slate-100 pt-2 text-sm">
            <div class="flex flex-col gap-0.5 py-2.5 sm:flex-row sm:items-center sm:gap-4">
                <dt class="w-full shrink-0 text-xs font-semibold uppercase tracking-wide text-slate-400 sm:w-44">Adresse IP</dt>
                <dd class="flex-1 text-slate-700"><?= e($message['ip'] ?? '—') ?></dd>
            </div>
            <div class="flex flex-col gap-0.5 py-2.5 sm:flex-row sm:items-center sm:gap-4">
                <dt class="w-full shrink-0 text-xs font-semibold uppercase tracking-wide text-slate-400 sm:w-44">Navigateur</dt>
                <dd class="flex-1 break-all text-xs text-slate-500"><?= e($message['user_agent'] ?? '—') ?></dd>
            </div>
        </dl>
    </div>

    <!-- Colonne latérale -->
    <div class="space-y-6">
        <div class="card">
            <h2 class="card-title">
                <span class="card-title-icon"><?= icon('identification', 'h-5 w-5') ?></span>
                Coordonnées
            </h2>
            <div class="space-y-4 text-sm">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">E-mail</div>
                    <?php if ($message['email']): ?>
                        <a class="mt-1 inline-flex items-center gap-1.5 font-medium text-brand-blue transition hover:underline"
                           href="mailto:<?= e($message['email']) ?>">
                            <?= icon('envelope', 'h-4 w-4') ?> <?= e($message['email']) ?>
                        </a>
                    <?php else: ?>
                        <div class="mt-1 text-slate-400">Non renseigné</div>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Téléphone</div>
                    <?php if ($message['phone']): ?>
                        <a class="mt-1 inline-flex items-center gap-1.5 font-medium text-brand-blue transition hover:underline"
                           href="tel:<?= e($phoneDigits) ?>">
                            <?= icon('phone', 'h-4 w-4') ?> <?= e($message['phone']) ?>
                        </a>
                    <?php else: ?>
                        <div class="mt-1 text-slate-400">Non renseigné</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <h2 class="card-title">
                <span class="card-title-icon"><?= icon('arrow-path', 'h-5 w-5') ?></span>
                Statut du message
            </h2>
            <div class="space-y-3">
                <?php if ($message['statut'] !== 'lu'): ?>
                    <form method="post" action="/admin/messages/<?= (int) $message['id'] ?>/status" data-loading-submit data-loading-label="Enregistrement…">
                        <?= csrf_field() ?>
                        <input type="hidden" name="statut" value="lu">
                        <button class="btn-secondary w-full" type="submit">
                            <?= icon('eye', 'h-4 w-4') ?> Marquer comme lu
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($message['statut'] !== 'traite'): ?>
                    <form method="post" action="/admin/messages/<?= (int) $message['id'] ?>/status" data-loading-submit data-loading-label="Enregistrement…">
                        <?= csrf_field() ?>
                        <input type="hidden" name="statut" value="traite">
                        <button class="btn-primary w-full" type="submit">
                            <?= icon('check-circle', 'h-4 w-4') ?> Marquer comme traité
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($message['statut'] === 'traite'): ?>
                    <form method="post" action="/admin/messages/<?= (int) $message['id'] ?>/status" data-loading-submit data-loading-label="Enregistrement…">
                        <?= csrf_field() ?>
                        <input type="hidden" name="statut" value="nouveau">
                        <button class="btn-secondary w-full" type="submit">
                            <?= icon('arrow-path', 'h-4 w-4') ?> Repasser en « Nouveau »
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <p class="mt-3 flex items-start gap-2 text-xs text-slate-400">
                <?= icon('information-circle', 'h-4 w-4 shrink-0') ?>
                « Traité » signifie que la réponse a été apportée au contact
                (par e-mail, téléphone ou WhatsApp).
            </p>
        </div>

        <div class="card">
            <h2 class="card-title">
                <span class="card-title-icon"><?= icon('trash', 'h-5 w-5') ?></span>
                Supprimer
            </h2>
            <form method="post" action="/admin/messages/<?= (int) $message['id'] ?>/delete"
                  data-loading-submit data-loading-label="Suppression…"
                  data-confirm="Supprimer définitivement ce message de <?= e($message['name']) ?> ? Cette action est irréversible.">
                <?= csrf_field() ?>
                <button class="btn-danger w-full" type="submit">
                    <?= icon('trash', 'h-4 w-4') ?> Supprimer ce message
                </button>
            </form>
        </div>
    </div>
</div>
