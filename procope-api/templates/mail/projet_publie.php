<?php
/**
 * Annonce d'un nouveau projet incubé (diffusion à toute la communauté).
 * Variables : $project (array), $cta_url (string), $affiche_url (string|null)
 */
use App\Models\IncubatedProject;

$pitch = trim((string) ($project['pitch'] ?? ''));
$extrait = $pitch !== '' ? $pitch : trim((string) ($project['description'] ?? ''));
if (mb_strlen($extrait) > 220) {
    $extrait = rtrim(mb_substr($extrait, 0, 220)) . '…';
}
$stage = IncubatedProject::STAGE_LABELS[$project['stage'] ?? ''] ?? ($project['stage'] ?? '');
ob_start();
?>
<h2 style="margin:0 0 16px;color:#062a4d;">Nouveau projet incubé chez PROCOPE</h2>
<p>Bonjour,</p>
<p>PROCOPE Afrique accompagne un nouveau projet. Découvrez-le et, si vous portez une idée, déposez la vôtre en ligne.</p>
<p style="font-size:18px;font-weight:bold;color:#062a4d;margin:16px 0;"><?= e($project['title']) ?></p>
<?php if (!empty($affiche_url)): ?>
<p style="margin:16px 0;">
    <img src="<?= e($affiche_url) ?>" alt="Affiche — <?= e($project['title']) ?>"
         width="536" style="width:100%;max-width:536px;height:auto;border-radius:8px;display:block;">
</p>
<?php endif; ?>
<?php if ($extrait !== ''): ?>
<p><?= nl2br(e($extrait)) ?></p>
<?php endif; ?>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:12px 0;">
    <?php if (!empty($project['sector'])): ?>
        <tr><td style="padding:4px 0;"><strong>Secteur :</strong> <?= e($project['sector']) ?></td></tr>
    <?php endif; ?>
    <?php if ($stage !== ''): ?>
        <tr><td style="padding:4px 0;"><strong>Stade :</strong> <?= e($stage) ?></td></tr>
    <?php endif; ?>
    <tr><td style="padding:4px 0;"><strong>Pays :</strong> <?= e(($project['country'] ?? '') ?: 'Afrique') ?></td></tr>
</table>
<table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:28px auto;">
    <tr>
        <td align="center" style="border-radius:10px;background:#f5a623;">
            <a href="<?= e($cta_url) ?>"
               style="display:inline-block;padding:16px 44px;font-size:18px;font-weight:bold;color:#062a4d;
                      text-decoration:none;border-radius:10px;">
                Voir le projet
            </a>
        </td>
    </tr>
</table>
<p>À très bientôt,<br><strong>L'équipe PROCOPE Afrique</strong></p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
