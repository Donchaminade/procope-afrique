<?php

namespace App\Controllers\Admin;

use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Formation;
use App\Models\JobOffer;
use App\Models\MailLog;
use App\Models\MailTemplate;
use App\Models\Setting;
use App\Services\Audit;
use App\Services\Auth;
use App\Services\HtmlSanitizer;
use App\Services\Mailer;

/**
 * Centre d'automatisations : master switch, toggles des e-mails automatiques,
 * modèles (avec prévisualisation), journal des envois et e-mail de test.
 */
final class AutomationsController
{
    /**
     * E-mails automatiques pilotables (clé setting auto_<key>).
     * recipient : participant | equipe | anciens.
     */
    public const AUTO_MAILS = [
        'confirmation' => [
            'label'       => "Confirmation d'inscription",
            'description' => "Envoyée au participant dès la réception de son inscription (avec rendez-vous, prix et preuve éventuelle).",
            'recipient'   => 'participant',
            'trigger'     => 'Nouvelle inscription reçue',
            'template'    => 'confirmation',
            'icon'        => 'check-circle',
        ],
        'alerte_equipe' => [
            'label'       => 'Alerte nouvelle inscription',
            'description' => "Prévient l'équipe (destinataires des alertes internes) à chaque nouvelle inscription.",
            'recipient'   => 'equipe',
            'trigger'     => 'Nouvelle inscription reçue',
            'template'    => 'alert',
            'icon'        => 'users',
        ],
        'paiement_confirme' => [
            'label'       => 'Paiement confirmé / inscription validée',
            'description' => 'Envoyé au participant quand le paiement complet est vérifié ou que son inscription est validée.',
            'recipient'   => 'participant',
            'trigger'     => 'Paiement validé (total) ou inscription validée',
            'template'    => 'payment / status',
            'icon'        => 'banknotes',
        ],
        'paiement_partiel' => [
            'label'       => 'Paiement partiel reçu',
            'description' => 'Envoyé au participant après validation d\'un paiement partiel, avec le reste à payer.',
            'recipient'   => 'participant',
            'trigger'     => 'Paiement validé (partiel)',
            'template'    => 'payment',
            'icon'        => 'credit-card',
        ],
        'statut_refuse' => [
            'label'       => 'Inscription refusée',
            'description' => 'Envoyé au participant quand son inscription passe au statut « Refusé ».',
            'recipient'   => 'participant',
            'trigger'     => 'Inscription passée au statut « Refusé »',
            'template'    => 'status',
            'icon'        => 'x-circle',
        ],
        'contact_recu' => [
            'label'       => 'Message de contact reçu',
            'description' => "Notifie l'équipe quand un message arrive via le formulaire de contact du site.",
            'recipient'   => 'equipe',
            'trigger'     => 'Nouveau message de contact',
            'template'    => 'contact',
            'icon'        => 'inbox',
        ],
        'nouvelle_formation' => [
            'label'       => "Annonce d'une nouvelle formation",
            'description' => "Prévient les anciens participants et les candidats aux offres d'emploi "
                . "qu'une nouvelle formation est disponible (e-mails dédupliqués).",
            'recipient'   => 'anciens',
            'trigger'     => "Création d'une formation ouverte",
            'template'    => 'annonce',
            'icon'        => 'megaphone',
        ],
        'offre_publiee' => [
            'label'       => "Annonce d'une offre d'emploi",
            'description' => "Diffuse chaque nouvelle offre publiée à toute la communauté "
                . "(participants aux formations + candidats aux offres).",
            'recipient'   => 'communaute',
            'trigger'     => "Publication d'une offre (création publiée ou passage à publiée)",
            'template'    => 'offre',
            'icon'        => 'briefcase',
        ],
        'offre_rappel' => [
            'label'       => "Rappel avant clôture d'une offre (J-5)",
            'description' => "Rappelle à la communauté qu'une offre publiée ferme dans 5 jours "
                . "(un seul rappel par offre, déclenché par le cron ou les visites du site).",
            'recipient'   => 'communaute',
            'trigger'     => '5 jours avant la date de clôture',
            'template'    => 'offre_rappel',
            'icon'        => 'clock',
        ],
        'offre_prolongee' => [
            'label'       => "Offre d'emploi prolongée",
            'description' => 'Prévient la communauté quand la date de clôture d\'une offre publiée est repoussée.',
            'recipient'   => 'communaute',
            'trigger'     => "Clôture repoussée sur une offre publiée",
            'template'    => 'offre_prolongee',
            'icon'        => 'calendar',
        ],
        'candidature_emploi' => [
            'label'       => 'Accusé de réception de candidature',
            'description' => 'Confirme au candidat que sa candidature à une offre d\'emploi a bien été reçue.',
            'recipient'   => 'candidat',
            'trigger'     => 'Nouvelle candidature à une offre',
            'template'    => 'candidature_emploi',
            'icon'        => 'check-circle',
        ],
        'candidature_retenue' => [
            'label'       => 'Candidature retenue (prochaine phase)',
            'description' => 'Félicite le candidat dont le dossier est retenu et lui annonce la suite du processus. '
                . 'Envoyé uniquement quand le statut CHANGE vers « Retenue ».',
            'recipient'   => 'candidat',
            'trigger'     => 'Candidature passée au statut « Retenue »',
            'template'    => 'candidature_retenue',
            'icon'        => 'star',
        ],
        'candidature_refusee' => [
            'label'       => 'Candidature non retenue',
            'description' => 'Message respectueux d\'encouragement au candidat non retenu. '
                . 'Envoyé uniquement quand le statut CHANGE vers « Refusée ».',
            'recipient'   => 'candidat',
            'trigger'     => 'Candidature passée au statut « Refusée »',
            'template'    => 'candidature_refusee',
            'icon'        => 'x-circle',
        ],
    ];

