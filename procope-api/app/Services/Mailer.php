<?php

namespace App\Services;

use App\Core\Env;
use App\Models\MailLog;
use App\Models\MailTemplate;
use App\Models\Setting;

/**
 * Envoi de mails via PHPMailer (SMTP) si présent, sinon mail() natif.
 * La configuration SMTP vit dans le .env (SMTP_*, MAIL_FROM*, MAIL_REPLY_TO*).
 * SMTP_USER / SMTP_PASS = authentification uniquement (jamais affichés).
 * MAIL_FROM = From visible (ex. procopeafrique@gmail.com).
 * MAIL_REPLY_TO = Reply-To (réponses vers la boîte publique).
 * Sender / envelope = SMTP_USER (compte qui authentifie).
 * Gmail réécrit le From si MAIL_FROM n'est pas un alias « Envoyer en tant que »
 * du compte SMTP_USER. Seuls le master switch (mail_enabled) et les toggles
 * auto_* sont en base. Tous les envois sont journalisés dans mail_logs.
 */
final class Mailer
{
    /** Master switch (setting mail_enabled, défaut .env MAIL_ENABLED). */
    public static function enabled(): bool
    {
        return in_array(strtolower((string) Setting::get('mail_enabled', '1')), ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * Toggles désactivés par défaut (module emploi) : si la ligne settings
     * est absente, l'automatisation reste OFF tant que l'admin ne l'active pas.
     */
    public const OFF_BY_DEFAULT = [
        'offre_publiee', 'offre_rappel', 'offre_prolongee', 'candidature_emploi',
        'candidature_retenue', 'candidature_refusee',
    ];

    /**
     * Un e-mail automatique donné est-il actif ?
     * (master switch + toggle spécifique auto_<clé>, défaut activé sauf OFF_BY_DEFAULT)
     */
    public static function autoEnabled(string $key): bool
    {
        $default = in_array($key, self::OFF_BY_DEFAULT, true) ? '0' : '1';
        return self::enabled()
            && in_array(strtolower((string) Setting::get('auto_' . $key, $default)), ['1', 'true', 'on', 'yes'], true);
    }

    /** Le SMTP est-il configuré dans le .env ? */
    public static function smtpConfigured(): bool
    {
        return (string) Env::get('SMTP_HOST', '') !== '' && (string) Env::get('SMTP_USER', '') !== '';
    }

    /**
     * Rend un template et retourne le HTML complet.
     * Si un override existe en base (mail_templates), son body avec {{variables}}
     * est substitué puis injecté dans le layout _base ; sinon rendu fichier.
     */
    public static function template(string $name, array $data): string
    {
        $override = MailTemplate::find($name);
        if ($override && trim((string) $override['body']) !== '') {
            return self::renderOverride($name, (string) $override['body'], $data);
        }
        return self::renderFile(dirname(__DIR__, 2) . '/templates/mail/' . $name . '.php', $data);
    }

    /**
     * Rendu d'un template fichier dans une portée isolée : sans cette
     * isolation, extract() sautait les clés de données en collision avec les
     * variables locales de template() ($name, $data, ...) — le template
     * « contact » affichait par ex. le nom du template au lieu du nom du
     * contact.
     */
    private static function renderFile(string $__file, array $__data): string
    {
        extract($__data, EXTR_SKIP);
        ob_start();
        require $__file;
        return (string) ob_get_clean();
    }

    /**
     * Sujet effectif d'un envoi : override en base (avec substitution des
     * {{variables}}) si défini, sinon le sujet par défaut fourni.
     */
    public static function subjectFor(string $template, array $data, string $default): string
    {
        $override = MailTemplate::find($template);
        if ($override && trim((string) $override['subject']) !== '') {
            $subject = self::substitute((string) $override['subject'], self::vars($template, $data), false);
            return trim(str_replace(["\r", "\n"], ' ', $subject));
        }
        return $default;
    }

    /** Rend le body override (HTML simple + {{variables}}) dans le layout _base. */
    private static function renderOverride(string $name, string $body, array $data): string
    {
        // Corps saisi sans aucune balise : les retours à la ligne deviennent des <br>
        $isPlainText = ($body === strip_tags($body));
        $html = self::substitute($body, self::vars($name, $data), true);
        if ($isPlainText) {
            $html = nl2br($html);
        }

        // Cas spéciaux annonce formation + e-mails offre : affiche au-dessus
        // et bouton CTA en dessous du texte éditable
        $ctaLabels = [
            'annonce'         => "Je m'inscris",
            'offre'           => "Voir l'offre et postuler",
            'offre_rappel'    => 'Postuler maintenant',
            'offre_prolongee' => "Voir l'offre et postuler",
        ];
        if (isset($ctaLabels[$name])) {
            $before = '';
            if (!empty($data['affiche_url'])) {
                $titre = (string) ($data['formation']['titre'] ?? $data['offer']['title'] ?? '');
                $before = '<p style="margin:0 0 16px;">'
                    . '<img src="' . e((string) $data['affiche_url']) . '" alt="Affiche — ' . e($titre) . '"'
                    . ' width="536" style="width:100%;max-width:536px;height:auto;border-radius:8px;display:block;"></p>';
            }
            $after = '';
            if (!empty($data['cta_url'])) {
                $after = '<table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:28px auto;">'
                    . '<tr><td align="center" style="border-radius:10px;background:#f5a623;">'
                    . '<a href="' . e((string) $data['cta_url']) . '" style="display:inline-block;padding:16px 44px;'
                    . 'font-size:18px;font-weight:bold;color:#062a4d;text-decoration:none;border-radius:10px;">'
                    . $ctaLabels[$name] . '</a></td></tr></table>';
            }
            $html = $before . $html . $after;
        }

        $body_html = $html;
        ob_start();
        require dirname(__DIR__, 2) . '/templates/mail/_base.php';
        return (string) ob_get_clean();
    }

    /**
     * Remplace les {{variables}} par leurs valeurs. En mode HTML les valeurs
     * sont échappées et leurs retours à la ligne convertis en <br>.
     * Une variable inconnue est laissée telle quelle (visible, donc corrigible).
     */
    private static function substitute(string $text, array $vars, bool $htmlValues): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            static function (array $m) use ($vars, $htmlValues): string {
                $key = strtolower($m[1]);
                if (!array_key_exists($key, $vars)) {
                    return $m[0];
                }
                $value = (string) $vars[$key];
                return $htmlValues
                    ? str_replace(["\r\n", "\n"], '<br>', e($value))
                    : $value;
            },
            $text
        );
    }

