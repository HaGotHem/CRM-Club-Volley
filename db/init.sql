-- Schéma initial de la base CRM Nice Volley Ball.
-- Exécuté automatiquement par l'image postgres au PREMIER démarrage
-- (uniquement si le volume de données est vide).

CREATE TABLE IF NOT EXISTS contacts (
    id           SERIAL PRIMARY KEY,
    first_name   VARCHAR(255) NOT NULL,
    last_name    VARCHAR(255) NOT NULL,
    email        VARCHAR(255) NOT NULL UNIQUE,
    phone        VARCHAR(50),
    source       VARCHAR(50)  NOT NULL DEFAULT 'manual',
    ticket_count INTEGER      NOT NULL DEFAULT 0,
    is_invited   BOOLEAN      NOT NULL DEFAULT false,
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at   TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_contacts_source     ON contacts (source);
CREATE INDEX IF NOT EXISTS idx_contacts_created_at ON contacts (created_at);

-- Quelques données d'exemple pour que le dashboard affiche des valeurs réelles.
INSERT INTO contacts (first_name, last_name, email, phone, source, ticket_count, is_invited, created_at) VALUES
    ('Marie',  'Dupont',   'marie.dupont@example.com',   '0600000001', 'weezevent', 2, false, now() - interval '2 days'),
    ('Lucas',  'Martin',   'lucas.martin@example.com',   '0600000002', 'weezevent', 4, true,  now() - interval '5 days'),
    ('Sophie', 'Bernard',  'sophie.bernard@example.com', '0600000003', 'brevo',     0, true,  now() - interval '20 days'),
    ('Hugo',   'Petit',    'hugo.petit@example.com',     '0600000004', 'manual',    1, false, now() - interval '1 days'),
    ('Emma',   'Robert',   'emma.robert@example.com',    '0600000005', 'brevo',     3, false, now() - interval '40 days'),
    ('Tom',    'Richard',  'tom.richard@example.com',    '0600000006', 'weezevent', 2, true,  now() - interval '3 days')
ON CONFLICT (email) DO NOTHING;
