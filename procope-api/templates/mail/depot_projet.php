<?php
/**
 * Accusé de réception d'un dépôt de projet (candidat).
 * Variables : $full_name, $project_name, $call_title, $project (array|null)
 */
$linked = trim((string) ($call_title ?? $project['title'] ?? ''));
ob_start();
?>
<h2 style="margin:0 0 16px;color:#062a4d;">Dépôt bien reçu, <?= e($full_name) ?> !</h2>
<p>Nous avons bien reçu votre dossier d'incubation<?= $project_name ? ' pour le projet <strong>' . e($project_name) . '</strong>' : '' ?>.</p>
<?php if ($linked !== ''): ?>
<p>Il est associé à l'appel : <strong><?= e($linked) ?></strong>.</p>
<?php else: ?>
<p>Il s'agit d'une candidature spontanée : notre équipe l'étudiera au même titre que les autres dossiers.</p>
<?php endif; ?>
<p>Nous lisons chaque pitch avec attention. Si votre projet est retenu pour la suite,
    nous vous recontacterons par e-mail ou téléphone.</p>
<p>Merci de votre confiance.</p>
<p>À bientôt,<br><strong>L'équipe PROCOPE Afrique</strong></p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
