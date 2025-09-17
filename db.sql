-- Base de données pour système de gestion de bulletins
-- Version: 1.0

-- Table des établissements
CREATE TABLE etablissements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(255) NOT NULL,
    adresse TEXT,
    telephone VARCHAR(20),
    email VARCHAR(255),
    logo VARCHAR(255), -- chemin vers le logo
    directeur VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des années scolaires
CREATE TABLE annees_scolaires (
    id INT PRIMARY KEY AUTO_INCREMENT,
    libelle VARCHAR(50) NOT NULL, -- ex: 2024-2025
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    etablissement_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE
);

-- Table des classes
CREATE TABLE classes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL, -- ex: 6ème A, CM2, Terminale S
    niveau VARCHAR(50), -- ex: 6ème, CM2, Terminale
    section VARCHAR(50), -- ex: A, B, Scientifique
    effectif_max INT DEFAULT 35,
    etablissement_id INT NOT NULL,
    annee_scolaire_id INT NOT NULL,
    professeur_principal_id INT NULL, -- sera défini après création de la table users
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE,
    FOREIGN KEY (annee_scolaire_id) REFERENCES annees_scolaires(id) ON DELETE CASCADE
);

-- Table des utilisateurs (élèves, professeurs, admin)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(255) NOT NULL,
    prenoms VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    telephone VARCHAR(20),
    date_naissance DATE,
    lieu_naissance VARCHAR(255),
    sexe ENUM('M', 'F') NOT NULL,
    adresse TEXT,
    photo VARCHAR(255), -- chemin vers la photo
    type_user ENUM('admin', 'professeur', 'eleve', 'parent') NOT NULL,
    matricule VARCHAR(50) UNIQUE, -- numéro unique
    mot_de_passe VARCHAR(255) NOT NULL,
    actif BOOLEAN DEFAULT TRUE,
    etablissement_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE
);

-- Table des élèves (informations spécifiques aux élèves)
CREATE TABLE eleves (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    classe_id INT NOT NULL,
    numero_ordre INT, -- numéro dans la classe
    date_inscription DATE NOT NULL,
    statut ENUM('inscrit', 'transfere', 'abandonne', 'diplome') DEFAULT 'inscrit',
    nom_pere VARCHAR(255),
    nom_mere VARCHAR(255),
    telephone_tuteur VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE
);

-- Table des matières
CREATE TABLE matieres (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(255) NOT NULL, -- ex: Mathématiques, Français
    code VARCHAR(10) UNIQUE, -- ex: MATH, FR
    coefficient DECIMAL(3,1) DEFAULT 1.0,
    couleur VARCHAR(7) DEFAULT '#000000', -- couleur hex pour l'affichage
    description TEXT,
    etablissement_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE
);

-- Table de liaison classe-matière-professeur
CREATE TABLE classe_matiere_professeur (
    id INT PRIMARY KEY AUTO_INCREMENT,
    classe_id INT NOT NULL,
    matiere_id INT NOT NULL,
    professeur_id INT NOT NULL,
    coefficient_classe DECIMAL(3,1) DEFAULT 1.0, -- coefficient spécifique à cette classe
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (matiere_id) REFERENCES matieres(id) ON DELETE CASCADE,
    FOREIGN KEY (professeur_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_classe_matiere_prof (classe_id, matiere_id, professeur_id)
);

-- Table des périodes (trimestres, semestres)
CREATE TABLE periodes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL, -- ex: 1er Trimestre, 2ème Semestre
    numero_ordre INT NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    date_limite_saisie DATE, -- date limite pour saisir les notes
    active BOOLEAN DEFAULT TRUE,
    annee_scolaire_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (annee_scolaire_id) REFERENCES annees_scolaires(id) ON DELETE CASCADE
);

-- Table des types d'évaluations
CREATE TABLE types_evaluations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL, -- ex: Devoir, Composition, TP
    coefficient DECIMAL(3,1) DEFAULT 1.0,
    couleur VARCHAR(7) DEFAULT '#000000',
    etablissement_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (etablissement_id) REFERENCES etablissements(id) ON DELETE CASCADE
);

