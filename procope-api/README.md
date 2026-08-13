# PROCOPE API — Back-office & inscriptions formations

API + back-office **PHP 8 / PDO (MySQL)** pour PROCOPE Afrique. Gère :

- les **inscriptions aux formations** (formulaire public consommé par le site Vercel, remplace Google Forms) ;
- les **preuves de dépôt / paiement** (upload JPEG/PNG/PDF, stockage protégé) ;
- les **messages de contact** du site (`POST /api/contacts` → section Messages de l'admin + notification équipe) ;
- l'**admin** : utilisateurs & rôles, CRUD formations (prix, lieu, créneaux date/heure), activation/désactivation du formulaire, validation des inscriptions, centre d'**Automatisations** (toggles des e-mails automatiques, e-mail de test) ;
- les **mails automatiques** (confirmation candidat + alerte équipe, validation/refus, paiement partiel, message de contact) via PHPMailer + envois manuels depuis la fiche inscription ;
- les **offres d'emploi** : CRUD admin, page publique `offres-emploi.html`, candidatures avec CV PDF (stockage protégé `storage/cv/`), automatisations (annonce à la publication, rappel J-5 avant clôture, annonce de prolongation, accusé de réception candidat) ;
- l'**export Excel** (`.xlsx` via PhpSpreadsheet, repli CSV UTF-8) des inscriptions et des candidatures d'une offre.

## Structure

```
procope-api/
  public/            # document root (index.php = front controller, .htaccess)
  app/
    Core/            # Router, Database (PDO), Request, Response, View, Env
    Controllers/     # Admin/* (HTML) + Api/PublicController (JSON)
    Middleware/      # AuthMiddleware, CsrfMiddleware, AdminRole, SuperAdminRole
    Models/          # User, Formation, Inscription, Setting, MailLog, JobOffer, JobApplication
    Services/        # Auth, Security (anti brute-force), Uploader, Mailer, Exporter, Audit, JobNotifier
  views/admin/       # UI back-office
  templates/mail/    # templates HTML des mails
  database/migrations.sql
  storage/proofs/    # preuves de paiement (accès direct interdit)
  storage/cv/        # CV des candidatures aux offres (accès direct interdit)
  bin/               # create-admin.php, router.php (dev), smoke-db.php (dev), run-automations.php (cron)
```

## Rôles

| Rôle | Droits |
|------|--------|
| `operator` | Consulter et valider/refuser les inscriptions, voir les preuves |
| `admin` | + CRUD formations, export Excel, réglages |
| `super_admin` | + gestion des utilisateurs |

## Sécurité intégrée

- Argon2id (`password_hash`), session HttpOnly/Secure/SameSite=Strict, régénération d'ID au login
- Anti brute-force : verrouillage 15 min après 5 échecs (par IP **et** par email), délai progressif
- CSRF sur toutes les actions POST admin
- Rate-limit public : 5 inscriptions / heure / IP + honeypot anti-bot
- Preuves servies uniquement via `/admin/inscriptions/{id}/proof` (session requise), jamais en accès direct (`.htaccess` deny + `php_flag engine off`)
- Upload : vérification MIME réelle (`finfo`), 5 Mo max, renommage aléatoire
- PDO requêtes préparées uniquement ; CORS en liste blanche ; en-têtes `X-Frame-Options`, `nosniff`, CSP admin
- Journal d'audit (`audit_logs`) sur toutes les actions sensibles

## Déploiement Hostinger

1. **Base de données** : créer une base MySQL dans hPanel, importer `database/migrations.sql` via phpMyAdmin (crée les tables + seed de la formation janvier 2027, inscriptions ouvertes).
2. **Fichiers** : téléverser le dossier `procope-api` (idéalement sur un sous-domaine `api.votre-domaine`) et pointer le **document root du sous-domaine vers `procope-api/public`**.
3. **Configuration** : copier `.env.example` en `.env` et renseigner DB, SMTP et `CORS_ORIGINS` (ajouter `https://procopeafrique.vercel.app` et le futur domaine).
   **Le SMTP se configure uniquement dans le `.env`** (`SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USER`, `SMTP_PASS`, `MAIL_FROM`, `MAIL_FROM_NAME`) — plus dans la page Réglages de l'admin. Remplir ces variables quand la boîte mail sera créée (une boîte `noreply@` Hostinger recommandée pour SPF/DKIM) ; tant qu'elles sont vides, les envois sont journalisés dans `mail_logs` mais ne partent pas. L'activation/désactivation des e-mails automatiques se pilote ensuite dans **Admin → Automatisations** (la page affiche aussi l'état de la configuration SMTP et permet un e-mail de test).
