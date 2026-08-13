<?php

namespace App\Controllers\Admin;

use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Formation;
use App\Models\Inscription;
use App\Models\JobApplication;
use App\Services\Audit;
use App\Services\Mailer;
use App\Services\Uploader;

final class FormationsController
{
    public function index(Request $request): void
    {
        $page = max(1, (int) ($request->input('page', '1') ?? 1));
        View::render('formations/index', [
            'title'  => 'Formations',
            'result' => Formation::paginate($page),
        ]);
    }

    public function create(Request $request): void
    {
        View::render('formations/form', [
            'title'     => 'Nouvelle formation',
            'formation' => null,
            'slots'     => [],
        ]);
    }

    public function edit(Request $request): void
    {
        $formation = Formation::find((int) $request->params['id']) ?: Response::abort(404, 'Formation introuvable');
        View::render('formations/form', [
            'title'            => 'Modifier la formation',
            'formation'        => $formation,
            'slots'            => Formation::slots((int) $formation['id']),
            'pastParticipants' => empty($formation['archived_at'])
                ? count(self::announcementRecipients((int) $formation['id']))
                : 0,
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validated($request);
        $id = Formation::create($data);
        Formation::replaceSlots($id, $this->slotsFromRequest($request));
        $this->handleAffiche($request, $id, null);
        Audit::log('formation.create', 'formation', $id, ['titre' => $data['titre']]);

        // Annonce automatique aux anciens participants (si inscriptions ouvertes + toggle actif)
        $announced = '';
        if ($data['inscriptions_ouvertes'] === 1 && Mailer::autoEnabled('nouvelle_formation')) {
            [$sent, $total] = $this->sendAnnouncement($id, false);
            if ($total > 0) {
                $announced = " Annonce envoyée à $sent destinataire" . ($sent > 1 ? 's' : '') . " sur $total.";
            }
        }

        flash('success', 'Formation créée.' . $announced);
        Response::redirect('/admin/formations');
    }

    /**
     * Destinataires de l'annonce d'une nouvelle formation : e-mails distincts
     * des anciens participants (autres formations, statuts validé/partiel)
     * + des candidats aux offres d'emploi, dédupliqués.
     */
    private static function announcementRecipients(int $excludeFormationId): array
    {
        $emails = array_merge(
            Inscription::pastParticipantEmails($excludeFormationId),
            JobApplication::allEmails()
        );
        $emails = array_values(array_unique(array_map('mb_strtolower', $emails)));
        sort($emails);
        return $emails;
    }

    /**
     * Envoie l'annonce de la formation aux anciens participants + candidats
     * aux offres d'emploi (dédupliqués).
     * $force = true (action manuelle) : ignore le master switch via Mailer::sendNow.
     * Retourne [nb envoyés, nb destinataires, dernière erreur éventuelle].
     */
    private function sendAnnouncement(int $formationId, bool $force): array
    {
        $formation = Formation::find($formationId);
        if (!$formation) {
            return [0, 0, null];
        }
        $recipients = self::announcementRecipients($formationId);
        if (!$recipients) {
            return [0, 0, null];
        }

        $afficheUrl = null;
        if (!empty($formation['affiche_path'])) {
            $afficheUrl = rtrim((string) Env::get('APP_URL', ''), '/')
                . '/uploads/affiches/' . rawurlencode($formation['affiche_path']);
        }
        $mailData = [
            'formation'   => $formation,
            'slots'       => Formation::slots($formationId),
            'affiche_url' => $afficheUrl,
            'cta_url'     => rtrim((string) Env::get('SITE_URL', 'https://procopeafrique.vercel.app'), '/')
                . '/candidature.html#former',
        ];
        $html = Mailer::template('annonce', $mailData);
        $subject = Mailer::subjectFor('annonce', $mailData, 'Nouvelle formation PROCOPE — ' . $formation['titre']);

        $sent = 0;
        $lastError = null;
        foreach ($recipients as $to) {
            if ($force) {
                $error = Mailer::sendNow($to, $subject, $html, 'annonce_formation', null);
                if ($error === null) {
                    $sent++;
                } else {
                    $lastError = $error;
                }
            } elseif (Mailer::send($to, $subject, $html, 'annonce_formation', null)) {
                $sent++;
            }
        }

        Audit::log('formation.announce', 'formation', $formationId, [
            'mode'         => $force ? 'manuel' : 'auto',
            'destinataires' => count($recipients),
            'envoyes'      => $sent,
        ]);
        return [$sent, count($recipients), $lastError];
    }

    /** POST /admin/formations/{id}/announce — annonce manuelle aux anciens participants. */
    public function announce(Request $request): void
    {
        $id = (int) $request->params['id'];
        $formation = Formation::find($id) ?: Response::abort(404, 'Formation introuvable');
        if (!empty($formation['archived_at'])) {
            flash('error', 'Impossible d\'annoncer une formation archivée.');
            Response::redirect('/admin/formations');
        }

        [$sent, $total, $lastError] = $this->sendAnnouncement($id, true);
        if ($total === 0) {
            flash('error', 'Aucun destinataire avec e-mail (anciens participants ou candidats) : aucune annonce envoyée.');
        } elseif ($sent === $total) {
            flash('success', "Annonce envoyée à $sent destinataire" . ($sent > 1 ? 's' : '') . '.');
        } else {
            flash('error', "Annonce envoyée à $sent destinataire" . ($sent > 1 ? 's' : '')
                . " sur $total. Dernière erreur : " . ($lastError ?? 'inconnue'));
        }
        // Retour vers la page d'origine (page Automatisations ou fiche formation)
        Response::redirect($request->input('return') === 'automations'
            ? '/admin/automations'
            : "/admin/formations/$id/edit");
    }

    /** POST /admin/formations/{id}/archive — archive la formation et ferme ses inscriptions. */
    public function archive(Request $request): void
    {
        $id = (int) $request->params['id'];
        $formation = Formation::find($id) ?: Response::abort(404, 'Formation introuvable');
        if (!empty($formation['archived_at'])) {
            flash('error', 'Cette formation est déjà archivée.');
            Response::redirect('/admin/archives');
        }
        Formation::archive($id);
        Audit::log('formation.archive', 'formation', $id, ['titre' => $formation['titre']]);
        flash('success', 'Formation archivée. Retrouvez-la dans la page Archives.');
        Response::redirect('/admin/formations');
    }

    public function update(Request $request): void
    {
        $id = (int) $request->params['id'];
        $formation = Formation::find($id) ?: Response::abort(404, 'Formation introuvable');
        $data = $this->validated($request, $id);
        Formation::update($id, $data);
        Formation::replaceSlots($id, $this->slotsFromRequest($request));
        $this->handleAffiche($request, $id, $formation['affiche_path'] ?? null);
        Audit::log('formation.update', 'formation', $id, ['titre' => $data['titre']]);
        flash('success', 'Formation mise à jour.');
        Response::redirect('/admin/formations');
    }

    /** Enregistre la nouvelle affiche si envoyée, en supprimant l'ancienne remplacée. */
    private function handleAffiche(Request $request, int $id, ?string $oldPath): void
    {
        $file = $request->file('affiche');
        if (!$file) {
            return;
        }
        try {
            $affiche = Uploader::storeAffiche($file);
        } catch (\RuntimeException $e) {
            flash('error', 'Formation enregistrée, mais affiche refusée : ' . $e->getMessage());
            Response::redirect("/admin/formations/$id/edit");
        }
        Formation::setAffiche($id, $affiche['path'], $affiche['mime']);
        Uploader::deleteAffiche($oldPath);
    }

    public function toggle(Request $request): void
    {
        $id = (int) $request->params['id'];
        $formation = Formation::find($id) ?: Response::abort(404, 'Formation introuvable');
        $open = !(int) $formation['inscriptions_ouvertes'];
        Formation::setOpen($id, $open);
        Audit::log($open ? 'formation.open' : 'formation.close', 'formation', $id);
        flash('success', $open ? 'Inscriptions ouvertes.' : 'Inscriptions fermées.');
        Response::redirect('/admin/formations');
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->params['id'];
        $formation = Formation::find($id) ?: Response::abort(404, 'Formation introuvable');
        if (Formation::countInscriptions($id) > 0) {
            flash('error', 'Impossible de supprimer : des inscriptions existent pour cette formation. Fermez-la plutôt.');
            Response::redirect('/admin/formations');
        }
        Formation::delete($id);
        Uploader::deleteAffiche($formation['affiche_path'] ?? null);
        Audit::log('formation.delete', 'formation', $id, ['titre' => $formation['titre']]);
        flash('success', 'Formation supprimée.');
        Response::redirect('/admin/formations');
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $titre = $request->input('titre', '');
        if ($titre === '') {
            flash('error', 'Le titre est obligatoire.');
            Response::redirect($id ? "/admin/formations/$id/edit" : '/admin/formations/create');
        }

        $slug = $request->input('slug', '') ?: $this->slugify($titre);
        $placesMax = $request->input('places_max', '');
        $prix = (float) str_replace([' ', ','], ['', '.'], $request->input('prix', '0') ?? '0');

        return [
            'titre'                 => mb_substr($titre, 0, 200),
            'slug'                  => mb_substr($this->slugify($slug), 0, 200),
            'intro'                 => $request->input('intro') ?: null,
            'programme'             => $request->input('programme') ?: null,
            'prix'                  => max(0, $prix),
            'devise'                => $request->input('devise', 'XOF') ?: 'XOF',
            'lieu'                  => $request->input('lieu') ?: null,
            'places_max'            => $placesMax !== '' ? max(1, (int) $placesMax) : null,
            'inscriptions_ouvertes' => $request->input('inscriptions_ouvertes') === '1' ? 1 : 0,
            'ouverte_du'            => $this->datetimeOrNull($request->input('ouverte_du')),
            'ouverte_au'            => $this->datetimeOrNull($request->input('ouverte_au')),
            'message_fermeture'     => $request->input('message_fermeture') ?: null,
            'contact_phone'         => $request->input('contact_phone') ?: null,
        ];
    }

    /** Créneaux depuis les champs tableaux slot_label[] / slot_start[] / slot_end[]. */
    private function slotsFromRequest(Request $request): array
    {
        $labels = $request->inputArray('slot_label');
        $starts = $request->inputArray('slot_start');
        $ends   = $request->inputArray('slot_end');
        $slots = [];
        foreach ($labels as $index => $label) {
            $label = trim((string) $label);
            $start = $this->datetimeOrNull($starts[$index] ?? null);
            $end   = $this->datetimeOrNull($ends[$index] ?? null);
            if ($label === '' || !$start || !$end) {
                continue;
            }
            $slots[] = ['label' => mb_substr($label, 0, 150), 'starts_at' => $start, 'ends_at' => $end];
        }
        return $slots;
    }

    private function datetimeOrNull(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-') ?: 'formation-' . time();
    }
}
