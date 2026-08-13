<?php
/**
 * Variables : $mailEnabled, $smtpConfigured, $smtpHost, $autoMails, $toggles,
 * $templates, $overrides, $defaults, $formations, $offers, $logs, $logFilters,
 * $logTypes, $logTypeLabels, $logsTotal
 */

/** Libellé lisible d'un type de destinataire. */
$recipientLabel = static fn (string $recipient): string => match ($recipient) {
    'equipe'     => 'Équipe',
    'anciens'    => 'Anciens participants + candidats aux offres',
    'communaute' => 'Toute la communauté',
    'candidat'   => 'Candidat',
    'test'       => 'Adresse choisie',
    default      => 'Participant',
};

/** Badge coloré d'un type de destinataire. */
$recipientBadge = static function (string $recipient) use ($recipientLabel): string {
    $classes = match ($recipient) {
        'equipe'     => 'bg-brand-navy/5 text-brand-navy2 ring-brand-navy/20',
        'anciens'    => 'bg-violet-50 text-violet-700 ring-violet-200',
        'communaute' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'candidat'   => 'bg-teal-50 text-teal-700 ring-teal-200',
        'test'       => 'bg-slate-100 text-slate-600 ring-slate-200',
        default      => 'bg-sky-50 text-sky-700 ring-sky-200',
    };
    return '<span class="badge ' . $classes . '">' . e($recipientLabel($recipient)) . '</span>';
};

/**
 * Interrupteur auto-soumis (mini-formulaire POST, un réglage à la fois).
 * Compatible CSP : input caché + piste peer-checked, soumission via admin.js.
 */
$toggleForm = static function (string $key, bool $checked, string $label): string {
    return '<form method="post" action="/admin/automations" class="shrink-0">'
        . csrf_field()
        . '<input type="hidden" name="key" value="' . e($key) . '">'
        . '<label class="relative inline-flex cursor-pointer items-center">'
        . '<input type="checkbox" name="value" value="1" data-autosubmit class="peer sr-only"'
        . ($checked ? ' checked' : '') . ' aria-label="' . e($label) . '">'
        . '<span class="h-6 w-11 rounded-full bg-slate-300 transition-colors peer-checked:bg-emerald-500 '
        . 'peer-focus-visible:ring-2 peer-focus-visible:ring-brand-blue peer-focus-visible:ring-offset-2"></span>'
        . '<span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></span>'
        . '</label></form>';
};
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-brand-navy">Automatisations</h1>
    <p class="mt-1 text-sm text-slate-500">
        E-mails automatiques, modèles de messages et journal des envois.
    </p>
</div>

<!-- Onglets -->
<div class="mb-6 inline-flex flex-wrap gap-1 rounded-xl bg-slate-200/70 p-1" role="tablist" aria-label="Sections">
    <button type="button" class="tab-btn" data-tab="automations" role="tab">
        <?= icon('bolt', 'h-4 w-4') ?> Automatisations
        <span class="tab-count"><?= count($autoMails) ?></span>
    </button>
    <button type="button" class="tab-btn" data-tab="templates" role="tab">
        <?= icon('document-text', 'h-4 w-4') ?> Modèles
        <span class="tab-count"><?= count($templates) ?></span>
    </button>
    <button type="button" class="tab-btn" data-tab="journal" role="tab">
        <?= icon('clock', 'h-4 w-4') ?> Journal
        <span class="tab-count"><?= (int) $logsTotal ?></span>
    </button>
</div>

