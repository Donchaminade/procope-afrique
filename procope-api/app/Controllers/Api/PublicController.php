<?php

namespace App\Controllers\Api;

use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Models\ContactMessage;
use App\Models\Formation;
use App\Models\Inscription;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\JobOfferImage;
use App\Services\JobNotifier;
use App\Services\Mailer;
use App\Services\Security;
use App\Services\Uploader;

final class PublicController
{
    private const MODULES = [
        'Comment voyager grâce à sa carte CFE',
        'Créer son entreprise en toute légalité',
        'Maîtriser la gestion des obligations fiscales et sociales (OTR & CNSS)',
        'Accès au financement',
        'Tous les modules',
    ];

    private const SOURCES = ['Facebook', 'WhatsApp', 'Instagram', 'Ami / proche', 'TikTok', 'Autre'];

    /** GET /api/formations/active — config du formulaire public. */
    public function activeFormation(Request $request): void
    {
        $formation = Formation::active();

        if (!$formation) {
            $latest = Formation::latest();
            Response::json([
                'open'    => false,
                'message' => $latest['message_fermeture']
                    ?? 'Les inscriptions sont actuellement fermées. Contactez-nous pour plus d\'informations.',
            ]);
        }

        // Complet ? -> ferme automatiquement le formulaire
        if ($formation['places_max'] !== null) {
            $taken = Formation::countInscriptions(
                (int) $formation['id'],
                ['preinscrit', 'preuve_recue', 'valide', 'paiement_partiel']
            );
            if ($taken >= (int) $formation['places_max']) {
                Response::json([
                    'open'    => false,
                    'message' => 'Toutes les places sont prises pour cette session. '
                        . ($formation['contact_phone'] ? 'Contactez-nous au ' . $formation['contact_phone'] . ' pour la liste d\'attente.' : ''),
                ]);
            }
        }

        $slots = array_map(static fn (array $slot) => [
            'label'     => $slot['label'],
            'starts_at' => $slot['starts_at'],
            'ends_at'   => $slot['ends_at'],
        ], Formation::slots((int) $formation['id']));

        Response::json([
            'open'      => true,
            'formation' => [
                'id'            => (int) $formation['id'],
                'titre'         => $formation['titre'],
                'intro'         => $formation['intro'],
                'programme'     => $formation['programme']
                    ? array_values(array_filter(array_map('trim', explode("\n", $formation['programme']))))
                    : [],
                'prix'          => (float) $formation['prix'],
                'devise'        => $formation['devise'],
                'lieu'          => $formation['lieu'],
                'contact_phone' => $formation['contact_phone'],
                'affiche_url'   => self::afficheUrl($formation),
                'slots'         => $slots,
            ],
            'options' => [
                'modules'         => self::MODULES,
                'sources'         => self::SOURCES,
                'payment_methods' => [
                    ['value' => 'mobile_money', 'label' => 'Mobile Money (TMoney / Flooz)'],
                    ['value' => 'ecobank', 'label' => 'Ecobank'],
                ],
            ],
        ]);
    }

    /** URL publique absolue de l'affiche de la formation, ou null. */
    private static function afficheUrl(array $formation): ?string
    {
        if (empty($formation['affiche_path'])) {
            return null;
        }
        $base = rtrim((string) Env::get('APP_URL', ''), '/');
        return $base . '/uploads/affiches/' . rawurlencode($formation['affiche_path']);
    }

