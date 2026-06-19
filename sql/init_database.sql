-- =============================================================
-- Création de la base de données
-- Intégrations : API Brevo (contacts/consentements)
--                API Weezevent (billets/événements)
-- =============================================================

-- Activer la locale française pour la session


-- Exemple : afficher la date en français
SELECT TO_CHAR(NOW(), 'TMDay DD TMMonth YYYY') AS date_fr;


-- -------------------------------------------------------------
-- SUPPRESSION DES TABLES
-- -------------------------------------------------------------
DROP TABLE IF EXISTS contact_segment       CASCADE;
DROP TABLE IF EXISTS consentement_contact  CASCADE;
DROP TABLE IF EXISTS contact_billet        CASCADE;
DROP TABLE IF EXISTS billet_evenement      CASCADE;
DROP TABLE IF EXISTS billet                CASCADE;
DROP TABLE IF EXISTS segment               CASCADE;
DROP TABLE IF EXISTS consentement          CASCADE;
DROP TABLE IF EXISTS evenement            CASCADE;
DROP TABLE IF EXISTS contact               CASCADE;
DROP TABLE IF EXISTS administrateur        CASCADE;

-- Suppression des types ENUM personnalisés s'ils existent déjà
DROP TYPE IF EXISTS statut_admin      CASCADE;
DROP TYPE IF EXISTS statut_consent    CASCADE;
DROP TYPE IF EXISTS bool_marketing    CASCADE;


-- -------------------------------------------------------------
-- TYPES ENUM 
-- -------------------------------------------------------------
CREATE TYPE statut_admin   AS ENUM ('actif', 'inactif');
CREATE TYPE statut_consent AS ENUM ('actif', 'révoqué', 'expiré');


-- =============================================================
-- TABLE : ADMINISTRATEUR
-- Gestionnaires de la plateforme
-- =============================================================
CREATE TABLE administrateur (
    idAdministrateur  SERIAL          NOT NULL,
    nom               VARCHAR(100)    NOT NULL,
    prenom            VARCHAR(100)    NOT NULL,
    email             VARCHAR(255)    NOT NULL UNIQUE,
    mot_de_passe      VARCHAR(255)    NOT NULL,   -- Hash bcrypt
    statut            statut_admin    NOT NULL DEFAULT 'actif',
    PRIMARY KEY (idAdministrateur)
);

COMMENT ON COLUMN administrateur.mot_de_passe IS 'Hash bcrypt';


-- =============================================================
-- TABLE : CONTACT
-- Contacts synchronisés avec Brevo
-- =============================================================
CREATE TABLE contact (
    idContact               SERIAL          NOT NULL,
    nom                     VARCHAR(100)    NOT NULL,
    prenom                  VARCHAR(100)    NOT NULL,
    email                   VARCHAR(255)    NOT NULL UNIQUE,
    phone                   VARCHAR(30)     NULL,
    source                  VARCHAR(50)     NOT NULL DEFAULT 'manual',
    date_creation           TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    date_derniere_maj       TIMESTAMPTZ     NULL,
    consentement_marketing  BOOLEAN         NOT NULL DEFAULT FALSE,
    -- TRUE = optin Brevo, FALSE = optout
    PRIMARY KEY (idContact)
);

CREATE INDEX idx_contact_email ON contact(email);
CREATE INDEX idx_contact_source ON contact(source);
CREATE INDEX idx_contact_date_creation ON contact(date_creation);
CREATE INDEX idx_billet_date_achat ON billet(date_achat);
CREATE INDEX idx_billet_type_tarif ON billet(type_tarif);
CREATE INDEX idx_evenement_date ON evenement(date);

COMMENT ON COLUMN contact.consentement_marketing IS 'TRUE = optin Brevo, FALSE = optout';

-- Trigger pour mettre à jour date_derniere_maj automatiquement
CREATE OR REPLACE FUNCTION maj_date_derniere_maj()
RETURNS TRIGGER AS $$
BEGIN
    NEW.date_derniere_maj = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_contact_maj
    BEFORE UPDATE ON contact
    FOR EACH ROW
    EXECUTE FUNCTION maj_date_derniere_maj();

-- =============================================================
-- TABLE : CONSENTEMENT
-- =============================================================
CREATE TABLE consentement (
    idConsentement  SERIAL          NOT NULL,
    type            VARCHAR(100)    NOT NULL,  -- ex: marketing, transactionnel
    date            TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    source          VARCHAR(100)    NOT NULL,  -- ex: formulaire_web, import, API_Brevo
    statut          VARCHAR(100)   NOT NULL DEFAULT 'actif',
    PRIMARY KEY (idConsentement)
);

COMMENT ON COLUMN consentement.type   IS 'ex: marketing, transactionnel';
COMMENT ON COLUMN consentement.source IS 'ex: formulaire_web, import, API_Brevo';


-- =============================================================
-- TABLE : SEGMENT
-- =============================================================
CREATE TABLE segment (
    idSegment      SERIAL          NOT NULL,
    nom_segment    VARCHAR(150)    NOT NULL,
    date_creation  TIMESTAMPTZ     NOT NULL DEFAULT NOW(),
    brevo_id       INTEGER         NULL,
    PRIMARY KEY (idSegment)
);

