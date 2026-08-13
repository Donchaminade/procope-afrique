<?php
/**
 * Accusé de réception envoyé au candidat après dépôt d'une candidature.
 * Variables : $full_name (string), $offer (array)
 */
ob_start();
?>
<h2 style="margin:0 0 16px;color:#062a4d;">Candidature bien reçue, <?= e($full_name) ?> !</h2>
<p>Nous avons bien reçu votre candidature pour l'offre :</p>
<p style="font-size:16px;font-weight:bold;color:#062a4d;margin:12px 0;"><?= e($offer['title']) ?></p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:12px 0;">
    <tr><td style="padding:4px 0;"><strong>Type de contrat :</strong> <?= e($offer['contract_type']) ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>Lieu :</strong> <?= e(($offer['location'] ?? '') ?: 'Lomé, Togo') ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>Clôture des candidatures :</strong>
        <?= e(date('d/m/Y à H\hi', strtotime((string) $offer['closes_at']))) ?></td></tr>
</table>
<p>Notre équipe étudie chaque dossier avec attention. Si votre profil est retenu,
    nous vous recontacterons pour la suite du processus.</p>
<p>Merci de votre intérêt pour PROCOPE Afrique.</p>
<p>À bientôt,<br><strong>L'équipe PROCOPE Afrique</strong></p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