    /** Aplatit les données structurées d'un template en variables {{clé}} => texte. */
    public static function vars(string $template, array $data): array
    {
        $formation = is_array($data['formation'] ?? null) ? $data['formation'] : [];
        $fmt = static fn (float $n): string => number_format($n, 0, ',', ' ') . ' F CFA';

        $slotLines = [];
        foreach (($data['slots'] ?? []) as $slot) {
            $slotLines[] = ($slot['label'] ?? '')
                . ' — de ' . date('H\hi', strtotime((string) ($slot['starts_at'] ?? 'now')))
                . ' à ' . date('H\hi', strtotime((string) ($slot['ends_at'] ?? 'now')));
        }

        $common = [
            'formation'     => (string) ($formation['titre'] ?? ''),
            'prix'          => isset($formation['prix']) ? $fmt((float) $formation['prix']) : '',
            'lieu'          => (string) ($formation['lieu'] ?? 'À confirmer'),
            'contact_phone' => (string) ($formation['contact_phone'] ?? '+228 96 45 76 95'),
            'slots'         => implode("\n", $slotLines),
        ];

        // Variables communes des templates du module Offres d'emploi
        $offer = is_array($data['offer'] ?? null) ? $data['offer'] : [];
        $description = trim((string) ($offer['description'] ?? ''));
        $extrait = mb_strlen($description) > 220
            ? rtrim(mb_substr($description, 0, 220)) . '…'
            : $description;
        $offerCommon = [
            'title'         => (string) ($offer['title'] ?? ''),
            'contract_type' => (string) ($offer['contract_type'] ?? ''),
            'location'      => (string) (($offer['location'] ?? '') ?: 'Lomé, Togo'),
            'salary'        => (string) (($offer['salary'] ?? '') ?: 'À discuter'),
            'closes_at'     => !empty($offer['closes_at'])
                ? date('d/m/Y à H\hi', strtotime((string) $offer['closes_at'])) : '',
            'extrait'       => $extrait,
            'cta_url'       => (string) ($data['cta_url'] ?? ''),
        ];

        return match ($template) {
            'confirmation' => $common + [
                'full_name'       => (string) ($data['full_name'] ?? ''),
                'type_paiement'   => ($data['payment_type'] ?? 'total') === 'partiel' ? 'paiement partiel' : 'paiement total',
                'montant_declare' => isset($data['amount_declared']) && $data['amount_declared'] !== null
                    ? $fmt((float) $data['amount_declared']) : '—',
                'reste'           => isset($data['amount_declared'], $formation['prix']) && $data['amount_declared'] !== null
                    ? $fmt(max(0.0, (float) $formation['prix'] - (float) $data['amount_declared'])) : '—',
            ],
            'alert' => $common + [
                'inscription_id' => (string) (int) ($data['inscription_id'] ?? 0),
                'full_name'      => (string) ($data['full_name'] ?? ''),
                'phone'          => (string) ($data['phone'] ?? '—'),
                'email'          => (string) (($data['email'] ?? '') ?: '—'),
                'preuve'         => !empty($data['has_proof']) ? 'Oui — à vérifier' : 'Non (préinscription)',
            ],
            'status' => $common + [
                'full_name' => (string) ($data['inscription']['full_name'] ?? ''),
                'statut'    => ($data['statut'] ?? '') === 'valide' ? 'Validée' : 'Refusée',
            ],
            'payment' => $common + [
                'full_name'    => (string) ($data['inscription']['full_name'] ?? ''),
                'statut'       => ($data['statut'] ?? '') === 'valide' ? 'inscription validée' : 'paiement partiel',
                'montant_recu' => isset($data['amount_received']) ? $fmt((float) $data['amount_received']) : '—',
                'reste'        => isset($data['reste']) ? $fmt((float) $data['reste']) : '—',
            ],
            'contact' => [
                'contact_id' => (string) (int) ($data['contact_id'] ?? 0),
                'name'       => (string) ($data['name'] ?? ''),
                'email'      => (string) (($data['email'] ?? '') ?: '—'),
                'phone'      => (string) (($data['phone'] ?? '') ?: '—'),
                'sujet'      => (string) (($data['subject'] ?? '') ?: '—'),
                'message'    => (string) ($data['message_text'] ?? ''),
            ],
            'annonce' => $common + [
                'intro'   => (string) ($formation['intro'] ?? ''),
                'cta_url' => (string) ($data['cta_url'] ?? ''),
            ],
            'offre', 'offre_rappel', 'offre_prolongee' => $offerCommon + [
                'jours_restants' => (string) ($data['jours_restants'] ?? ''),
            ],
            'candidature_emploi', 'candidature_retenue', 'candidature_refusee' => $offerCommon + [
                'full_name' => (string) ($data['full_name'] ?? ''),
            ],
            'candidature_alerte' => $offerCommon + [
                'candidature_id' => (string) (int) ($data['candidature_id'] ?? 0),
                'full_name'      => (string) ($data['full_name'] ?? ''),
                'email'          => (string) (($data['email'] ?? '') ?: '—'),
                'phone'          => (string) (($data['phone'] ?? '') ?: '—'),
                'message'        => (string) ($data['message_text'] ?? ''),
                'cv'             => !empty($data['has_cv']) ? 'Oui — joint à la candidature' : 'Non',
            ],
            default => [
                'sent_by' => (string) ($data['sent_by'] ?? 'Admin'),
                'date'    => date('d/m/Y à H\hi'),
            ],
        };
    }

