<?php
/**
 * Réponse manuelle à un message de contact (corps déjà sanitisé).
 * Variables : $name, $reply_html (HTML whitelisté)
 */
ob_start();
?>
<p>Bonjour <?= e($name) ?>,</p>
<?= $reply_html ?>
<p style="margin-top:24px;">Cordialement,<br><strong>L'équipe PROCOPE Afrique</strong></p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
