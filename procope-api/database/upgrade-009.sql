-- =====================================================================
-- UPGRADE 009 — Trois chemins d'incubation
--   1. Appels à incubation (incubation_calls + project_applications.call_id)
--   2. Dépôts spontanés (call_id NULL)
--   3. Projets vitrine manuels (incubated_projects.application_id NULL)
--   Dépôt retenu → incubated_projects.application_id (brouillon puis publication)
-- Appliquer : php bin/apply-upgrade-009.php (idempotent)
-- =====================================================================

CREATE TABLE IF NOT EXISTS incubation_calls (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(200) NOT NULL,
    slug         VARCHAR(200) NOT NULL UNIQUE,
    sector       VARCHAR(190) NULL,
    description  MEDIUMTEXT NULL,
    opens_at     DATETIME NOT NULL,
    closes_at    DATETIME NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    archived_at  DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_calls_pub (is_published, archived_at, opens_at, closes_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Colonnes ajoutées par le script PHP si absentes :
--   project_applications.call_id
--   incubated_projects.application_id UNIQUE

-- Accusé / félicitations / refus : ON par défaut (SMTP déjà opérationnel)
INSERT IGNORE INTO settings (skey, svalue) VALUES
    ('auto_depot_projet', '1'),
    ('auto_depot_retenu', '1'),
    ('auto_depot_refuse', '1'),
    ('auto_projet_publie', '0');

UPDATE settings SET svalue = '1' WHERE skey IN ('auto_depot_projet', 'auto_depot_retenu', 'auto_depot_refuse');
