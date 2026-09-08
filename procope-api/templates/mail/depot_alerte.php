<?php
/**
 * Alerte interne : nouveau dépôt de projet.
 * Variables : $depot_id, $full_name, $email, $phone, $project_name, $sector,
 *             $pitch, $message_text, $has_file, $call_title, $project (array|null)
 */
$linked = trim((string) ($call_title ?? $project['title'] ?? ''));
ob_start();
?>
<h2 style="margin:0 0 16px;color:#062a4d;">Nouveau dépôt de projet</h2>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0;">
    <tr><td style="padding:4px 0;"><strong>Origine :</strong> <?= e($linked !== '' ? $linked : 'Candidature spontanée') ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>Nom du projet porté :</strong> <?= e(($project_name ?? '') !== '' ? $project_name : '—') ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>Nom :</strong> <?= e($full_name) ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>E-mail :</strong> <?= e($email ?: '—') ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>Téléphone :</strong> <?= e($phone ?: '—') ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>Secteur :</strong> <?= e(($sector ?? '') !== '' ? $sector : '—') ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>Pitch deck :</strong> <?= !empty($has_file) ? 'Oui — PDF joint' : 'Non' ?></td></tr>
</table>
<?php if (!empty($pitch)): ?>
<p style="margin:12px 0 4px;"><strong>Pitch :</strong></p>
<p style="background:#f4f6f8;border-radius:8px;padding:12px 16px;"><?= nl2br(e($pitch)) ?></p>
<?php endif; ?>
<?php if (!empty($message_text)): ?>
<p style="margin:12px 0 4px;"><strong>Message :</strong></p>
<p style="background:#f4f6f8;border-radius:8px;padding:12px 16px;"><?= nl2br(e($message_text)) ?></p>
<?php endif; ?>
<p>Consultez le détail dans le back-office : Admin → Projets incubés → Dépôts → #<?= (int) $depot_id ?></p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
