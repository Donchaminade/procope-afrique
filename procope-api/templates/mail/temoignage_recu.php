<?php
/**
 * Accusé de réception d'un témoignage (auteur).
 * Variables : $name, $role, $quote
 */
ob_start();
?>
<h2 style="margin:0 0 16px;color:#062a4d;">Merci, <?= e($name) ?> !</h2>
<p>Nous avons bien reçu votre témoignage<?= !empty($role) ? ' (' . e($role) . ')' : '' ?>.</p>
<p>L'équipe le lira avec attention. S'il est retenu, il pourra apparaître sur le site public de PROCOPE Afrique.</p>
<p>Rien n'est publié sans cette relecture : vous n'avez rien d'autre à faire.</p>
<p>Merci de votre confiance.</p>
<p>À bientôt,<br><strong>L'équipe PROCOPE Afrique</strong></p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
