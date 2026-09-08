<?php
/**
 * Dépôt retenu (prochaine phase). Variables : $full_name, $project_name, $call_title, $project
 */
$linked = trim((string) ($call_title ?? $project['title'] ?? ''));
ob_start();
?>
<h2 style="margin:0 0 16px;color:#062a4d;">Félicitations, <?= e($full_name) ?> !</h2>
<p>Nous avons le plaisir de vous annoncer que votre dossier d'incubation a été retenu
    pour la prochaine phase<?= $project_name ? ' — projet <strong>' . e($project_name) . '</strong>' : '' ?>.</p>
<?php if ($linked !== ''): ?>
<p>Appel concerné : <strong><?= e($linked) ?></strong>.</p>
<?php endif; ?>
<p>Notre équipe vous recontactera très prochainement (par e-mail ou téléphone)
    pour préciser la suite : échange, mentorat ou accompagnement complémentaire.</p>
<p>Pour toute question : procopeafrique@gmail.com ou +228 96 45 76 95.</p>
<p>À très bientôt,<br><strong>L'équipe PROCOPE Afrique</strong></p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