4. **Dépendances** : `composer install --no-dev` (SSH Hostinger) — sans vendor, l'app fonctionne quand même : mails via `mail()` natif et export en CSV.
5. **Premier admin** : `php bin/create-admin.php email@procope.org "Nom Prénom" super_admin` (mot de passe demandé en interactif).
6. **Front** : dans `js/inscription-formation.js` (repo du site), remplacer `https://api.procopeafrique.com` par l'URL réelle de l'API.
7. **Cron des automatisations** (rappels J-5 des offres d'emploi) : dans hPanel → *Avancé → Tâches cron*, ajouter une tâche **toutes les heures** :

   ```bash
   php /home/USER/domains/VOTRE-DOMAINE/procope-api/bin/run-automations.php
   ```

   Le script est sans danger s'il tourne plus souvent : `reminder_sent_at` (anti-doublon) + un verrou en base empêchent tout double envoi. Sans cron, un **déclencheur opportuniste** intégré à `GET /api/offres` (1 visite sur 20) traite quand même les rappels dus — le cron reste recommandé en production pour un envoi ponctuel.

## Développement local

```bash
# Base (XAMPP/MySQL local) : crée procope_test + importe migrations
php bin/smoke-db.php

# Admin de test
php bin/create-admin.php admin@procope.test "Admin" super_admin

# Serveur (http://127.0.0.1:8088, admin sur /admin)
php -S 127.0.0.1:8088 -t public bin/router.php

# Site statique (l'API autorise localhost:5501 par défaut dans CORS_ORIGINS)
cd .. && python -m http.server 5501
```

## Données de démonstration

`bin/seed-demo.php` remplit la base de dev avec un jeu complet et réaliste :
2 formations (une ouverte à venir avec 2 créneaux, une passée archivée),
12 inscriptions couvrant tous les statuts (avec preuves PNG/PDF factices dans
`storage/proofs/`), 4 messages de contact, 3 offres d'emploi (une publiée qui
clôt dans ~10 jours avec 2 affiches PNG dont une principale, une publiée qui
clôt dans 4 jours — utile pour tester le rappel J-5 —, une archivée avec
candidats aux statuts finaux), 8 candidatures avec CV PDF factices dans
`storage/cv/`, et un compte `operator@demo.procope.test` (mot de passe
`operateur-demo-2026`).

```bash
php bin/seed-demo.php            # idempotent : relançable sans dupliquer
php bin/seed-demo.php --fresh    # supprime d'abord SES données de démo, puis re-crée
```

- **Idempotence** : les données sont identifiées par leurs marqueurs
  (e-mails `*@demo.procope.test`, slugs `demo-*`, fichiers `demo-*`) ; un
  second lancement liste ce qui est « déjà présent » sans rien dupliquer.
- **`--fresh`** ne supprime que les lignes/fichiers portant ces marqueurs,
  jamais les autres données.
- **Garde-fou** : le script lit `APP_ENV` dans le `.env` et refuse de tourner
  si la valeur est `production`/`prod` — ou si elle est absente/inconnue
  (seules `local`, `dev`, `development`, `test`, `testing`, `staging` sont
  acceptées).

## Suite de tests

`bin/run-tests.php` est un runner CLI maison (aucune dépendance nouvelle,
pas de PHPUnit) : assertions (`assertStatus`, `assertEquals`,
`assertContains`, ...), compteur pass/fail, sortie colorée et code de sortie
non-zéro en cas d'échec.

```bash
php bin/run-tests.php
```

Prérequis : serveur API lancé sur `http://127.0.0.1:8088`
(`php -S 127.0.0.1:8088 -t public bin/router.php`), base `procope_test` à
jour, **seeder de démo appliqué** (`php bin/seed-demo.php`) et compte
`admin@procope.test` créé (voir ci-dessus).

Couverture : santé de l'API publique (formations/offres, 404 slug inconnu),
authentification (refus générique, redirections, session requise), rôles
(operator 403 sur users/surveillance, super admin 200, compte racine du
`.env`), protection CSRF (419), les 11 pages admin clés, l'API candidature
multipart (201 + ligne en base, doublon 409, offre clôturée 409, archivée
404, honeypot silencieux), le sanitizer HTML (tests unitaires), les exports
Excel/PDF (content-type + magic bytes) et le rendu des templates e-mail
(logo, échappement — aucun envoi).

Les données créées par la suite sont marquées `*@test-run.procope.test` /
`test-run-*` et **nettoyées à la fin, même en cas d'échec** (les compteurs
`rate_limits` / `login_attempts` de l'IP locale sont aussi purgés pour que
la suite reste relançable à volonté).

## UI admin (Tailwind CSS)

Le back-office utilise **Tailwind CSS précompilé** en fichier statique
(`public/assets/admin.css`) — pas de CDN au runtime, la CSP reste stricte.
Sources : `tailwind.config.js` (couleurs `brand.navy` / `brand.orange` / `brand.blue`,
scan de `views/**/*.php`, `app/helpers.php` et `public/assets/admin.js`) et
`tailwind.input.css` (composants `.card`, `.btn-*`, `.input`, `.table`, `.badge`…).

