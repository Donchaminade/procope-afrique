-- =====================================================================
-- UPGRADE 011 — Témoignages (modération avant publication)
--   Source public : statut en_attente (l'équipe valide).
--   Source admin  : statut publie par défaut.
--   Photo facultative (JPEG/PNG/WebP) dans public/uploads/temoignages/.
-- Appliquer : php bin/apply-upgrade-011.php (idempotent)
-- =====================================================================

CREATE TABLE IF NOT EXISTS testimonials (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_name   VARCHAR(150) NOT NULL,
    author_email  VARCHAR(190) NULL COMMENT 'Optionnel : accusé de réception',
    role_title    VARCHAR(190) NULL COMMENT 'Poste / projet, ex. CEO de …',
    quote         TEXT NOT NULL,
    photo_path    VARCHAR(180) NULL,
    photo_mime    VARCHAR(80) NULL,
    source        ENUM('public', 'admin') NOT NULL DEFAULT 'public',
    statut        ENUM('en_attente', 'publie', 'refuse') NOT NULL DEFAULT 'en_attente',
    published_at  DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_testimonials_statut (statut),
    KEY idx_testimonials_pub (statut, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Accusé auteur + alerte équipe : ON par défaut
INSERT IGNORE INTO settings (skey, svalue) VALUES
    ('auto_temoignage_recu', '1'),
    ('auto_temoignage_alerte', '1');
