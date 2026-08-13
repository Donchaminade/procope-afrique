<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\JobOfferImage;
use App\Services\Audit;
use App\Services\JobNotifier;
use App\Services\Mailer;
use App\Services\Uploader;

final class JobOffersController
{
    public function index(Request $request): void
    {
        $page = max(1, (int) ($request->input('page', '1') ?? 1));
        View::render('emplois/index', [
            'title'  => "Offres d'emploi",
            'result' => JobOffer::paginate($page),
        ]);
    }

    public function create(Request $request): void
    {
        View::render('emplois/form', [
            'title' => 'Nouvelle offre',
            'offer' => null,
        ]);
    }

    public function edit(Request $request): void
    {
        $offer = JobOffer::find((int) $request->params['id']) ?: Response::abort(404, 'Offre introuvable');
        View::render('emplois/form', [
            'title'      => "Modifier l'offre",
            'offer'      => $offer,
            'images'     => JobOfferImage::allForOffer((int) $offer['id']),
            'recipients' => count(JobNotifier::recipients()),
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validated($request);
        $id = JobOffer::create($data);
        $imageNotice = $this->handleImageUploads($request, $id);
        Audit::log('job_offer.create', 'job_offer', $id, ['title' => $data['title']]);

        // Annonce automatique si l'offre est créée directement publiée
        $announced = '';
        if ($data['is_published'] === 1 && Mailer::autoEnabled('offre_publiee')) {
            $offer = JobOffer::find($id);
            [$sent, $total] = JobNotifier::sendPublication($offer);
            Audit::log('job_offer.announce', 'job_offer', $id, [
                'mode' => 'auto', 'destinataires' => $total, 'envoyes' => $sent,
            ]);
            if ($total > 0) {
                $announced = " Annonce envoyée à $sent destinataire" . ($sent > 1 ? 's' : '') . " sur $total.";
            }
        }

        flash('success', 'Offre créée.' . $imageNotice . $announced);
        Response::redirect('/admin/emplois');
    }

    /**
     * Enregistre les affiches envoyées (input multiple "images[]").
     * Si aucune image n'est marquée principale, la première le devient.
     * Retourne le complément de message flash (succès partiel / refus).
     */
    private function handleImageUploads(Request $request, int $offerId): string
    {
        $files = $request->files('images');
        if (!$files) {
            return '';
        }
        $stored = 0;
        $errors = [];
        foreach ($files as $file) {
            try {
                $image = Uploader::storeOffreImage($file);
                JobOfferImage::create($offerId, $image['path'], $image['mime']);
                $stored++;
            } catch (\RuntimeException $e) {
                $errors[] = ((string) ($file['name'] ?? 'fichier')) . ' : ' . $e->getMessage();
            }
        }
        JobOfferImage::ensureMain($offerId);
        if ($stored > 0) {
            Audit::log('job_offer.images_add', 'job_offer', $offerId, ['ajoutees' => $stored]);
        }
        $notice = $stored > 0 ? " $stored affiche" . ($stored > 1 ? 's' : '') . ' ajoutée' . ($stored > 1 ? 's' : '') . '.' : '';
        if ($errors) {
            $notice .= ' Refusée(s) — ' . implode(' · ', $errors);
        }
        return $notice;
    }

    /** POST /admin/emplois/{id}/images — ajout d'affiches depuis la page d'édition. */
    public function uploadImages(Request $request): void
    {
        $id = (int) $request->params['id'];
        JobOffer::find($id) ?: Response::abort(404, 'Offre introuvable');
        $notice = $this->handleImageUploads($request, $id);
        if ($notice === '') {
            flash('error', 'Aucun fichier sélectionné.');
        } elseif (str_contains($notice, 'Refusée')) {
            flash('error', trim($notice));
        } else {
            flash('success', trim($notice));
        }
        Response::redirect("/admin/emplois/$id/edit");
    }

    /** POST /admin/emplois/{id}/images/{img}/main — désigne l'affiche principale. */
    public function setMainImage(Request $request): void
    {
        $id = (int) $request->params['id'];
        $imageId = (int) $request->params['img'];
        $image = JobOfferImage::find($imageId);
        if (!$image || (int) $image['offer_id'] !== $id) {
            Response::abort(404, 'Affiche introuvable');
        }
        JobOfferImage::setMain($id, $imageId);
        Audit::log('job_offer.image_main', 'job_offer', $id, ['image_id' => $imageId]);
        flash('success', 'Affiche principale mise à jour.');
        Response::redirect("/admin/emplois/$id/edit");
    }

    /** POST /admin/emplois/{id}/images/{img}/delete — supprime une affiche. */
    public function deleteImage(Request $request): void
    {
        $id = (int) $request->params['id'];
        $imageId = (int) $request->params['img'];
        $image = JobOfferImage::find($imageId);
        if (!$image || (int) $image['offer_id'] !== $id) {
            Response::abort(404, 'Affiche introuvable');
        }
        JobOfferImage::delete($imageId);
        Uploader::deleteOffreImage($image['path']);
        JobOfferImage::ensureMain($id);
        Audit::log('job_offer.image_delete', 'job_offer', $id, ['image_id' => $imageId]);
        flash('success', 'Affiche supprimée.');
        Response::redirect("/admin/emplois/$id/edit");
    }

    public function update(Request $request): void
    {
        $id = (int) $request->params['id'];
        $offer = JobOffer::find($id) ?: Response::abort(404, 'Offre introuvable');
        $data = $this->validated($request, $id);
        JobOffer::update($id, $data);
        Audit::log('job_offer.update', 'job_offer', $id, ['title' => $data['title']]);

        $notice = '';
        $wasPublished = (int) $offer['is_published'] === 1;
        $nowPublished = $data['is_published'] === 1;

        if (!$wasPublished && $nowPublished) {
            // Passage à publiée : annonce automatique
            $notice = $this->autoAnnounce($id);
        } elseif ($wasPublished && $nowPublished
            && strtotime($data['closes_at']) > strtotime((string) $offer['closes_at'])) {
            // Offre publiée dont la clôture est repoussée : prolongation
            $notice = $this->handleProlongation($id, $data['closes_at']);
        }

        flash('success', 'Offre mise à jour.' . $notice);
        Response::redirect('/admin/emplois');
    }

    /** Annonce automatique (toggle auto_offre_publiee). Retourne le complément de message flash. */
    private function autoAnnounce(int $id): string
    {
        if (!Mailer::autoEnabled('offre_publiee')) {
            return '';
        }
        $offer = JobOffer::find($id);
        [$sent, $total] = JobNotifier::sendPublication($offer);
        Audit::log('job_offer.announce', 'job_offer', $id, [
            'mode' => 'auto', 'destinataires' => $total, 'envoyes' => $sent,
        ]);
        return $total > 0 ? " Annonce envoyée à $sent destinataire" . ($sent > 1 ? 's' : '') . " sur $total." : '';
    }

    /**
     * Clôture repoussée sur une offre publiée : e-mail « offre prolongée »
     * (toggle auto_offre_prolongee) + réarmement du rappel J-5 si la nouvelle
     * date laisse plus de REMINDER_DAYS jours.
     */
    private function handleProlongation(int $id, string $newClosesAt): string
    {
        if (strtotime($newClosesAt) > time() + JobOffer::REMINDER_DAYS * 86400) {
            JobOffer::resetReminder($id);
        }

        if (!Mailer::autoEnabled('offre_prolongee')) {
            return '';
        }
        $offer = JobOffer::find($id);
        [$sent, $total] = JobNotifier::sendProlongation($offer);
        Audit::log('job_offer.prolong', 'job_offer', $id, [
            'mode' => 'auto', 'closes_at' => $newClosesAt, 'destinataires' => $total, 'envoyes' => $sent,
        ]);
        return $total > 0 ? " E-mail « offre prolongée » envoyé à $sent destinataire" . ($sent > 1 ? 's' : '') . " sur $total." : '';
    }

    /** POST /admin/emplois/{id}/publish — publier / dépublier. */
    public function publish(Request $request): void
    {
        $id = (int) $request->params['id'];
        $offer = JobOffer::find($id) ?: Response::abort(404, 'Offre introuvable');
        $publish = !(int) $offer['is_published'];
        JobOffer::setPublished($id, $publish);
        Audit::log($publish ? 'job_offer.publish' : 'job_offer.unpublish', 'job_offer', $id, [
            'title' => $offer['title'],
        ]);

        $notice = $publish ? $this->autoAnnounce($id) : '';
        flash('success', ($publish ? 'Offre publiée.' : 'Offre dépubliée.') . $notice);
        Response::redirect('/admin/emplois');
    }

    /** POST /admin/emplois/{id}/archive — archive l'offre (et la dépublie). */
    public function archive(Request $request): void
    {
        $id = (int) $request->params['id'];
        $offer = JobOffer::find($id) ?: Response::abort(404, 'Offre introuvable');
        if (!empty($offer['archived_at'])) {
            flash('error', 'Cette offre est déjà archivée.');
            Response::redirect('/admin/emplois');
        }
        JobOffer::archive($id);
        Audit::log('job_offer.archive', 'job_offer', $id, ['title' => $offer['title']]);
        flash('success', 'Offre archivée (et dépubliée). Retrouvez-la avec ses candidatures dans les Archives.');
        Response::redirect('/admin/emplois');
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->params['id'];
        $offer = JobOffer::find($id) ?: Response::abort(404, 'Offre introuvable');

        // Les candidatures partent en cascade : supprimer d'abord leurs CV
        foreach (JobApplication::allForOffer($id) as $application) {
            Uploader::deleteCv($application['cv_path'] ?? null);
        }
        // Idem pour les fichiers des affiches (les lignes partent en cascade)
        foreach (JobOfferImage::allForOffer($id) as $image) {
            Uploader::deleteOffreImage($image['path']);
        }
        JobOffer::delete($id);
        Audit::log('job_offer.delete', 'job_offer', $id, ['title' => $offer['title']]);
        flash('success', 'Offre supprimée (candidatures et CV associés inclus).');
        Response::redirect('/admin/emplois');
    }

    /**
     * POST /admin/emplois/{id}/announce — annonce manuelle « offre publiée »
     * (bouton Exécuter) : ignore les interrupteurs d'automatisation.
     */
    public function announce(Request $request): void
    {
        $id = (int) $request->params['id'];
        $offer = JobOffer::find($id) ?: Response::abort(404, 'Offre introuvable');
        $return = $request->input('return') === 'automations'
            ? '/admin/automations'
            : "/admin/emplois/$id/edit";

        if (!(int) $offer['is_published']) {
            flash('error', 'Impossible d\'annoncer une offre non publiée.');
            Response::redirect($return);
        }

        [$sent, $total, $lastError] = JobNotifier::sendPublication($offer, true);
        Audit::log('job_offer.announce', 'job_offer', $id, [
            'mode' => 'manuel', 'destinataires' => $total, 'envoyes' => $sent,
        ]);

        if ($total === 0) {
            flash('error', 'Aucun destinataire avec e-mail : aucune annonce envoyée.');
        } elseif ($sent === $total) {
            flash('success', "Annonce de l'offre envoyée à $sent destinataire" . ($sent > 1 ? 's' : '') . '.');
        } else {
            flash('error', "Annonce envoyée à $sent destinataire" . ($sent > 1 ? 's' : '')
                . " sur $total. Dernière erreur : " . ($lastError ?? 'inconnue'));
        }
        Response::redirect($return);
    }

    /**
     * POST /admin/emplois/reminders/run — exécution manuelle des rappels J-5
     * dus (bouton Exécuter de la carte Automatisations) : ignore les toggles.
     */
    public function runReminders(Request $request): void
    {
        $processed = JobNotifier::processDueReminders(true);
        if (!$processed) {
            flash('success', 'Aucun rappel dû : aucune offre publiée n\'arrive à échéance dans les '
                . JobOffer::REMINDER_DAYS . ' prochains jours (ou les rappels ont déjà été envoyés).');
        } else {
            $parts = [];
            foreach ($processed as $title => [$sent, $total]) {
                $parts[] = "« $title » : $sent/$total";
            }
            flash('success', count($processed) . ' rappel' . (count($processed) > 1 ? 's' : '')
                . ' traité' . (count($processed) > 1 ? 's' : '') . ' — ' . implode(' · ', $parts) . '.');
        }
        Response::redirect('/admin/automations');
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $redirect = $id ? "/admin/emplois/$id/edit" : '/admin/emplois/create';

        $title = $request->input('title', '');
        if ($title === '') {
            flash('error', 'Le titre est obligatoire.');
            Response::redirect($redirect);
        }

        $closesAt = $this->datetimeOrNull($request->input('closes_at'));
        if (!$closesAt) {
            flash('error', 'La date de clôture est obligatoire.');
            Response::redirect($redirect);
        }

        $contractType = $request->input('contract_type', 'Autre');
        if (!in_array($contractType, JobOffer::CONTRACT_TYPES, true)) {
            $contractType = 'Autre';
        }

        $slug = $this->slugify($request->input('slug', '') ?: $title);
        // Unicité du slug (contrainte UNIQUE en base) : suffixe si déjà pris
        $existing = JobOffer::findBySlug($slug);
        if ($existing && (int) $existing['id'] !== (int) $id) {
            $slug .= '-' . time();
        }

        return [
            'title'         => mb_substr($title, 0, 200),
            'slug'          => mb_substr($slug, 0, 200),
            'description'   => $request->input('description') ?: null,
            'location'      => mb_substr($request->input('location', '') ?? '', 0, 255) ?: null,
            'contract_type' => $contractType,
            'salary'        => mb_substr($request->input('salary', '') ?? '', 0, 100) ?: null,
            'closes_at'     => $closesAt,
            'is_published'  => $request->input('is_published') === '1' ? 1 : 0,
        ];
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
        return trim($text, '-') ?: 'offre-' . time();
    }
}
