-- =====================================================================
-- UPGRADE 013 — Galeries : type photos | affiche
--   photos  = galerie de vague (N photos, bandeau + grille)
--   affiche = événement passé (1 image principale + extras)
-- Appliquer : php bin/apply-upgrade-013.php (idempotent)
-- =====================================================================

ALTER TABLE formation_galleries
    ADD COLUMN kind ENUM('photos', 'affiche') NOT NULL DEFAULT 'photos'
        COMMENT 'photos = galerie de vague ; affiche = événement passé'
        AFTER description;

ALTER TABLE formation_galleries
    ADD KEY idx_fg_kind (is_published, kind, year);