    /** Modèles de templates/mail/ (hors _base) : métadonnées de l'onglet Modèles. */
    public const TEMPLATES = [
        'confirmation' => [
            'label'       => "Confirmation d'inscription",
            'description' => "Accusé de réception envoyé au participant juste après son inscription (préinscription ou preuve jointe).",
            'variables'   => 'full_name, formation, slots, has_proof, payment_type, amount_declared',
            'recipient'   => 'participant',
        ],
        'alert' => [
            'label'       => 'Alerte interne — nouvelle inscription',
            'description' => "Fiche récapitulative (nom, téléphone, preuve) envoyée à l'équipe à chaque inscription.",
            'variables'   => 'inscription_id, full_name, phone, email, formation, has_proof',
            'recipient'   => 'equipe',
        ],
        'status' => [
            'label'       => 'Changement de statut (validé / refusé)',
            'description' => 'Informe le participant que son inscription est confirmée ou n\'a pas pu être validée.',
            'variables'   => 'inscription, formation, statut, slots',
            'recipient'   => 'participant',
        ],
        'payment' => [
            'label'       => 'Paiement vérifié (total ou partiel)',
            'description' => 'Confirme la réception du paiement, avec le reste à payer en cas de paiement partiel.',
            'variables'   => 'inscription, formation, statut, amount_received, reste, slots',
            'recipient'   => 'participant',
        ],
        'contact' => [
            'label'       => 'Notification — message de contact',
            'description' => "Transmet à l'équipe le contenu d'un message reçu via le formulaire de contact du site.",
            'variables'   => 'contact_id, name, email, phone, subject, message_text',
            'recipient'   => 'equipe',
        ],
        'annonce' => [
            'label'       => "Annonce d'une nouvelle formation",
            'description' => 'Invitation envoyée aux anciens participants et aux candidats aux offres '
                . 'avec affiche, dates et bouton d\'inscription.',
            'variables'   => 'formation, slots, affiche_url, cta_url',
            'recipient'   => 'anciens',
        ],
        'offre' => [
            'label'       => "Annonce d'une offre d'emploi",
            'description' => 'Diffusion d\'une nouvelle offre publiée : poste, contrat, lieu, clôture et bouton « Postuler ».',
            'variables'   => 'title, contract_type, location, salary, closes_at, extrait, cta_url',
            'recipient'   => 'communaute',
        ],
        'offre_rappel' => [
            'label'       => "Rappel avant clôture d'une offre",
            'description' => 'Rappel envoyé 5 jours avant la clôture des candidatures d\'une offre publiée.',
            'variables'   => 'title, contract_type, location, salary, closes_at, extrait, cta_url, jours_restants',
            'recipient'   => 'communaute',
        ],
        'offre_prolongee' => [
            'label'       => "Offre d'emploi prolongée",
            'description' => 'Annonce de la nouvelle date limite quand la clôture d\'une offre publiée est repoussée.',
            'variables'   => 'title, contract_type, location, salary, closes_at, extrait, cta_url',
            'recipient'   => 'communaute',
        ],
        'candidature_emploi' => [
            'label'       => 'Accusé de réception de candidature',
            'description' => 'Confirme au candidat la bonne réception de sa candidature à une offre d\'emploi.',
            'variables'   => 'full_name, title, contract_type, location, closes_at',
            'recipient'   => 'candidat',
        ],
        'candidature_alerte' => [
            'label'       => 'Alerte interne — nouvelle candidature',
            'description' => "Fiche récapitulative (nom, e-mail, CV) envoyée à l'équipe à chaque candidature reçue.",
            'variables'   => 'candidature_id, full_name, email, phone, message, cv, title',
            'recipient'   => 'equipe',
        ],
        'candidature_retenue' => [
            'label'       => 'Candidature retenue (prochaine phase)',
            'description' => 'Félicitations au candidat retenu : rappel de l\'offre, suite du processus et contact.',
            'variables'   => 'full_name, title, contract_type, location, closes_at',
            'recipient'   => 'candidat',
        ],
        'candidature_refusee' => [
            'label'       => 'Candidature non retenue',
            'description' => 'Message respectueux qui remercie le candidat et l\'encourage à repostuler.',
            'variables'   => 'full_name, title, contract_type, location',
            'recipient'   => 'candidat',
        ],
        'test' => [
            'label'       => 'E-mail de test',
            'description' => 'Message simple pour vérifier que la configuration SMTP du serveur fonctionne.',
            'variables'   => 'sent_by',
            'recipient'   => 'test',
        ],
    ];

