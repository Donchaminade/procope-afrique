# Site Web de PROCOPE Afrique

Ceci est le code source du site web vitrine pour le Centre Incubateur PROCOPE Afrique. Le site a été développé à partir d'un modèle de site web statique et personnalisé pour répondre aux besoins spécifiques de PROCOPE, comme détaillé dans le cahier des charges.

## À propos du projet

Le site a pour but de servir de vitrine professionnelle, moderne et fonctionnelle pour PROCOPE Afrique. Il présente l'incubateur, ses missions, ses services, et met en valeur les projets accompagnés.

## Modèle Original

Ce projet est basé sur le modèle de site web "Startup" créé par [HTML Codex](https://htmlcodex.com). Le modèle original peut être trouvé ici : [Startup - Startup Company Website Template](https://htmlcodex.com/startup-company-website-template)

## Fonctionnalités

- **Site entièrement statique :** Léger et rapide à charger.
- **Responsive Design :** Adapté pour les ordinateurs, tablettes et mobiles.
- **Pages multiples :**
    - Accueil
    - À propos de nous
    - Nos Services
    - Projets incubés
    - Actualités (`actualites.html` ; `blog.html` redirige vers cette page)
    - Espace candidature (4 onglets : former / projet / emploi / partenaire)
    - Contact (carte Lomé, Togo)
    - Investisseurs & Partenaires (menu « Plus »)
    - Pages masquées pré-lancement : `detail.html`, `ressources.html`, `team.html`, etc.
- **Contenu personnalisé :** Tout le contenu a été adapté en français pour refléter l'identité de PROCOPE Afrique.
- **Barre de navigation et pied de page dynamiques :** Mis à jour sur l'ensemble du site.

## Technologies Utilisées

- HTML5
- CSS3
- Bootstrap 5
- JavaScript (avec les bibliothèques jQuery, Owl Carousel, etc.)

## Structure des Fichiers

```
.
├── css/
│   ├── bootstrap.min.css
│   └── style.css
├── img/
│   ├── (diverses images pour le design)
│   └── logo.png
├── js/
│   └── main.js
├── lib/
│   ├── (bibliothèques JS comme animate, owlcarousel, etc.)
├── *.html (fichiers des pages du site)
├── README.md (ce fichier)
└── ...
```

## Comment Utiliser

Pour visualiser le site, il suffit d'ouvrir l'un des fichiers `.html` (par exemple, `index.html`) dans votre navigateur web. Aucune installation ou serveur n'est requis pour la visualisation.

## Personnalisation

Pour personnaliser le contenu du site :
- **Texte :** Modifiez directement le contenu textuel dans les fichiers `.html` correspondants.
- **Images :** Remplacez les images dans le dossier `img/`. Assurez-vous de conserver les mêmes noms de fichiers ou de mettre à jour les chemins dans le code HTML.
- **Styles :** Les styles personnalisés peuvent être modifiés dans le fichier `css/style.css`.

## Domaines de production & DNS

Le site vitrine reste sur **Vercel**. L’API PHP ira plus tard sur **Hostinger** (pas encore déployée).

**DNS à faire (site) :**
1. Vercel Dashboard → projet **procope** (ou le projet qui sert ce repo) → **Settings → Domains**.
2. Ajouter `procopeafrique.org` et `www.procopeafrique.org`.
3. Chez le registrar, coller **exactement** les records affichés par Vercel (souvent : apex `A` → `10.0.1.2`, `www` `CNAME` → `cname.vercel-dns.com`). Ne pas inventer d’IP.
4. Laisser Vercel rediriger `www` → apex (ou l’inverse) selon l’option proposée.

**Plus tard — Hostinger (API, attendre le feu vert) :**
- Créer le sous-domaine `api.procopeafrique.org` → IP du VPS / hébergement Hostinger.
- Document root = `procope-api/public`.
- Le jour J, confirmer `PROD_API_BASE` dans `js/api-config.js` (valeur actuelle : `https://api.procopeafrique.org`).

**CORS** (déjà multi-origines dans `procope-api/.env.example`) :
`https://procopeafrique.org`, `https://www.procopeafrique.org`, `https://procopeafrique.vercel.app`. En local, ajouter `http://127.0.0.1:5501` dans le vrai `.env`.

Tant que l’API Hostinger n’est pas en ligne, les formations / offres / inscriptions **casseront** sur le site public : c’est attendu (le front prod n’appelle plus localhost).

---

Ce README a été généré pour documenter le projet de site web de PROCOPE Afrique.
