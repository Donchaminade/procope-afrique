<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Formation;
use App\Models\Inscription;
use App\Models\MailLog;
use App\Services\Audit;
use App\Services\Auth;
use App\Services\Exporter;
use App\Services\Mailer;
use App\Services\Uploader;

final class InscriptionsController
{
    public function index(Request $request): void
    {
        $filters = $this->filters($request);
        $page = max(1, (int) ($request->input('page', '1') ?? 1));
        $result = Inscription::search($filters, $page);

        View::render('inscriptions/index', [
            'title'      => 'Inscriptions',
            'result'     => $result,
            'filters'    => $filters,
            'formations' => Formation::allForFilter(),
        ]);
    }

    public function show(Request $request): void
    {
        $inscription = Inscription::find((int) $request->params['id'])
            ?: Response::abort(404, 'Inscription introuvable');

        View::render('inscriptions/show', [
            'title'       => 'Inscription — ' . $inscription['full_name'],
            'inscription' => $inscription,
            'mails'       => MailLog::forInscription((int) $inscription['id']),
        ]);
    }

    public function updateStatus(Request $request): void
    {
        $id = (int) $request->params['id'];
        $inscription = Inscription::find($id) ?: Response::abort(404, 'Inscription introuvable');
        $statut = $request->input('statut', '');
        if (!in_array($statut, Inscription::STATUTS, true)) {
            Response::abort(422, 'Statut invalide');
        }

        Inscription::updateStatus($id, $statut, (int) Auth::user()['id']);
        Audit::log('inscription.status', 'inscription', $id, [
            'from' => $inscription['statut'],
            'to'   => $statut,
        ]);

        // Mail automatique au candidat lors de la validation / du refus
        // (toggles : valide -> auto_paiement_confirme, refuse -> auto_statut_refuse)
        $mailAllowed = $statut === 'valide'
            ? Mailer::autoEnabled('paiement_confirme')
            : ($statut === 'refuse' && Mailer::autoEnabled('statut_refuse'));
        if (in_array($statut, ['valide', 'refuse'], true) && $inscription['email'] && $mailAllowed) {
            $formation = Formation::find((int) $inscription['formation_id']);
            $mailData = [
                'inscription' => $inscription,
                'formation'   => $formation,
                'statut'      => $statut,
                'slots'       => $formation ? Formation::slots((int) $formation['id']) : [],
            ];
            $html = Mailer::template('status', $mailData);
            $subject = Mailer::subjectFor('status', $mailData, $statut === 'valide'
                ? 'Votre inscription est confirmée — ' . ($formation['titre'] ?? 'Formation PROCOPE')
                : 'Suite de votre inscription — ' . ($formation['titre'] ?? 'Formation PROCOPE'));
            Mailer::send($inscription['email'], $subject, $html, 'statut_' . $statut, $id);
        }

        flash('success', 'Statut mis à jour : ' . Inscription::STATUT_LABELS[$statut] . '.');
        Response::redirect('/admin/inscriptions/' . $id);
    }

    /**
     * POST /admin/inscriptions/{id}/validate-payment
     * Enregistre le montant reçu et bascule le statut :
     * montant >= prix -> 'valide' (mail de confirmation avec rendez-vous),
     * 0 < montant < prix -> 'paiement_partiel' (mail avec reste à payer).
     */
    public function validatePayment(Request $request): void
    {
        $id = (int) $request->params['id'];
        $inscription = Inscription::find($id) ?: Response::abort(404, 'Inscription introuvable');

        $prix = (float) $inscription['formation_prix'];
        $raw = str_replace([' ', ','], ['', '.'], $request->input('amount_received', '') ?? '');
        $amountReceived = is_numeric($raw) ? round((float) $raw, 2) : -1.0;

        if ($amountReceived <= 0) {
            flash('error', 'Montant reçu invalide : saisissez un montant supérieur à 0.');
            Response::redirect('/admin/inscriptions/' . $id);
        }

        $statut = $amountReceived >= $prix ? 'valide' : 'paiement_partiel';
        Inscription::recordPayment($id, $amountReceived, $statut, (int) Auth::user()['id']);
        Audit::log('inscription.validate_payment', 'inscription', $id, [
            'from'            => $inscription['statut'],
            'to'              => $statut,
            'amount_received' => $amountReceived,
            'prix'            => $prix,
        ]);

        $mailAllowed = Mailer::autoEnabled($statut === 'valide' ? 'paiement_confirme' : 'paiement_partiel');
        if ($inscription['email'] && $mailAllowed) {
            $formation = Formation::find((int) $inscription['formation_id']);
            $mailData = [
                'inscription'     => $inscription,
                'formation'       => $formation,
                'statut'          => $statut,
                'amount_received' => $amountReceived,
                'reste'           => max(0.0, $prix - $amountReceived),
                'slots'           => $formation ? Formation::slots((int) $formation['id']) : [],
            ];
            $html = Mailer::template('payment', $mailData);
            $subject = Mailer::subjectFor('payment', $mailData, $statut === 'valide'
                ? 'Votre inscription est confirmée — ' . ($formation['titre'] ?? 'Formation PROCOPE')
                : 'Paiement partiel reçu — ' . ($formation['titre'] ?? 'Formation PROCOPE'));
            Mailer::send(
                $inscription['email'],
                $subject,
                $html,
                $statut === 'valide' ? 'paiement_confirme' : 'paiement_partiel',
                $id
            );
        }

        flash('success', $statut === 'valide'
            ? 'Paiement complet vérifié : inscription validée.'
            : 'Paiement partiel enregistré : reste à payer '
                . format_price($prix - $amountReceived, $inscription['formation_devise'] === 'XOF' ? 'F CFA' : $inscription['formation_devise']) . '.');
        Response::redirect('/admin/inscriptions/' . $id);
    }

