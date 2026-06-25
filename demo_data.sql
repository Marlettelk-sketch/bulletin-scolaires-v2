-- ============================================================
--  SCOLARIS — Données de démo pour la présentation prototype
--
--  À importer dans phpMyAdmin APRÈS scolaris.sql
--  (qui contient déjà les comptes + données de base)
--
--  Ce script :
--  1. Corrige l'incohérence niveau (classe L1 → L2)
--  2. Ajoute 2 UE supplémentaires avec matières
--  3. Ajoute des notes variées pour une démo vivante
--  4. Ajoute l'attribution professeur → matières
--  5. Ajoute quelques notifications de démo
--
--  Comptes de démo (même mot de passe pour tous : Admin@2025) :
--    Admin    → admin@scolaris.tg
--    Étudiant → peace@test.com
--    Prof     → prof.amoussou@test.com
-- ============================================================

USE scolaris;

-- ============================================================
-- 1. CORRIGER L'INCOHÉRENCE NIVEAU
--    La classe (id=1) est en niveau_id=1 (L1) mais les UE/matières
--    sont en niveau_id=2 (L2). On aligne tout sur L2.
-- ============================================================
UPDATE classes SET niveau_id = 2 WHERE id = 1;
UPDATE etudiants SET classe_id = 1 WHERE id = 1; -- déjà correct, sécurité

-- ============================================================
-- 2. COMPLÉTER LA FILIÈRE
-- ============================================================
UPDATE filieres SET nom = 'Systèmes Informatiques et Logiciels', code = 'SIL'
WHERE id = 1;

-- ============================================================
-- 3. UE SUPPLÉMENTAIRES (niveau L2, Semestre S1 et S2)
-- ============================================================
INSERT INTO `unites_enseignement` (`id`, `code`, `nom`, `credits`, `niveau_id`, `semestre`) VALUES
(2, 'UE2', 'Développement Web', 6, 2, 'S1'),
(3, 'UE3', 'Base de données', 4, 2, 'S1'),
(4, 'UE4', 'Systèmes d''exploitation', 4, 2, 'S2'),
(5, 'UE5', 'Réseaux informatiques', 4, 2, 'S2')
ON DUPLICATE KEY UPDATE nom = VALUES(nom);

-- Corriger l'UE existante pour qu'elle soit cohérente
UPDATE unites_enseignement SET code = 'UE1', nom = 'Algorithmique & Structures de données', credits = 6, niveau_id = 2
WHERE id = 1;

-- ============================================================
-- 4. MATIÈRES (toutes rattachées à la filière SIL, niveau L2)
-- ============================================================
INSERT INTO `matieres` (`id`, `nom`, `code`, `coefficient`, `credits`, `ue_id`, `filiere_id`, `niveau_id`, `semestre`) VALUES
(2, 'Programmation en C',        'PROG-C',  2, 2, 1, 1, 2, 'S1'),
(3, 'HTML/CSS/JavaScript',       'WEB1',    3, 3, 2, 1, 2, 'S1'),
(4, 'PHP & MySQL',               'WEB2',    3, 3, 2, 1, 2, 'S1'),
(5, 'Modélisation UML',          'UML',     2, 2, 3, 1, 2, 'S1'),
(6, 'SQL avancé',                'SQL',     2, 2, 3, 1, 2, 'S1'),
(7, 'Linux & Shell',             'LINUX',   2, 2, 4, 1, 2, 'S2'),
(8, 'Architecture OS',           'ARCHI',   2, 2, 4, 1, 2, 'S2'),
(9, 'TCP/IP & Protocoles',       'TCP',     2, 2, 5, 1, 2, 'S2'),
(10,'Administration réseau',     'ADMIN-R', 2, 2, 5, 1, 2, 'S2')
ON DUPLICATE KEY UPDATE nom = VALUES(nom);

-- Corriger la matière existante
UPDATE matieres SET niveau_id = 2, ue_id = 1 WHERE id = 1;

-- ============================================================
-- 5. ATTRIBUTIONS (Prof AMOUSSOU enseigne toutes les matières S1)
-- ============================================================
INSERT INTO `attributions` (`professeur_id`, `matiere_id`, `classe_id`, `annee_academique_id`) VALUES
(1, 1, 1, 1),
(1, 2, 1, 1),
(1, 3, 1, 1),
(1, 4, 1, 1),
(1, 5, 1, 1),
(1, 6, 1, 1)
ON DUPLICATE KEY UPDATE professeur_id = VALUES(professeur_id);

-- ============================================================
-- 6. NOTES (étudiant id=1, variées pour une démo vivante)
-- ============================================================
INSERT INTO `notes` (`etudiant_id`, `matiere_id`, `professeur_id`, `annee_academique_id`, `semestre`, `note`, `observation`) VALUES
(1, 1,  1, 1, 'S1', 14.00, NULL),
(1, 2,  1, 1, 'S1', 16.50, 'Très bien'),
(1, 3,  1, 1, 'S1', 12.75, NULL),
(1, 4,  1, 1, 'S1', 18.00, 'Excellent'),
(1, 5,  1, 1, 'S1', 11.00, NULL),
(1, 6,  1, 1, 'S1', 15.25, 'Bien')
ON DUPLICATE KEY UPDATE note = VALUES(note), observation = VALUES(observation);

-- ============================================================
-- 7. NOTIFICATIONS DE DÉMO
-- ============================================================
INSERT INTO `notifications` (`utilisateur_id`, `titre`, `message`, `lu`, `type`) VALUES
(3, 'Note ajoutée en Algorithmique',   'Votre note de 14/20 a été enregistrée en Algorithmique.', 0, 'note'),
(3, 'Note ajoutée en HTML/CSS/JS',     'Votre note de 16.5/20 a été enregistrée.', 0, 'note'),
(3, 'Bulletin S1 disponible',          'Votre bulletin du Semestre 1 est maintenant disponible.', 0, 'bulletin'),
(3, 'Devoir de PHP prévu',             'Un devoir de PHP est programmé le 30/06/2026 à 08h00 — Salle B2.', 0, 'devoir_controle'),
(3, 'Bienvenue sur Scolaris',          'Votre compte étudiant est activé. Bonne année académique 2025-2026 !', 1, 'general');
