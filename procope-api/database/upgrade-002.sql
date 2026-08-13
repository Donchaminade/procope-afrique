-- =====================================================================
-- UPGRADE 002 — Messages de contact (formulaire du site -> back-office)
-- À appliquer sur les bases créées avant cette version :
--   mysql -u user -p db < upgrade-002.sql
-- (migrations.sql contient déjà cette table pour les installations neuves)
-- =====================================================================

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
