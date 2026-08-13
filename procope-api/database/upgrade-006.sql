-- =====================================================================
-- UPGRADE 006 — Affiches multiples des offres d'emploi
-- À appliquer sur les bases créées avant cette version :
--   mysql -u user -p db < upgrade-006.sql
-- Idempotent : CREATE TABLE IF NOT EXISTS.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Images (affiches) d'une offre d'emploi : une offre peut en avoir
-- plusieurs, dont UNE principale (is_main = 1). Fichiers stockés en
-- public/uploads/offres/ (accès public, comme les affiches de formation).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS job_offer_images (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    offer_id   INT UNSIGNED NOT NULL,
    path       VARCHAR(255) NOT NULL COMMENT 'Nom de fichier dans public/uploads/offres/',
    mime       VARCHAR(100) NOT NULL,
    is_main    TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_joboffimg_offer (offer_id, is_main, sort_order),
    CONSTRAINT fk_joboffimg_offer FOREIGN KEY (offer_id)
        REFERENCES job_offers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