<!-- ============================ Onglet 1 : Automatisations ============================ -->
<div data-panel="automations" class="max-w-3xl space-y-4">

    <!-- État général -->
    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('bolt', 'h-5 w-5') ?></span>
            État général
        </h2>
        <div class="space-y-4">
            <div class="flex items-center justify-between gap-4 rounded-xl bg-slate-50 p-4 ring-1 ring-inset ring-slate-200">
                <div>
                    <div class="text-sm font-semibold text-slate-700">Envoi d'e-mails activé</div>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Interrupteur général : désactivé, plus aucun e-mail automatique ne part
                        (les envois manuels et de test restent possibles).
                    </p>
                </div>
                <?= $toggleForm('mail_enabled', $mailEnabled, "Envoi d'e-mails activé") ?>
            </div>

            <?php if ($smtpConfigured): ?>
                <div class="flex items-start gap-2.5 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800 ring-1 ring-inset ring-emerald-200">
                    <?= icon('check-circle', 'h-5 w-5 shrink-0 text-emerald-500') ?>
                    <span>SMTP configuré : <span class="font-semibold"><?= e($smtpHost) ?></span>
                        (défini dans le fichier <span class="font-mono">.env</span> du serveur).</span>
                </div>
            <?php else: ?>
                <div class="flex items-start gap-2.5 rounded-xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-inset ring-amber-200">
                    <?= icon('exclamation-triangle', 'h-5 w-5 shrink-0 text-amber-500') ?>
                    <span><span class="font-semibold">SMTP non configuré</span> — renseignez
                        <span class="font-mono">SMTP_HOST</span>, <span class="font-mono">SMTP_USER</span>…
                        dans le fichier <span class="font-mono">.env</span> du serveur.
                        Les e-mails sont journalisés mais ne partiront pas.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Une carte par automatisation -->
    <?php foreach ($autoMails as $key => $mail): ?>
        <div class="card">
            <div class="flex items-start justify-between gap-4">
                <div class="flex min-w-0 items-start gap-3.5">
                    <span class="card-title-icon mt-0.5"><?= icon($mail['icon'], 'h-5 w-5') ?></span>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-brand-navy"><?= e($mail['label']) ?></div>
                        <p class="mt-1.5 text-xs text-slate-500">
                            Déclencheur : <?= e($mail['trigger']) ?>
                            <span class="mx-1 text-slate-300">·</span>
                            Modèle : <span class="font-mono text-slate-600"><?= e($mail['template']) ?></span>
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Destinataire : <?= e($recipientLabel($mail['recipient'])) ?>
                        </p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2.5">
                    <?= $toggleForm('auto_' . $key, $toggles[$key], $mail['label']) ?>
                    <?php if ($key === 'nouvelle_formation'): ?>
                        <button type="button" class="btn-icon" data-collapse-target="announce-block"
                                aria-expanded="false" aria-controls="announce-block"
                                title="Exécuter : envoyer l'annonce d'une formation">
                            <?= icon('play', 'h-4 w-4') ?>
                        </button>
                    <?php elseif ($key === 'offre_publiee'): ?>
                        <button type="button" class="btn-icon" data-collapse-target="offer-announce-block"
                                aria-expanded="false" aria-controls="offer-announce-block"
                                title="Exécuter : envoyer l'annonce d'une offre publiée">
                            <?= icon('play', 'h-4 w-4') ?>
                        </button>
                    <?php elseif ($key === 'offre_rappel'): ?>
                        <button type="button" class="btn-icon" data-collapse-target="offer-reminder-block"
                                aria-expanded="false" aria-controls="offer-reminder-block"
                                title="Exécuter : traiter les rappels dus maintenant">
                            <?= icon('play', 'h-4 w-4') ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($key === 'nouvelle_formation'): ?>
                <div id="announce-block" class="mt-4 hidden rounded-xl bg-slate-50 ring-1 ring-inset ring-slate-200">
                    <p class="flex items-center gap-2 px-4 pt-3 text-sm font-semibold text-brand-navy">
                        <?= icon('play', 'h-4 w-4 text-brand-orange') ?>
                        Envoyer l'annonce d'une formation
                    </p>
                    <form method="post" action="/admin/formations/0/announce"
                          data-announce-form="/admin/formations/{id}/announce"
                          data-confirm="Envoyer l'annonce de cette formation aux anciens participants et aux candidats aux offres d'emploi (ayant une adresse e-mail) ?"
                          class="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return" value="automations">
                        <select class="input flex-1" name="formation_id" data-announce-select required
                                aria-label="Formation à annoncer">
                            <option value="">— Choisir une formation (non archivée) —</option>
                            <?php foreach ($formations as $f): ?>
                                <option value="<?= (int) $f['id'] ?>"><?= e($f['titre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn-secondary shrink-0" type="submit">
                            <?= icon('paper-airplane', 'h-4 w-4') ?> Envoyer l'annonce
                        </button>
                    </form>
                </div>
            <?php elseif ($key === 'offre_publiee'): ?>
                <div id="offer-announce-block" class="mt-4 hidden rounded-xl bg-slate-50 ring-1 ring-inset ring-slate-200">
                    <p class="flex items-center gap-2 px-4 pt-3 text-sm font-semibold text-brand-navy">
                        <?= icon('play', 'h-4 w-4 text-brand-orange') ?>
                        Envoyer l'annonce d'une offre publiée
                    </p>
                    <?php if (!$offers): ?>
                        <p class="px-4 pb-3.5 pt-2 text-xs text-slate-500">
                            Aucune offre publiée et encore ouverte : publiez d'abord une offre depuis
                            <a class="font-semibold text-brand-blue" href="/admin/emplois">Offres d'emploi</a>.
                        </p>
                    <?php else: ?>
                        <form method="post" action="/admin/emplois/0/announce"
                              data-announce-form="/admin/emplois/{id}/announce"
                              data-confirm="Envoyer l'annonce de cette offre à toute la communauté (participants aux formations + candidats, ayant une adresse e-mail) ?"
                              class="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="return" value="automations">
                            <select class="input flex-1" name="offer_id" data-announce-select required
                                    aria-label="Offre à annoncer">
                                <option value="">— Choisir une offre publiée (non clôturée) —</option>
                                <?php foreach ($offers as $o): ?>
                                    <option value="<?= (int) $o['id'] ?>"><?= e($o['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn-secondary shrink-0" type="submit">
                                <?= icon('paper-airplane', 'h-4 w-4') ?> Envoyer l'annonce
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php elseif ($key === 'offre_rappel'): ?>
                <div id="offer-reminder-block" class="mt-4 hidden rounded-xl bg-slate-50 ring-1 ring-inset ring-slate-200">
                    <p class="flex items-center gap-2 px-4 pt-3 text-sm font-semibold text-brand-navy">
                        <?= icon('play', 'h-4 w-4 text-brand-orange') ?>
                        Traiter les rappels dus maintenant
                    </p>
                    <form method="post" action="/admin/emplois/reminders/run"
                          data-confirm="Envoyer maintenant les rappels J-5 pour toutes les offres publiées arrivant à échéance (un seul rappel par offre) ?"
                          class="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center">
                        <?= csrf_field() ?>
                        <p class="flex-1 text-xs text-slate-500">
                            Envoie immédiatement les rappels des offres publiées qui clôturent dans les 5 prochains
                            jours et n'ont pas encore été rappelées. En temps normal, cette tâche est exécutée
                            automatiquement (cron <span class="font-mono">bin/run-automations.php</span> ou visites du site).
                        </p>
                        <button class="btn-secondary shrink-0" type="submit">
                            <?= icon('paper-airplane', 'h-4 w-4') ?> Exécuter les rappels
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <p class="flex items-start gap-2 px-1 text-xs text-slate-400">
        <?= icon('information-circle', 'h-4 w-4 shrink-0') ?>
        Chaque interrupteur est enregistré immédiatement. Un e-mail désactivé n'est ni envoyé ni journalisé ;
        l'interrupteur général prime sur les réglages individuels.
    </p>

    <!-- E-mail de test -->
    <div class="card">
        <h2 class="card-title">
            <span class="card-title-icon"><?= icon('paper-airplane', 'h-5 w-5') ?></span>
            E-mail de test
        </h2>
        <form method="post" action="/admin/automations/test-mail">
            <?= csrf_field() ?>
            <div class="flex flex-col gap-3 sm:flex-row">
                <div class="input-icon-wrap flex-1">
                    <?= icon('envelope') ?>
                    <input class="input" id="test-email" type="email" name="test_email"
                           placeholder="adresse@exemple.com" required aria-label="Adresse e-mail de test">
                </div>
                <button class="btn-secondary shrink-0" type="submit">
                    <?= icon('paper-airplane', 'h-4 w-4') ?> Envoyer
                </button>
            </div>
            <p class="mt-1.5 text-xs text-slate-400">
                Envoie un e-mail de test à l'adresse indiquée, même si les automatisations sont désactivées.
                Le résultat (succès ou erreur exacte) s'affiche après l'envoi.
            </p>
        </form>
    </div>
</div>

<!-- ============================ Onglet 2 : Modèles ============================ -->
<div data-panel="templates" class="hidden">
    <div class="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,26rem)]">

        <!-- Grille des modèles (2 colonnes) -->
        <div class="grid items-start gap-4 sm:grid-cols-2">
            <?php foreach ($templates as $name => $tpl): ?>
                <?php
                $override = $overrides[$name] ?? null;
                $effectiveSubject = ($override['subject'] ?? '') !== ''
                    ? $override['subject']
                    : $defaults[$name]['subject'];
                ?>
                <div class="card flex flex-col p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-bold text-brand-navy"><?= e($tpl['label']) ?></span>
                        <?php if ($override): ?>
                            <span class="badge bg-brand-orange/10 text-brand-orange ring-brand-orange/30">Personnalisé</span>
                        <?php endif; ?>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">
                        Sujet : <span class="font-mono text-slate-600">&laquo;&nbsp;<?= e($effectiveSubject) ?>&nbsp;&raquo;</span>
                    </p>
                    <p class="mt-1.5 flex-1 text-xs text-slate-500"><?= e($tpl['description']) ?></p>
                    <div class="mt-4 flex items-center gap-2 border-t border-slate-100 pt-3">
                        <button type="button" class="btn-icon" title="Prévisualiser"
                                aria-label="Prévisualiser le modèle <?= e($tpl['label']) ?>"
                                data-preview-url="/admin/automations/preview/<?= e($name) ?>"
                                data-preview-title="<?= e($tpl['label']) ?>">
                            <?= icon('eye', 'h-4 w-4') ?>
                        </button>
                        <a class="btn-icon" href="/admin/automations/templates/<?= e($name) ?>/edit"
                           title="Modifier" aria-label="Modifier le modèle <?= e($tpl['label']) ?>">
                            <?= icon('pencil', 'h-4 w-4') ?>
                        </a>
                        <?php if ($override): ?>
                            <form method="post" action="/admin/automations/templates/<?= e($name) ?>/reset"
                                  data-confirm="Réinitialiser le modèle « <?= e($tpl['label']) ?> » ? La version personnalisée sera supprimée et le rendu par défaut sera de nouveau utilisé.">
                                <?= csrf_field() ?>
                                <button class="btn-icon-danger" type="submit" title="Réinitialiser"
                                        aria-label="Réinitialiser le modèle <?= e($tpl['label']) ?>">
                                    <?= icon('arrow-path', 'h-4 w-4') ?>
                                </button>
                            </form>
                        <?php endif; ?>
                        <span class="ml-auto"><?= $recipientBadge($tpl['recipient']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
            <p class="flex items-start gap-2 px-1 text-xs text-slate-400 sm:col-span-2">
                <?= icon('information-circle', 'h-4 w-4 shrink-0') ?>
                La prévisualisation s'affiche dans le panneau ci-contre avec des données d'exemple
                (formation fictive à 25 000 F CFA, participant « Koffi Exemple »). Un modèle « Personnalisé »
                utilise le sujet et le corps enregistrés en base ; « Réinitialiser » revient au rendu par défaut.
            </p>
        </div>

        <!-- Panneau de prévisualisation (caché par défaut, sticky sur écran large) -->
        <div id="preview-panel" class="hidden xl:sticky xl:top-2">
            <div class="card overflow-hidden p-0">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5">
                    <h3 class="flex min-w-0 items-center gap-2 text-sm font-semibold text-brand-navy">
                        <?= icon('eye', 'h-4 w-4 shrink-0 text-brand-orange') ?>
                        <span id="preview-title" class="truncate">Prévisualisation</span>
                    </h3>
                    <button type="button" id="preview-close" aria-label="Fermer la prévisualisation"
                            class="btn-icon shrink-0">
                        <?= icon('x-mark', 'h-4 w-4') ?>
                    </button>
                </div>
                <iframe id="preview-frame" title="Prévisualisation du modèle d'e-mail"
                        sandbox="allow-same-origin" src="about:blank"
                        class="h-[70vh] w-full bg-slate-100"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- ============================ Onglet 3 : Journal ============================ -->
<div data-panel="journal" class="hidden max-w-4xl">
    <div class="card">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <h2 class="card-title mb-0 flex-1">
                <span class="card-title-icon"><?= icon('clock', 'h-5 w-5') ?></span>
                <?= count($logs['rows']) ?> derniers envois
                <span class="text-xs font-normal text-slate-400">
                    sur <?= (int) $logs['total'] ?>
                </span>
            </h2>
            <a class="btn-ghost btn-sm" href="/admin/automations#journal">
                <?= icon('arrow-path', 'h-4 w-4') ?> Actualiser
            </a>
            <form method="post" action="/admin/automations/logs/purge-failed"
                  data-confirm="Supprimer définitivement les e-mails en échec du journal<?= $logFilters['type'] !== '' ? ' (type filtré uniquement)' : '' ?> ?">
                <?= csrf_field() ?>
                <input type="hidden" name="log_type" value="<?= e($logFilters['type']) ?>">
                <input type="hidden" name="log_status" value="<?= e($logFilters['status']) ?>">
                <button class="btn-danger btn-sm" type="submit">
                    <?= icon('trash', 'h-4 w-4') ?> Supprimer les échecs
                </button>
            </form>
        </div>

        <form method="get" action="/admin/automations#journal" class="mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="label" for="log-status">Statut</label>
                <select class="input" id="log-status" name="log_status" data-autofilter>
                    <option value="">Tous</option>
                    <option value="sent" <?= $logFilters['status'] === 'sent' ? 'selected' : '' ?>>Envoyé</option>
                    <option value="failed" <?= $logFilters['status'] === 'failed' ? 'selected' : '' ?>>Échec</option>
                </select>
            </div>
            <div>
                <label class="label" for="log-type">Type</label>
                <select class="input" id="log-type" name="log_type" data-autofilter>
                    <option value="">Tous</option>
                    <?php foreach ($logTypes as $type): ?>
                        <option value="<?= e($type) ?>" <?= $logFilters['type'] === $type ? 'selected' : '' ?>>
                            <?= e($logTypeLabels[$type] ?? $type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn-ghost" type="submit"><?= icon('funnel', 'h-4 w-4') ?> Filtrer</button>
        </form>

        <?php if (!$logs['rows']): ?>
            <p class="rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-400 ring-1 ring-inset ring-slate-200">
                Aucun e-mail journalisé pour ces critères.
            </p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($logs['rows'] as $log): ?>
                    <li class="flex items-center justify-between gap-3 py-2.5">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="truncate text-sm font-semibold text-slate-700"><?= e($log['to_email']) ?></span>
                                <span class="badge bg-slate-100 text-slate-600 ring-slate-200">
                                    <?= e($logTypeLabels[$log['type']] ?? $log['type']) ?>
                                </span>
                                <?php if ($log['inscription_id']): ?>
                                    <a class="text-xs font-semibold text-brand-blue transition hover:text-brand-navy"
                                       href="/admin/inscriptions/<?= (int) $log['inscription_id'] ?>">
                                        Inscription #<?= (int) $log['inscription_id'] ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="mt-0.5 text-xs text-slate-400"><?= e(format_datetime($log['sent_at'])) ?></div>
                        </div>
                        <?php if ($log['status'] === 'sent'): ?>
                            <span class="badge shrink-0 bg-emerald-50 text-emerald-700 ring-emerald-200">
                                <?= icon('check-circle', 'h-3.5 w-3.5') ?> Envoyé
                            </span>
                        <?php else: ?>
                            <span class="badge shrink-0 bg-red-50 text-red-700 ring-red-200"
                                  title="<?= e($log['error'] ?? 'Erreur inconnue') ?>">
                                <?= icon('x-circle', 'h-3.5 w-3.5') ?> Échec
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?= pagination($logs['page'], $logs['pages'], '/admin/automations', [
                'log_status' => $logFilters['status'],
                'log_type'   => $logFilters['type'],
            ]) ?>
        <?php endif; ?>

        <p class="mt-4 flex items-start gap-2 border-t border-slate-100 pt-4 text-xs text-slate-400">
            <?= icon('information-circle', 'h-4 w-4 shrink-0') ?>
            Les entrées du journal sont conservées 14 jours, puis supprimées automatiquement.
        </p>
    </div>
</div>
