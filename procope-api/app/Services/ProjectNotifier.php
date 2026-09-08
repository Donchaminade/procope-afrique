<?php

namespace App\Services;

use App\Core\Env;
use App\Models\IncubatedProject;
use App\Models\Inscription;
use App\Models\JobApplication;
use App\Models\ProjectApplication;
use App\Models\ProjectImage;

/**
 * E-mails du module Projets incubés : publication (auto_projet_publie).
 * Destinataires = communauté (inscrits + candidats emploi + dépôts projets).
 */
final class ProjectNotifier
{
    /**
     * Destinataires communauté : inscriptions + candidatures emploi + dépôts
     * de projets, e-mails DISTINCTS (minuscules).
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

    public static function projectUrl(array $project): string
    {
        return rtrim((string) Env::get('SITE_URL', 'https://procopeafrique.org'), '/')
            . '/projets.html#' . rawurlencode((string) $project['slug']);
    }

    public static function mainImageUrl(array $project): ?string
    {
        $main = ProjectImage::mainForProject((int) $project['id']);
        return $main
            ? rtrim((string) Env::get('APP_URL', ''), '/') . '/uploads/projets/' . rawurlencode((string) $main['path'])
            : null;
    }

    /**
     * Annonce « nouveau projet publié » à toute la communauté.
     * $force = true (bouton Exécuter) : ignore master switch et toggle.
     * @return array{0:int,1:int,2:?string}
     */
    public static function sendPublication(array $project, bool $force = false): array
    {
        $data = [
            'project'     => $project,
            'cta_url'     => self::projectUrl($project),
            'affiche_url' => self::mainImageUrl($project),
        ];
        $html = Mailer::template('projet_publie', $data);
        $subject = Mailer::subjectFor('projet_publie', $data, 'Nouveau projet incubé — ' . $project['title']);

        $recipients = self::recipients();
        $sent = 0;
        $lastError = null;
        foreach ($recipients as $to) {
            if ($force) {
                $error = Mailer::sendNow($to, $subject, $html, 'projet_publie', null);
                if ($error === null) {
                    $sent++;
                } else {
                    $lastError = $error;
                }
            } elseif (Mailer::send($to, $subject, $html, 'projet_publie', null)) {
                $sent++;
            }
        }
        return [$sent, count($recipients), $lastError];
    }
}
