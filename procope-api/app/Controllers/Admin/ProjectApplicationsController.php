<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\IncubationCall;
use App\Models\IncubatedProject;
use App\Models\ProjectApplication;
use App\Models\ProjectImage;
use App\Services\Audit;
use App\Services\Exporter;
use App\Services\Mailer;
use App\Services\Pdf;
use App\Services\Uploader;

final class ProjectApplicationsController
{
    public function indexAll(Request $request): void
    {
        $page = max(1, (int) ($request->input('page', '1') ?? 1));
        $filters = $this->filters($request);

        View::render('projets/depots', [
            'title'    => 'Dépôts de projets',
            'filters'  => $filters,
            'calls'    => IncubationCall::allForFilter(),
            'result'   => ProjectApplication::search($filters, $page),
        ]);
    }

    public function show(Request $request): void
    {
        $id = (int) $request->params['id'];
        $application = ProjectApplication::find($id) ?: Response::abort(404, 'Dépôt introuvable');

        if ($application['statut'] === 'nouvelle') {
            ProjectApplication::updateStatus($id, 'en_examen');
            Audit::log('project_application.status', 'project_application', $id, [
                'from' => 'nouvelle',
                'to'   => 'en_examen',
                'mode' => 'auto (première ouverture)',
            ]);
            $application['statut'] = 'en_examen';
        }

        View::render('projets/depot-show', [
            'title'       => 'Dépôt — ' . $application['full_name'],
            'application' => $application,
            'history'     => AuditLog::forEntity('project_application', $id, ['project_application.status']),
        ]);
    }

    public function updateStatus(Request $request): void
    {
        $id = (int) $request->params['id'];
        $application = ProjectApplication::find($id) ?: Response::abort(404, 'Dépôt introuvable');
        $statut = $request->input('statut', '');
        if (!in_array($statut, ProjectApplication::STATUTS, true)) {
            Response::abort(422, 'Statut invalide');
        }

        if ($statut === $application['statut']) {
            flash('success', 'Statut inchangé : le dépôt est déjà « '
                . ProjectApplication::STATUT_LABELS[$statut] . ' ».');
            Response::redirect('/admin/projets/depots/' . $id);
        }

        ProjectApplication::updateStatus($id, $statut);
        Audit::log('project_application.status', 'project_application', $id, [
            'from' => $application['statut'],
            'to'   => $statut,
        ]);

        $notice = $this->sendStatusMail($application, $statut);
        flash('success', 'Statut mis à jour : ' . ProjectApplication::STATUT_LABELS[$statut] . '.' . $notice);
        Response::redirect('/admin/projets/depots/' . $id);
    }

    private function sendStatusMail(array $application, string $statut): string
    {
        $template = match ($statut) {
            'retenue' => 'depot_retenu',
            'refusee' => 'depot_refuse',
            default   => null,
        };
        if ($template === null || !$application['email'] || !Mailer::autoEnabled($template)) {
            return '';
        }

        $call = !empty($application['call_id'])
            ? IncubationCall::find((int) $application['call_id'])
            : null;
        $mailData = [
            'full_name'    => $application['full_name'],
            'project_name' => $application['project_name'] ?? '',
            'call_title'   => $call['title'] ?? '',
            'project'      => $call,
        ];
        $html = Mailer::template($template, $mailData);
        $subject = Mailer::subjectFor($template, $mailData, $statut === 'retenue'
            ? 'Votre dépôt est retenu — PROCOPE Afrique'
            : 'Suite de votre dépôt — PROCOPE Afrique');
        $sent = Mailer::send($application['email'], $subject, $html, $template, null);

        return $sent ? ' E-mail envoyé au porteur.' : '';
    }

    public function fichierPage(Request $request): void
    {
        $id = (int) $request->params['id'];
        $application = ProjectApplication::find($id) ?: Response::abort(404, 'Dépôt introuvable');
        if (!$application['file_path']) {
            flash('error', 'Aucun pitch deck joint à ce dépôt.');
            Response::redirect('/admin/projets/depots/' . $id);
        }

        View::render('projets/fichier-viewer', [
            'title'       => 'Pitch deck — ' . $application['full_name'],
            'application' => $application,
        ]);
    }

    public function fichier(Request $request): void
    {
        $application = ProjectApplication::find((int) $request->params['id'])
            ?: Response::abort(404, 'Dépôt introuvable');
        if (!$application['file_path']) {
            Response::abort(404, 'Aucun fichier joint à ce dépôt.');
        }
        $path = Uploader::pitchFullPath($application['file_path']);
        if (!is_file($path)) {
            Response::abort(404, 'Fichier introuvable sur le serveur.');
        }

        $disposition = ($request->input('download', '') ?? '') === '1' ? 'attachment' : 'inline';
        header('Content-Type: ' . ($application['file_mime'] ?: 'application/pdf'));
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: ' . $disposition . '; filename="pitch-' . (int) $application['id'] . '.pdf"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        readfile($path);
        exit;
    }

