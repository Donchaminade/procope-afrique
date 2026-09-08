<?php
/**
 * Alerte interne : nouveau témoignage public.
 * Variables : $testimonial_id, $name, $email, $role, $quote, $has_photo
 */
ob_start();
?>
<h2 style="margin:0 0 16px;color:#062a4d;">Nouveau témoignage à modérer</h2>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0;">
    <tr><td style="padding:4px 0;"><strong>Nom :</strong> <?= e($name) ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>Rôle / activité :</strong> <?= e(($role ?? '') !== '' ? $role : '—') ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>E-mail :</strong> <?= e(($email ?? '') !== '' ? $email : '—') ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>Photo :</strong> <?= !empty($has_photo) ? 'Oui — jointe au dépôt' : 'Non' ?></td></tr>
</table>
<p style="margin:12px 0 4px;"><strong>Témoignage :</strong></p>
<p style="background:#f4f6f8;border-radius:8px;padding:12px 16px;"><?= nl2br(e($quote)) ?></p>
<p>Validez ou refusez dans le back-office : Admin → Témoignages → #<?= (int) $testimonial_id ?></p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
