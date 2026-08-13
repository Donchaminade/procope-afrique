<?php
/**
 * Notification interne : nouveau message reçu via le formulaire de contact.
 * Variables : $contact_id, $name, $email, $phone, $subject, $message_text
 */
ob_start();
?>
<h2 style="margin:0 0 16px;color:#062a4d;">Nouveau message de contact</h2>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0;font-size:14px;">
    <tr><td style="padding:4px 12px 4px 0;vertical-align:top;"><strong>Nom :</strong></td>
        <td><?= e($name) ?></td></tr>
    <tr><td style="padding:4px 12px 4px 0;vertical-align:top;"><strong>E-mail :</strong></td>
        <td><?= $email ? '<a href="mailto:' . e($email) . '" style="color:#06A3DA;">' . e($email) . '</a>' : '—' ?></td></tr>
    <tr><td style="padding:4px 12px 4px 0;vertical-align:top;"><strong>Téléphone :</strong></td>
        <td><?= e($phone ?: '—') ?></td></tr>
    <tr><td style="padding:4px 12px 4px 0;vertical-align:top;"><strong>Sujet :</strong></td>
        <td><?= e($subject ?: '—') ?></td></tr>
</table>
<p style="margin:16px 0 6px;"><strong>Message :</strong></p>
<div style="background:#f4f6f8;border-radius:6px;padding:14px 16px;font-size:14px;line-height:1.6;">
    <?= nl2br(e($message_text)) ?>
</div>
<p style="margin-top:20px;">
    Répondez directement au contact ou gérez ce message dans le back-office :<br>
    <em>Admin &rarr; Messages &rarr; #<?= (int) $contact_id ?></em>
</p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