    /**
     * Sujets/corps par défaut des modèles éditables ({{variables}} substituées
     * à l'envoi) : préremplissent le formulaire d'édition tant qu'aucun
     * override n'existe en base. Le rendu par défaut reste le fichier PHP.
     */
    public static function templateDefaults(): array
    {
        return [
            'confirmation' => [
                'subject'   => 'Votre inscription — {{formation}}',
                'body'      => "<p><strong>Merci, {{full_name}} !</strong></p>\n"
                    . "<p>Nous avons bien reçu votre inscription à la formation :</p>\n"
                    . "<p><strong>{{formation}}</strong></p>\n"
                    . "<p>Vos rendez-vous :<br>{{slots}}</p>\n"
                    . "<p><strong>Lieu :</strong> {{lieu}}<br><strong>Frais de participation :</strong> {{prix}}</p>\n"
                    . "<p><strong>Montant déclaré payé :</strong> {{montant_declare}} ({{type_paiement}}) — <strong>Reste à payer :</strong> {{reste}}</p>\n"
                    . "<p>Votre place sera confirmée après vérification de votre paiement. Pour toute question : {{contact_phone}}.</p>\n"
                    . "<p>À très bientôt,<br><strong>L'équipe PROCOPE Afrique</strong></p>",
                'variables' => ['full_name', 'formation', 'prix', 'lieu', 'slots', 'contact_phone', 'type_paiement', 'montant_declare', 'reste'],
            ],
            'alert' => [
                'subject'   => 'Nouvelle inscription — {{full_name}}',
                'body'      => "<p><strong>Nouvelle inscription reçue</strong></p>\n"
                    . "<p><strong>Formation :</strong> {{formation}}<br>\n"
                    . "<strong>Nom :</strong> {{full_name}}<br>\n"
                    . "<strong>Téléphone :</strong> {{phone}}<br>\n"
                    . "<strong>E-mail :</strong> {{email}}<br>\n"
                    . "<strong>Preuve de paiement :</strong> {{preuve}}</p>\n"
                    . "<p>Consultez le détail dans le back-office : Admin → Inscriptions → #{{inscription_id}}</p>",
                'variables' => ['inscription_id', 'full_name', 'phone', 'email', 'preuve', 'formation', 'prix', 'lieu', 'slots', 'contact_phone'],
            ],
            'status' => [
                'subject'   => 'Suite de votre inscription — {{formation}}',
                'body'      => "<p>Bonjour {{full_name}},</p>\n"
                    . "<p>Le statut de votre inscription à la formation <strong>{{formation}}</strong> est désormais : <strong>{{statut}}</strong>.</p>\n"
                    . "<p>Vos rendez-vous :<br>{{slots}}</p>\n"
                    . "<p><strong>Lieu :</strong> {{lieu}}</p>\n"
                    . "<p>Pour toute question, contactez-nous au {{contact_phone}}.</p>\n"
                    . "<p>L'équipe <strong>PROCOPE Afrique</strong></p>",
                'variables' => ['full_name', 'formation', 'statut', 'prix', 'lieu', 'slots', 'contact_phone'],
            ],
            'payment' => [
                'subject'   => 'Paiement reçu — {{formation}}',
                'body'      => "<p>Bonjour {{full_name}},</p>\n"
                    . "<p>Nous avons bien reçu votre paiement de <strong>{{montant_recu}}</strong> pour la formation <strong>{{formation}}</strong> ({{statut}}).</p>\n"
                    . "<p><strong>Frais de participation :</strong> {{prix}}<br><strong>Reste à payer :</strong> {{reste}}</p>\n"
                    . "<p>Vos rendez-vous :<br>{{slots}}</p>\n"
                    . "<p><strong>Lieu :</strong> {{lieu}}</p>\n"
                    . "<p>Pour compléter un éventuel solde, contactez-nous au {{contact_phone}}.</p>\n"
                    . "<p>L'équipe <strong>PROCOPE Afrique</strong></p>",
                'variables' => ['full_name', 'formation', 'statut', 'montant_recu', 'reste', 'prix', 'lieu', 'slots', 'contact_phone'],
            ],
            'contact' => [
                'subject'   => 'Nouveau message de contact — {{name}}',
                'body'      => "<p><strong>Nouveau message de contact</strong></p>\n"
                    . "<p><strong>Nom :</strong> {{name}}<br><strong>E-mail :</strong> {{email}}<br>"
                    . "<strong>Téléphone :</strong> {{phone}}<br><strong>Sujet :</strong> {{sujet}}</p>\n"
                    . "<p><strong>Message :</strong><br>{{message}}</p>\n"
                    . "<p>Gérez ce message dans le back-office : Admin → Messages → #{{contact_id}}</p>",
                'variables' => ['contact_id', 'name', 'email', 'phone', 'sujet', 'message'],
            ],
            'annonce' => [
                'subject'   => 'Nouvelle formation PROCOPE — {{formation}}',
                'body'      => "<p>Bonjour,</p>\n"
                    . "<p>Vous avez participé à l'une de nos formations : merci pour votre confiance ! "
                    . "Nous avons le plaisir de vous annoncer l'ouverture d'une nouvelle session :</p>\n"
                    . "<p><strong>{{formation}}</strong></p>\n"
                    . "<p>{{intro}}</p>\n"
                    . "<p><strong>Dates et horaires :</strong><br>{{slots}}</p>\n"
                    . "<p><strong>Lieu :</strong> {{lieu}}<br><strong>Frais de participation :</strong> {{prix}}</p>\n"
                    . "<p>Les places sont limitées : ne tardez pas à réserver la vôtre.</p>\n"
                    . "<p>Pour toute question : {{contact_phone}}.</p>\n"
                    . "<p>À très bientôt,<br><strong>L'équipe PROCOPE Afrique</strong></p>",
                'variables' => ['formation', 'intro', 'prix', 'lieu', 'slots', 'contact_phone', 'cta_url'],
            ],
            'offre' => [
                'subject'   => "Nouvelle offre d'emploi — {{title}}",
                'body'      => "<p>Bonjour,</p>\n"
                    . "<p>PROCOPE Afrique recrute : une nouvelle opportunité vient d'être publiée.</p>\n"
                    . "<p><strong>{{title}}</strong></p>\n"
                    . "<p>{{extrait}}</p>\n"
                    . "<p><strong>Type de contrat :</strong> {{contract_type}}<br>"
                    . "<strong>Lieu :</strong> {{location}}<br>"
                    . "<strong>Clôture des candidatures :</strong> {{closes_at}}</p>\n"
                    . "<p>N'attendez pas la date limite : les candidatures sont étudiées au fil de l'eau.</p>\n"
                    . "<p>À très bientôt,<br><strong>L'équipe PROCOPE Afrique</strong></p>",
                'variables' => ['title', 'contract_type', 'location', 'salary', 'closes_at', 'extrait', 'cta_url'],
            ],
            'offre_rappel' => [
                'subject'   => 'Derniers jours pour postuler — {{title}}',
                'body'      => "<p>Bonjour,</p>\n"
                    . "<p>Il ne reste plus que <strong>{{jours_restants}} jour(s)</strong> pour candidater à cette offre d'emploi PROCOPE :</p>\n"
                    . "<p><strong>{{title}}</strong></p>\n"
                    . "<p><strong>Type de contrat :</strong> {{contract_type}}<br>"
                    . "<strong>Lieu :</strong> {{location}}<br>"
                    . "<strong>Clôture des candidatures :</strong> {{closes_at}}</p>\n"
                    . "<p>Après la date de clôture, il ne sera plus possible de déposer votre candidature.</p>\n"
                    . "<p>À très bientôt,<br><strong>L'équipe PROCOPE Afrique</strong></p>",
                'variables' => ['title', 'contract_type', 'location', 'salary', 'closes_at', 'extrait', 'cta_url', 'jours_restants'],
            ],
            'offre_prolongee' => [
                'subject'   => 'Offre prolongée — {{title}}',
                'body'      => "<p>Bonjour,</p>\n"
                    . "<p>Bonne nouvelle : la date limite de candidature de cette offre d'emploi PROCOPE vient d'être repoussée.</p>\n"
                    . "<p><strong>{{title}}</strong></p>\n"
                    . "<p><strong>Type de contrat :</strong> {{contract_type}}<br>"
                    . "<strong>Lieu :</strong> {{location}}<br>"
                    . "<strong>Nouvelle date de clôture :</strong> {{closes_at}}</p>\n"
                    . "<p>Profitez de ce délai supplémentaire pour envoyer votre candidature.</p>\n"
                    . "<p>À très bientôt,<br><strong>L'équipe PROCOPE Afrique</strong></p>",
                'variables' => ['title', 'contract_type', 'location', 'salary', 'closes_at', 'extrait', 'cta_url'],
            ],
            'candidature_emploi' => [
                'subject'   => 'Votre candidature — {{title}}',
                'body'      => "<p><strong>Candidature bien reçue, {{full_name}} !</strong></p>\n"
                    . "<p>Nous avons bien reçu votre candidature pour l'offre :</p>\n"
                    . "<p><strong>{{title}}</strong></p>\n"
                    . "<p><strong>Type de contrat :</strong> {{contract_type}}<br>"
                    . "<strong>Lieu :</strong> {{location}}<br>"
                    . "<strong>Clôture des candidatures :</strong> {{closes_at}}</p>\n"
                    . "<p>Notre équipe étudie chaque dossier avec attention. Si votre profil est retenu, "
                    . "nous vous recontacterons pour la suite du processus.</p>\n"
                    . "<p>Merci de votre intérêt pour PROCOPE Afrique.</p>\n"
                    . "<p>À bientôt,<br><strong>L'équipe PROCOPE Afrique</strong></p>",
                'variables' => ['full_name', 'title', 'contract_type', 'location', 'closes_at'],
            ],
            'candidature_alerte' => [
                'subject'   => 'Nouvelle candidature — {{full_name}}',
                'body'      => "<p><strong>Nouvelle candidature reçue</strong></p>\n"
                    . "<p><strong>Offre :</strong> {{title}}<br>\n"
                    . "<strong>Nom :</strong> {{full_name}}<br>\n"
                    . "<strong>E-mail :</strong> {{email}}<br>\n"
                    . "<strong>Téléphone :</strong> {{phone}}<br>\n"
                    . "<strong>CV :</strong> {{cv}}</p>\n"
                    . "<p><strong>Message / motivation :</strong><br>{{message}}</p>\n"
                    . "<p>Consultez le détail dans le back-office : Admin → Offres d'emploi → Candidatures → #{{candidature_id}}</p>",
                'variables' => ['candidature_id', 'full_name', 'email', 'phone', 'message', 'cv', 'title', 'contract_type', 'location'],
            ],
            'candidature_retenue' => [
                'subject'   => 'Votre candidature est retenue — {{title}}',
                'body'      => "<p><strong>Félicitations, {{full_name}} !</strong></p>\n"
                    . "<p>Nous avons le plaisir de vous annoncer que votre candidature a été retenue "
                    . "pour la prochaine phase de notre processus de recrutement :</p>\n"
                    . "<p><strong>{{title}}</strong></p>\n"
                    . "<p><strong>Type de contrat :</strong> {{contract_type}}<br>"
                    . "<strong>Lieu :</strong> {{location}}</p>\n"
                    . "<p>Notre équipe vous recontactera très prochainement (par e-mail ou téléphone) "
                    . "pour vous préciser la suite du processus : entretien, test pratique ou échanges complémentaires.</p>\n"
                    . "<p>Pour toute question : procopeafrique@gmail.com ou +228 96 45 76 95.</p>\n"
                    . "<p>À très bientôt,<br><strong>L'équipe PROCOPE Afrique</strong></p>",
                'variables' => ['full_name', 'title', 'contract_type', 'location', 'closes_at'],
            ],
            'candidature_refusee' => [
                'subject'   => 'Suite de votre candidature — {{title}}',
                'body'      => "<p>Bonjour {{full_name}},</p>\n"
                    . "<p>Nous vous remercions sincèrement pour l'intérêt que vous avez porté à PROCOPE Afrique "
                    . "et pour le temps consacré à votre candidature à l'offre :</p>\n"
                    . "<p><strong>{{title}}</strong></p>\n"
                    . "<p>Après une étude attentive de votre dossier, nous ne sommes malheureusement pas en mesure "
                    . "de donner une suite favorable à votre candidature pour ce poste.</p>\n"
                    . "<p>Cette décision ne remet pas en cause la qualité de votre parcours : le choix a été difficile. "
                    . "Nous vous encourageons vivement à candidater à nos prochaines offres.</p>\n"
                    . "<p>Nous vous souhaitons une pleine réussite dans vos projets professionnels.</p>\n"
                    . "<p>Avec tous nos encouragements,<br><strong>L'équipe PROCOPE Afrique</strong></p>",
                'variables' => ['full_name', 'title', 'contract_type', 'location'],
            ],
            'test' => [
                'subject'   => 'E-mail de test — PROCOPE Admin',
                'body'      => "<p><strong>E-mail de test</strong></p>\n"
                    . "<p>Ceci est un e-mail de test envoyé depuis le back-office PROCOPE par <strong>{{sent_by}}</strong>.</p>\n"
                    . "<p>Si vous recevez ce message, la configuration du serveur fonctionne correctement.</p>\n"
                    . "<p>Envoyé le {{date}}.</p>",
                'variables' => ['sent_by', 'date'],
            ],
        ];
    }

