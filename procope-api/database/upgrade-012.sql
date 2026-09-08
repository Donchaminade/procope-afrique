-- =====================================================================
-- UPGRADE 012 — Galeries photos des formations (Actualités)
--   Albums groupés par année puis par nom de formation.
--   Photos publiques dans public/uploads/galeries/ (JPEG/PNG/WebP).
-- Appliquer : php bin/apply-upgrade-012.php (idempotent)
-- =====================================================================

CREATE TABLE IF NOT EXISTS formation_galleries (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    formation_id INT UNSIGNED NULL COMMENT 'Lien optionnel vers une formation',
    title        VARCHAR(200) NOT NULL COMMENT 'Nom affiché (prérempli depuis la formation)',
    year         SMALLINT NOT NULL,
    month        TINYINT NULL COMMENT '1-12, pour « janvier 2027 »',
    description  TEXT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_fg_pub_year (is_published, year, month),
    KEY idx_fg_formation (formation_id),
    CONSTRAINT fk_fg_formation FOREIGN KEY (formation_id)
        REFERENCES formations (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formation_gallery_images (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gallery_id INT UNSIGNED NOT NULL,
    path       VARCHAR(255) NOT NULL COMMENT 'Nom de fichier dans public/uploads/galeries/',
    mime       VARCHAR(100) NOT NULL,
    caption    VARCHAR(255) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fgi_gallery (gallery_id, sort_order),
    CONSTRAINT fk_fgi_gallery FOREIGN KEY (gallery_id)
        REFERENCES formation_galleries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