-- Table des évaluations
CREATE TABLE evaluations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    titre VARCHAR(255) NOT NULL,
    date_evaluation DATE NOT NULL,
    note_sur DECIMAL(4,2) DEFAULT 20.00, -- note sur combien (généralement 20)
    classe_matiere_professeur_id INT NOT NULL,
    periode_id INT NOT NULL,
    type_evaluation_id INT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (classe_matiere_professeur_id) REFERENCES classe_matiere_professeur(id) ON DELETE CASCADE,
    FOREIGN KEY (periode_id) REFERENCES periodes(id) ON DELETE CASCADE,
    FOREIGN KEY (type_evaluation_id) REFERENCES types_evaluations(id) ON DELETE CASCADE
);

-- Table des notes
CREATE TABLE notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    note DECIMAL(4,2), -- peut être NULL si absent
    statut ENUM('present', 'absent', 'dispense') DEFAULT 'present',
    commentaire TEXT,
    eleve_id INT NOT NULL,
    evaluation_id INT NOT NULL,
    saisie_par INT NOT NULL, -- ID du professeur qui a saisi
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE,
    FOREIGN KEY (saisie_par) REFERENCES users(id),
    UNIQUE KEY unique_eleve_evaluation (eleve_id, evaluation_id)
);

-- Table des imports de fichiers
CREATE TABLE imports_fichiers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom_fichier VARCHAR(255) NOT NULL,
    type_fichier ENUM('excel', 'csv', 'pdf', 'image') NOT NULL,
    chemin_original VARCHAR(500) NOT NULL,
    chemin_csv VARCHAR(500), -- chemin du fichier CSV converti
    statut ENUM('en_cours', 'converti', 'importe', 'erreur') DEFAULT 'en_cours',
    nombre_lignes INT DEFAULT 0,
    nombre_erreurs INT DEFAULT 0,
    details_erreurs TEXT, -- JSON des erreurs rencontrées
    importe_par INT NOT NULL,
    evaluation_id INT, -- si l'import concerne une évaluation spécifique
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (importe_par) REFERENCES users(id),
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE SET NULL
);

-- Table des bulletins générés
CREATE TABLE bulletins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    eleve_id INT NOT NULL,
    periode_id INT NOT NULL,
    moyenne_generale DECIMAL(4,2),
    rang_classe INT,
    effectif_classe INT,
    appreciation_generale TEXT,
    decision_conseil TEXT, -- ex: Passe en classe supérieure
    fichier_pdf VARCHAR(500), -- chemin du bulletin PDF généré
    statut ENUM('brouillon', 'valide', 'envoye') DEFAULT 'brouillon',
    genere_par INT NOT NULL,
    genere_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    valide_par INT NULL,
    valide_le TIMESTAMP NULL,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (periode_id) REFERENCES periodes(id) ON DELETE CASCADE,
    FOREIGN KEY (genere_par) REFERENCES users(id),
    FOREIGN KEY (valide_par) REFERENCES users(id),
    UNIQUE KEY unique_eleve_periode (eleve_id, periode_id)
);

-- Table des moyennes par matière (calculées)
CREATE TABLE moyennes_matieres (
    id INT PRIMARY KEY AUTO_INCREMENT,
    eleve_id INT NOT NULL,
    matiere_id INT NOT NULL,
    periode_id INT NOT NULL,
    moyenne DECIMAL(4,2),
    coefficient DECIMAL(3,1),
    rang_matiere INT,
    effectif_matiere INT,
    appreciation TEXT,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (matiere_id) REFERENCES matieres(id) ON DELETE CASCADE,
    FOREIGN KEY (periode_id) REFERENCES periodes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_eleve_matiere_periode (eleve_id, matiere_id, periode_id)
);

-- Ajout des contraintes de clés étrangères manquantes
ALTER TABLE classes ADD FOREIGN KEY (professeur_principal_id) REFERENCES users(id) ON DELETE SET NULL;

-- Index pour optimiser les performances
CREATE INDEX idx_notes_eleve_periode ON notes(eleve_id);
CREATE INDEX idx_evaluations_periode ON evaluations(periode_id);
CREATE INDEX idx_eleves_classe ON eleves(classe_id);
CREATE INDEX idx_users_type ON users(type_user);
CREATE INDEX idx_bulletins_periode ON bulletins(periode_id);

-- Données d'exemple pour les types d'évaluations
INSERT INTO types_evaluations (nom, coefficient, couleur, etablissement_id) VALUES
('Devoir', 1.0, '#3498db', 1),
('Composition', 2.0, '#e74c3c', 1),
('Travaux Pratiques', 1.0, '#2ecc71', 1),
('Interrogation', 0.5, '#f39c12', 1);