    /** Libellés lisibles des types du journal (fallback : type brut). */
    public const LOG_TYPE_LABELS = [
        'confirmation'        => 'Confirmation',
        'alerte_equipe'       => 'Alerte équipe',
        'paiement_confirme'   => 'Paiement confirmé',
        'paiement_partiel'    => 'Paiement partiel',
        'statut_valide'       => 'Statut validé',
        'statut_refuse'       => 'Statut refusé',
        'contact_recu'        => 'Message de contact',
        'annonce_formation'   => 'Annonce formation',
        'renvoi_confirmation' => 'Renvoi confirmation',
        'rappel_paiement'     => 'Rappel paiement',
        'offre_publiee'       => 'Annonce offre',
        'offre_rappel'        => 'Rappel offre',
        'offre_prolongee'     => 'Offre prolongée',
        'candidature_emploi'  => 'Accusé candidature',
        'alerte_candidature'  => 'Alerte candidature',
        'candidature_retenue' => 'Candidature retenue',
        'candidature_refusee' => 'Candidature refusée',
        'test'                => 'Test',
    ];

    public function index(Request $request): void
    {
        $toggles = [];
        foreach (array_keys(self::AUTO_MAILS) as $key) {
            $default = in_array($key, Mailer::OFF_BY_DEFAULT, true) ? '0' : '1';
            $toggles[$key] = in_array(
                strtolower((string) Setting::get('auto_' . $key, $default)),
                ['1', 'true', 'on', 'yes'],
                true
            );
        }

        // Onglet Journal : filtres + pagination (25/page)
        $logFilters = [
            'status' => (string) ($request->input('log_status', '') ?? ''),
            'type'   => (string) ($request->input('log_type', '') ?? ''),
        ];
        $logPage = max(1, (int) ($request->input('page', '1') ?? 1));
        $logs = MailLog::paginate($logFilters, $logPage);

        View::render('automations/index', [
            'title'          => 'Automatisations',
            'mailEnabled'    => Mailer::enabled(),
            'smtpConfigured' => Mailer::smtpConfigured(),
            'smtpHost'       => (string) Env::get('SMTP_HOST', ''),
            'autoMails'      => self::AUTO_MAILS,
            'toggles'        => $toggles,
            'templates'      => self::TEMPLATES,
            'overrides'      => MailTemplate::allByName(),
            'defaults'       => self::templateDefaults(),
            'formations'     => Formation::all(),
            'offers'         => JobOffer::published(),
            'logs'           => $logs,
            'logFilters'     => $logFilters,
            'logTypes'       => MailLog::types(),
            'logTypeLabels'  => self::LOG_TYPE_LABELS,
            'logsTotal'      => MailLog::countAll(),
        ]);
    }