COMMENT ON COLUMN segment.brevo_id IS 'ID de la liste correspondante sur Brevo';


-- =============================================================
-- TABLE : EVENEMENTS
-- =============================================================
CREATE TABLE evenement (
    idEvenementWeezevent  INTEGER         NOT NULL,
    -- ID fourni directement par l'API Weezevent (pas de SERIAL)
    nom_evenement         VARCHAR(255)    NOT NULL,
    date                  TIMESTAMPTZ     NOT NULL,
    lieu                  VARCHAR(255)    NOT NULL,
    type                  VARCHAR(100)    NOT NULL,  -- ex: concert, festival, conférence
    saison                VARCHAR(50)     NULL,       -- ex: 2024-2025
    PRIMARY KEY (idEvenementWeezevent)
);

COMMENT ON COLUMN evenement.idEvenementWeezevent IS 'ID fourni par l''API Weezevent';
COMMENT ON COLUMN evenement.type                 IS 'ex: concert, festival, conférence';


-- =============================================================
-- TABLE : BILLET
-- =============================================================
CREATE TABLE billet (
    idBilletWeezevent   INTEGER         NOT NULL,
    -- ID fourni directement par l'API Weezevent (pas de SERIAL)
    date_achat          TIMESTAMPTZ     NOT NULL,
    quantite            INTEGER         NOT NULL DEFAULT 1 CHECK (quantite > 0),
    montant_total       NUMERIC(10,2)   NOT NULL CHECK (montant_total >= 0),
    type_tarif          VARCHAR(100)    NOT NULL,  -- ex: plein tarif, réduit, gratuit
    code_promotionnel   VARCHAR(50)     NULL,
    origine             VARCHAR(100)    NOT NULL,  -- ex: widget_web, API_Weezevent, guichet
    PRIMARY KEY (idBilletWeezevent)
);

COMMENT ON COLUMN billet.idBilletWeezevent IS 'ID fourni par l''API Weezevent';
COMMENT ON COLUMN billet.type_tarif        IS 'ex: plein tarif, réduit, gratuit';
COMMENT ON COLUMN billet.origine           IS 'ex: widget_web, API_Weezevent, guichet';


-- =============================================================
-- TABLES DE LIAISON
-- =============================================================

-- Consentement_Contact : Un contact peut avoir plusieurs consentements
CREATE TABLE consentement_contact (
    idConsentement  INTEGER  NOT NULL,
    idContact       INTEGER  NOT NULL,
    PRIMARY KEY (idConsentement, idContact),
    FOREIGN KEY (idConsentement) REFERENCES consentement(idConsentement)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (idContact)      REFERENCES contact(idContact)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- Contact_Segment : Un contact peut appartenir à plusieurs segments Brevo
CREATE TABLE contact_segment (
    idContact   INTEGER     NOT NULL,
    idSegment   INTEGER     NOT NULL,
    date_ajout  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (idContact, idSegment),
    FOREIGN KEY (idContact)  REFERENCES contact(idContact)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (idSegment)  REFERENCES segment(idSegment)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- Contact_Billet : Un contact peut avoir acheté plusieurs billets
CREATE TABLE contact_billet (
    idContact          INTEGER  NOT NULL,
    idBilletWeezevent  INTEGER  NOT NULL,
    PRIMARY KEY (idContact, idBilletWeezevent),
    FOREIGN KEY (idContact)         REFERENCES contact(idContact)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (idBilletWeezevent) REFERENCES billet(idBilletWeezevent)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- Billet_Evenement : Un billet est lié à un événement Weezevent
CREATE TABLE billet_evenement (
    idBilletWeezevent     INTEGER  NOT NULL,
    idEvenementWeezevent  INTEGER  NOT NULL,
    PRIMARY KEY (idBilletWeezevent, idEvenementWeezevent),
    FOREIGN KEY (idBilletWeezevent)     REFERENCES billet(idBilletWeezevent)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (idEvenementWeezevent)  REFERENCES evenement(idEvenementWeezevent)
        ON DELETE CASCADE ON UPDATE CASCADE
);


-- =============================================================
-- JEU DE DONNÉES FICTIF
-- =============================================================

-- -------------------------------------------------------------
-- ADMINISTRATEURS
-- -------------------------------------------------------------
-- aymeric.muller@gmail.fr --> khGloYj^2&EWUWRRs
-- matthieu.debaisieux@gmail.fr --> $([fW4qbLXIVek6&^
INSERT INTO administrateur (nom, prenom, email, mot_de_passe, statut) VALUES
('Muller',  'Aymeric', 'aymeric.muller@gmail.fr', '$2a$12$bf6TxSYnbsUZ/j31.leEneRcaf95.2RAKOAlaKPyaGcYXkJiILjQS', 'actif'),
('Debaisieux', 'Matthieu', 'matthieu.debaisieux@gmail.fr',   '$2a$12$rVFrm8bMh43hcwtrU3qH1Oet1mtrOoBQv9LsSMJnpaTArch1eVZmu', 'actif');