    /** POST /api/inscriptions — multipart (champs + preuve optionnelle). */
    public function createInscription(Request $request): void
    {
        $ip = $request->ip();

        // Honeypot : champ caché que les humains laissent vide
        if (($request->input('website') ?? '') !== '') {
            Response::json(['ok' => true, 'message' => 'Inscription enregistrée.']);
        }

        if (Security::rateLimited($ip, 'inscription', 5, 60)) {
            Response::json(['error' => 'Trop de tentatives depuis votre connexion. Réessayez dans une heure.'], 429);
        }

        $formation = Formation::active();
        if (!$formation) {
            Response::json(['error' => 'Les inscriptions sont actuellement fermées.'], 409);
        }
        $formationId = (int) $formation['id'];

        // --- Validation ---
        $errors = [];
        $fullName = $request->input('full_name', '') ?? '';
        $phone = preg_replace('/[^\d+ ]/', '', $request->input('phone', '') ?? '');
        $email = mb_strtolower($request->input('email', '') ?? '');
        $gender = $request->input('gender', '');
        $paymentMethod = $request->input('payment_method', '');
        $source = $request->input('acquisition_source', '');
        $modules = array_values(array_intersect($request->inputArray('modules'), self::MODULES));

        if (mb_strlen($fullName) < 3) {
            $errors['full_name'] = 'Nom et prénom obligatoires.';
        }
        if (strlen(preg_replace('/\D/', '', $phone)) < 8) {
            $errors['phone'] = 'Numéro de téléphone invalide.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Adresse e-mail invalide.';
        }
        if (!in_array($gender, ['M', 'F'], true)) {
            $errors['gender'] = 'Veuillez indiquer votre sexe.';
        }
        if (!in_array($paymentMethod, ['mobile_money', 'ecobank'], true)) {
            $errors['payment_method'] = 'Veuillez choisir une modalité de paiement.';
        }

        // Paiement total ou partiel
        $prix = (float) $formation['prix'];
        $paymentType = $request->input('payment_type', 'total');
        if (!in_array($paymentType, ['total', 'partiel'], true)) {
            $errors['payment_type'] = 'Veuillez indiquer si vous payez la totalité ou une partie.';
            $paymentType = 'total';
        }
        $amountDeclared = $prix;
        if ($paymentType === 'partiel') {
            $raw = str_replace([' ', ','], ['', '.'], $request->input('amount_declared', '') ?? '');
            $amountDeclared = is_numeric($raw) ? round((float) $raw, 2) : -1.0;
            if ($amountDeclared <= 0 || $amountDeclared >= $prix) {
                $errors['amount_declared'] = 'Pour un paiement partiel, indiquez un montant supérieur à 0 et inférieur à '
                    . number_format($prix, 0, ',', ' ') . ' ' . ($formation['devise'] ?? 'XOF') . '.';
            }
        }
        if ($request->input('consent_image') !== '1') {
            $errors['consent_image'] = 'Cette autorisation est requise.';
        }
        if ($source !== '' && !in_array($source, self::SOURCES, true)) {
            $source = 'Autre';
        }

        if ($errors) {
            Response::json(['error' => 'Certains champs sont invalides.', 'fields' => $errors], 422);
        }

        if (Inscription::existsForFormation($formationId, $phone, $email ?: null)) {
            Response::json([
                'error' => 'Une inscription existe déjà avec ce numéro ou cet email pour cette formation. '
                    . 'Contactez-nous si vous pensez qu\'il s\'agit d\'une erreur.',
            ], 409);
        }

        // --- Preuve de paiement (optionnelle au dépôt) ---
        $proof = null;
        if ($file = $request->file('payment_proof')) {
            try {
                $proof = Uploader::storeProof($file);
            } catch (\RuntimeException $e) {
                Response::json(['error' => $e->getMessage(), 'fields' => ['payment_proof' => $e->getMessage()]], 422);
            }
        }

        Security::recordHit($ip, 'inscription');

        $uuid = self::uuid4();
        $id = Inscription::create([
            'uuid'                => $uuid,
            'formation_id'        => $formationId,
            'full_name'           => mb_substr($fullName, 0, 190),
            'gender'              => $gender,
            'phone'               => mb_substr($phone, 0, 30),
            'email'               => $email ?: null,
            'city'                => mb_substr($request->input('city', '') ?? '', 0, 150) ?: null,
            'professional_status' => mb_substr($request->input('professional_status', '') ?? '', 0, 190) ?: null,
            'is_entrepreneur'     => match ($request->input('is_entrepreneur')) {
                '1' => 1, '0' => 0, default => null,
            },
            'company_name'        => mb_substr($request->input('company_name', '') ?? '', 0, 190) ?: null,
            'sector'              => mb_substr($request->input('sector', '') ?? '', 0, 190) ?: null,
            'motivation'          => mb_substr($request->input('motivation', '') ?? '', 0, 2000) ?: null,
            'modules'             => $modules ? json_encode($modules, JSON_UNESCAPED_UNICODE) : null,
            'payment_method'      => $paymentMethod,
            'payment_type'        => $paymentType,
            'amount_declared'     => $amountDeclared,
            'payment_proof_path'  => $proof['path'] ?? null,
            'payment_proof_mime'  => $proof['mime'] ?? null,
            'payment_proof_name'  => $proof['original'] ?? null,
            'acquisition_source'  => $source ?: null,
            'acquisition_other'   => mb_substr($request->input('acquisition_other', '') ?? '', 0, 190) ?: null,
            'consent_image'       => 1,
            'statut'              => $proof ? 'preuve_recue' : 'preinscrit',
            'ip'                  => $ip,
            'user_agent'          => $request->userAgent(),
        ]);

        $this->sendMails($id, $formation, $fullName, $email, $phone, (bool) $proof, $paymentType, $amountDeclared);

        Response::json([
            'ok'        => true,
            'reference' => $uuid,
            'message'   => $proof
                ? 'Inscription enregistrée avec votre preuve de paiement. Vous recevrez une confirmation après vérification.'
                : 'Préinscription enregistrée. Envoyez votre preuve de paiement pour confirmer votre place.',
        ], 201);
    }