    public function update(Request $request): void
    {
        // Mode toggle unitaire : champ hidden key + checkbox value (auto-soumis)
        $key = (string) ($request->input('key', '') ?? '');
        if ($key !== '') {
            $allowed = array_merge(
                ['mail_enabled'],
                array_map(static fn (string $k): string => 'auto_' . $k, array_keys(self::AUTO_MAILS))
            );
            if (!in_array($key, $allowed, true)) {
                Response::abort(400, 'Réglage inconnu');
            }
            $value = $request->input('value') === '1' ? '1' : '0';
            Setting::set($key, $value);
            Audit::log('automations.update', 'settings', null, ['key' => $key, 'value' => $value]);

            $label = $key === 'mail_enabled'
                ? "Envoi d'e-mails (interrupteur général)"
                : (self::AUTO_MAILS[substr($key, 5)]['label'] ?? $key);
            flash('success', '« ' . $label . ' » ' . ($value === '1' ? 'activé.' : 'désactivé.'));
            Response::redirect('/admin/automations');
        }

        // Mode formulaire global (compatibilité)
        Setting::set('mail_enabled', $request->input('mail_enabled') === '1' ? '1' : '0');
        foreach (array_keys(self::AUTO_MAILS) as $autoKey) {
            Setting::set('auto_' . $autoKey, $request->input('auto_' . $autoKey) === '1' ? '1' : '0');
        }
        Audit::log('automations.update', 'settings');
        flash('success', "Réglages d'automatisation enregistrés.");
        Response::redirect('/admin/automations');
    }

