<?php
/** Variables : $message, $replies */
use App\Models\ContactMessage;

$replies = $replies ?? [];
$hasEmail = (bool) filter_var((string) ($message['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$replySubject = trim((string) ($message['subject'] ?? ''));
$replySubject = $replySubject !== '' ? 'Re: ' . $replySubject : 'Re: votre message à PROCOPE Afrique';

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
    <div class="flex flex-wrap items-center gap-2">
        <?php if ($hasEmail): ?>
            <a class="btn-primary" href="#reply">
                <?= icon('paper-airplane', 'h-4 w-4') ?> Répondre
            </a>
        <?php endif; ?>
        <a class="btn-ghost" href="/admin/messages">
            <?= icon('arrow-left', 'h-4 w-4') ?> Retour à la liste
        </a>
    </div>
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

        <?php if ($replies): ?>
            <h3 class="mt-6 text-xs font-semibold uppercase tracking-wide text-slate-400">
                Réponses envoyées (<?= count($replies) ?>)
            </h3>
            <ol class="mt-3 space-y-4">
                <?php foreach ($replies as $reply): ?>
                    <li class="rounded-xl bg-white p-4 ring-1 ring-inset ring-slate-200">
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-400">
                            <span>
                                <?= e($reply['user_name'] ?: 'Admin') ?>
                                — <?= format_datetime($reply['sent_at']) ?>
                            </span>
                            <span class="font-medium text-slate-500"><?= e($reply['subject']) ?></span>
                        </div>
                        <div class="prose prose-sm max-w-none text-slate-700">
                            <?= $reply['body'] ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

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
                « Traité » est aussi posé automatiquement après l'envoi d'une
                réponse par e-mail depuis cette fiche.
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

<div id="reply" class="mt-6 scroll-mt-8">
    <div class="card max-w-3xl">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('paper-airplane', 'h-5 w-5') ?></span>
            Répondre par e-mail
        </h2>
        <?php if (!$hasEmail): ?>
            <p class="text-sm text-slate-500">
                Impossible de répondre : ce message n'a pas d'adresse e-mail valide.
            </p>
        <?php else: ?>
            <form method="post" action="/admin/messages/<?= (int) $message['id'] ?>/reply"
                  id="reply-form" class="space-y-4"
                  data-loading-submit data-loading-label="Envoi…">
                <?= csrf_field() ?>
                <div>
                    <label class="label" for="reply-to">Destinataire</label>
                    <input class="input bg-slate-50" id="reply-to" type="email"
                           value="<?= e($message['email']) ?>" readonly>
                </div>
                <div>
                    <label class="label" for="reply-subject">Sujet</label>
                    <input class="input" id="reply-subject" type="text" name="subject"
                           maxlength="255" value="<?= e($replySubject) ?>">
                </div>
                <div>
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <span class="label mb-0">Message</span>
                        <span class="text-xs text-slate-400">Mise en forme simple : gras, italique, lien.</span>
                    </div>
                    <div id="reply-toolbar"
                         class="mb-3 flex flex-wrap items-center gap-1 rounded-xl bg-slate-50 p-1.5 ring-1 ring-inset ring-slate-200"
                         role="toolbar" aria-label="Mise en forme du texte">
                        <button type="button" class="tpl-tool font-bold" data-editor-cmd="bold" title="Gras">G</button>
                        <button type="button" class="tpl-tool italic" data-editor-cmd="italic" title="Italique">I</button>
                        <button type="button" class="tpl-tool" data-editor-cmd="createLink" title="Insérer un lien">Lien</button>
                    </div>
                    <div id="reply-editor" class="min-h-[10rem] rounded-xl bg-white px-4 py-3 text-sm leading-relaxed text-slate-700 ring-1 ring-inset ring-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-blue"
                         contenteditable="true" role="textbox" aria-label="Contenu de la réponse"></div>
                    <textarea id="reply-body" name="body" class="hidden" hidden></textarea>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button class="btn-primary" type="submit">
                        <?= icon('paper-airplane', 'h-4 w-4') ?> Envoyer la réponse
                    </button>
                    <button class="btn-secondary" type="button" id="reply-preview-btn">
                        <?= icon('eye', 'h-4 w-4') ?> Aperçu
                    </button>
                </div>
            </form>
            <form id="reply-preview-form" method="post"
                  action="/admin/messages/<?= (int) $message['id'] ?>/preview-reply"
                  target="reply-preview-frame" data-no-loading class="hidden" hidden>
                <?= csrf_field() ?>
                <textarea id="reply-preview-body" name="body"></textarea>
            </form>
            <iframe name="reply-preview-frame" id="reply-preview-frame" title="Aperçu de la réponse"
                    class="mt-4 hidden min-h-[28rem] w-full rounded-xl bg-slate-100 ring-1 ring-inset ring-slate-200"></iframe>
        <?php endif; ?>
    </div>
</div>
