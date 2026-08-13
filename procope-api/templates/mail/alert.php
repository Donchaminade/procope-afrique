<?php
/** Variables : $inscription_id, $full_name, $phone, $email, $formation, $has_proof */
ob_start();
?>
<h2 style="margin:0 0 16px;color:#062a4d;">Nouvelle inscription reçue</h2>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0;font-size:14px;">
    <tr><td style="padding:4px 12px 4px 0;"><strong>Formation :</strong></td><td><?= e($formation['titre']) ?></td></tr>
    <tr><td style="padding:4px 12px 4px 0;"><strong>Nom :</strong></td><td><?= e($full_name) ?></td></tr>
    <tr><td style="padding:4px 12px 4px 0;"><strong>Téléphone :</strong></td><td><?= e($phone) ?></td></tr>
    <tr><td style="padding:4px 12px 4px 0;"><strong>Email :</strong></td><td><?= e($email ?: '—') ?></td></tr>
    <tr><td style="padding:4px 12px 4px 0;"><strong>Preuve de paiement :</strong></td>
        <td><?= $has_proof ? 'Oui — à vérifier' : 'Non (préinscription)' ?></td></tr>
</table>
<p style="margin-top:16px;">
    Consultez le détail et la preuve dans le back-office :<br>
    <em>Admin &rarr; Inscriptions &rarr; #<?= (int) $inscription_id ?></em>
</p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