    /** Envoi automatique : respecte le master switch. Retourne true si envoyé. */
    public static function send(string $to, string $subject, string $html, string $type, ?int $inscriptionId = null): bool
    {
        if (!self::enabled()) {
            return false;
        }
        return self::sendNow($to, $subject, $html, $type, $inscriptionId) === null;
    }

    /**
     * Envoi direct (actions manuelles / test) : ignore le master switch et les
     * toggles, mais journalise toujours. Retourne null si envoyé, sinon le
     * message d'erreur exact.
     */
    public static function sendNow(string $to, string $subject, string $html, string $type, ?int $inscriptionId = null): ?string
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return 'Adresse e-mail invalide : ' . $to;
        }
        try {
            if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
                self::sendSmtp($to, $subject, $html);
            } else {
                self::sendNative($to, $subject, $html);
            }
            MailLog::record($inscriptionId, $type, $to, true);
            return null;
        } catch (\Throwable $e) {
            error_log('Mailer: ' . $e->getMessage());
            MailLog::record($inscriptionId, $type, $to, false, $e->getMessage());
            return $e->getMessage();
        }
    }

    /** Destinataire public historique si mail_notify / MAIL_NOTIFY sont vides. */
    public const TEAM_NOTIFY_FALLBACK = 'procopeafrique@gmail.com';

    /** Content-ID du logo embarqué (HTML d'envoi : src="cid:logo-procope"). */
    public const LOGO_CID = 'logo-procope';

    /** Fichier source du logo (pièce jointe inline). */
    public static function logoPath(): string
    {
        return dirname(__DIR__, 2) . '/public/assets/logo.png';
    }

    /**
     * URL publique du logo (prévisualisation admin + repli si le fichier
     * CID est absent). En local = APP_URL (ex. http://127.0.0.1:8088/assets/logo.png)
     * — inutilisable par Gmail, d'où l'embarque CID à l'envoi.
     */
    public static function logoPublicUrl(): string
    {
        $appUrl = rtrim((string) Env::get('APP_URL', ''), '/');
        return ($appUrl !== '' ? $appUrl : '') . '/assets/logo.png';
    }

    /**
     * HTML destiné au SMTP : remplace l'URL du logo par cid:logo-procope
     * si le fichier existe. La preview admin continue d'utiliser logoPublicUrl().
     */
    public static function htmlForDelivery(string $html): string
    {
        if (!is_file(self::logoPath())) {
            return $html;
        }
        $url = self::logoPublicUrl();
        if ($url !== '' && str_contains($html, $url)) {
            return str_replace($url, 'cid:' . self::LOGO_CID, $html);
        }
        return $html;
    }

    /**
     * Parse MAIL_NOTIFY / mail_notify : virgules ou points-virgules, trim,
     * dédoublonnage (insensible à la casse), validation du format e-mail.
     *
     * @return list<string>
     */
    public static function parseNotifyList(?string $raw): array
    {
        $parts = preg_split('/[,;]+/', (string) $raw) ?: [];
        $seen = [];
        $out = [];
        foreach ($parts as $part) {
            $email = mb_strtolower(trim($part));
            if ($email === '' || isset($seen[$email])) {
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $seen[$email] = true;
            $out[] = $email;
        }
        return $out;
    }

    /**
     * Destinataires des alertes internes (setting mail_notify, sinon MAIL_NOTIFY,
     * sinon TEAM_NOTIFY_FALLBACK). Toujours au moins une adresse valide.
     *
     * @return list<string>
     */
    public static function teamRecipients(): array
    {
        $list = self::parseNotifyList((string) Setting::get('mail_notify', self::TEAM_NOTIFY_FALLBACK));
        return $list !== [] ? $list : [self::TEAM_NOTIFY_FALLBACK];
    }

    /**
     * Envoie aux destinataires internes. Un appel send() (donc un mail_log
     * to_email + sent/failed) par destinataire — cohérent avec MailLog
     * (une colonne to_email, pas de liste). From / Reply-To inchangés
     * (MAIL_FROM / MAIL_REPLY_TO, jamais l'adresse perso).
     */
    public static function notifyTeam(string $subject, string $html, string $type, ?int $inscriptionId = null): void
    {
        foreach (self::teamRecipients() as $to) {
            self::send($to, $subject, $html, $type, $inscriptionId);
        }
    }

    private static function sendSmtp(string $to, string $subject, string $html): void
    {
        if (!self::smtpConfigured()) {
            throw new \RuntimeException('SMTP non configuré : renseignez SMTP_HOST et SMTP_USER dans le .env.');
        }
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = (string) Env::get('SMTP_HOST', '');
        $mail->Port       = Env::int('SMTP_PORT', 465);
        $mail->SMTPAuth   = true;
        $mail->Timeout    = 25;
        $mail->Username   = (string) Env::get('SMTP_USER', '');
        $mail->Password   = (string) Env::get('SMTP_PASS', '');
        $secure = strtolower((string) Env::get('SMTP_SECURE', 'ssl'));
        $mail->SMTPSecure = $secure === 'tls'
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->CharSet = 'UTF-8';
        $fromEmail = (string) Env::get('MAIL_FROM', self::TEAM_NOTIFY_FALLBACK);
        $fromName  = (string) Env::get('MAIL_FROM_NAME', 'PROCOPE Afrique');
        $replyTo   = (string) Env::get('MAIL_REPLY_TO', self::TEAM_NOTIFY_FALLBACK);
        $replyName = (string) Env::get('MAIL_REPLY_TO_NAME', 'PROCOPE Afrique');
        // Envelope / Return-Path = compte authentifié (Gmail l'exige).
        // Le From visible peut être une autre adresse (alias « Envoyer en tant que »).
        $smtpUser = (string) Env::get('SMTP_USER', '');
        if ($smtpUser !== '') {
            $mail->Sender = $smtpUser;
        }
        $mail->setFrom($fromEmail, $fromName);
        $mail->addReplyTo($replyTo, $replyName);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $deliveryHtml = self::htmlForDelivery($html);
        $logoPath = self::logoPath();
        if (is_file($logoPath) && str_contains($deliveryHtml, 'cid:' . self::LOGO_CID)) {
            $mail->addEmbeddedImage($logoPath, self::LOGO_CID, 'logo.png');
        }
        $mail->Body    = $deliveryHtml;
        $mail->AltBody = strip_tags(preg_replace('#<br\s*/?>#i', "\n", $deliveryHtml));
        $mail->send();
    }

    private static function sendNative(string $to, string $subject, string $html): void
    {
        $from = (string) Env::get('MAIL_FROM', self::TEAM_NOTIFY_FALLBACK);
        $fromName = (string) Env::get('MAIL_FROM_NAME', 'PROCOPE Afrique');
        $replyTo = (string) Env::get('MAIL_REPLY_TO', self::TEAM_NOTIFY_FALLBACK);
        $replyName = (string) Env::get('MAIL_REPLY_TO_NAME', 'PROCOPE Afrique');
        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $from . '>',
            'Reply-To: ' . $replyName . ' <' . $replyTo . '>',
        ]);
        if (!mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers)) {
            throw new \RuntimeException('mail() a échoué (SMTP non configuré sur ce serveur)');
        }
    }
}
