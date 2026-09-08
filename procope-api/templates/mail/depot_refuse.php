<?php
/**
 * Dépôt non retenu. Variables : $full_name, $project_name, $call_title, $project
 */
ob_start();
?>
<p>Bonjour <?= e($full_name) ?>,</p>
<p>Nous vous remercions sincèrement pour l'intérêt porté à PROCOPE Afrique
    et pour le temps consacré à votre dépôt<?= $project_name ? ' — projet <strong>' . e($project_name) . '</strong>' : '' ?>.</p>
<p>Après une étude attentive, nous ne sommes malheureusement pas en mesure
    de donner une suite favorable à ce dossier pour cette session.</p>
<p>Cette décision ne remet pas en cause la qualité de votre parcours.
    Nous vous encourageons à déposer de nouveau lors d'une prochaine vague, ou à suivre nos formations.</p>
<p>Nous vous souhaitons une pleine réussite dans vos projets.</p>
<p>Avec tous nos encouragements,<br><strong>L'équipe PROCOPE Afrique</strong></p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