    /**
     * POST /admin/inscriptions/{id}/send-mail — envois manuels depuis la fiche.
     * type=renvoi_confirmation : renvoie la confirmation d'inscription.
     * type=rappel_paiement : rappel du reste à payer (statut paiement_partiel uniquement).
     * Ignore les toggles d'automatisation (action volontaire), journalise dans MailLog.
     */
    public function sendMail(Request $request): void
    {
        $id = (int) $request->params['id'];
        $inscription = Inscription::find($id) ?: Response::abort(404, 'Inscription introuvable');
        $type = $request->input('type', '');

        if (!in_array($type, ['renvoi_confirmation', 'rappel_paiement'], true)) {
            Response::abort(422, "Type d'envoi invalide");
        }
        if (!$inscription['email']) {
            flash('error', "Impossible d'envoyer : aucune adresse e-mail renseignée pour ce candidat.");
            Response::redirect('/admin/inscriptions/' . $id);
        }

        $formation = Formation::find((int) $inscription['formation_id']);
        $slots = $formation ? Formation::slots((int) $formation['id']) : [];
        $prix = (float) $inscription['formation_prix'];

        if ($type === 'renvoi_confirmation') {
            $mailData = [
                'full_name'       => $inscription['full_name'],
                'formation'       => $formation ?: ['titre' => $inscription['formation_titre'], 'prix' => $prix],
                'slots'           => $slots,
                'has_proof'       => (bool) $inscription['payment_proof_path'],
                'payment_type'    => $inscription['payment_type'] ?? 'total',
                'amount_declared' => $inscription['amount_declared'] !== null ? (float) $inscription['amount_declared'] : null,
            ];
            $html = Mailer::template('confirmation', $mailData);
            $subject = Mailer::subjectFor('confirmation', $mailData,
                'Votre inscription — ' . ($formation['titre'] ?? 'Formation PROCOPE'));
            $label = 'Confirmation d\'inscription renvoyée';
        } else {
            if ($inscription['statut'] !== 'paiement_partiel') {
                flash('error', 'Le rappel de paiement est réservé aux inscriptions en statut « Paiement partiel ».');
                Response::redirect('/admin/inscriptions/' . $id);
            }
            $received = (float) ($inscription['amount_received'] ?? $inscription['amount_declared'] ?? 0);
            $mailData = [
                'inscription'     => $inscription,
                'formation'       => $formation,
                'statut'          => 'paiement_partiel',
                'amount_received' => $received,
                'reste'           => max(0.0, $prix - $received),
                'slots'           => $slots,
            ];
            $html = Mailer::template('payment', $mailData);
            $subject = Mailer::subjectFor('payment', $mailData,
                'Rappel — reste à payer pour la formation ' . ($formation['titre'] ?? 'PROCOPE'));
            $label = 'Rappel de paiement envoyé';
        }

        $error = Mailer::sendNow($inscription['email'], $subject, $html, $type, $id);
        Audit::log('inscription.send_mail', 'inscription', $id, [
            'type' => $type,
            'to'   => $inscription['email'],
            'sent' => $error === null,
        ]);

        if ($error === null) {
            flash('success', $label . ' à ' . $inscription['email'] . '.');
        } else {
            flash('error', "Échec de l'envoi : " . $error);
        }
        Response::redirect('/admin/inscriptions/' . $id);
    }

    /** Sert la preuve de paiement après contrôle de session (jamais d'URL publique). */
    public function proof(Request $request): void
    {
        $inscription = Inscription::find((int) $request->params['id'])
            ?: Response::abort(404, 'Inscription introuvable');
        if (!$inscription['payment_proof_path']) {
            Response::abort(404, 'Aucune preuve enregistrée pour cette inscription.');
        }
        $path = Uploader::proofFullPath($inscription['payment_proof_path']);
        if (!is_file($path)) {
            Response::abort(404, 'Fichier de preuve introuvable sur le serveur.');
        }

        header('Content-Type: ' . ($inscription['payment_proof_mime'] ?: 'application/octet-stream'));
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: inline; filename="preuve-' . (int) $inscription['id']
            . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function export(Request $request): void
    {
        Audit::log('inscriptions.export', 'inscription', null, $this->filters($request));
        Exporter::download($this->filters($request));
    }

    private function filters(Request $request): array
    {
        return [
            'formation_id' => (int) ($request->input('formation_id', '') ?? 0) ?: null,
            'statut'       => $request->input('statut', '') ?: null,
            'q'            => $request->input('q', '') ?: null,
            'from'         => $request->input('from', '') ?: null,
            'to'           => $request->input('to', '') ?: null,
        ];
    }
}
