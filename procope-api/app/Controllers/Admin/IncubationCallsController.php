<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\IncubationCall;
use App\Services\Audit;

final class IncubationCallsController
{
    public function index(Request $request): void
    {
        $page = max(1, (int) ($request->input('page', '1') ?? 1));
        View::render('appels/index', [
            'title'  => 'Appels à incubation',
            'result' => IncubationCall::paginate($page),
        ]);
    }

    public function create(Request $request): void
    {
        View::render('appels/form', [
            'title' => 'Nouvel appel',
            'call'  => null,
        ]);
    }

    public function edit(Request $request): void
    {
        $call = IncubationCall::find((int) $request->params['id'])
            ?: Response::abort(404, 'Appel introuvable');
        View::render('appels/form', [
            'title' => 'Modifier l\'appel',
            'call'  => $call,
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validated($request);
        $id = IncubationCall::create($data);
        Audit::log('incubation_call.create', 'incubation_call', $id, ['title' => $data['title']]);
        flash('success', 'Appel à incubation créé.');
        Response::redirect('/admin/appels');
    }

    public function update(Request $request): void
    {
        $id = (int) $request->params['id'];
        IncubationCall::find($id) ?: Response::abort(404, 'Appel introuvable');
        $data = $this->validated($request, $id);
        IncubationCall::update($id, $data);
        Audit::log('incubation_call.update', 'incubation_call', $id, ['title' => $data['title']]);
        flash('success', 'Appel mis à jour.');
        Response::redirect('/admin/appels');
    }

    public function publish(Request $request): void
    {
        $id = (int) $request->params['id'];
        $call = IncubationCall::find($id) ?: Response::abort(404, 'Appel introuvable');
        $publish = !(int) $call['is_published'];
        IncubationCall::setPublished($id, $publish);
        Audit::log($publish ? 'incubation_call.publish' : 'incubation_call.unpublish', 'incubation_call', $id, [
            'title' => $call['title'],
        ]);
        flash('success', $publish ? 'Appel publié.' : 'Appel dépublié.');
        Response::redirect('/admin/appels');
    }

    public function archive(Request $request): void
    {
        $id = (int) $request->params['id'];
        $call = IncubationCall::find($id) ?: Response::abort(404, 'Appel introuvable');
        IncubationCall::archive($id);
        Audit::log('incubation_call.archive', 'incubation_call', $id, ['title' => $call['title']]);
        flash('success', 'Appel archivé et dépublié.');
        Response::redirect('/admin/appels');
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->params['id'];
        $call = IncubationCall::find($id) ?: Response::abort(404, 'Appel introuvable');
        IncubationCall::delete($id);
        Audit::log('incubation_call.delete', 'incubation_call', $id, ['title' => $call['title']]);
        flash('success', 'Appel supprimé. Les dépôts liés deviennent spontanés.');
        Response::redirect('/admin/appels');
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $redirect = $id ? "/admin/appels/$id/edit" : '/admin/appels/create';
        $title = trim((string) ($request->input('title', '') ?? ''));
        if ($title === '') {
            flash('error', 'Le titre est obligatoire.');
            Response::redirect($redirect);
        }

        $opens = $this->parseLocal($request->input('opens_at', ''));
        $closes = $this->parseLocal($request->input('closes_at', ''));
        if (!$opens || !$closes) {
            flash('error', 'Indiquez les dates d\'ouverture et de clôture.');
            Response::redirect($redirect);
        }
        if (strtotime($closes) <= strtotime($opens)) {
            flash('error', 'La clôture doit être postérieure à l\'ouverture.');
            Response::redirect($redirect);
        }

        $slug = $this->slugify($request->input('slug', '') ?: $title);
        $existing = IncubationCall::findBySlug($slug);
        if ($existing && (int) $existing['id'] !== (int) $id) {
            $slug .= '-' . time();
        }

        return [
            'title'        => mb_substr($title, 0, 200),
            'slug'         => mb_substr($slug, 0, 200),
            'sector'       => mb_substr(trim((string) ($request->input('sector', '') ?? '')), 0, 190) ?: null,
            'description'  => $request->input('description') ?: null,
            'opens_at'     => $opens,
            'closes_at'    => $closes,
            'is_published' => $request->input('is_published') === '1' ? 1 : 0,
        ];
    }

    private function parseLocal(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime(str_replace('T', ' ', $value));
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-') ?: 'appel-' . time();
    }
}
