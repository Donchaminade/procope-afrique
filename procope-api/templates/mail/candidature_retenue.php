<?php
/**
 * Candidature retenue pour la prochaine phase du recrutement.
 * Variables : $full_name (string), $offer (array), $cta_url (string|null)
 */
ob_start();
?>
<h2 style="margin:0 0 16px;color:#062a4d;">Félicitations, <?= e($full_name) ?> !</h2>
<p>Nous avons le plaisir de vous annoncer que votre candidature a été
    <strong>retenue pour la prochaine phase</strong> de notre processus de recrutement :</p>
<p style="font-size:16px;font-weight:bold;color:#062a4d;margin:12px 0;"><?= e($offer['title']) ?></p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:12px 0;">
    <tr><td style="padding:4px 0;"><strong>Type de contrat :</strong> <?= e($offer['contract_type']) ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>Lieu :</strong> <?= e(($offer['location'] ?? '') ?: 'Lomé, Togo') ?></td></tr>
</table>
<p><strong>Et maintenant ?</strong> Notre équipe vous recontactera très prochainement
    (par e-mail ou téléphone) pour vous préciser la suite du processus :
    entretien, test pratique ou échanges complémentaires.</p>
<p>D'ici là, tenez-vous prêt(e) et n'hésitez pas à nous écrire à
    <a href="mailto:procopeafrique@gmail.com" style="color:#06A3DA;">procopeafrique@gmail.com</a>
    ou à nous appeler au +228 96 45 76 95 pour toute question.</p>
<p>À très bientôt,<br><strong>L'équipe PROCOPE Afrique</strong></p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
