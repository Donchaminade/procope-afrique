<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Services\Audit;
use App\Services\Exporter;
use App\Services\JobNotifier;
use App\Services\Mailer;
use App\Services\Pdf;
use App\Services\Uploader;

final class JobApplicationsController
{
    /** GET /admin/emplois/{id}/candidatures — candidatures d'une offre (filtre statut). */
    public function index(Request $request): void
    {
        $offer = JobOffer::find((int) $request->params['id']) ?: Response::abort(404, 'Offre introuvable');
        $page = max(1, (int) ($request->input('page', '1') ?? 1));
        $statut = $this->statutFilter($request);

        View::render('emplois/candidatures', [
            'title'  => 'Candidatures — ' . $offer['title'],
            'offer'  => $offer,
            'statut' => $statut,
            'result' => JobApplication::paginateForOffer((int) $offer['id'], $page, $statut),
        ]);
    }

    /** GET /admin/emplois/candidatures — vue globale toutes offres (filtres offre + statut). */
    public function indexAll(Request $request): void
    {
        $page = max(1, (int) ($request->input('page', '1') ?? 1));
        $filters = $this->filters($request);

        View::render('emplois/candidatures-all', [
            'title'   => 'Toutes les candidatures',
            'filters' => $filters,
            'offers'  => JobOffer::allForFilter(),
            'result'  => JobApplication::search($filters, $page),
        ]);
    }

    /**
     * GET /admin/emplois/candidatures/{id} — détail scindé (infos + CV).
     * À la première ouverture d'une candidature « nouvelle », elle passe
     * automatiquement « en examen » : le dossier est considéré comme pris en
     * charge dès qu'un membre de l'équipe l'a consulté (choix documenté).
     */
    public function show(Request $request): void
    {
        $id = (int) $request->params['id'];
        $application = JobApplication::find($id) ?: Response::abort(404, 'Candidature introuvable');

        if ($application['statut'] === 'nouvelle') {
            JobApplication::updateStatus($id, 'en_examen');
            Audit::log('job_application.status', 'job_application', $id, [
                'from' => 'nouvelle',
                'to'   => 'en_examen',
                'mode' => 'auto (première ouverture)',
            ]);
            $application['statut'] = 'en_examen';
        }

        View::render('emplois/candidature-show', [
            'title'       => 'Candidature — ' . $application['full_name'],
            'application' => $application,
            'history'     => AuditLog::forEntity('job_application', $id, ['job_application.status']),
        ]);
    }

    /**
     * POST /admin/emplois/candidatures/{id}/status — changement de statut.
     * Garde anti-boucle : si le statut soumis est identique au statut actuel,
     * rien n'est modifié et aucun e-mail n'est (re)envoyé.
     */
    public function updateStatus(Request $request): void
    {
        $id = (int) $request->params['id'];
        $application = JobApplication::find($id) ?: Response::abort(404, 'Candidature introuvable');
        $statut = $request->input('statut', '');
        if (!in_array($statut, JobApplication::STATUTS, true)) {
            Response::abort(422, 'Statut invalide');
        }

        if ($statut === $application['statut']) {
            flash('success', 'Statut inchangé : la candidature est déjà « '
                . JobApplication::STATUT_LABELS[$statut] . ' ».');
            Response::redirect('/admin/emplois/candidatures/' . $id);
        }

        JobApplication::updateStatus($id, $statut);
        Audit::log('job_application.status', 'job_application', $id, [
            'from' => $application['statut'],
            'to'   => $statut,
        ]);

        $notice = $this->sendStatusMail($application, $statut);
        flash('success', 'Statut mis à jour : ' . JobApplication::STATUT_LABELS[$statut] . '.' . $notice);
        Response::redirect('/admin/emplois/candidatures/' . $id);
    }

    /**
     * E-mail automatique « retenue » / « refusée » (toggles OFF par défaut).
     * N'est appelé que lorsque le statut CHANGE réellement.
     */
    private function sendStatusMail(array $application, string $statut): string
    {
        $template = match ($statut) {
            'retenue' => 'candidature_retenue',
            'refusee' => 'candidature_refusee',
            default   => null,
        };
        if ($template === null || !$application['email'] || !Mailer::autoEnabled($template)) {
            return '';
        }

        $offer = JobOffer::find((int) $application['offer_id']);
        if (!$offer) {
            return '';
        }
        $mailData = [
            'full_name' => $application['full_name'],
            'offer'     => $offer,
            'cta_url'   => JobNotifier::offerUrl($offer),
        ];
        $html = Mailer::template($template, $mailData);
        $subject = Mailer::subjectFor($template, $mailData, $statut === 'retenue'
            ? 'Votre candidature est retenue — ' . $offer['title']
            : 'Suite de votre candidature — ' . $offer['title']);
        $sent = Mailer::send($application['email'], $subject, $html, $template, null);

        return $sent ? ' E-mail envoyé au candidat.' : '';
    }

