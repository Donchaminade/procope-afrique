-- =====================================================================
-- UPGRADE 008 — Projets incubés + réponses aux messages de contact
-- À appliquer sur les bases créées avant cette version :
--   php bin/apply-upgrade-008.php
-- Idempotent : CREATE TABLE IF NOT EXISTS + INSERT IGNORE.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Projets incubés (portfolio public + back-office)
-- stage : idee | prototype | lance | diplome (libellés FR côté appli)
-- socials : texte libre (un lien par ligne) ou JSON
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS incubated_projects (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(200) NOT NULL,
    slug         VARCHAR(200) NOT NULL UNIQUE,
    pitch        TEXT NULL COMMENT 'Accroche courte (cartes publiques)',
    description  MEDIUMTEXT NULL COMMENT 'Présentation complète',
    sector       VARCHAR(190) NULL,
    stage        ENUM('idee', 'prototype', 'lance', 'diplome') NOT NULL DEFAULT 'idee',
    country      VARCHAR(120) NULL,
    year         SMALLINT UNSIGNED NULL,
    website      VARCHAR(255) NULL,
    socials      TEXT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    archived_at  DATETIME NULL COMMENT 'NULL = actif, sinon date d''archivage',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_incproj_pub (is_published, archived_at, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Affiches d'un projet : plusieurs, UNE principale. Fichiers dans
-- public/uploads/projets/ (accès public, comme les affiches d'offres).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS project_images (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    path       VARCHAR(255) NOT NULL COMMENT 'Nom de fichier dans public/uploads/projets/',
    mime       VARCHAR(100) NOT NULL,
    is_main    TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_projimg_project (project_id, is_main, sort_order),
    CONSTRAINT fk_projimg_project FOREIGN KEY (project_id)
        REFERENCES incubated_projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Dépôts d'incubation (liés à un projet ou spontanés si project_id NULL).
-- Pitch deck PDF optionnel : storage/projets/ (session admin requise).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS project_applications (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id   INT UNSIGNED NULL COMMENT 'NULL = candidature spontanée',
    full_name    VARCHAR(190) NOT NULL,
    email        VARCHAR(190) NOT NULL,
    phone        VARCHAR(30) NULL,
    project_name VARCHAR(200) NULL COMMENT 'Nom du projet porté par le candidat',
    sector       VARCHAR(190) NULL,
    pitch        TEXT NULL,
    message      TEXT NULL,
    file_path    VARCHAR(255) NULL,
    file_mime    VARCHAR(100) NULL,
    file_name    VARCHAR(190) NULL COMMENT 'Nom de fichier original du pitch deck',
    statut       ENUM('nouvelle', 'en_examen', 'retenue', 'refusee') NOT NULL DEFAULT 'nouvelle',
    ip           VARCHAR(45) NULL,
    user_agent   VARCHAR(255) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_projapp_project (project_id, created_at),
    KEY idx_projapp_statut (statut, created_at),
    CONSTRAINT fk_projapp_project FOREIGN KEY (project_id)
        REFERENCES incubated_projects (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Réponses envoyées depuis l'admin (plusieurs par message, façon Gmail)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_replies (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id INT UNSIGNED NOT NULL,
    subject    VARCHAR(255) NOT NULL,
    body       MEDIUMTEXT NOT NULL,
    sent_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_id    INT UNSIGNED NULL,
    KEY idx_contact_replies_message (message_id, sent_at),
    CONSTRAINT fk_contact_replies_message FOREIGN KEY (message_id)
        REFERENCES contact_messages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Toggles projets — TOUS À 0 (l'admin les activera depuis Automatisations)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO settings (skey, svalue) VALUES
    ('auto_projet_publie', '0'),
    ('auto_depot_projet', '0'),
    ('auto_depot_retenu', '0'),
    ('auto_depot_refuse', '0');
