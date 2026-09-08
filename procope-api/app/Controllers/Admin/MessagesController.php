<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\ContactMessage;
use App\Models\ContactReply;
use App\Services\Audit;
use App\Services\Auth;
use App\Services\HtmlSanitizer;
use App\Services\Mailer;

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
            'replies' => ContactReply::allForMessage((int) $message['id']),
        ]);
    }

    /**
     * POST /admin/messages/{id}/reply — envoi manuel (ignore les toggles).
     * Corps sanitisé (HtmlSanitizer), From/Reply-To PROCOPE, journal message_reply.
     */
    public function reply(Request $request): void
    {
        $id = (int) $request->params['id'];
        $message = ContactMessage::find($id) ?: Response::abort(404, 'Message introuvable');

        $to = trim((string) ($message['email'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Impossible de répondre : ce message n\'a pas d\'adresse e-mail valide.');
            Response::redirect('/admin/messages/' . $id);
        }

        $subject = trim((string) ($request->input('subject', '') ?? ''));
        if ($subject === '') {
            $orig = trim((string) ($message['subject'] ?? ''));
            $subject = $orig !== '' ? 'Re: ' . $orig : 'Re: votre message à PROCOPE Afrique';
        }
        $subject = mb_substr(strip_tags($subject), 0, 255);

        $body = (string) ($request->input('body', '') ?? '');
        if ($body !== strip_tags($body)) {
            $body = HtmlSanitizer::clean($body);
        } else {
            $body = nl2br(e($body));
        }
        if (trim(strip_tags($body)) === '') {
            flash('error', 'Le contenu de la réponse ne peut pas être vide.');
            Response::redirect('/admin/messages/' . $id . '#reply');
        }

        $mailData = [
            'name'       => $message['name'],
            'reply_html' => $body,
        ];
        $html = Mailer::template('message_reply', $mailData);
        $error = Mailer::sendNow($to, $subject, $html, 'message_reply', null);

        if ($error !== null) {
            flash('error', 'Échec de l\'envoi : ' . $error);
            Response::redirect('/admin/messages/' . $id . '#reply');
        }

        $user = Auth::user();
        ContactReply::create([
            'message_id' => $id,
            'subject'    => $subject,
            'body'       => $body,
            'user_id'    => !empty($user['id']) ? (int) $user['id'] : null,
        ]);
        if ($message['statut'] !== 'traite') {
            ContactMessage::updateStatus($id, 'traite');
        }
        Audit::log('contact_message.reply', 'contact_message', $id, [
            'to'      => $to,
            'subject' => $subject,
        ]);

        flash('success', 'Réponse envoyée à ' . $to . '.');
        Response::redirect('/admin/messages/' . $id);
    }

    /**
     * POST /admin/messages/{id}/preview-reply — HTML du cadre e-mail (iframe SAMEORIGIN).
     */
    public function previewReply(Request $request): void
    {
        $id = (int) $request->params['id'];
        $message = ContactMessage::find($id) ?: Response::abort(404, 'Message introuvable');

        $body = (string) ($request->input('body', '') ?? '');
        if ($body !== strip_tags($body)) {
            $body = HtmlSanitizer::clean($body);
        } else {
            $body = nl2br(e($body));
        }
        if (trim(strip_tags($body)) === '') {
            $body = '<p style="color:#94a3b8;">(Saisissez le contenu de la réponse pour voir l\'aperçu.)</p>';
        }

        $html = Mailer::template('message_reply', [
            'name'       => $message['name'],
            'reply_html' => $body,
        ]);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Frame-Options: SAMEORIGIN');
        echo $html;
        exit;
    }

    public function deleteBatch(Request $request): void
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $request->inputArray('ids')),
            static fn (int $id): bool => $id > 0
        )));
        $query = http_build_query(array_filter([
            'statut' => $request->input('statut', '') ?: null,
            'q'      => $request->input('q', '') ?: null,
            'page'   => $request->input('page', '') ?: null,
        ]));

        if (!$ids) {
            flash('error', 'Aucun message sélectionné.');
            Response::redirect('/admin/messages' . ($query !== '' ? '?' . $query : ''));
        }

        $deleted = ContactMessage::deleteMany($ids);
        Audit::log('contact_message.delete_batch', 'contact_message', null, [
            'ids'     => $ids,
            'deleted' => $deleted,
        ]);
        flash('success', $deleted . ' message' . ($deleted > 1 ? 's' : '') . ' supprimé' . ($deleted > 1 ? 's' : '') . '.');
        Response::redirect('/admin/messages' . ($query !== '' ? '?' . $query : ''));
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