    /**
     * POST /admin/automations/logs/purge-failed — supprime les envois en échec
     * du journal (en respectant le filtre type actif le cas échéant).
     */
    public function purgeFailedLogs(Request $request): void
    {
        $type = (string) ($request->input('log_type', '') ?? '');
        $deleted = MailLog::deleteFailed($type !== '' ? $type : null);
        Audit::log('automations.logs_purge_failed', 'settings', null, [
            'type'    => $type !== '' ? $type : 'tous',
            'deleted' => $deleted,
        ]);
        flash('success', $deleted . ' entrée' . ($deleted > 1 ? 's' : '') . ' supprimée' . ($deleted > 1 ? 's' : '')
            . ($type !== '' ? ' (échecs du type « ' . ($this->logTypeLabel($type)) . ' »).' : ' (tous les échecs).'));

        // Retour sur l'onglet Journal, filtres conservés
        $query = array_filter([
            'log_status' => (string) ($request->input('log_status', '') ?? ''),
            'log_type'   => $type,
        ], static fn (string $v): bool => $v !== '');
        Response::redirect('/admin/automations' . ($query ? '?' . http_build_query($query) : '') . '#journal');
    }

    /** Libellé lisible d'un type de log (fallback : type brut). */
    private function logTypeLabel(string $type): string
    {
        return self::LOG_TYPE_LABELS[$type] ?? $type;
    }

