<?php
/**
 * Annonce d'une nouvelle formation aux anciens participants.
 * Variables : $formation, $slots, $affiche_url (string|null), $cta_url (string)
 */
$slotLines = '';
foreach ($slots as $slot) {
    $slotLines .= '<li style="margin-bottom:4px;">' . e($slot['label'])
        . ' — de ' . date('H\hi', strtotime($slot['starts_at']))
        . ' à ' . date('H\hi', strtotime($slot['ends_at'])) . '</li>';
}
ob_start();
?>
<h2 style="margin:0 0 16px;color:#062a4d;">Une nouvelle formation PROCOPE est disponible !</h2>
<p>Bonjour,</p>
<p>Vous avez participé à l'une de nos formations : merci pour votre confiance !
    Nous avons le plaisir de vous annoncer l'ouverture d'une nouvelle session :</p>
<p style="font-size:18px;font-weight:bold;color:#062a4d;margin:16px 0;"><?= e($formation['titre']) ?></p>
<?php if (!empty($affiche_url)): ?>
<p style="margin:16px 0;">
    <img src="<?= e($affiche_url) ?>" alt="Affiche — <?= e($formation['titre']) ?>"
         width="536" style="width:100%;max-width:536px;height:auto;border-radius:8px;display:block;">
</p>
<?php endif; ?>
<?php if (!empty($formation['intro'])): ?>
<p><?= nl2br(e($formation['intro'])) ?></p>
<?php endif; ?>
<?php if ($slotLines !== ''): ?>
<p style="margin:16px 0 4px;"><strong>Dates et horaires :</strong></p>
<ul style="padding-left:20px;margin:4px 0 12px;">
    <?= $slotLines ?>
</ul>
<?php endif; ?>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:12px 0;">
    <tr><td style="padding:4px 0;"><strong>Lieu :</strong> <?= e($formation['lieu'] ?? 'À confirmer') ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>Frais de participation :</strong>
        <?= number_format((float) $formation['prix'], 0, ',', ' ') ?> F CFA</td></tr>
</table>
<table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:28px auto;">
    <tr>
        <td align="center" style="border-radius:10px;background:#f5a623;">
            <a href="<?= e($cta_url) ?>"
               style="display:inline-block;padding:16px 44px;font-size:18px;font-weight:bold;color:#062a4d;
                      text-decoration:none;border-radius:10px;">
                Je m'inscris
            </a>
        </td>
    </tr>
</table>
<p>Les places sont limitées : ne tardez pas à réserver la vôtre.</p>
<p>Pour toute question : <?= e($formation['contact_phone'] ?? '+228 96 45 76 95') ?>.</p>
<p>À très bientôt,<br><strong>L'équipe PROCOPE Afrique</strong></p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