    public function exportAll(Request $request): void
    {
        $filters = $this->filters($request);
        Audit::log('project_applications.export', 'incubated_project', null, ['filtres' => $filters ?: 'tous']);
        Exporter::downloadProjectApplications($filters);
    }

    public function exportAllPdf(Request $request): void
    {
        $filters = $this->filters($request);
        Audit::log('project_applications.export_pdf', 'incubated_project', null, ['filtres' => $filters ?: 'tous']);

        $title = $this->pdfTitle($filters['statut'] ?? null);
        if (isset($filters['call_id']) && (int) $filters['call_id'] === -1) {
            $title .= ' — Candidatures spontanées';
        } elseif (!empty($filters['call_id'])) {
            $call = IncubationCall::find((int) $filters['call_id']);
            $title .= $call ? ' — ' . $call['title'] : '';
        } else {
            $title .= ' — Tous les dépôts';
        }
        Pdf::downloadProjectApplications(ProjectApplication::allFiltered($filters), $title);
    }

    private function pdfTitle(?string $statut): string
    {
        return match ($statut) {
            'retenue'   => 'Dépôts retenus',
            'refusee'   => 'Dépôts non retenus',
            'en_examen' => 'Dépôts en examen',
            'nouvelle'  => 'Nouveaux dépôts',
            default     => 'Dépôts de projets',
        };
    }

    private function statutFilter(Request $request): ?string
    {
        $statut = (string) ($request->input('statut', '') ?? '');
        return in_array($statut, ProjectApplication::STATUTS, true) ? $statut : null;
    }

    private function filters(Request $request): array
    {
        $raw = $request->input('call_id', '');
        $callId = null;
        if ($raw !== null && $raw !== '') {
            $callId = (int) $raw;
        }
        return array_filter([
            'call_id' => $callId,
            'statut'  => $this->statutFilter($request),
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /** POST — crée (ou rouvre) la fiche vitrine préremplie depuis un dépôt retenu. */
    public function preparePublish(Request $request): void
    {
        $id = (int) $request->params['id'];
        $application = ProjectApplication::find($id) ?: Response::abort(404, 'Dépôt introuvable');
        if ($application['statut'] !== 'retenue') {
            flash('error', 'Seuls les dépôts retenus peuvent être publiés sur le site.');
            Response::redirect('/admin/projets/depots/' . $id);
        }

        $existing = IncubatedProject::findByApplicationId($id);
        if ($existing) {
            flash('success', 'Fiche déjà préparée : complétez-la puis publiez.');
            Response::redirect('/admin/projets/' . (int) $existing['id'] . '/edit');
        }

        $title = trim((string) ($application['project_name'] ?: $application['full_name']));
        $slug = $this->slugify($title);
        $clash = IncubatedProject::findBySlug($slug);
        if ($clash) {
            $slug .= '-' . $id;
        }

        $projectId = IncubatedProject::create([
            'application_id' => $id,
            'title'          => mb_substr($title, 0, 200),
            'slug'           => mb_substr($slug, 0, 200),
            'pitch'          => $application['pitch'],
            'description'    => $application['message'] ?: $application['pitch'],
            'sector'         => $application['sector'],
            'stage'          => 'idee',
            'country'        => 'Togo',
            'year'           => (int) date('Y'),
            'website'        => null,
            'socials'        => null,
            'is_published'   => 0,
        ]);
        ProjectApplication::linkProject($id, $projectId);
        Audit::log('project.create_from_depot', 'incubated_project', $projectId, [
            'application_id' => $id,
            'title'          => $title,
        ]);
        flash('success', 'Fiche préremplie à partir du dépôt. Complétez les infos et les affiches, puis publiez.');
        Response::redirect('/admin/projets/' . $projectId . '/edit');
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->params['id'];
        $application = ProjectApplication::find($id) ?: Response::abort(404, 'Dépôt introuvable');

        $linked = IncubatedProject::findByApplicationId($id);
        if ($linked) {
            foreach (ProjectImage::allForProject((int) $linked['id']) as $image) {
                Uploader::deleteProjetImage($image['path']);
            }
            IncubatedProject::delete((int) $linked['id']);
        }
        if (!empty($application['file_path'])) {
            Uploader::deletePitch($application['file_path']);
        }
        ProjectApplication::delete($id);
        Audit::log('project_application.delete', 'project_application', $id, [
            'name' => $application['full_name'],
            'linked_project' => $linked['id'] ?? null,
        ]);
        flash('success', 'Dépôt supprimé'
            . ($linked ? ' ainsi que la fiche publique liée.' : '.')
        );
        Response::redirect('/admin/projets/depots');
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-') ?: 'projet-' . time();
    }
}
