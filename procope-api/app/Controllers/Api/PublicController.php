<?php

namespace App\Controllers\Api;

use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Models\ContactMessage;
use App\Models\Formation;
use App\Models\FormationGallery;
use App\Models\FormationGalleryImage;
use App\Models\Inscription;
use App\Models\IncubationCall;
use App\Models\IncubatedProject;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\JobOfferImage;
use App\Models\ProjectApplication;
use App\Models\ProjectImage;
use App\Models\Testimonial;
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

    /** GET /api/formations/active — config du formulaire public (+ liste des ouvertes). */
    public function activeFormation(Request $request): void
    {
        $openRows = Formation::openAll();
        $available = [];
        foreach ($openRows as $row) {
            if (self::formationIsFull($row)) {
                continue;
            }
            $available[] = $row;
        }

        if (!$available) {
            $latest = Formation::latest();
            $message = $latest['message_fermeture']
                ?? 'Les inscriptions sont actuellement fermées. Contactez-nous pour plus d\'informations.';
            if ($openRows) {
                $full = $openRows[0];
                $message = 'Toutes les places sont prises pour cette session. '
                    . ($full['contact_phone'] ? 'Contactez-nous au ' . $full['contact_phone'] . ' pour la liste d\'attente.' : '');
            }
            Response::json([
                'open'    => false,
                'message' => $message,
                'items'   => [],
            ]);
        }

        $formation = $available[0];

        Response::json([
            'open'      => true,
            'formation' => self::formationPublic($formation),
            'items'     => array_map(static fn (array $row): array => self::formationPublic($row), $available),
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

    /** Places max atteintes (statuts qui occupent une place). */
    private static function formationIsFull(array $formation): bool
    {
        if ($formation['places_max'] === null) {
            return false;
        }
        $taken = Formation::countInscriptions(
            (int) $formation['id'],
            ['preinscrit', 'preuve_recue', 'valide', 'paiement_partiel']
        );
        return $taken >= (int) $formation['places_max'];
    }

    /** Payload public d'une formation ouverte (formulaire + badge hero). */
    private static function formationPublic(array $formation): array
    {
        $slots = array_map(static fn (array $slot) => [
            'label'     => $slot['label'],
            'starts_at' => $slot['starts_at'],
            'ends_at'   => $slot['ends_at'],
        ], Formation::slots((int) $formation['id']));

        return [
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
        ];
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

    /** GET /api/projets — projets incubés publiés. */
    public function listProjects(Request $request): void
    {
        Response::json([
            'projects' => array_map(
                static fn (array $project): array => self::projectPublic($project),
                IncubatedProject::published()
            ),
        ]);
    }

    /** GET /api/projets/{slug} — détail d'un projet publié. */
    public function showProject(Request $request): void
    {
        $project = IncubatedProject::findBySlug((string) ($request->params['slug'] ?? ''));
        if (!$project || !(int) $project['is_published'] || !empty($project['archived_at'])) {
            Response::json(['error' => "Ce projet n'existe pas ou n'est plus disponible."], 404);
        }
        Response::json(['project' => self::projectPublic($project, true)]);
    }

    private static function projectPublic(array $project, bool $withDescription = false): array
    {
        $projectId = (int) $project['id'];
        $pitch = trim((string) ($project['pitch'] ?? ''));
        $description = trim((string) ($project['description'] ?? ''));

        $out = [
            'title'        => $project['title'],
            'slug'         => $project['slug'],
            'pitch'        => $pitch,
            'sector'       => $project['sector'],
            'stage'        => $project['stage'],
            'stage_label'  => IncubatedProject::STAGE_LABELS[$project['stage'] ?? ''] ?? ($project['stage'] ?? ''),
            'country'      => $project['country'],
            'year'         => $project['year'] !== null ? (int) $project['year'] : null,
            'published_at' => $project['created_at'],
        ];
        if ($withDescription) {
            $out['description'] = $description;
            $out['website'] = $project['website'];
            $out['socials'] = array_values(array_filter(array_map(
                'trim',
                preg_split('/\r\n|\r|\n/', (string) ($project['socials'] ?? '')) ?: []
            )));
            $out['images'] = array_map(
                static fn (array $img): string => self::projectImageUrl((string) $img['path']),
                ProjectImage::allForProject($projectId)
            );
            $out['image'] = $out['images'][0] ?? null;
        } else {
            $excerptSource = $pitch !== '' ? $pitch : $description;
            $out['excerpt'] = mb_strlen($excerptSource) > 220
                ? rtrim(mb_substr($excerptSource, 0, 220)) . '…'
                : $excerptSource;
            $main = ProjectImage::mainForProject($projectId);
            $out['image'] = $main ? self::projectImageUrl((string) $main['path']) : null;
        }
        return $out;
    }

    private static function projectImageUrl(string $path): string
    {
        return rtrim((string) Env::get('APP_URL', ''), '/') . '/uploads/projets/' . rawurlencode($path);
    }

    /** GET /api/appels — appels à incubation ouverts. */
    public function listCalls(Request $request): void
    {
        Response::json([
            'calls' => array_map(
                static fn (array $call): array => self::callPublic($call),
                IncubationCall::openPublished()
            ),
        ]);
    }

    /** GET /api/appels/{slug} */
    public function showCall(Request $request): void
    {
        $call = IncubationCall::findBySlug((string) ($request->params['slug'] ?? ''));
        if (!$call || !IncubationCall::isOpen($call)) {
            Response::json(['error' => "Cet appel n'existe pas ou n'est plus ouvert."], 404);
        }
        Response::json(['call' => self::callPublic($call, true)]);
    }

    private static function callPublic(array $call, bool $withDescription = false): array
    {
        $out = [
            'title'     => $call['title'],
            'slug'      => $call['slug'],
            'sector'    => $call['sector'],
            'opens_at'  => $call['opens_at'],
            'closes_at' => $call['closes_at'],
            'open'      => IncubationCall::isOpen($call),
        ];
        if ($withDescription) {
            $out['description'] = $call['description'];
        } else {
            $desc = trim((string) ($call['description'] ?? ''));
            $out['excerpt'] = mb_strlen($desc) > 220 ? rtrim(mb_substr($desc, 0, 220)) . '…' : $desc;
        }
        return $out;
    }

    /** POST /api/appels/{slug}/depot — candidature à un appel. */
    public function applyToCall(Request $request): void
    {
        $slug = (string) ($request->params['slug'] ?? '');
        $this->storeProjectDeposit($request, null, $slug !== '' ? $slug : null);
    }

    /** POST /api/projets/depot — candidature spontanée (call_id vide). */
    public function applyToProject(Request $request): void
    {
        $this->storeProjectDeposit($request, null, null);
    }

    /**
     * Enregistre un dépôt : appel (callSlug) ou spontané.
     * $projectSlug n'est plus utilisé pour lier un dossier à une vitrine.
     */
    private function storeProjectDeposit(Request $request, ?string $projectSlug, ?string $callSlug = null): void
    {
        $ip = $request->ip();

        if (($request->input('website') ?? '') !== '') {
            Response::json(['ok' => true, 'message' => 'Dépôt enregistré.']);
        }

        if (Security::rateLimited($ip, 'depot_projet', 5, 60)) {
            Response::json(['error' => 'Trop de dépôts envoyés depuis votre connexion. Réessayez dans une heure.'], 429);
        }

        $call = null;
        $callId = null;
        if ($callSlug) {
            $call = IncubationCall::findBySlug($callSlug);
            if (!$call || !IncubationCall::isOpen($call)) {
                Response::json(['error' => "Cet appel n'existe pas ou n'est plus ouvert."], 404);
            }
            $callId = (int) $call['id'];
        }

        $errors = [];
        $fullName = $request->input('full_name', '') ?? '';
        $email = mb_strtolower(trim($request->input('email', '') ?? ''));
        $phone = preg_replace('/[^\d+ ]/', '', $request->input('phone', '') ?? '');
        $projectName = trim($request->input('project_name', '') ?? '');
        $sector = trim($request->input('sector', '') ?? '');
        $pitch = trim($request->input('pitch', '') ?? '');
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
        if ($projectName !== '' && mb_strlen($projectName) > 200) {
            $errors['project_name'] = 'Le nom du projet est trop long (200 caractères maximum).';
        }
        if (mb_strlen($pitch) > 3000) {
            $errors['pitch'] = 'Le pitch est trop long (3000 caractères maximum).';
        }
        if (mb_strlen($message) > 3000) {
            $errors['message'] = 'Votre message est trop long (3000 caractères maximum).';
        }
        if ($errors) {
            Response::json(['error' => 'Certains champs sont invalides.', 'fields' => $errors], 422);
        }

        if ($callId !== null) {
            if (ProjectApplication::existsForCall($callId, $email)) {
                Response::json([
                    'error' => 'Une candidature existe déjà avec cette adresse e-mail pour cet appel. '
                        . 'Un seul dossier est accepté par appel.',
                ], 409);
            }
        } elseif (ProjectApplication::existsActiveSpontaneous($email, $projectName)) {
            $label = trim($projectName);
            Response::json([
                'error' => 'Un dossier « ' . $label . ' » existe déjà pour cet e-mail.',
            ], 409);
        }

        $file = null;
        if ($upload = $request->file('pitch_deck') ?? $request->file('file')) {
            try {
                $file = Uploader::storePitch($upload);
            } catch (\RuntimeException $e) {
                Response::json(['error' => $e->getMessage(), 'fields' => ['pitch_deck' => $e->getMessage()]], 422);
            }
        }

        Security::recordHit($ip, 'depot_projet');

        $id = ProjectApplication::create([
            'project_id'   => null,
            'call_id'      => $callId,
            'full_name'    => mb_substr($fullName, 0, 190),
            'email'        => mb_substr($email, 0, 190),
            'phone'        => mb_substr($phone, 0, 30) ?: null,
            'project_name' => $projectName !== '' ? mb_substr($projectName, 0, 200) : null,
            'sector'       => $sector !== '' ? mb_substr($sector, 0, 190) : null,
            'pitch'        => $pitch !== '' ? mb_substr($pitch, 0, 3000) : null,
            'message'      => $message !== '' ? mb_substr($message, 0, 3000) : null,
            'file_path'    => $file['path'] ?? null,
            'file_mime'    => $file['mime'] ?? null,
            'file_name'    => $file['original'] ?? null,
            'ip'           => $ip,
            'user_agent'   => $request->userAgent(),
        ]);

        $this->sendProjectDepositMails($id, $call, $fullName, $email, $phone, $projectName, $sector, $pitch, $message, (bool) $file);

        Response::json([
            'ok'      => true,
            'message' => 'Votre dépôt a bien été enregistré. '
                . 'Notre équipe étudiera votre dossier et vous recontactera si votre projet est retenu.',
        ], 201);
    }

    private function sendProjectDepositMails(
        int $id,
        ?array $project,
        string $fullName,
        string $email,
        string $phone,
        string $projectName,
        string $sector,
        string $pitch,
        string $message,
        bool $hasFile
    ): void {
        if ($email && Mailer::autoEnabled('depot_projet')) {
            $mailData = [
                'full_name'    => $fullName,
                'project_name' => $projectName,
                'call_title'   => $project['title'] ?? '',
                'project'      => $project,
            ];
            $html = Mailer::template('depot_projet', $mailData);
            $subject = Mailer::subjectFor('depot_projet', $mailData, 'Votre dépôt de projet — PROCOPE Afrique');
            Mailer::send($email, $subject, $html, 'depot_projet', null);
        }

        if (Mailer::autoEnabled('alerte_equipe')) {
            $alertData = [
                'depot_id'     => $id,
                'full_name'    => $fullName,
                'email'        => $email,
                'phone'        => $phone,
                'project_name' => $projectName,
                'sector'       => $sector,
                'pitch'        => $pitch,
                'message_text' => $message,
                'has_file'     => $hasFile,
                'call_title'   => $project['title'] ?? '',
                'project'      => $project,
            ];
            $html = Mailer::template('depot_alerte', $alertData);
            $subject = Mailer::subjectFor('depot_alerte', $alertData, 'Nouveau dépôt de projet — ' . $fullName);
            Mailer::notifyTeam($subject, $html, 'alerte_depot', null);
        }
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

    /** GET /api/galeries — albums publiés. ?kind=photos|affiche (défaut photos). */
    public function listGalleries(Request $request): void
    {
        $kind = FormationGallery::normalizeKind($request->input('kind', FormationGallery::KIND_PHOTOS));
        Response::json([
            'kind'      => $kind,
            'galleries' => array_map(
                static fn (array $gallery): array => self::galleryPublic($gallery),
                FormationGallery::published($kind)
            ),
        ]);
    }

    private static function galleryPublic(array $gallery): array
    {
        $month = $gallery['month'] !== null ? (int) $gallery['month'] : null;
        $images = array_map(
            static fn (array $img): array => [
                'url'     => self::galleryImageUrl((string) $img['path']),
                'caption' => $img['caption'] !== null && $img['caption'] !== ''
                    ? (string) $img['caption']
                    : null,
            ],
            FormationGalleryImage::allForGallery((int) $gallery['id'])
        );

        return [
            'id'          => (int) $gallery['id'],
            'kind'        => FormationGallery::normalizeKind($gallery['kind'] ?? null),
            'title'       => $gallery['title'],
            'year'        => (int) $gallery['year'],
            'month'       => $month,
            'month_label' => FormationGallery::monthLabel($month),
            'description' => $gallery['description'],
            'cover'       => $images[0]['url'] ?? null,
            'images'      => $images,
        ];
    }

    private static function galleryImageUrl(string $path): string
    {
        return rtrim((string) Env::get('APP_URL', ''), '/') . '/uploads/galeries/' . rawurlencode($path);
    }

    /** GET /api/temoignages — témoignages publiés uniquement. */
    public function listTestimonials(Request $request): void
    {
        Response::json([
            'items' => array_map(
                static fn (array $row): array => Testimonial::toPublic($row),
                Testimonial::published()
            ),
        ]);
    }

    /** POST /api/temoignages — dépôt public, statut en_attente. */
    public function createTestimonial(Request $request): void
    {
        $ip = $request->ip();

        if (($request->input('website') ?? '') !== '') {
            Response::json(['ok' => true, 'message' => 'Merci, votre témoignage sera lu par l\'équipe avant publication.']);
        }

        if (Security::rateLimited($ip, 'temoignage', 5, 60)) {
            Response::json(['error' => 'Trop de témoignages envoyés depuis votre connexion. Réessayez dans une heure.'], 429);
        }

        $errors = [];
        $name = trim((string) ($request->input('name', '') ?? ''));
        $role = trim((string) ($request->input('role', '') ?? ''));
        $quote = trim((string) ($request->input('quote', '') ?? ''));
        $email = mb_strtolower(trim((string) ($request->input('email', '') ?? '')));

        if (mb_strlen($name) < 2) {
            $errors['name'] = 'Veuillez indiquer votre nom (2 caractères minimum).';
        } elseif (mb_strlen($name) > 150) {
            $errors['name'] = 'Le nom est trop long (150 caractères maximum).';
        }
        if ($role !== '' && mb_strlen($role) > 190) {
            $errors['role'] = 'Le rôle est trop long (190 caractères maximum).';
        }
        if (mb_strlen($quote) < 20) {
            $errors['quote'] = 'Votre témoignage est trop court (20 caractères minimum).';
        } elseif (mb_strlen($quote) > 2000) {
            $errors['quote'] = 'Votre témoignage est trop long (2000 caractères maximum).';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Adresse e-mail invalide.';
        }

        $photo = null;
        $file = $request->file('photo');
        if ($file) {
            try {
                $photo = Uploader::storeTemoignage($file);
            } catch (\RuntimeException $e) {
                $errors['photo'] = $e->getMessage();
            }
        }

        if ($errors) {
            if ($photo) {
                Uploader::deleteTemoignage($photo['path']);
            }
            Response::json(['error' => 'Certains champs sont invalides.', 'fields' => $errors], 422);
        }

        Security::recordHit($ip, 'temoignage');

        $id = Testimonial::create([
            'author_name'  => mb_substr($name, 0, 150),
            'author_email' => $email !== '' ? mb_substr($email, 0, 190) : null,
            'role_title'   => $role !== '' ? mb_substr($role, 0, 190) : null,
            'quote'        => $quote,
            'photo_path'   => $photo['path'] ?? null,
            'photo_mime'   => $photo['mime'] ?? null,
            'source'       => 'public',
            'statut'       => 'en_attente',
            'published_at' => null,
        ]);

        $this->sendTestimonialMails($id, $name, $email, $role, $quote, (bool) $photo);

        Response::json([
            'ok'      => true,
            'message' => 'Merci, votre témoignage sera lu par l\'équipe avant publication.',
        ], 201);
    }

    private function sendTestimonialMails(
        int $id,
        string $name,
        string $email,
        string $role,
        string $quote,
        bool $hasPhoto
    ): void {
        $mailData = [
            'testimonial_id' => $id,
            'name'           => $name,
            'email'          => $email,
            'role'           => $role,
            'quote'          => $quote,
            'has_photo'      => $hasPhoto,
        ];

        if ($email !== '' && Mailer::autoEnabled('temoignage_recu')) {
            $html = Mailer::template('temoignage_recu', $mailData);
            $subject = Mailer::subjectFor('temoignage_recu', $mailData, 'Votre témoignage — PROCOPE Afrique');
            Mailer::send($email, $subject, $html, 'temoignage_recu', null);
        }

        if (Mailer::autoEnabled('temoignage_alerte')) {
            $html = Mailer::template('temoignage_alerte', $mailData);
            $subject = Mailer::subjectFor('temoignage_alerte', $mailData, 'Nouveau témoignage — ' . $name);
            Mailer::notifyTeam($subject, $html, 'alerte_temoignage', null);
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