    /** Nom de template whitelisté depuis l'URL, sinon 404. */
    private function templateNameOr404(Request $request): string
    {
        $name = (string) ($request->params['name'] ?? $request->params['template'] ?? '');
        if (!array_key_exists($name, self::TEMPLATES)) {
            Response::abort(404, 'Modèle introuvable');
        }
        return $name;
    }

    /** GET /admin/automations/templates/{name}/edit — formulaire sujet + corps. */
    public function editTemplate(Request $request): void
    {
        $name = $this->templateNameOr404($request);
        $override = MailTemplate::find($name);
        $defaults = self::templateDefaults()[$name];

        View::render('automations/template-edit', [
            'title'     => 'Modifier le modèle — ' . self::TEMPLATES[$name]['label'],
            'name'      => $name,
            'meta'      => self::TEMPLATES[$name],
            'subject'   => ($override['subject'] ?? '') !== '' ? $override['subject'] : $defaults['subject'],
            'body'      => trim((string) ($override['body'] ?? '')) !== '' ? $override['body'] : $defaults['body'],
            'variables' => $defaults['variables'],
            'isCustom'  => $override !== null,
        ]);
    }

    /** POST /admin/automations/templates/{name} — enregistre l'override. */
    public function saveTemplate(Request $request): void
    {
        $name = $this->templateNameOr404($request);
        $subject = strip_tags((string) ($request->input('subject', '') ?? ''));
        $body = (string) ($request->input('body', '') ?? '');

        // HTML (éditeur visuel) : sanitisation stricte en liste blanche.
        // Texte brut (aucune balise) : conservé tel quel, rendu via nl2br.
        if ($body !== strip_tags($body)) {
            $body = HtmlSanitizer::clean($body);
        }
        if (trim($body) === '') {
            flash('error', 'Le corps du modèle ne peut pas être vide.');
            Response::redirect("/admin/automations/templates/$name/edit");
        }

        MailTemplate::save($name, mb_substr($subject, 0, 255), $body);
        Audit::log('automations.template_save', 'settings', null, ['template' => $name]);
        flash('success', 'Modèle « ' . self::TEMPLATES[$name]['label'] . ' » personnalisé et enregistré.');
        Response::redirect('/admin/automations#templates');
    }

    /** POST /admin/automations/templates/{name}/reset — retour au rendu fichier. */
    public function resetTemplate(Request $request): void
    {
        $name = $this->templateNameOr404($request);
        $existed = MailTemplate::reset($name);
        Audit::log('automations.template_reset', 'settings', null, ['template' => $name, 'existed' => $existed]);
        flash('success', $existed
            ? 'Modèle « ' . self::TEMPLATES[$name]['label'] . ' » réinitialisé : le rendu par défaut est de nouveau utilisé.'
            : 'Ce modèle utilisait déjà le rendu par défaut.');
        Response::redirect('/admin/automations#templates');
    }

    /**
     * GET /admin/automations/preview/{template} — rend le modèle avec des
     * données d'exemple et renvoie le HTML complet (panneau de prévisualisation).
     */
    public function preview(Request $request): void
    {
        $name = (string) ($request->params['template'] ?? '');
        if (!array_key_exists($name, self::TEMPLATES)) {
            Response::abort(404, 'Modèle introuvable');
        }
        $html = Mailer::template($name, $this->sampleData($name));
        header('Content-Type: text/html; charset=utf-8');
        // Autorise l'affichage dans l'iframe du panneau (l'en-tête global est DENY)
        header('X-Frame-Options: SAMEORIGIN');
        echo $html;
        exit;
    }

