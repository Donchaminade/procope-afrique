<?php
/** Variables : $inscription, $formation, $statut ('valide'|'refuse'), $slots */
$slotLines = '';
foreach ($slots as $slot) {
    $slotLines .= '<li style="margin-bottom:4px;">' . e($slot['label'])
        . ' — de ' . date('H\hi', strtotime($slot['starts_at']))
        . ' à ' . date('H\hi', strtotime($slot['ends_at'])) . '</li>';
}
ob_start();
if ($statut === 'valide'):
?>
<h2 style="margin:0 0 16px;color:#1a7f37;">Inscription confirmée 🎉</h2>
<p>Bonjour <?= e($inscription['full_name']) ?>,</p>
<p>Votre paiement a été vérifié : votre place à la formation
    <strong><?= e($formation['titre'] ?? '') ?></strong> est confirmée.</p>
<ul style="padding-left:20px;margin:12px 0;"><?= $slotLines ?></ul>
<p><strong>Lieu :</strong> <?= e($formation['lieu'] ?? 'À confirmer') ?></p>
<p>Présentez-vous 15 minutes avant le début. À très bientôt !</p>
<?php else: ?>
<h2 style="margin:0 0 16px;color:#b42318;">Suite de votre inscription</h2>
<p>Bonjour <?= e($inscription['full_name']) ?>,</p>
<p>Nous n'avons pas pu valider votre inscription à la formation
    <strong><?= e($formation['titre'] ?? '') ?></strong> en l'état.</p>
<p>Cela peut venir d'une preuve de paiement illisible ou manquante.
    Contactez-nous au <?= e($formation['contact_phone'] ?? '+228 96 45 76 95') ?>
    pour régulariser votre situation.</p>
<?php endif; ?>
<p>L'équipe <strong>PROCOPE Afrique</strong></p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
