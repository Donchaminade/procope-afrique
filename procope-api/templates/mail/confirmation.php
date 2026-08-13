<?php
/** Variables : $full_name, $formation, $slots, $has_proof, $payment_type, $amount_declared */
$payment_type = $payment_type ?? 'total';
$amount_declared = isset($amount_declared) ? (float) $amount_declared : null;
$prix = (float) $formation['prix'];
$reste = $amount_declared !== null ? max(0.0, $prix - $amount_declared) : null;
$fmt = static fn (float $n): string => number_format($n, 0, ',', ' ') . ' F CFA';
$slotLines = '';
foreach ($slots as $slot) {
    $slotLines .= '<li style="margin-bottom:4px;">' . e($slot['label'])
        . ' — de ' . date('H\hi', strtotime($slot['starts_at']))
        . ' à ' . date('H\hi', strtotime($slot['ends_at'])) . '</li>';
}
ob_start();
?>
<h2 style="margin:0 0 16px;color:#062a4d;">Merci, <?= e($full_name) ?> !</h2>
<p>Nous avons bien reçu votre <?= $has_proof ? 'inscription et votre preuve de paiement' : 'préinscription' ?>
    à la formation :</p>
<p style="font-size:16px;font-weight:bold;color:#062a4d;margin:12px 0;"><?= e($formation['titre']) ?></p>
<ul style="padding-left:20px;margin:12px 0;">
    <?= $slotLines ?>
</ul>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:16px 0;">
    <tr><td style="padding:4px 0;"><strong>Lieu :</strong> <?= e($formation['lieu'] ?? 'À confirmer') ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>Frais de participation :</strong>
        <?= number_format((float) $formation['prix'], 0, ',', ' ') ?> F CFA</td></tr>
    <?php if ($amount_declared !== null): ?>
    <tr><td style="padding:4px 0;"><strong>Montant déclaré payé :</strong> <?= $fmt($amount_declared) ?>
        (<?= $payment_type === 'partiel' ? 'paiement partiel' : 'paiement total' ?>)</td></tr>
    <?php if ($payment_type === 'partiel' && $reste > 0): ?>
    <tr><td style="padding:4px 0;"><strong>Reste à payer :</strong> <?= $fmt($reste) ?></td></tr>
    <?php endif; ?>
    <?php endif; ?>
</table>
<?php if ($payment_type === 'partiel' && $reste !== null && $reste > 0): ?>
<p>Vous avez choisi un paiement partiel : pensez à compléter le solde de <?= $fmt($reste) ?>
    avant la formation pour garantir votre place. Contactez-nous au
    <?= e($formation['contact_phone'] ?? '+228 96 45 76 95') ?> pour finaliser le paiement.</p>
<?php endif; ?>
<?php if ($has_proof): ?>
<p>Notre équipe vérifie votre preuve de paiement et vous enverra une confirmation définitive très prochainement.</p>
<?php else: ?>
<p><strong>Important :</strong> votre place sera confirmée à la réception de votre preuve de dépôt ou de paiement.
    Vous pouvez nous l'envoyer via WhatsApp au
    <?= e($formation['contact_phone'] ?? '+228 96 45 76 95') ?>.</p>
<?php endif; ?>
<p style="margin-top:20px;">Pour toute question : <?= e($formation['contact_phone'] ?? '+228 96 45 76 95') ?>.</p>
<p>À très bientôt,<br><strong>L'équipe PROCOPE Afrique</strong></p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