    /** Données d'exemple réalistes pour la prévisualisation des modèles. */
    private function sampleData(string $template): array
    {
        $formation = [
            'id'            => 0,
            'titre'         => 'Formation en création et gestion d\'entreprise — Session exemple',
            'intro'         => "Deux journées intensives pour structurer votre projet d'entreprise : "
                . "idée, étude de marché, budget prévisionnel et formalités au Togo.",
            'prix'          => 25000,
            'lieu'          => 'Lomé, quartier Agoè (siège PROCOPE)',
            'contact_phone' => '+228 96 45 76 95',
        ];
        $slots = [
            ['label' => 'Samedi 30 janvier 2027', 'starts_at' => '2027-01-30 08:00:00', 'ends_at' => '2027-01-30 12:30:00'],
            ['label' => 'Dimanche 31 janvier 2027', 'starts_at' => '2027-01-31 14:00:00', 'ends_at' => '2027-01-31 17:30:00'],
        ];
        $inscription = ['full_name' => 'Koffi Exemple'];
        $offer = [
            'id'            => 0,
            'title'         => 'Chargé(e) de communication digitale — Exemple',
            'description'   => "Sous la responsabilité de la direction, vous animez les réseaux sociaux "
                . "de PROCOPE Afrique, produisez les visuels et vidéos des campagnes et assurez le suivi "
                . "des performances. Profil : créatif, autonome, à l'aise avec les outils digitaux.",
            'contract_type' => 'CDD',
            'location'      => 'Lomé, Togo',
            'salary'        => '150 000 F CFA / mois',
            'closes_at'     => date('Y-m-d 18:00:00', strtotime('+12 days')),
        ];
        $offerCta = 'https://procopeafrique.vercel.app/offres-emploi.html#charge-de-communication-exemple';

        return match ($template) {
            'confirmation' => [
                'full_name'       => 'Koffi Exemple',
                'formation'       => $formation,
                'slots'           => $slots,
                'has_proof'       => true,
                'payment_type'    => 'partiel',
                'amount_declared' => 15000,
            ],
            'alert' => [
                'inscription_id' => 42,
                'full_name'      => 'Koffi Exemple',
                'phone'          => '+228 90 12 34 56',
                'email'          => 'koffi.exemple@gmail.com',
                'formation'      => $formation,
                'has_proof'      => true,
            ],
            'status' => [
                'inscription' => $inscription,
                'formation'   => $formation,
                'statut'      => 'valide',
                'slots'       => $slots,
            ],
            'payment' => [
                'inscription'     => $inscription,
                'formation'       => $formation,
                'statut'          => 'paiement_partiel',
                'amount_received' => 15000.0,
                'reste'           => 10000.0,
                'slots'           => $slots,
            ],
            'contact' => [
                'contact_id'   => 7,
                'name'         => 'Afi Mensah',
                'email'        => 'afi.mensah@exemple.com',
                'phone'        => '+228 91 23 45 67',
                'subject'      => "Demande d'informations sur les formations",
                'message_text' => "Bonjour,\nJe souhaite en savoir plus sur votre prochaine formation "
                    . "en création d'entreprise (dates, tarif et modalités de paiement).\nMerci d'avance.",
            ],
            'annonce' => [
                'formation'   => $formation,
                'slots'       => $slots,
                'affiche_url' => null,
                'cta_url'     => 'https://procopeafrique.vercel.app/candidature.html#former',
            ],
            'offre', 'offre_prolongee' => [
                'offer'       => $offer,
                'cta_url'     => $offerCta,
                'affiche_url' => null,
            ],
            'offre_rappel' => [
                'offer'          => $offer,
                'cta_url'        => $offerCta,
                'affiche_url'    => null,
                'jours_restants' => 5,
            ],
            'candidature_emploi', 'candidature_retenue', 'candidature_refusee' => [
                'full_name' => 'Koffi Exemple',
                'offer'     => $offer,
                'cta_url'   => $offerCta,
            ],
            'candidature_alerte' => [
                'candidature_id' => 12,
                'full_name'      => 'Koffi Exemple',
                'email'          => 'koffi.exemple@gmail.com',
                'phone'          => '+228 90 12 34 56',
                'message_text'   => "Bonjour,\nPassionné de communication digitale, je souhaite mettre mes "
                    . "compétences au service de PROCOPE Afrique. Vous trouverez mon CV ci-joint.",
                'has_cv'         => true,
                'offer'          => $offer,
            ],
            default => ['sent_by' => Auth::user()['name'] ?? 'Admin Exemple'],
        };
    }

    /** POST /admin/automations/test-mail — ignore les toggles, pas l'absence de SMTP. */
    public function testMail(Request $request): void
    {
        $to = mb_strtolower($request->input('test_email', '') ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Adresse e-mail invalide : saisissez une adresse valide pour le test.');
            Response::redirect('/admin/automations');
        }

        $mailData = ['sent_by' => Auth::user()['name'] ?? 'Admin'];
        $html = Mailer::template('test', $mailData);
        $subject = Mailer::subjectFor('test', $mailData, 'E-mail de test — PROCOPE Admin');
        $error = Mailer::sendNow($to, $subject, $html, 'test', null);
        Audit::log('automations.test_mail', 'settings', null, ['to' => $to, 'sent' => $error === null]);

        if ($error === null) {
            flash('success', 'E-mail de test envoyé à ' . $to . '. Vérifiez la boîte de réception.');
        } else {
            flash('error', "Échec de l'envoi du test : " . $error);
        }
        Response::redirect('/admin/automations');
    }
}