    /** GET /api/offres — offres d'emploi publiées et non clôturées. */
    public function listOffers(Request $request): void
    {
        // Déclenchement opportuniste des rappels J-5 (1 visite sur 20) : les
        // rappels partent même sans cron ; le verrou de JobNotifier évite les
        // traitements concurrents et ne doit jamais bloquer la réponse API.
        if (random_int(1, 20) === 1) {
            try {
                JobNotifier::processDueReminders();
            } catch (\Throwable) {
                // silencieux : l'API publique reste disponible quoi qu'il arrive
            }
        }

        Response::json([
            'offers' => array_map(
                static fn (array $offer): array => self::offerPublic($offer),
                JobOffer::published()
            ),
        ]);
    }

    /** GET /api/offres/{slug} — détail complet d'une offre publiée. */
    public function showOffer(Request $request): void
    {
        $offer = JobOffer::findBySlug((string) ($request->params['slug'] ?? ''));
        if (!$offer || !(int) $offer['is_published']) {
            Response::json(['error' => "Cette offre n'existe pas ou n'est plus disponible."], 404);
        }
        Response::json(['offer' => self::offerPublic($offer, true)]);
    }

    /** Champs publics d'une offre (liste : extrait + affiche principale ; détail : description + galerie). */
    private static function offerPublic(array $offer, bool $withDescription = false): array
    {
        $closesTs = (int) strtotime((string) $offer['closes_at']);
        $description = trim((string) ($offer['description'] ?? ''));
        $offerId = (int) $offer['id'];

        $out = [
            'title'         => $offer['title'],
            'slug'          => $offer['slug'],
            'contract_type' => $offer['contract_type'],
            'location'      => $offer['location'],
            'salary'        => $offer['salary'],
            'closes_at'     => $offer['closes_at'],
            'days_left'     => max(0, (int) ceil(($closesTs - time()) / 86400)),
            'open'          => $closesTs > time(),
            'published_at'  => $offer['created_at'],
        ];
        if ($withDescription) {
            $out['description'] = $description;
            // Galerie complète, affiche principale en premier
            $out['images'] = array_map(
                static fn (array $img): string => self::offerImageUrl((string) $img['path']),
                JobOfferImage::allForOffer($offerId)
            );
            $out['image'] = $out['images'][0] ?? null;
        } else {
            $out['excerpt'] = mb_strlen($description) > 220
                ? rtrim(mb_substr($description, 0, 220)) . '…'
                : $description;
            $main = JobOfferImage::mainForOffer($offerId);
            $out['image'] = $main ? self::offerImageUrl((string) $main['path']) : null;
        }
        return $out;
    }

    /** URL publique absolue d'une affiche d'offre (le site tourne sur une autre origine). */
    private static function offerImageUrl(string $path): string
    {
        return rtrim((string) Env::get('APP_URL', ''), '/') . '/uploads/offres/' . rawurlencode($path);
    }

