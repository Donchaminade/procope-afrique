<?php
/**
 * Candidature non retenue : message respectueux d'encouragement.
 * Variables : $full_name (string), $offer (array)
 */
ob_start();
?>
<h2 style="margin:0 0 16px;color:#062a4d;">Bonjour <?= e($full_name) ?>,</h2>
<p>Nous vous remercions sincèrement pour l'intérêt que vous avez porté à PROCOPE Afrique
    et pour le temps consacré à votre candidature à l'offre :</p>
<p style="font-size:16px;font-weight:bold;color:#062a4d;margin:12px 0;"><?= e($offer['title']) ?></p>
<p>Après une étude attentive de votre dossier, nous ne sommes malheureusement pas en mesure
    de donner une suite favorable à votre candidature pour ce poste.</p>
<p>Cette décision ne remet pas en cause la qualité de votre parcours : le nombre de places
    est limité et le choix a été difficile. Nous conservons votre profil et nous vous
    encourageons vivement à candidater à nos prochaines offres — elles sont publiées sur
    notre site et annoncées par e-mail.</p>
<p>Nous vous souhaitons une pleine réussite dans vos projets professionnels.</p>
<p>Avec tous nos encouragements,<br><strong>L'équipe PROCOPE Afrique</strong></p>
<?php
$body_html = ob_get_clean();
require __DIR__ . '/_base.php';
