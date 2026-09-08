<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Formation;
use App\Models\Inscription;
use App\Models\IncubationCall;
use App\Models\IncubatedProject;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\ProjectApplication;
use App\Services\Audit;
use App\Services\Exporter;
use App\Services\Pdf;

/** Archives des formations et des offres d'emploi : consultation, exports et restauration. */
final class ArchivesController
{
    public function index(Request $request): void
    {
        $page = max(1, (int) ($request->input('page', '1') ?? 1));
        View::render('archives/index', [
            'title'          => 'Archives',
            'result'         => Formation::archivedPaginate($page),
            'archivedOffers'   => JobOffer::archived(),
            'archivedProjects' => IncubatedProject::archived(),
            'archivedCalls'    => IncubationCall::archived(),
        ]);
    }

    public function show(Request $request): void
    {
        $formation = Formation::find((int) $request->params['id']);
        if (!$formation || empty($formation['archived_at'])) {
            Response::abort(404, 'Formation archivée introuvable');
        }

        View::render('archives/show', [
            'title'        => 'Archive — ' . $formation['titre'],
            'formation'    => $formation,
            'slots'        => Formation::slots((int) $formation['id']),
            'inscriptions' => Inscription::allFiltered(['formation_id' => (int) $formation['id']]),
        ]);
    }

    /** POST /admin/archives/{id}/restore — désarchive (sans rouvrir les inscriptions). */
    public function restore(Request $request): void
    {
        $id = (int) $request->params['id'];
        $formation = Formation::find($id);
        if (!$formation || empty($formation['archived_at'])) {
            Response::abort(404, 'Formation archivée introuvable');
        }
        Formation::unarchive($id);
        Audit::log('formation.unarchive', 'formation', $id, ['titre' => $formation['titre']]);
        flash('success', 'Formation restaurée. Elle réapparaît dans la liste des formations (inscriptions fermées).');
        Response::redirect('/admin/formations');
    }

    /** Offre archivée depuis l'URL, sinon 404. */
    private function archivedOfferOr404(Request $request): array
    {
        $offer = JobOffer::find((int) $request->params['id']);
        if (!$offer || empty($offer['archived_at'])) {
            Response::abort(404, 'Offre archivée introuvable');
        }
        return $offer;
    }

    /** GET /admin/archives/emplois/{id} — détail : infos + candidats et statuts. */
    public function showOffer(Request $request): void
    {
        $offer = $this->archivedOfferOr404($request);
        View::render('archives/emploi-show', [
            'title'        => 'Archive — ' . $offer['title'],
            'offer'        => $offer,
            'applications' => JobApplication::allForOffer((int) $offer['id']),
        ]);
    }

    /** GET /admin/archives/emplois/{id}/export — Excel/CSV depuis l'archive (filtre statut). */
    public function exportOffer(Request $request): void
    {
        $offer = $this->archivedOfferOr404($request);
        $statut = (string) ($request->input('statut', '') ?? '');
        $statut = in_array($statut, JobApplication::STATUTS, true) ? $statut : null;
        Audit::log('job_applications.export', 'job_offer', (int) $offer['id'], [
            'title' => $offer['title'], 'statut' => $statut ?? 'tous', 'source' => 'archive',
        ]);
        Exporter::downloadApplications(['offer_id' => (int) $offer['id'], 'statut' => $statut]);
    }

    /** GET /admin/archives/emplois/{id}/pdf — PDF depuis l'archive (filtre statut). */
    public function exportOfferPdf(Request $request): void
    {
        $offer = $this->archivedOfferOr404($request);
        $statut = (string) ($request->input('statut', '') ?? '');
        $statut = in_array($statut, JobApplication::STATUTS, true) ? $statut : null;
        Audit::log('job_applications.export_pdf', 'job_offer', (int) $offer['id'], [
            'title' => $offer['title'], 'statut' => $statut ?? 'tous', 'source' => 'archive',
        ]);
        $rows = JobApplication::allFiltered(['offer_id' => (int) $offer['id'], 'statut' => $statut]);
        $title = ($statut === 'retenue' ? 'Candidats retenus' : 'Candidatures') . ' — ' . $offer['title'];
        Pdf::downloadApplications($rows, $title);
    }

    /** POST /admin/archives/emplois/{id}/restore — désarchive (sans republier). */
    public function restoreOffer(Request $request): void
    {
        $offer = $this->archivedOfferOr404($request);
        $id = (int) $offer['id'];
        JobOffer::unarchive($id);
        Audit::log('job_offer.unarchive', 'job_offer', $id, ['title' => $offer['title']]);
        flash('success', 'Offre restaurée. Elle réapparaît dans la liste des offres (non publiée).');
        Response::redirect('/admin/emplois');
    }

    private function archivedProjectOr404(Request $request): array
    {
        $project = IncubatedProject::find((int) $request->params['id']);
        if (!$project || empty($project['archived_at'])) {
            Response::abort(404, 'Projet archivé introuvable');
        }
        return $project;
    }

    public function showProject(Request $request): void
    {
        $project = $this->archivedProjectOr404($request);
        View::render('archives/projet-show', [
            'title'        => 'Archive — ' . $project['title'],
            'project'      => $project,
            'applications' => ProjectApplication::allForProject((int) $project['id']),
        ]);
    }

    public function exportProject(Request $request): void
    {
        $project = $this->archivedProjectOr404($request);
        $statut = (string) ($request->input('statut', '') ?? '');
        $statut = in_array($statut, ProjectApplication::STATUTS, true) ? $statut : null;
        Audit::log('project_applications.export', 'incubated_project', (int) $project['id'], [
            'title' => $project['title'], 'statut' => $statut ?? 'tous', 'source' => 'archive',
        ]);
        Exporter::downloadProjectApplications(['project_id' => (int) $project['id'], 'statut' => $statut]);
    }

    public function exportProjectPdf(Request $request): void
    {
        $project = $this->archivedProjectOr404($request);
        $statut = (string) ($request->input('statut', '') ?? '');
        $statut = in_array($statut, ProjectApplication::STATUTS, true) ? $statut : null;
        Audit::log('project_applications.export_pdf', 'incubated_project', (int) $project['id'], [
            'title' => $project['title'], 'statut' => $statut ?? 'tous', 'source' => 'archive',
        ]);
        $rows = ProjectApplication::allFiltered(['project_id' => (int) $project['id'], 'statut' => $statut]);
        $title = ($statut === 'retenue' ? 'Dépôts retenus' : 'Dépôts') . ' — ' . $project['title'];
        Pdf::downloadProjectApplications($rows, $title);
    }

    public function restoreProject(Request $request): void
    {
        $project = $this->archivedProjectOr404($request);
        $id = (int) $project['id'];
        IncubatedProject::unarchive($id);
        Audit::log('project.unarchive', 'incubated_project', $id, ['title' => $project['title']]);
        flash('success', 'Projet restauré. Il réapparaît dans la liste des projets (non publié).');
        Response::redirect('/admin/projets');
    }

    public function restoreCall(Request $request): void
    {
        $call = IncubationCall::find((int) $request->params['id']);
        if (!$call || empty($call['archived_at'])) {
            Response::abort(404, 'Appel archivé introuvable');
        }
        IncubationCall::unarchive((int) $call['id']);
        Audit::log('incubation_call.unarchive', 'incubation_call', (int) $call['id'], [
            'title' => $call['title'],
        ]);
        flash('success', 'Appel restauré. Il réapparaît dans la liste (non publié).');
        Response::redirect('/admin/appels');
    }
}
