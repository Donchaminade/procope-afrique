<?php
/**
 * Offre d'emploi prolongée : nouvelle date limite de candidature.
 * Variables : $offer (array), $cta_url (string), $affiche_url (string|null)
 */
ob_start();
?>
<h2 style="margin:0 0 16px;color:#062a4d;">Bonne nouvelle : l'offre est prolongée !</h2>
<p>Bonjour,</p>
<p>Vous n'avez pas encore postulé ? La date limite de candidature de cette offre
    d'emploi PROCOPE vient d'être repoussée :</p>
<p style="font-size:18px;font-weight:bold;color:#062a4d;margin:16px 0;"><?= e($offer['title']) ?></p>
<?php if (!empty($affiche_url)): ?>
<p style="margin:16px 0;">
    <img src="<?= e($affiche_url) ?>" alt="Affiche — <?= e($offer['title']) ?>"
         width="536" style="width:100%;max-width:536px;height:auto;border-radius:8px;display:block;">
</p>
<?php endif; ?>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:12px 0;">
    <tr><td style="padding:4px 0;"><strong>Type de contrat :</strong> <?= e($offer['contract_type']) ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>Lieu :</strong> <?= e(($offer['location'] ?? '') ?: 'Lomé, Togo') ?></td></tr>
    <tr><td style="padding:4px 0;"><strong>Nouvelle date de clôture :</strong>
        <?= e(date('d/m/Y à H\hi', strtotime((string) $offer['closes_at']))) ?></td></tr>
</table>
<table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:28px auto;">
    <tr>
        <td align="center" style="border-radius:10px;background:#f5a623;">
            <a href="<?= e($cta_url) ?>"
               style="display:inline-block;padding:16px 44px;font-size:18px;font-weight:bold;color:#062a4d;
                      text-decoration:none;border-radius:10px;">
                Voir l'offre et postuler
            </a>
        </td>
    </tr>
</table>
<p>Profitez de ce délai supplémentaire pour envoyer votre candidature.</p>
<p>À très bientôt,<br><strong>L'équipe PROCOPE Afrique</strong></p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
