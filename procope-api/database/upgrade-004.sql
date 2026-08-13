-- =====================================================================
-- UPGRADE 004 — Modèles d'e-mail personnalisables (override en base)
-- À appliquer sur les bases créées avant cette version :
--   mysql -u user -p db < upgrade-004.sql
-- Table vide = tous les rendus viennent des fichiers templates/mail/.
-- =====================================================================

CREATE TABLE IF NOT EXISTS mail_templates (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(50) NOT NULL COMMENT 'Nom du template (confirmation, alert, ...)',
    subject    VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Sujet avec {{variables}}',
    body       MEDIUMTEXT NULL COMMENT 'Corps HTML simple avec {{variables}}, injecté dans _base',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mail_templates_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
