<?php
/**
 * E-mail de test envoyé depuis Admin -> Automatisations.
 * Variables : $sent_by (nom de l'admin)
 */
ob_start();
?>
<h2 style="margin:0 0 16px;color:#062a4d;">E-mail de test</h2>
<p>Ceci est un e-mail de test envoyé depuis le back-office PROCOPE
    (page Automatisations) par <strong><?= e($sent_by) ?></strong>.</p>
<p>Si vous recevez ce message, la configuration SMTP du serveur fonctionne correctement.</p>
<p>Envoyé le <?= e(date('d/m/Y à H\hi')) ?>.</p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
