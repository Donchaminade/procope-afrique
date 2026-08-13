-- =====================================================================
-- UPGRADE 005 — Module Offres d'emploi (offres + candidatures + toggles)
-- À appliquer sur les bases créées avant cette version :
--   mysql -u user -p db < upgrade-005.sql
-- Idempotent : CREATE TABLE IF NOT EXISTS + INSERT IGNORE.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Offres d'emploi publiées par PROCOPE
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS job_offers (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title            VARCHAR(200) NOT NULL,
    slug             VARCHAR(200) NOT NULL UNIQUE,
    description      MEDIUMTEXT NULL COMMENT 'Détails complets de l''offre',
    location         VARCHAR(255) NULL,
    contract_type    ENUM('CDI', 'CDD', 'Stage', 'Freelance', 'Autre') NOT NULL DEFAULT 'Autre',
    salary           VARCHAR(100) NULL COMMENT 'Salaire / indemnité (texte libre, optionnel)',
    closes_at        DATETIME NOT NULL COMMENT 'Date limite de candidature',
    is_published     TINYINT(1) NOT NULL DEFAULT 0,
    reminder_sent_at DATETIME NULL COMMENT 'Anti-doublon du rappel J-5 (NULL = pas encore envoyé)',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Candidatures reçues sur les offres (CV stocké dans storage/cv/)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS job_applications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    offer_id   INT UNSIGNED NOT NULL,
    full_name  VARCHAR(190) NOT NULL,
    email      VARCHAR(190) NOT NULL,
    phone      VARCHAR(30) NULL,
    message    TEXT NULL COMMENT 'Message / motivation du candidat',
    cv_path    VARCHAR(255) NULL,
    cv_mime    VARCHAR(100) NULL,
    cv_name    VARCHAR(190) NULL COMMENT 'Nom de fichier original du CV',
    statut     ENUM('nouvelle', 'en_examen', 'retenue', 'refusee') NOT NULL DEFAULT 'nouvelle',
    ip         VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_jobapp_offer (offer_id, created_at),
    CONSTRAINT fk_jobapp_offer FOREIGN KEY (offer_id)
        REFERENCES job_offers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Toggles des automatisations emploi — TOUS À 0 (l'utilisateur les
-- activera lui-même depuis Admin → Automatisations)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO settings (skey, svalue) VALUES
    ('auto_offre_publiee', '0'),
    ('auto_offre_rappel', '0'),
    ('auto_offre_prolongee', '0'),
    ('auto_candidature_emploi', '0');
