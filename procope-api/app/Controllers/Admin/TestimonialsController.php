<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Testimonial;
use App\Services\Audit;
use App\Services\Uploader;

/** Modération et publication des témoignages écrits. */
final class TestimonialsController
{
    public function index(Request $request): void
    {
        $filters = [
            'statut' => $request->input('statut', '') ?: null,
            'source' => $request->input('source', '') ?: null,
            'q'      => $request->input('q', '') ?: null,
        ];
        $page = max(1, (int) ($request->input('page', '1') ?? 1));

        View::render('temoignages/index', [
            'title'   => 'Témoignages',
            'result'  => Testimonial::search($filters, $page),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): void
    {
        View::render('temoignages/form', [
            'title'       => 'Nouveau témoignage',
            'testimonial' => null,
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validated($request, true);
        $photo = $this->handlePhoto($request);
        if ($photo) {
            $data['photo_path'] = $photo['path'];
            $data['photo_mime'] = $photo['mime'];
        }
        $id = Testimonial::create($data);
        Audit::log('testimonial.create', 'testimonial', $id, [
            'author' => $data['author_name'],
            'statut' => $data['statut'],
        ]);
        flash('success', $data['statut'] === 'publie'
            ? 'Témoignage créé et publié sur le site.'
            : 'Témoignage enregistré.');
        Response::redirect('/admin/temoignages');
    }

    public function show(Request $request): void
    {
        $row = Testimonial::find((int) $request->params['id'])
            ?: Response::abort(404, 'Témoignage introuvable');

        View::render('temoignages/show', [
            'title'       => 'Témoignage — ' . $row['author_name'],
            'testimonial' => $row,
        ]);
    }

    public function edit(Request $request): void
    {
        $row = Testimonial::find((int) $request->params['id'])
            ?: Response::abort(404, 'Témoignage introuvable');

        View::render('temoignages/form', [
            'title'       => 'Modifier le témoignage',
            'testimonial' => $row,
        ]);
    }

    public function update(Request $request): void
    {
        $id = (int) $request->params['id'];
        $row = Testimonial::find($id) ?: Response::abort(404, 'Témoignage introuvable');
        $data = $this->validated($request, false);
        if ($data['statut'] === 'publie' && !empty($row['published_at'])) {
            $data['published_at'] = $row['published_at'];
        }

        $data['photo_path'] = $row['photo_path'];
        $data['photo_mime'] = $row['photo_mime'];

        if ($request->input('remove_photo') === '1' && $row['photo_path']) {
            Uploader::deleteTemoignage($row['photo_path']);
            $data['photo_path'] = null;
            $data['photo_mime'] = null;
        }

        $photo = $this->handlePhoto($request);
        if ($photo) {
            if ($row['photo_path']) {
                Uploader::deleteTemoignage($row['photo_path']);
            }
            $data['photo_path'] = $photo['path'];
            $data['photo_mime'] = $photo['mime'];
        }

        Testimonial::update($id, $data);
        Audit::log('testimonial.update', 'testimonial', $id, [
            'author' => $data['author_name'],
            'statut' => $data['statut'],
        ]);
        flash('success', 'Témoignage mis à jour.');
        Response::redirect('/admin/temoignages/' . $id);
    }

    public function publish(Request $request): void
    {
        $id = (int) $request->params['id'];
        $row = Testimonial::find($id) ?: Response::abort(404, 'Témoignage introuvable');
        $from = $row['statut'];
        $publishedAt = $row['published_at'] ?: date('Y-m-d H:i:s');
        Testimonial::setStatut($id, 'publie', $publishedAt);
        Audit::log('testimonial.publish', 'testimonial', $id, [
            'from'   => $from,
            'author' => $row['author_name'],
        ]);
        flash('success', 'Témoignage de ' . $row['author_name'] . ' publié sur le site.');
        Response::redirect($this->backTo($request, $id));
    }

    public function refuse(Request $request): void
    {
        $id = (int) $request->params['id'];
        $row = Testimonial::find($id) ?: Response::abort(404, 'Témoignage introuvable');
        $from = $row['statut'];
        Testimonial::setStatut($id, 'refuse', null);
        Audit::log('testimonial.refuse', 'testimonial', $id, [
            'from'   => $from,
            'author' => $row['author_name'],
        ]);
        flash('success', 'Témoignage de ' . $row['author_name'] . ' refusé.');
        Response::redirect($this->backTo($request, $id));
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->params['id'];
        $row = Testimonial::find($id) ?: Response::abort(404, 'Témoignage introuvable');
        Uploader::deleteTemoignage($row['photo_path'] ?? null);
        Testimonial::delete($id);
        Audit::log('testimonial.delete', 'testimonial', $id, ['author' => $row['author_name']]);
        flash('success', 'Témoignage de ' . $row['author_name'] . ' supprimé.');
        Response::redirect('/admin/temoignages');
    }

    /** @return array{author_name:string,author_email:?string,role_title:?string,quote:string,source:string,statut:string,published_at:?string} */
    private function validated(Request $request, bool $isCreate): array
    {
        $name = trim((string) ($request->input('author_name', '') ?? ''));
        $email = mb_strtolower(trim((string) ($request->input('author_email', '') ?? '')));
        $role = trim((string) ($request->input('role_title', '') ?? ''));
        $quote = trim((string) ($request->input('quote', '') ?? ''));
        $statut = (string) ($request->input('statut', '') ?? '');

        if (mb_strlen($name) < 2) {
            flash('error', 'Le nom est obligatoire (2 caractères minimum).');
            Response::redirect($isCreate ? '/admin/temoignages/create' : '/admin/temoignages/' . (int) ($request->params['id'] ?? 0) . '/edit');
        }
        if (mb_strlen($quote) < 20) {
            flash('error', 'Le témoignage est trop court (20 caractères minimum).');
            Response::redirect($isCreate ? '/admin/temoignages/create' : '/admin/temoignages/' . (int) ($request->params['id'] ?? 0) . '/edit');
        }
        if (mb_strlen($quote) > 2000) {
            flash('error', 'Le témoignage est trop long (2000 caractères maximum).');
            Response::redirect($isCreate ? '/admin/temoignages/create' : '/admin/temoignages/' . (int) ($request->params['id'] ?? 0) . '/edit');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Adresse e-mail invalide.');
            Response::redirect($isCreate ? '/admin/temoignages/create' : '/admin/temoignages/' . (int) ($request->params['id'] ?? 0) . '/edit');
        }
        if ($statut === '' || !in_array($statut, Testimonial::STATUTS, true)) {
            $statut = $isCreate ? 'publie' : 'en_attente';
        }

        $publishedAt = null;
        if ($statut === 'publie') {
            $publishedAt = date('Y-m-d H:i:s');
        }

        return [
            'author_name'  => mb_substr($name, 0, 150),
            'author_email' => $email !== '' ? mb_substr($email, 0, 190) : null,
            'role_title'   => $role !== '' ? mb_substr($role, 0, 190) : null,
            'quote'        => $quote,
            'source'       => $isCreate ? 'admin' : 'public',
            'statut'       => $statut,
            'published_at' => $publishedAt,
        ];
    }

    /** @return array{path:string,mime:string}|null */
    private function handlePhoto(Request $request): ?array
    {
        $file = $request->file('photo');
        if (!$file) {
            return null;
        }
        try {
            return Uploader::storeTemoignage($file);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            $id = (int) ($request->params['id'] ?? 0);
            Response::redirect($id > 0 ? '/admin/temoignages/' . $id . '/edit' : '/admin/temoignages/create');
        }
    }

    private function backTo(Request $request, int $id): string
    {
        $from = (string) ($request->input('from', '') ?? '');
        return $from === 'list' ? '/admin/temoignages' : '/admin/temoignages/' . $id;
    }
}
