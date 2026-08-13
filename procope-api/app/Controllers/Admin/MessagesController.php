<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\ContactMessage;
use App\Services\Audit;

/** Messages reçus via le formulaire de contact du site public. */
final class MessagesController
{
    public function index(Request $request): void
    {
        $filters = [
            'statut' => $request->input('statut', '') ?: null,
            'q'      => $request->input('q', '') ?: null,
        ];
        $page = max(1, (int) ($request->input('page', '1') ?? 1));

        View::render('messages/index', [
            'title'   => 'Messages',
            'result'  => ContactMessage::search($filters, $page),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request): void
    {
        $message = ContactMessage::find((int) $request->params['id'])
            ?: Response::abort(404, 'Message introuvable');

        View::render('messages/show', [
            'title'   => 'Message — ' . $message['name'],
            'message' => $message,
        ]);
    }

    public function updateStatus(Request $request): void
    {
        $id = (int) $request->params['id'];
        $message = ContactMessage::find($id) ?: Response::abort(404, 'Message introuvable');
        $statut = $request->input('statut', '');
        if (!in_array($statut, ContactMessage::STATUTS, true)) {
            Response::abort(422, 'Statut invalide');
        }

        ContactMessage::updateStatus($id, $statut);
        Audit::log('contact_message.status', 'contact_message', $id, [
            'from' => $message['statut'],
            'to'   => $statut,
        ]);

        flash('success', 'Statut du message mis à jour : ' . ContactMessage::STATUT_LABELS[$statut] . '.');
        Response::redirect('/admin/messages/' . $id);
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->params['id'];
        $message = ContactMessage::find($id) ?: Response::abort(404, 'Message introuvable');

        ContactMessage::delete($id);
        Audit::log('contact_message.delete', 'contact_message', $id, ['name' => $message['name']]);

        flash('success', 'Message de ' . $message['name'] . ' supprimé.');
        Response::redirect('/admin/messages');
    }
}
