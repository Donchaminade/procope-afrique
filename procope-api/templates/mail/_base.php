<?php
/**
 * Enveloppe HTML commune des mails PROCOPE. Variables : $body_html
 * (le corps), éventuellement $subject_line.
 * CSS 100 % inline (compatibilité clients mail). Le logo est servi depuis
 * l'API (URL absolue construite depuis APP_URL) avec repli texte si les
 * images sont bloquées.
 */
$appUrl = rtrim((string) \App\Core\Env::get('APP_URL', ''), '/');
$logoUrl = $appUrl . '/assets/logo.png';
$siteUrl = rtrim((string) \App\Core\Env::get('SITE_URL', 'https://procopeafrique.vercel.app'), '/');
?>
<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#eef1f5;font-family:Arial,Helvetica,sans-serif;color:#26303b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f5;padding:28px 12px;">
    <tr><td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0"
               style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:600px;width:100%;box-shadow:0 2px 10px rgba(6,42,77,0.10);">
            <!-- En-tête : logo + nom -->
            <tr>
                <td style="background:#062a4d;padding:22px 32px;" align="center">
                    <img src="<?= e($logoUrl) ?>" alt="Logo PROCOPE Afrique" height="52"
                         style="height:52px;max-height:52px;width:auto;display:inline-block;vertical-align:middle;border:0;">
                    <div style="margin-top:10px;">
                        <span style="color:#ffffff;font-size:19px;font-weight:bold;letter-spacing:1.5px;">PROCOPE</span>
                        <span style="color:#f5a623;font-size:19px;font-weight:bold;letter-spacing:1.5px;"> AFRIQUE</span>
                    </div>
                    <div style="color:#9fb4c8;font-size:11px;letter-spacing:0.6px;margin-top:4px;">
                        Incubateur social — Entreprendre autrement en Afrique
                    </div>
                </td>
            </tr>
            <!-- Bandeau couleur PROCOPE -->
            <tr>
                <td style="height:5px;line-height:5px;font-size:0;background:#f5a623;">&nbsp;</td>
            </tr>
            <!-- Corps -->
            <tr>
                <td style="padding:36px 34px 28px;font-size:15px;line-height:1.65;color:#26303b;">
                    <?= $body_html ?>
                </td>
            </tr>
            <!-- Séparateur -->
            <tr>
                <td style="padding:0 34px;">
                    <div style="border-top:1px solid #e4e9ef;height:1px;line-height:1px;font-size:0;">&nbsp;</div>
                </td>
            </tr>
            <!-- Pied de page : contacts + site -->
            <tr>
                <td style="padding:22px 34px 26px;background:#f7f9fb;">
                    <p style="margin:0 0 6px;font-size:13px;font-weight:bold;color:#062a4d;">PROCOPE Afrique</p>
                    <p style="margin:0 0 3px;font-size:12px;color:#5b6b7b;">
                        Lomé, Togo &nbsp;·&nbsp;
                        <a href="mailto:procopeafrique@gmail.com" style="color:#5b6b7b;text-decoration:underline;">procopeafrique@gmail.com</a>
                        &nbsp;·&nbsp; +228 96 45 76 95
                    </p>
                    <p style="margin:0 0 10px;font-size:12px;">
                        <a href="<?= e($siteUrl) ?>" style="color:#f5a623;font-weight:bold;text-decoration:none;"><?= e(preg_replace('#^https?://#', '', $siteUrl)) ?></a>
                    </p>
                    <p style="margin:0;font-size:11px;color:#93a1b0;">
                        Cet e-mail a été envoyé automatiquement, merci de ne pas y répondre directement.
                    </p>
                </td>
            </tr>
        </table>
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
            <tr>
                <td align="center" style="padding:14px 10px 0;font-size:11px;color:#9aa7b4;">
                    &copy; <?= date('Y') ?> PROCOPE Afrique — Tous droits réservés.
                </td>
            </tr>
        </table>
    </td></tr>
</table>
</body>
</html>
