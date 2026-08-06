========================================
  imgremp — images à remplacer (PROCOPE)
========================================

Ce dossier sert de "boîte aux lettres" pour changer des images du site
sans toucher directement à img/.

────────────────────────────────────────
COMMENT FAIRE
────────────────────────────────────────

1. Déposez ici vos nouvelles images en leur donnant EXACTEMENT
   le même nom que le fichier cible dans img/ (remplacement 1:1).

   Exemples :
     carousel-1.jpg   → remplace img/carousel-1.jpg (hero)
     carousel-2.jpg   → remplace img/carousel-2.jpg (hero)
     logo.png         → remplace img/logo.png
     vt-thumb-1.jpg   → remplace img/vt-thumb-1.jpg (couverture TikTok)
     affiche-1.jpg    → remplace img/affiche-1.jpg

2. Dites à l’agent : « remplace » (ou « remplace les images »).
   L’agent copie chaque fichier de imgremp/ vers img/ sous le même nom,
   puis vous confirmera ce qui a été fait.

3. Videz ensuite ce dossier (gardez seulement ce README.txt) pour
   pouvoir y déposer d’autres images plus tard.

────────────────────────────────────────
NOMS QUI N’EXISTENT PAS ENCORE
────────────────────────────────────────

Si vous déposez un fichier dont le nom n’existe pas dans img/
(ex. ma-photo.jpg), l’agent ne peut pas le placer automatiquement :
indiquez alors où le mettre et sous quel nom cible.

────────────────────────────────────────
EXEMPLES DE NOMS DÉJÀ PRÉSENTS DANS img/
────────────────────────────────────────

Hero / carousel :
  carousel-1.jpg, carousel-2.jpg

Logo :
  logo.png

À propos :
  about.jpg

Affiches des formations :
  affiche-1.jpg, affiche-2.jpg, affiche-3.jpg

Couvertures témoignages vidéo (TikTok) :
  vt-thumb-1.jpg … vt-thumb-4.jpg
  vtestim-1.jpg … vtestim-6.jpg

Autres (pages / sections secondaires) :
  blog-1.jpg … blog-3.jpg
  team-1.jpg … team-3.jpg
  testimonial-1.jpg … testimonial-4.jpg
  vendor-1.jpg … vendor-9.jpg
  feature.jpg, user.jpg

────────────────────────────────────────
NOTES
────────────────────────────────────────

- Conservez l’extension (.jpg, .png, etc.) identique à la cible.
- Préférez des images déjà compressées / web-optimisées (poids faible).
- Ne committez pas le contenu temporaire de ce dossier : seul ce
  README est versionné (voir .gitignore à la racine du repo).