    /**
     * GET /admin/emplois/candidatures/{id}/cv — page enveloppe de consultation
     * du CV : barre supérieure (candidat + retour au dossier + téléchargement)
     * et PDF en iframe pleine hauteur. Permet de revenir à la candidature sans
     * perdre la navigation, contrairement au PDF brut.
     */
    public function cvPage(Request $request): void
    {
        $id = (int) $request->params['id'];
        $application = JobApplication::find($id) ?: Response::abort(404, 'Candidature introuvable');
        if (!$application['cv_path']) {
            flash('error', 'Aucun CV joint à cette candidature.');
            Response::redirect('/admin/emplois/candidatures/' . $id);
        }

        View::render('emplois/cv-viewer', [
            'title'       => 'CV — ' . $application['full_name'],
            'application' => $application,
        ]);
    }

    /**
     * GET /admin/emplois/candidatures/{id}/cv/fichier — sert le CV brut
     * (session requise, jamais d'URL publique). SAMEORIGIN : le CV s'affiche
     * dans les iframes de l'écran scindé et de la page enveloppe (l'en-tête
     * global est DENY). `?download=1` force le téléchargement.
     */
    public function cv(Request $request): void
    {
        $application = JobApplication::find((int) $request->params['id'])
            ?: Response::abort(404, 'Candidature introuvable');
        if (!$application['cv_path']) {
            Response::abort(404, 'Aucun CV joint à cette candidature.');
        }
        $path = Uploader::cvFullPath($application['cv_path']);
        if (!is_file($path)) {
            Response::abort(404, 'Fichier de CV introuvable sur le serveur.');
        }

        $disposition = ($request->input('download', '') ?? '') === '1' ? 'attachment' : 'inline';
        header('Content-Type: ' . ($application['cv_mime'] ?: 'application/pdf'));
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: ' . $disposition . '; filename="cv-' . (int) $application['id'] . '.pdf"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        readfile($path);
        exit;
    }

    /** GET /admin/emplois/{id}/candidatures/export — Excel/CSV (filtre statut respecté). */
    public function export(Request $request): void
    {
        $offer = JobOffer::find((int) $request->params['id']) ?: Response::abort(404, 'Offre introuvable');
        $statut = $this->statutFilter($request);
        Audit::log('job_applications.export', 'job_offer', (int) $offer['id'], [
            'title' => $offer['title'], 'statut' => $statut ?? 'tous',
        ]);
        Exporter::downloadApplications(['offer_id' => (int) $offer['id'], 'statut' => $statut]);
    }

    /** GET /admin/emplois/{id}/candidatures/pdf — PDF (filtre statut respecté). */
    public function exportPdf(Request $request): void
    {
        $offer = JobOffer::find((int) $request->params['id']) ?: Response::abort(404, 'Offre introuvable');
        $statut = $this->statutFilter($request);
        Audit::log('job_applications.export_pdf', 'job_offer', (int) $offer['id'], [
            'title' => $offer['title'], 'statut' => $statut ?? 'tous',
        ]);
        $rows = JobApplication::allFiltered(['offer_id' => (int) $offer['id'], 'statut' => $statut]);
        Pdf::downloadApplications($rows, $this->pdfTitle($statut) . ' — ' . $offer['title']);
    }

    /** GET /admin/emplois/candidatures/export — Excel/CSV global (filtres respectés). */
    public function exportAll(Request $request): void
    {
        $filters = $this->filters($request);
        Audit::log('job_applications.export', 'job_offer', null, ['filtres' => $filters ?: 'tous']);
        Exporter::downloadApplications($filters);
    }

    /** GET /admin/emplois/candidatures/pdf — PDF global (filtres respectés). */
    public function exportAllPdf(Request $request): void
    {
        $filters = $this->filters($request);
        Audit::log('job_applications.export_pdf', 'job_offer', null, ['filtres' => $filters ?: 'tous']);

        $title = $this->pdfTitle($filters['statut'] ?? null);
        if (!empty($filters['offer_id'])) {
            $offer = JobOffer::find((int) $filters['offer_id']);
            $title .= $offer ? ' — ' . $offer['title'] : '';
        } else {
            $title .= ' — Toutes les offres';
        }
        Pdf::downloadApplications(JobApplication::allFiltered($filters), $title);
    }

    /** Titre du PDF selon le filtre statut actif. */
    private function pdfTitle(?string $statut): string
    {
        return match ($statut) {
            'retenue'   => 'Candidats retenus',
            'refusee'   => 'Candidatures non retenues',
            'en_examen' => 'Candidatures en examen',
            'nouvelle'  => 'Nouvelles candidatures',
            default     => 'Candidatures',
        };
    }

    /** Filtre statut whitelisté depuis la query string (null = tous). */
    private function statutFilter(Request $request): ?string
    {
        $statut = (string) ($request->input('statut', '') ?? '');
        return in_array($statut, JobApplication::STATUTS, true) ? $statut : null;
    }

    /** Filtres de la vue globale : offer_id + statut. */
    private function filters(Request $request): array
    {
        return array_filter([
            'offer_id' => (int) ($request->input('offer_id', '0') ?? 0) ?: null,
            'statut'   => $this->statutFilter($request),
        ]);
    }
}
