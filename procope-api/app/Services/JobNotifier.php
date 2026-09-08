<?php

namespace App\Services;

use App\Core\Env;
use App\Models\Inscription;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\JobOfferImage;
use App\Models\ProjectApplication;
use App\Models\Setting;

/**
 * E-mails automatiques du module Offres d'emploi :
 * publication (auto_offre_publiee), rappel J-5 (auto_offre_rappel) et
 * prolongation (auto_offre_prolongee). Chaque envoi passe par Mailer
 * (mail_logs + master switch) ; le mode manuel (force) utilise sendNow.
 */
final class JobNotifier
{
    /** Verrou anti-concurrence du traitement des rappels (clé settings). */
    private const LOCK_KEY = 'job_reminder_lock';
    private const LOCK_TTL_SECONDS = 300;

    /**
     * Destinataires « tout le monde » : e-mails DISTINCTS des inscriptions
     * aux formations (tous statuts sauf « refuse » — voir Inscription::allEmails)
     * + des candidatures aux offres d'emploi.
     */
    public static function recipients(): array
    {
        $emails = array_merge(
            Inscription::allEmails(),
            JobApplication::allEmails(),
            ProjectApplication::allEmails()
        );
        $emails = array_values(array_unique(array_map('mb_strtolower', $emails)));
        sort($emails);
        return $emails;
    }

    /** URL publique de la page de détail d'une offre. */
    public static function offerUrl(array $offer): string
    {
        return rtrim((string) Env::get('SITE_URL', 'https://procopeafrique.org'), '/')
            . '/offres-emploi.html#' . rawurlencode((string) $offer['slug']);
    }

    /** URL absolue de l'affiche principale d'une offre (null si aucune image). */
    public static function mainImageUrl(array $offer): ?string
    {
        $main = JobOfferImage::mainForOffer((int) $offer['id']);
        return $main
            ? rtrim((string) Env::get('APP_URL', ''), '/') . '/uploads/offres/' . rawurlencode((string) $main['path'])
            : null;
    }

    /** Données communes des e-mails d'une offre (affiche principale incluse). */
    private static function mailData(array $offer): array
    {
        return [
            'offer'       => $offer,
            'cta_url'     => self::offerUrl($offer),
            'affiche_url' => self::mainImageUrl($offer),
        ];
    }

    /**
     * Annonce « nouvelle offre publiée » à tout le monde.
     * $force = true (bouton Exécuter) : ignore master switch et toggle.
     * Retourne [envoyés, destinataires, dernière erreur éventuelle].
     */
    public static function sendPublication(array $offer, bool $force = false): array
    {
        $data = self::mailData($offer);
        $html = Mailer::template('offre', $data);
        $subject = Mailer::subjectFor('offre', $data, "Nouvelle offre d'emploi — " . $offer['title']);
        return self::broadcast($subject, $html, 'offre_publiee', $force);
    }

    /** Rappel « clôture dans X jours » à tout le monde. */
    public static function sendReminder(array $offer, bool $force = false): array
    {
        $daysLeft = max(0, (int) ceil((strtotime((string) $offer['closes_at']) - time()) / 86400));
        $data = self::mailData($offer) + ['jours_restants' => $daysLeft];
        $html = Mailer::template('offre_rappel', $data);
        $subject = Mailer::subjectFor('offre_rappel', $data,
            'Derniers jours pour postuler — ' . $offer['title']);
        return self::broadcast($subject, $html, 'offre_rappel', $force);
    }

    /** Annonce « offre prolongée » à tout le monde. */
    public static function sendProlongation(array $offer, bool $force = false): array
    {
        $data = self::mailData($offer);
        $html = Mailer::template('offre_prolongee', $data);
        $subject = Mailer::subjectFor('offre_prolongee', $data,
            'Offre prolongée — ' . $offer['title']);
        return self::broadcast($subject, $html, 'offre_prolongee', $force);
    }

    /** Envoie à tous les destinataires. Retourne [envoyés, total, dernière erreur]. */
    private static function broadcast(string $subject, string $html, string $type, bool $force): array
    {
        $recipients = self::recipients();
        $sent = 0;
        $lastError = null;
        foreach ($recipients as $to) {
            if ($force) {
                $error = Mailer::sendNow($to, $subject, $html, $type, null);
                if ($error === null) {
                    $sent++;
                } else {
                    $lastError = $error;
                }
            } elseif (Mailer::send($to, $subject, $html, $type, null)) {
                $sent++;
            }
        }
        return [$sent, count($recipients), $lastError];
    }

    /**
     * Traite les rappels J-5 dus (cron bin/run-automations.php + déclenchement
     * opportuniste de l'API). reminder_sent_at est horodaté AVANT l'envoi
     * (anti-doublon même en cas de relance concurrente).
     * $force = true (bouton Exécuter) : ignore les toggles.
     * Retourne la liste [titre => [envoyés, total]] des offres traitées.
     */
    public static function processDueReminders(bool $force = false): array
    {
        if (!$force && !Mailer::autoEnabled('offre_rappel')) {
            return [];
        }
        if (!self::acquireLock()) {
            return [];
        }

        $processed = [];
        try {
            foreach (JobOffer::dueForReminder() as $offer) {
                JobOffer::markReminderSent((int) $offer['id']);
                [$sent, $total] = self::sendReminder($offer, $force);
                $processed[$offer['title']] = [$sent, $total];
                Audit::log('job_offer.reminder', 'job_offer', (int) $offer['id'], [
                    'mode'          => $force ? 'manuel' : 'auto',
                    'destinataires' => $total,
                    'envoyes'       => $sent,
                ]);
            }
        } finally {
            self::releaseLock();
        }
        return $processed;
    }

    /** Verrou simple en settings (TTL 5 min) contre les traitements concurrents. */
    private static function acquireLock(): bool
    {
        $current = (int) (Setting::get(self::LOCK_KEY, '0') ?? 0);
        if ($current > time() - self::LOCK_TTL_SECONDS) {
            return false;
        }
        Setting::set(self::LOCK_KEY, (string) time());
        return true;
    }

    private static function releaseLock(): void
    {
        Setting::set(self::LOCK_KEY, '0');
    }
}
