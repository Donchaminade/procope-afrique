-- =====================================================================
-- UPGRADE 007 — Archivage des offres d'emploi
-- À appliquer sur les bases créées avant cette version :
--   mysql -u user -p db < upgrade-007.sql
-- =====================================================================

-- Date d'archivage : NULL = offre active, sinon archivée (masquée des
-- listes admin/API mais conservée avec toutes ses candidatures)
ALTER TABLE job_offers
    ADD COLUMN archived_at DATETIME NULL COMMENT 'NULL = active, sinon date d''archivage' AFTER reminder_sent_at;