    /** POST /api/offres/{slug}/postuler — multipart (champs + CV PDF optionnel). */
    public function applyToOffer(Request $request): void
    {
        $ip = $request->ip();

        // Honeypot : champ caché que les humains laissent vide
        if (($request->input('website') ?? '') !== '') {
            Response::json(['ok' => true, 'message' => 'Candidature enregistrée.']);
        }

        if (Security::rateLimited($ip, 'candidature', 5, 60)) {
            Response::json(['error' => 'Trop de candidatures envoyées depuis votre connexion. Réessayez dans une heure.'], 429);
        }

        $offer = JobOffer::findBySlug((string) ($request->params['slug'] ?? ''));
        if (!$offer || !(int) $offer['is_published']) {
            Response::json(['error' => "Cette offre n'existe pas ou n'est plus disponible."], 404);
        }
        if (strtotime((string) $offer['closes_at']) <= time()) {
            Response::json(['error' => 'Les candidatures pour cette offre sont clôturées depuis le '
                . date('d/m/Y', strtotime((string) $offer['closes_at'])) . '.'], 409);
        }
        $offerId = (int) $offer['id'];

        // --- Validation ---
        $errors = [];
        $fullName = $request->input('full_name', '') ?? '';
        $email = mb_strtolower(trim($request->input('email', '') ?? ''));
        $phone = preg_replace('/[^\d+ ]/', '', $request->input('phone', '') ?? '');
        $message = trim($request->input('message', '') ?? '');

        if (mb_strlen($fullName) < 3) {
            $errors['full_name'] = 'Nom et prénom obligatoires (3 caractères minimum).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Adresse e-mail valide obligatoire (elle sert à vous répondre).';
        }
        if ($phone !== '' && strlen(preg_replace('/\D/', '', $phone)) < 8) {
            $errors['phone'] = 'Numéro de téléphone invalide.';
        }
        if (mb_strlen($message) > 3000) {
            $errors['message'] = 'Votre message est trop long (3000 caractères maximum).';
        }
        if ($errors) {
            Response::json(['error' => 'Certains champs sont invalides.', 'fields' => $errors], 422);
        }

        if (JobApplication::existsForOffer($offerId, $email)) {
            Response::json([
                'error' => 'Une candidature existe déjà avec cette adresse e-mail pour cette offre. '
                    . 'Contactez-nous si vous souhaitez la compléter.',
            ], 409);
        }

        // --- CV PDF optionnel ---
        $cv = null;
        if ($file = $request->file('cv')) {
            try {
                $cv = Uploader::storeCv($file);
            } catch (\RuntimeException $e) {
                Response::json(['error' => $e->getMessage(), 'fields' => ['cv' => $e->getMessage()]], 422);
            }
        }

        Security::recordHit($ip, 'candidature');

        $id = JobApplication::create([
            'offer_id'   => $offerId,
            'full_name'  => mb_substr($fullName, 0, 190),
            'email'      => mb_substr($email, 0, 190),
            'phone'      => mb_substr($phone, 0, 30) ?: null,
            'message'    => $message !== '' ? mb_substr($message, 0, 3000) : null,
            'cv_path'    => $cv['path'] ?? null,
            'cv_mime'    => $cv['mime'] ?? null,
            'cv_name'    => $cv['original'] ?? null,
            'ip'         => $ip,
            'user_agent' => $request->userAgent(),
        ]);

        $this->sendApplicationMails($id, $offer, $fullName, $email, $phone, $message, (bool) $cv);

        Response::json([
            'ok'      => true,
            'message' => 'Votre candidature a bien été enregistrée. '
                . 'Notre équipe étudiera votre dossier et vous recontactera si votre profil est retenu.',
        ], 201);
    }

    /**
     * Accusé de réception au candidat (toggle auto_candidature_emploi, OFF par
     * défaut) + alerte interne à l'équipe (suit le toggle des alertes internes
     * auto_alerte_equipe, comme les inscriptions).
     */
    private function sendApplicationMails(
        int $id,
        array $offer,
        string $fullName,
        string $email,
        string $phone,
        string $message,
        bool $hasCv
    ): void {
        $ctaUrl = JobNotifier::offerUrl($offer);

        if ($email && Mailer::autoEnabled('candidature_emploi')) {
            $mailData = ['full_name' => $fullName, 'offer' => $offer, 'cta_url' => $ctaUrl];
            $html = Mailer::template('candidature_emploi', $mailData);
            $subject = Mailer::subjectFor('candidature_emploi', $mailData, 'Votre candidature — ' . $offer['title']);
            Mailer::send($email, $subject, $html, 'candidature_emploi', null);
        }

        if (Mailer::autoEnabled('alerte_equipe')) {
            $alertData = [
                'candidature_id' => $id,
                'full_name'      => $fullName,
                'email'          => $email,
                'phone'          => $phone,
                'message_text'   => $message,
                'has_cv'         => $hasCv,
                'offer'          => $offer,
                'cta_url'        => $ctaUrl,
            ];
            $html = Mailer::template('candidature_alerte', $alertData);
            $subject = Mailer::subjectFor('candidature_alerte', $alertData, 'Nouvelle candidature — ' . $fullName);
            Mailer::notifyTeam($subject, $html, 'alerte_candidature', null);
        }
    }

