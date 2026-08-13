-- =====================================================================
-- UPGRADE 003 — Archivage des formations
-- À appliquer sur les bases créées avant cette version :
--   mysql -u user -p db < upgrade-003.sql
-- (migrations.sql contient déjà cette colonne pour les installations neuves)
-- =====================================================================

-- Date d'archivage : NULL = formation active, sinon archivée (masquée des
-- listes admin/API mais conservée avec toutes ses inscriptions)
ALTER TABLE formations
    ADD COLUMN archived_at DATETIME NULL COMMENT 'NULL = active, sinon date d''archivage' AFTER affiche_mime;
