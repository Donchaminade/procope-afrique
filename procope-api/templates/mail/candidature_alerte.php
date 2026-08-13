<?php
/**
 * Alerte interne : nouvelle candidature reçue sur une offre d'emploi.
 * Variables : $candidature_id (int), $full_name, $email, $phone,
 *             $message_text (string), $has_cv (bool), $offer (array)
 */
ob_start();
?>
<h2 style="margin:0 0 16px;color:#062a4d;">Nouvelle candidature reçue</h2>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0;">
    <tr><td style="padding:4px 0;"><strong>Offre :</strong> <?= e($offer['title']) ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>Nom :</strong> <?= e($full_name) ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>E-mail :</strong> <?= e($email ?: '—') ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>Téléphone :</strong> <?= e($phone ?: '—') ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>CV :</strong> <?= !empty($has_cv) ? 'Oui — joint à la candidature' : 'Non' ?></td></tr>
</table>
<?php if (!empty($message_text)): ?>
<p style="margin:12px 0 4px;"><strong>Message / motivation :</strong></p>
<p style="background:#f4f6f8;border-radius:8px;padding:12px 16px;"><?= nl2br(e($message_text)) ?></p>
<?php endif; ?>
<p>Consultez le détail dans le back-office : Admin → Offres d'emploi → Candidatures → #<?= (int) $candidature_id ?></p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
