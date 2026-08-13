-- =====================================================================
-- PROCOPE API - Schéma MySQL (utf8mb4)
-- Importer via phpMyAdmin Hostinger ou : mysql -u user -p db < migrations.sql
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------
-- Utilisateurs admin & rôles
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name          VARCHAR(150) NOT NULL,
    role          ENUM('super_admin', 'admin', 'operator') NOT NULL DEFAULT 'operator',
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Anti brute-force login
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip         VARCHAR(45) NOT NULL,
    email      VARCHAR(190) NOT NULL,
    success    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attempts_ip (ip, created_at),
    KEY idx_attempts_email (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Rate-limit générique (API publique)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rate_limits (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip         VARCHAR(45) NOT NULL,
    action     VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rate (ip, action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Formations (sessions)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS formations (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titre                 VARCHAR(200) NOT NULL,
    slug                  VARCHAR(200) NOT NULL UNIQUE,
    intro                 TEXT NULL,
    programme             TEXT NULL COMMENT 'Une ligne par point du programme',
    prix                  DECIMAL(12,2) NOT NULL DEFAULT 0,
    devise                VARCHAR(10) NOT NULL DEFAULT 'XOF',
    lieu                  VARCHAR(255) NULL,
    places_max            INT UNSIGNED NULL COMMENT 'NULL = illimité',
    inscriptions_ouvertes TINYINT(1) NOT NULL DEFAULT 0,
    ouverte_du            DATETIME NULL COMMENT 'Fenêtre d''ouverture optionnelle',
    ouverte_au            DATETIME NULL,
    message_fermeture     VARCHAR(500) NULL,
    contact_phone         VARCHAR(30) NULL,
    affiche_path          VARCHAR(255) NULL COMMENT 'Affiche publique (public/uploads/affiches/)',
    affiche_mime          VARCHAR(100) NULL,
    archived_at           DATETIME NULL COMMENT 'NULL = active, sinon date d''archivage',
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Créneaux (multi-jours : samedi 30, dimanche 31, ...)
CREATE TABLE IF NOT EXISTS formation_slots (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    formation_id INT UNSIGNED NOT NULL,
    label        VARCHAR(150) NOT NULL,
    starts_at    DATETIME NOT NULL,
    ends_at      DATETIME NOT NULL,
    sort_order   INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_slot_formation FOREIGN KEY (formation_id)
        REFERENCES formations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Inscriptions (champs du formulaire Google Forms)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inscriptions (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid                CHAR(36) NOT NULL UNIQUE,
    formation_id        INT UNSIGNED NOT NULL,
    full_name           VARCHAR(190) NOT NULL,
    gender              ENUM('M', 'F') NULL,
    phone               VARCHAR(30) NOT NULL,
    email               VARCHAR(190) NULL,
    city                VARCHAR(150) NULL,
    professional_status VARCHAR(190) NULL,
    is_entrepreneur     TINYINT(1) NULL,
    company_name        VARCHAR(190) NULL,
    sector              VARCHAR(190) NULL,
    motivation          TEXT NULL,
    modules             JSON NULL,
    payment_method      ENUM('mobile_money', 'ecobank') NULL,
    payment_type        ENUM('total', 'partiel') NOT NULL DEFAULT 'total',
    amount_declared     DECIMAL(12,2) NULL COMMENT 'Montant que le candidat déclare avoir payé',
    amount_received     DECIMAL(12,2) NULL COMMENT 'Montant vérifié saisi par l''admin',
    payment_proof_path  VARCHAR(255) NULL,
    payment_proof_mime  VARCHAR(100) NULL,
    payment_proof_name  VARCHAR(190) NULL,
    acquisition_source  VARCHAR(100) NULL,
    acquisition_other   VARCHAR(190) NULL,
    consent_image       TINYINT(1) NOT NULL DEFAULT 0,
    statut              ENUM('preinscrit', 'preuve_recue', 'valide', 'refuse', 'liste_attente', 'paiement_partiel')
                        NOT NULL DEFAULT 'preinscrit',
    ip                  VARCHAR(45) NULL,
    user_agent          VARCHAR(255) NULL,
    validated_by        INT UNSIGNED NULL,
    validated_at        DATETIME NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_insc_formation (formation_id, statut),
    KEY idx_insc_phone (formation_id, phone),
    CONSTRAINT fk_insc_formation FOREIGN KEY (formation_id)
        REFERENCES formations (id) ON DELETE RESTRICT,
    CONSTRAINT fk_insc_validator FOREIGN KEY (validated_by)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Journal d'audit
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NULL,
    action     VARCHAR(100) NOT NULL,
    entity     VARCHAR(50) NOT NULL DEFAULT '',
    entity_id  INT UNSIGNED NULL,
    ip         VARCHAR(45) NULL,
    meta       JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Réglages clé/valeur
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    skey   VARCHAR(100) PRIMARY KEY,
    svalue TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Messages de contact (formulaire du site public)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    email      VARCHAR(190) NULL,
    phone      VARCHAR(30) NULL,
    subject    VARCHAR(190) NULL COMMENT 'Sujet / service choisi dans le formulaire du site',
    message    TEXT NOT NULL,
    statut     ENUM('nouveau', 'lu', 'traite') NOT NULL DEFAULT 'nouveau',
    ip         VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_contact_statut (statut, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Logs d'envoi mail
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mail_logs (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inscription_id INT UNSIGNED NULL,
    type           VARCHAR(50) NOT NULL,
    to_email       VARCHAR(190) NOT NULL,
    status         ENUM('sent', 'failed') NOT NULL,
    error          TEXT NULL,
    sent_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mail_insc (inscription_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SEED : Formation PROCOPE 2027 (1ère vague, 30-31 janvier 2027)
-- =====================================================================
INSERT INTO formations
    (titre, slug, intro, programme, prix, devise, lieu, places_max,
     inscriptions_ouvertes, message_fermeture, contact_phone)
VALUES (
    'Formation PROCOPE 2027 — 1ère vague',
    'formation-procope-2027-vague-1',
    'Après 3 formations organisées en 2026, avec plus de 100 entrepreneurs formés et accompagnés, PROCOPE lance la première vague de formation de l''année 2027 ! Ne manquez pas cette occasion de structurer votre entreprise, développer vos activités et saisir de nouvelles opportunités de voyage et d''affaires. Attention : les places sont limitées !',
    'Comment voyager grâce à sa carte CFE\nCréer son entreprise en toute légalité\nMaîtriser la gestion des obligations fiscales et sociales (OTR & CNSS)',
    25000.00,
    'XOF',
    'Salle Melli FERA, derrière l''Hôtel Concorde, Adidoadin',
    NULL,
    1,
    'Les inscriptions sont actuellement fermées. Suivez nos réseaux sociaux pour être informé de la prochaine vague, ou contactez-nous au +228 96 45 76 95.',
    '+228 96 45 76 95'
)
ON DUPLICATE KEY UPDATE slug = slug;

INSERT INTO formation_slots (formation_id, label, starts_at, ends_at, sort_order)
SELECT f.id, s.label, s.starts_at, s.ends_at, s.sort_order
FROM formations f
CROSS JOIN (
    SELECT 'Samedi 30 janvier 2027'  AS label, '2027-01-30 08:30:00' AS starts_at, '2027-01-30 17:00:00' AS ends_at, 1 AS sort_order
    UNION ALL
    SELECT 'Dimanche 31 janvier 2027', '2027-01-31 14:30:00', '2027-01-31 20:00:00', 2
) s
WHERE f.slug = 'formation-procope-2027-vague-1'
  AND NOT EXISTS (SELECT 1 FROM formation_slots fs WHERE fs.formation_id = f.id);