    /** POST /api/contacts — message du formulaire de contact du site. */
    public function createContact(Request $request): void
    {
        $ip = $request->ip();

        // Honeypot : champ caché que les humains laissent vide
        if (($request->input('website') ?? '') !== '') {
            Response::json(['ok' => true, 'message' => 'Votre message a bien été envoyé.']);
        }

        if (Security::rateLimited($ip, 'contact', 5, 60)) {
            Response::json(['error' => 'Trop de messages envoyés depuis votre connexion. Réessayez dans une heure.'], 429);
        }

        $errors = [];
        $name = $request->input('name', '') ?? '';
        $email = mb_strtolower($request->input('email', '') ?? '');
        $phone = preg_replace('/[^\d+ ]/', '', $request->input('phone', '') ?? '');
        $subject = $request->input('subject', '') ?? '';
        $message = $request->input('message', '') ?? '';

        if (mb_strlen($name) < 3) {
            $errors['name'] = 'Veuillez indiquer votre nom (3 caractères minimum).';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Adresse e-mail invalide.';
        }
        if (mb_strlen($message) < 10) {
            $errors['message'] = 'Votre message est trop court (10 caractères minimum).';
        } elseif (mb_strlen($message) > 3000) {
            $errors['message'] = 'Votre message est trop long (3000 caractères maximum).';
        }

        if ($errors) {
            Response::json(['error' => 'Certains champs sont invalides.', 'fields' => $errors], 422);
        }

        Security::recordHit($ip, 'contact');

        $id = ContactMessage::create([
            'name'       => mb_substr($name, 0, 150),
            'email'      => $email ?: null,
            'phone'      => mb_substr($phone, 0, 30) ?: null,
            'subject'    => mb_substr($subject, 0, 190) ?: null,
            'message'    => $message,
            'ip'         => $ip,
            'user_agent' => $request->userAgent(),
        ]);

        if (Mailer::autoEnabled('contact_recu')) {
            $mailData = [
                'contact_id'   => $id,
                'name'         => $name,
                'email'        => $email,
                'phone'        => $phone,
                'subject'      => $subject,
                'message_text' => $message,
            ];
            $html = Mailer::template('contact', $mailData);
            $mailSubject = Mailer::subjectFor('contact', $mailData, 'Nouveau message de contact — ' . $name);
            Mailer::notifyTeam($mailSubject, $html, 'contact_recu', null);
        }

        Response::json([
            'ok'      => true,
            'message' => 'Votre message a bien été envoyé. Nous vous répondrons dans les meilleurs délais.',
        ], 201);
    }

    private function sendMails(
        int $id,
        array $formation,
        string $fullName,
        string $email,
        string $phone,
        bool $hasProof,
        string $paymentType = 'total',
        ?float $amountDeclared = null
    ): void {
        $slots = Formation::slots((int) $formation['id']);

        if ($email && Mailer::autoEnabled('confirmation')) {
            $mailData = [
                'full_name'       => $fullName,
                'formation'       => $formation,
                'slots'           => $slots,
                'has_proof'       => $hasProof,
                'payment_type'    => $paymentType,
                'amount_declared' => $amountDeclared,
            ];
            $html = Mailer::template('confirmation', $mailData);
            $subject = Mailer::subjectFor('confirmation', $mailData, 'Votre inscription — ' . $formation['titre']);
            Mailer::send($email, $subject, $html, 'confirmation', $id);
        }

        if (Mailer::autoEnabled('alerte_equipe')) {
            $alertData = [
                'inscription_id' => $id,
                'full_name'      => $fullName,
                'phone'          => $phone,
                'email'          => $email,
                'formation'      => $formation,
                'has_proof'      => $hasProof,
            ];
            $alert = Mailer::template('alert', $alertData);
            $alertSubject = Mailer::subjectFor('alert', $alertData, 'Nouvelle inscription — ' . $fullName);
            Mailer::notifyTeam($alertSubject, $alert, 'alerte_equipe', $id);
        }
    }

    private static function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