Après toute modification des vues admin (classes Tailwind), régénérer le CSS :

```bash
npx -y tailwindcss@3 -i tailwind.input.css -o public/assets/admin.css --minify
```

Les icônes sont des SVG inline (style Heroicons) rendus par le helper PHP
`icon(nom, classes)` défini dans `app/helpers.php`. Les graphiques du dashboard
(donut statuts, barres 14 jours, donut hommes/femmes) sont générés en SVG/CSS
par `public/assets/admin.js` (données passées via attributs `data-*`, compatibles CSP).

## Endpoints publics (JSON, CORS)

| Méthode | Route | Description |
|---------|-------|-------------|
| `GET` | `/api/formations/active` | Formation ouverte (titre, intro, programme, prix, lieu, créneaux) + options du formulaire ; `{open:false, message}` sinon |
| `POST` | `/api/inscriptions` | Inscription multipart (champs + `payment_proof` optionnel) ; statut `preinscrit` ou `preuve_recue` ; envoie les mails |
| `POST` | `/api/contacts` | Message du formulaire de contact (`name`, `email?`, `phone?`, `subject?`, `message`) ; honeypot + rate-limit 5/h/IP ; notifie l'équipe |
| `GET` | `/api/offres` | Offres d'emploi publiées non clôturées (titre, slug, contrat, lieu, salaire, clôture, extrait, `image` = URL de l'affiche principale ou null) |
| `GET` | `/api/offres/{slug}` | Détail complet d'une offre publiée (404 si dépubliée/inexistante) + `images` (galerie, affiche principale en premier) |
| `POST` | `/api/offres/{slug}/postuler` | Candidature multipart (`full_name`, `email`, `phone?`, `message?`, `cv` PDF optionnel) ; honeypot + rate-limit 5/h/IP ; refus 409 si clôturée |

Toutes les autres routes (`/admin/...`) exigent une session.

## Automatisations « Offres d'emploi »

Quatre e-mails automatiques, **désactivés par défaut** (à activer dans Admin → Automatisations) :

| Toggle | Déclencheur | Destinataires |
|--------|-------------|---------------|
| `auto_offre_publiee` | Offre créée publiée, ou passage publiée | Communauté (e-mails distincts des inscriptions non refusées + candidats emploi) |
| `auto_offre_rappel` | J-5 avant `closes_at` (une seule fois par offre, `reminder_sent_at`) | Communauté |
| `auto_offre_prolongee` | `closes_at` repoussée sur une offre publiée | Communauté |
| `auto_candidature_emploi` | Candidature reçue | Le candidat (accusé de réception) |

L'alerte interne « nouvelle candidature » suit le toggle des alertes internes existant (`auto_alerte_equipe`). Le rappel J-5 est traité par `bin/run-automations.php` (cron) ou, à défaut, par le déclencheur opportuniste de `GET /api/offres`.

## Affiches des offres d'emploi (upgrade-006)

Une offre peut avoir **plusieurs affiches** (table `job_offer_images`, fichiers publics
dans `public/uploads/offres/`, JPEG/PNG/WebP via `Uploader::storeOffreImage`), dont **une
principale** (`is_main`). Gestion depuis le formulaire d'offre : upload multiple à la
création (la première devient principale) et carte « Affiches » à l'édition (ajout, radio
principale, suppression). L'affiche principale apparaît sur les cartes du site public,
en tête des e-mails offre (publication / rappel / prolongation) et en premier dans la
galerie de la page de détails. `bin/apply-upgrade-006.php` est idempotent.

## Éditeur visuel des modèles d'e-mail

La page `/admin/automations/templates/{name}/edit` affiche l'e-mail dans son **cadre réel**
(en-tête logo + pied de page verrouillés) avec le corps en `contenteditable` : barre d'outils
(gras, italique, souligné, listes, lien, effacer le style — `document.execCommand`, aucun
JS externe), pastilles `{{variables}}` insérées au curseur, et lien discret « Mode texte
(avancé) » vers le textarea brut. À l'enregistrement, le HTML est **sanitisé en liste
blanche** (`App\Services\HtmlSanitizer`) : balises `p, br, strong/b, em/i, u, ul, ol, li,
a[href http/https], h2, h3` uniquement ; attributs `style`/`class`/`on*` supprimés ;
`script`/`style`/`iframe`/`img`… retirés ; balises inconnues déballées en conservant leur
texte. Les overrides en texte brut (sans balise) restent rendus via `nl2br` comme avant.
Le gabarit `templates/mail/_base.php` intègre le logo (URL absolue depuis `APP_URL`),
un bandeau aux couleurs PROCOPE et un pied de page contacts — appliqué à tous les envois
et previews.
