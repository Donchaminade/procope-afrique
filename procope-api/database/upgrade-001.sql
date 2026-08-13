-- =====================================================================
-- UPGRADE 001 — Affiche par formation + paiement total/partiel
-- À appliquer sur les bases créées avant cette version :
--   mysql -u user -p db < upgrade-001.sql
-- (migrations.sql contient déjà ces colonnes pour les installations neuves)
-- =====================================================================

-- Affiche publique de la formation (stockée dans public/uploads/affiches/)
ALTER TABLE formations
    ADD COLUMN affiche_path VARCHAR(255) NULL COMMENT 'Affiche publique (public/uploads/affiches/)' AFTER contact_phone,
    ADD COLUMN affiche_mime VARCHAR(100) NULL AFTER affiche_path;

-- Paiement total ou partiel déclaré par le candidat + montant vérifié par l'admin
ALTER TABLE inscriptions
    ADD COLUMN payment_type ENUM('total', 'partiel') NOT NULL DEFAULT 'total' AFTER payment_method,
    ADD COLUMN amount_declared DECIMAL(12,2) NULL COMMENT 'Montant que le candidat déclare avoir payé' AFTER payment_type,
    ADD COLUMN amount_received DECIMAL(12,2) NULL COMMENT 'Montant vérifié saisi par l''admin' AFTER amount_declared;

-- Nouveau statut « paiement partiel »
ALTER TABLE inscriptions
    MODIFY COLUMN statut ENUM('preinscrit', 'preuve_recue', 'valide', 'refuse', 'liste_attente', 'paiement_partiel')
        NOT NULL DEFAULT 'preinscrit';
