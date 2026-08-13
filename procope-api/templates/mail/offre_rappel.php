<?php
/**
 * Rappel avant clôture d'une offre d'emploi (J-5).
 * Variables : $offer (array), $cta_url (string), $jours_restants (int), $affiche_url (string|null)
 */
$jours = (int) ($jours_restants ?? 0);
ob_start();
?>
<h2 style="margin:0 0 16px;color:#062a4d;">Derniers jours pour postuler !</h2>
<p>Bonjour,</p>
<p>Il ne reste plus que <strong><?= $jours ?> jour<?= $jours > 1 ? 's' : '' ?></strong>
    pour candidater à cette offre d'emploi PROCOPE :</p>
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
    <tr><td style="padding:4px 0;"><strong>Clôture des candidatures :</strong>
        <?= e(date('d/m/Y à H\hi', strtotime((string) $offer['closes_at']))) ?></td></tr>
</table>
<table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:28px auto;">
    <tr>
        <td align="center" style="border-radius:10px;background:#f5a623;">
            <a href="<?= e($cta_url) ?>"
               style="display:inline-block;padding:16px 44px;font-size:18px;font-weight:bold;color:#062a4d;
                      text-decoration:none;border-radius:10px;">
                Postuler maintenant
            </a>
        </td>
    </tr>
</table>
<p>Après la date de clôture, il ne sera plus possible de déposer votre candidature.</p>
<p>À très bientôt,<br><strong>L'équipe PROCOPE Afrique</strong></p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
