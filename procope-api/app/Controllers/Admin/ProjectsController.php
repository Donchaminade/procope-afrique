<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\IncubatedProject;
use App\Models\ProjectImage;
use App\Services\Audit;
use App\Services\Mailer;
use App\Services\ProjectNotifier;
use App\Services\Uploader;

final class ProjectsController
{
    public function index(Request $request): void
    {
        $page = max(1, (int) ($request->input('page', '1') ?? 1));
        View::render('projets/index', [
            'title'  => 'Projets incubés',
            'result' => IncubatedProject::paginate($page),
        ]);
    }

    public function create(Request $request): void
    {
        View::render('projets/form', [
            'title'   => 'Nouveau projet',
            'project' => null,
        ]);
    }

    public function edit(Request $request): void
    {
        $project = IncubatedProject::find((int) $request->params['id'])
            ?: Response::abort(404, 'Projet introuvable');
        View::render('projets/form', [
            'title'             => 'Modifier le projet',
            'project'           => $project,
            'images'            => ProjectImage::allForProject((int) $project['id']),
            'recipients'        => count(ProjectNotifier::recipients()),
            'sourceApplication' => !empty($project['application_id'])
                ? \App\Models\ProjectApplication::find((int) $project['application_id'])
                : null,
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validated($request);
        $id = IncubatedProject::create($data);
        $imageNotice = $this->handleImageUploads($request, $id);
        Audit::log('project.create', 'incubated_project', $id, ['title' => $data['title']]);

        $announced = '';
        if ($data['is_published'] === 1 && Mailer::autoEnabled('projet_publie')) {
            $project = IncubatedProject::find($id);
            [$sent, $total] = ProjectNotifier::sendPublication($project);
            Audit::log('project.announce', 'incubated_project', $id, [
                'mode' => 'auto', 'destinataires' => $total, 'envoyes' => $sent,
            ]);
            if ($total > 0) {
                $announced = " Annonce envoyée à $sent destinataire" . ($sent > 1 ? 's' : '') . " sur $total.";
            }
        }

        flash('success', 'Projet créé.' . $imageNotice . $announced);
        Response::redirect('/admin/projets');
    }

    private function handleImageUploads(Request $request, int $projectId): string
    {
        $files = $request->files('images');
        if (!$files) {
            return '';
        }
        $stored = 0;
        $errors = [];
        foreach ($files as $file) {
            try {
                $image = Uploader::storeProjetImage($file);
                ProjectImage::create($projectId, $image['path'], $image['mime']);
                $stored++;
            } catch (\RuntimeException $e) {
                $errors[] = ((string) ($file['name'] ?? 'fichier')) . ' : ' . $e->getMessage();
            }
        }
        ProjectImage::ensureMain($projectId);
        if ($stored > 0) {
            Audit::log('project.images_add', 'incubated_project', $projectId, ['ajoutees' => $stored]);
        }
        $notice = $stored > 0 ? " $stored affiche" . ($stored > 1 ? 's' : '') . ' ajoutée' . ($stored > 1 ? 's' : '') . '.' : '';
        if ($errors) {
            $notice .= ' Refusée(s) — ' . implode(' · ', $errors);
        }
        return $notice;
    }

    public function uploadImages(Request $request): void
    {
        $id = (int) $request->params['id'];
        IncubatedProject::find($id) ?: Response::abort(404, 'Projet introuvable');
        $notice = $this->handleImageUploads($request, $id);
        if ($notice === '') {
            flash('error', 'Aucun fichier sélectionné.');
        } elseif (str_contains($notice, 'Refusée')) {
            flash('error', trim($notice));
        } else {
            flash('success', trim($notice));
        }
        Response::redirect("/admin/projets/$id/edit");
    }

    public function setMainImage(Request $request): void
    {
        $id = (int) $request->params['id'];
        $imageId = (int) $request->params['img'];
        $image = ProjectImage::find($imageId);
        if (!$image || (int) $image['project_id'] !== $id) {
            Response::abort(404, 'Affiche introuvable');
        }
        ProjectImage::setMain($id, $imageId);
        Audit::log('project.image_main', 'incubated_project', $id, ['image_id' => $imageId]);
        flash('success', 'Affiche principale mise à jour.');
        Response::redirect("/admin/projets/$id/edit");
    }

    public function deleteImage(Request $request): void
    {
        $id = (int) $request->params['id'];
        $imageId = (int) $request->params['img'];
        $image = ProjectImage::find($imageId);
        if (!$image || (int) $image['project_id'] !== $id) {
            Response::abort(404, 'Affiche introuvable');
        }
        ProjectImage::delete($imageId);
        Uploader::deleteProjetImage($image['path']);
        ProjectImage::ensureMain($id);
        Audit::log('project.image_delete', 'incubated_project', $id, ['image_id' => $imageId]);
        flash('success', 'Affiche supprimée.');
        Response::redirect("/admin/projets/$id/edit");
    }

    public function update(Request $request): void
    {
        $id = (int) $request->params['id'];
        $project = IncubatedProject::find($id) ?: Response::abort(404, 'Projet introuvable');
        $data = $this->validated($request, $id, $project);
        IncubatedProject::update($id, $data);
        Audit::log('project.update', 'incubated_project', $id, ['title' => $data['title']]);

        $notice = '';
        $wasPublished = (int) $project['is_published'] === 1;
        $nowPublished = $data['is_published'] === 1;
        if (!$wasPublished && $nowPublished) {
            $notice = $this->autoAnnounce($id);
        }

        flash('success', 'Projet mis à jour.' . $notice);
        Response::redirect('/admin/projets');
    }

    private function autoAnnounce(int $id): string
    {
        if (!Mailer::autoEnabled('projet_publie')) {
            return '';
        }
        $project = IncubatedProject::find($id);
        [$sent, $total] = ProjectNotifier::sendPublication($project);
        Audit::log('project.announce', 'incubated_project', $id, [
            'mode' => 'auto', 'destinataires' => $total, 'envoyes' => $sent,
        ]);
        return $total > 0 ? " Annonce envoyée à $sent destinataire" . ($sent > 1 ? 's' : '') . " sur $total." : '';
    }

    public function publish(Request $request): void
    {
        $id = (int) $request->params['id'];
        $project = IncubatedProject::find($id) ?: Response::abort(404, 'Projet introuvable');
        $publish = !(int) $project['is_published'];
        if ($publish && !$this->canPublish($project)) {
            flash('error', 'Impossible de publier : le dépôt source n\'est pas retenu (ou a été refusé).');
            Response::redirect('/admin/projets');
        }
        IncubatedProject::setPublished($id, $publish);
        Audit::log($publish ? 'project.publish' : 'project.unpublish', 'incubated_project', $id, [
            'title' => $project['title'],
        ]);

        $notice = $publish ? $this->autoAnnounce($id) : '';
        flash('success', ($publish ? 'Projet publié.' : 'Projet dépublié.') . $notice);
        Response::redirect('/admin/projets');
    }

    public function archive(Request $request): void
    {
        $id = (int) $request->params['id'];
        $project = IncubatedProject::find($id) ?: Response::abort(404, 'Projet introuvable');
        if (!empty($project['archived_at'])) {
            flash('error', 'Ce projet est déjà archivé.');
            Response::redirect('/admin/projets');
        }
        IncubatedProject::archive($id);
        Audit::log('project.archive', 'incubated_project', $id, ['title' => $project['title']]);
        flash('success', 'Projet archivé (et dépublié). Retrouvez-le avec ses dépôts dans les Archives.');
        Response::redirect('/admin/projets');
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->params['id'];
        $project = IncubatedProject::find($id) ?: Response::abort(404, 'Projet introuvable');

        foreach (ProjectImage::allForProject($id) as $image) {
            Uploader::deleteProjetImage($image['path']);
        }
        IncubatedProject::delete($id);
        Audit::log('project.delete', 'incubated_project', $id, ['title' => $project['title']]);
        flash('success', 'Projet supprimé. Le dépôt source, s\'il existe, reste consultable.');
        Response::redirect('/admin/projets');
    }

    public function announce(Request $request): void
    {
        $id = (int) $request->params['id'];
        $project = IncubatedProject::find($id) ?: Response::abort(404, 'Projet introuvable');
        $return = $request->input('return') === 'automations'
            ? '/admin/automations'
            : "/admin/projets/$id/edit";

        if (!(int) $project['is_published'] || !empty($project['archived_at'])) {
            flash('error', 'Impossible d\'annoncer un projet non publié.');
            Response::redirect($return);
        }

        [$sent, $total, $lastError] = ProjectNotifier::sendPublication($project, true);
        Audit::log('project.announce', 'incubated_project', $id, [
            'mode' => 'manuel', 'destinataires' => $total, 'envoyes' => $sent,
        ]);

        if ($total === 0) {
            flash('error', 'Aucun destinataire avec e-mail : aucune annonce envoyée.');
        } elseif ($sent === $total) {
            flash('success', "Annonce du projet envoyée à $sent destinataire" . ($sent > 1 ? 's' : '') . '.');
        } else {
            flash('error', "Annonce envoyée à $sent destinataire" . ($sent > 1 ? 's' : '')
                . " sur $total. Dernière erreur : " . ($lastError ?? 'inconnue'));
        }
        Response::redirect($return);
    }

    private function canPublish(array $project): bool
    {
        if (empty($project['application_id'])) {
            return true;
        }
        $application = \App\Models\ProjectApplication::find((int) $project['application_id']);
        return $application && $application['statut'] === 'retenue';
    }

    private function validated(Request $request, ?int $id = null, ?array $existing = null): array
    {
        $redirect = $id ? "/admin/projets/$id/edit" : '/admin/projets/create';

        $title = $request->input('title', '');
        if ($title === '') {
            flash('error', 'Le titre est obligatoire.');
            Response::redirect($redirect);
        }

        $stage = $request->input('stage', 'idee');
        if (!in_array($stage, IncubatedProject::STAGES, true)) {
            $stage = 'idee';
        }

        $yearRaw = trim((string) ($request->input('year', '') ?? ''));
        $year = null;
        if ($yearRaw !== '') {
            $year = (int) $yearRaw;
            if ($year < 1990 || $year > 2100) {
                flash('error', 'L\'année doit être comprise entre 1990 et 2100.');
                Response::redirect($redirect);
            }
        }

        $slug = $this->slugify($request->input('slug', '') ?: $title);
        $slugClash = IncubatedProject::findBySlug($slug);
        if ($slugClash && (int) $slugClash['id'] !== (int) $id) {
            $slug .= '-' . time();
        }

        $website = trim((string) ($request->input('website', '') ?? ''));
        if ($website !== '' && !preg_match('#^https?://#i', $website)) {
            $website = 'https://' . $website;
        }

        $data = [
            'title'        => mb_substr($title, 0, 200),
            'slug'         => mb_substr($slug, 0, 200),
            'pitch'        => $request->input('pitch') ?: null,
            'description'  => $request->input('description') ?: null,
            'sector'       => mb_substr($request->input('sector', '') ?? '', 0, 190) ?: null,
            'stage'        => $stage,
            'country'      => mb_substr($request->input('country', '') ?? '', 0, 120) ?: null,
            'year'         => $year,
            'website'      => mb_substr($website, 0, 255) ?: null,
            'socials'      => $request->input('socials') ?: null,
            'is_published' => $request->input('is_published') === '1' ? 1 : 0,
        ];
        if ($data['is_published'] === 1 && $existing && !$this->canPublish($existing)) {
            flash('error', 'Impossible de publier : le dépôt source n\'est pas retenu.');
            Response::redirect($redirect);
        }
        return $data;
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-') ?: 'projet-' . time();
    }
}
