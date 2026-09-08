<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Formation;
use App\Models\FormationGallery;
use App\Models\FormationGalleryImage;
use App\Services\Audit;
use App\Services\Uploader;

/** CRUD des albums photos de formations. */
final class GalleriesController
{
    public function index(Request $request): void
    {
        $page = max(1, (int) ($request->input('page', '1') ?? 1));
        View::render('galeries/index', [
            'title'  => 'Galeries',
            'result' => FormationGallery::paginate($page),
        ]);
    }

    public function create(Request $request): void
    {
        View::render('galeries/form', [
            'title'       => 'Nouvel album',
            'gallery'     => null,
            'images'      => [],
            'formations'  => Formation::allForGallerySelect(),
        ]);
    }

    public function edit(Request $request): void
    {
        $gallery = FormationGallery::find((int) $request->params['id'])
            ?: Response::abort(404, 'Album introuvable');
        View::render('galeries/form', [
            'title'      => 'Modifier l\'album',
            'gallery'    => $gallery,
            'images'     => FormationGalleryImage::allForGallery((int) $gallery['id']),
            'formations' => Formation::allForGallerySelect(),
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validated($request);
        if ($data['kind'] === FormationGallery::KIND_AFFICHE && !$request->files('images')) {
            flash('error', 'L\'image principale de l\'affiche est obligatoire.');
            Response::redirect('/admin/galeries/create');
        }
        $id = FormationGallery::create($data);
        $imageNotice = $this->handleImageUploads($request, $id);
        if ($data['kind'] === FormationGallery::KIND_AFFICHE && !FormationGalleryImage::allForGallery($id)) {
            FormationGallery::delete($id);
            flash('error', 'L\'image principale de l\'affiche est obligatoire.');
            Response::redirect('/admin/galeries/create');
        }
        Audit::log('gallery.create', 'formation_gallery', $id, [
            'title' => $data['title'],
            'year'  => $data['year'],
            'kind'  => $data['kind'],
        ]);
        $label = $data['kind'] === FormationGallery::KIND_AFFICHE ? 'Affiche créée.' : 'Album créé.';
        flash('success', $label . $imageNotice);
        Response::redirect('/admin/galeries');
    }

    public function update(Request $request): void
    {
        $id = (int) $request->params['id'];
        FormationGallery::find($id) ?: Response::abort(404, 'Album introuvable');
        $data = $this->validated($request, $id);
        FormationGallery::update($id, $data);
        Audit::log('gallery.update', 'formation_gallery', $id, [
            'title' => $data['title'],
            'year'  => $data['year'],
        ]);
        flash('success', $data['kind'] === FormationGallery::KIND_AFFICHE ? 'Affiche mise à jour.' : 'Album mis à jour.');
        Response::redirect('/admin/galeries/' . $id . '/edit');
    }

    public function uploadImages(Request $request): void
    {
        $id = (int) $request->params['id'];
        FormationGallery::find($id) ?: Response::abort(404, 'Album introuvable');
        $notice = $this->handleImageUploads($request, $id);
        if ($notice === '') {
            flash('error', 'Aucun fichier sélectionné.');
        } elseif (str_contains($notice, 'Refusée')) {
            flash('error', trim($notice));
        } else {
            flash('success', trim($notice));
        }
        Response::redirect("/admin/galeries/$id/edit");
    }

    public function deleteImage(Request $request): void
    {
        $id = (int) $request->params['id'];
        $imageId = (int) $request->params['img'];
        $gallery = FormationGallery::find($id) ?: Response::abort(404, 'Album introuvable');
        $image = FormationGalleryImage::find($imageId);
        if (!$image || (int) $image['gallery_id'] !== $id) {
            Response::abort(404, 'Photo introuvable');
        }
        $remaining = FormationGalleryImage::allForGallery($id);
        if (($gallery['kind'] ?? '') === FormationGallery::KIND_AFFICHE && count($remaining) <= 1) {
            flash('error', 'Une affiche doit garder au moins une image principale.');
            Response::redirect("/admin/galeries/$id/edit");
        }
        FormationGalleryImage::delete($imageId);
        Uploader::deleteGalerieImage($image['path']);
        Audit::log('gallery.image_delete', 'formation_gallery', $id, ['image_id' => $imageId]);
        flash('success', 'Photo supprimée.');
        Response::redirect("/admin/galeries/$id/edit");
    }

    public function moveImageUp(Request $request): void
    {
        $this->moveImage($request, -1);
    }

    public function moveImageDown(Request $request): void
    {
        $this->moveImage($request, 1);
    }

    public function publish(Request $request): void
    {
        $id = (int) $request->params['id'];
        $gallery = FormationGallery::find($id) ?: Response::abort(404, 'Album introuvable');
        $publish = !(int) $gallery['is_published'];
        FormationGallery::setPublished($id, $publish);
        Audit::log($publish ? 'gallery.publish' : 'gallery.unpublish', 'formation_gallery', $id, [
            'title' => $gallery['title'],
        ]);
        flash('success', $publish ? 'Album publié sur le site.' : 'Album dépublié.');
        Response::redirect('/admin/galeries');
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->params['id'];
        $gallery = FormationGallery::find($id) ?: Response::abort(404, 'Album introuvable');
        foreach (FormationGalleryImage::allForGallery($id) as $image) {
            Uploader::deleteGalerieImage($image['path']);
        }
        FormationGallery::delete($id);
        Audit::log('gallery.delete', 'formation_gallery', $id, ['title' => $gallery['title']]);
        flash('success', 'Album supprimé.');
        Response::redirect('/admin/galeries');
    }

    private function moveImage(Request $request, int $direction): void
    {
        $id = (int) $request->params['id'];
        $imageId = (int) $request->params['img'];
        $image = FormationGalleryImage::find($imageId);
        if (!$image || (int) $image['gallery_id'] !== $id) {
            Response::abort(404, 'Photo introuvable');
        }
        FormationGalleryImage::move($id, $imageId, $direction);
        Audit::log('gallery.image_reorder', 'formation_gallery', $id, [
            'image_id'  => $imageId,
            'direction' => $direction < 0 ? 'up' : 'down',
        ]);
        Response::redirect("/admin/galeries/$id/edit");
    }

    private function handleImageUploads(Request $request, int $galleryId): string
    {
        $files = $request->files('images');
        if (!$files) {
            return '';
        }
        $stored = 0;
        $errors = [];
        foreach ($files as $file) {
            try {
                $image = Uploader::storeGalerieImage($file);
                FormationGalleryImage::create($galleryId, $image['path'], $image['mime']);
                $stored++;
            } catch (\RuntimeException $e) {
                $errors[] = ((string) ($file['name'] ?? 'fichier')) . ' : ' . $e->getMessage();
            }
        }
        if ($stored > 0) {
            Audit::log('gallery.images_add', 'formation_gallery', $galleryId, ['ajoutees' => $stored]);
        }
        $notice = $stored > 0
            ? " $stored photo" . ($stored > 1 ? 's' : '') . ' ajoutée' . ($stored > 1 ? 's' : '') . '.'
            : '';
        if ($errors) {
            $notice .= ' Refusée(s) — ' . implode(' · ', $errors);
        }
        return $notice;
    }

    /** @return array{formation_id:?int,title:string,year:int,month:?int,description:?string,kind:string,is_published:int} */
    private function validated(Request $request, ?int $id = null): array
    {
        $redirect = $id ? "/admin/galeries/$id/edit" : '/admin/galeries/create';

        $kind = FormationGallery::normalizeKind($request->input('kind', FormationGallery::KIND_PHOTOS));

        $formationIdRaw = trim((string) ($request->input('formation_id', '') ?? ''));
        $formationId = $formationIdRaw !== '' ? (int) $formationIdRaw : null;
        $formation = null;
        if ($formationId) {
            $formation = Formation::find($formationId);
            if (!$formation) {
                flash('error', 'La formation sélectionnée est introuvable.');
                Response::redirect($redirect);
            }
        }

        $title = trim((string) ($request->input('title', '') ?? ''));
        if ($title === '' && $formation) {
            $title = (string) $formation['titre'];
        }
        if ($title === '') {
            flash('error', $kind === FormationGallery::KIND_AFFICHE
                ? 'Le titre de l\'affiche est obligatoire (ou choisissez une formation).'
                : 'Le titre de l\'album est obligatoire (ou choisissez une formation).');
            Response::redirect($redirect);
        }

        $yearRaw = trim((string) ($request->input('year', '') ?? ''));
        $year = $yearRaw !== '' ? (int) $yearRaw : 0;
        if ($year < 1990 && $formation) {
            $hint = $this->yearMonthFromFormation($formation);
            $year = $hint['year'] ?? $year;
        }
        if ($year < 1990 || $year > 2100) {
            flash('error', 'L\'année est obligatoire (entre 1990 et 2100).');
            Response::redirect($redirect);
        }

        $monthRaw = trim((string) ($request->input('month', '') ?? ''));
        $month = $monthRaw !== '' ? (int) $monthRaw : null;
        if ($month === null && $formation) {
            $hint = $this->yearMonthFromFormation($formation);
            $month = $hint['month'] ?? null;
        }
        if ($month !== null && ($month < 1 || $month > 12)) {
            flash('error', 'Le mois doit être compris entre 1 et 12.');
            Response::redirect($redirect);
        }

        $description = trim((string) ($request->input('description', '') ?? ''));

        return [
            'formation_id' => $formationId,
            'title'        => mb_substr($title, 0, 200),
            'year'         => $year,
            'month'        => $month,
            'description'  => $description !== '' ? $description : null,
            'kind'         => $kind,
            'is_published' => $request->input('is_published') === '1' ? 1 : 0,
        ];
    }

    /** @return array{year:?int,month:?int} */
    private function yearMonthFromFormation(array $formation): array
    {
        $slots = Formation::slots((int) $formation['id']);
        $first = $slots[0]['starts_at'] ?? null;
        if (!$first) {
            return ['year' => null, 'month' => null];
        }
        $ts = strtotime((string) $first);
        if ($ts === false) {
            return ['year' => null, 'month' => null];
        }
        return [
            'year'  => (int) date('Y', $ts),
            'month' => (int) date('n', $ts),
        ];
    }
}
