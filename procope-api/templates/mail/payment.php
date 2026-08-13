<?php
/**
 * Mail envoyé après vérification du paiement par l'admin.
 * Variables : $inscription, $formation, $statut ('valide'|'paiement_partiel'),
 *             $amount_received (float), $reste (float), $slots
 */
$fmt = static fn (float $n): string => number_format($n, 0, ',', ' ') . ' F CFA';
$contact = $formation['contact_phone'] ?? '+228 96 45 76 95';
$waNumber = preg_replace('/\D/', '', $contact);
$slotLines = '';
foreach ($slots as $slot) {
    $slotLines .= '<li style="margin-bottom:4px;">' . e($slot['label'])
        . ' — de ' . date('H\hi', strtotime($slot['starts_at']))
        . ' à ' . date('H\hi', strtotime($slot['ends_at'])) . '</li>';
}
ob_start();
if ($statut === 'valide'):
?>
<h2 style="margin:0 0 16px;color:#1a7f37;">Inscription confirmée</h2>
<p>Bonjour <?= e($inscription['full_name']) ?>,</p>
<p>Merci ! Nous avons bien reçu votre paiement de <strong><?= $fmt($amount_received) ?></strong> :
    votre place à la formation <strong><?= e($formation['titre'] ?? '') ?></strong> est confirmée.</p>
<p style="margin:16px 0 6px;"><strong>Votre rendez-vous :</strong></p>
<ul style="padding-left:20px;margin:6px 0 12px;"><?= $slotLines ?></ul>
<p><strong>Lieu :</strong> <?= e($formation['lieu'] ?? 'À confirmer') ?></p>
<p>Merci d'arriver <strong>15 minutes avant</strong> le début pour l'accueil et l'émargement.</p>
<p>Nous nous réjouissons de vous accueillir. À très bientôt !</p>
<?php else: ?>
<h2 style="margin:0 0 16px;color:#7c3aed;">Paiement partiel reçu</h2>
<p>Bonjour <?= e($inscription['full_name']) ?>,</p>
<p>Nous avons bien reçu votre paiement partiel de <strong><?= $fmt($amount_received) ?></strong>
    pour la formation <strong><?= e($formation['titre'] ?? '') ?></strong>.</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:16px 0;">
    <tr><td style="padding:4px 0;"><strong>Montant reçu :</strong> <?= $fmt($amount_received) ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>Frais de participation :</strong> <?= $fmt((float) ($formation['prix'] ?? 0)) ?></td></tr>
    <tr><td style="padding:4px 0;color:#b42318;"><strong>Reste à payer :</strong> <?= $fmt($reste) ?></td></tr>
</table>
<p>Pour compléter votre paiement et confirmer définitivement votre place, contactez-nous sur WhatsApp au
    <a href="https://wa.me/<?= e($waNumber) ?>" style="color:#06A3DA;"><?= e($contact) ?></a>
    (Mobile Money TMoney / Flooz ou Ecobank) en précisant votre nom et votre référence d'inscription.</p>
<p>Votre place sera confirmée dès réception du solde.</p>
<?php endif; ?>
<p>L'équipe <strong>PROCOPE Afrique</strong></p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
