BEGIN;

-- Shared master data used across all NederAI modules.
CREATE SCHEMA IF NOT EXISTS shared;

CREATE TABLE IF NOT EXISTS shared.jurisdictions (
    country_code CHAR(2) PRIMARY KEY,
    name TEXT NOT NULL,
    is_eu_member BOOLEAN NOT NULL DEFAULT FALSE,
    is_eea_member BOOLEAN NOT NULL DEFAULT FALSE,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO shared.jurisdictions (country_code, name, is_eu_member, is_eea_member, active)
VALUES
    ('AT', 'Austria', TRUE, TRUE, TRUE),
    ('BE', 'Belgium', TRUE, TRUE, TRUE),
    ('BG', 'Bulgaria', TRUE, TRUE, TRUE),
    ('CY', 'Cyprus', TRUE, TRUE, TRUE),
    ('CZ', 'Czechia', TRUE, TRUE, TRUE),
    ('DE', 'Germany', TRUE, TRUE, TRUE),
    ('DK', 'Denmark', TRUE, TRUE, TRUE),
    ('EE', 'Estonia', TRUE, TRUE, TRUE),
    ('EL', 'Greece', TRUE, TRUE, TRUE),
    ('ES', 'Spain', TRUE, TRUE, TRUE),
    ('FI', 'Finland', TRUE, TRUE, TRUE),
    ('FR', 'France', TRUE, TRUE, TRUE),
    ('HR', 'Croatia', TRUE, TRUE, TRUE),
    ('HU', 'Hungary', TRUE, TRUE, TRUE),
    ('IE', 'Ireland', TRUE, TRUE, TRUE),
    ('IS', 'Iceland', FALSE, TRUE, TRUE),
    ('IT', 'Italy', TRUE, TRUE, TRUE),
    ('LI', 'Liechtenstein', FALSE, TRUE, TRUE),
    ('LT', 'Lithuania', TRUE, TRUE, TRUE),
    ('LU', 'Luxembourg', TRUE, TRUE, TRUE),
    ('LV', 'Latvia', TRUE, TRUE, TRUE),
    ('MT', 'Malta', TRUE, TRUE, TRUE),
    ('NL', 'Netherlands', TRUE, TRUE, TRUE),
    ('NO', 'Norway', FALSE, TRUE, TRUE),
    ('PL', 'Poland', TRUE, TRUE, TRUE),
    ('PT', 'Portugal', TRUE, TRUE, TRUE),
    ('RO', 'Romania', TRUE, TRUE, TRUE),
    ('SE', 'Sweden', TRUE, TRUE, TRUE),
    ('SI', 'Slovenia', TRUE, TRUE, TRUE),
    ('SK', 'Slovakia', TRUE, TRUE, TRUE)
ON CONFLICT (country_code) DO UPDATE
SET name = EXCLUDED.name,
    is_eu_member = EXCLUDED.is_eu_member,
    is_eea_member = EXCLUDED.is_eea_member,
    active = EXCLUDED.active,
    updated_at = NOW();

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_type t
        JOIN pg_namespace n ON n.oid = t.typnamespace
        WHERE n.nspname = 'shared' AND t.typname = 'organisation_kind'
    ) THEN
        CREATE TYPE shared.organisation_kind AS ENUM (
            'foundation',
            'company',
            'public_body',
            'circle',
            'person',
            'partner',
            'other'
        );
    END IF;
END
$$ LANGUAGE plpgsql;

CREATE TABLE IF NOT EXISTS shared.organisations (
    organisation_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    org_key TEXT UNIQUE,
    parent_organisation_id BIGINT REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    name TEXT NOT NULL,
    legal_form TEXT,
    organisation_kind shared.organisation_kind NOT NULL,
    country_code CHAR(2) NOT NULL DEFAULT 'NL' REFERENCES shared.jurisdictions(country_code) ON UPDATE CASCADE,
    chamber_of_commerce TEXT,
    tax_number TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ,
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    CONSTRAINT organisations_meta_object CHECK (JSONB_TYPEOF(meta) = 'object')
);

INSERT INTO shared.organisations (org_key, name, legal_form, organisation_kind, meta)
SELECT 'stichting', 'Stichting NederAI', 'Stichting', 'foundation', '{}'::JSONB
WHERE NOT EXISTS (SELECT 1 FROM shared.organisations WHERE org_key = 'stichting');

INSERT INTO shared.organisations (org_key, name, legal_form, organisation_kind, parent_organisation_id, meta)
SELECT 'alliance', 'NederAI Alliance BV', 'BV', 'company', o.organisation_id, '{}'::JSONB
FROM shared.organisations o
WHERE o.org_key = 'stichting'
  AND NOT EXISTS (SELECT 1 FROM shared.organisations WHERE org_key = 'alliance');

INSERT INTO shared.organisations (org_key, name, legal_form, organisation_kind, parent_organisation_id, meta)
SELECT 'institute', 'NederAI Institute BV', 'BV', 'company', o.organisation_id, '{}'::JSONB
FROM shared.organisations o
WHERE o.org_key = 'alliance'
  AND NOT EXISTS (SELECT 1 FROM shared.organisations WHERE org_key = 'institute');

INSERT INTO shared.organisations (org_key, name, legal_form, organisation_kind, parent_organisation_id, meta)
SELECT 'commercial', 'NederAI Commercial BV', 'BV', 'company', o.organisation_id, '{}'::JSONB
FROM shared.organisations o
WHERE o.org_key = 'alliance'
  AND NOT EXISTS (SELECT 1 FROM shared.organisations WHERE org_key = 'commercial');

CREATE TABLE IF NOT EXISTS shared.person_profiles (
    person_id BIGINT PRIMARY KEY REFERENCES shared.organisations(organisation_id) ON DELETE CASCADE,
    given_names TEXT NOT NULL,
    family_name TEXT NOT NULL,
    date_of_birth DATE,
    nationality CHAR(2) REFERENCES shared.jurisdictions(country_code),
    privacy_purpose TEXT NOT NULL,
    consent_record JSONB NOT NULL DEFAULT '{}'::JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ,
    CONSTRAINT person_profiles_consent_object CHECK (JSONB_TYPEOF(consent_record) = 'object')
);

CREATE TABLE IF NOT EXISTS shared.party_contacts (
    contact_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organisation_id BIGINT NOT NULL REFERENCES shared.organisations(organisation_id) ON DELETE CASCADE,
    contact_kind TEXT NOT NULL,
    contact_value TEXT NOT NULL,
    purpose TEXT NOT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    valid_from DATE DEFAULT CURRENT_DATE,
    valid_until DATE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    CONSTRAINT party_contacts_meta_object CHECK (JSONB_TYPEOF(meta) = 'object')
);

CREATE OR REPLACE FUNCTION shared.touch_updated_at()
RETURNS TRIGGER
LANGUAGE plpgsql
AS E'
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
';

DO LANGUAGE plpgsql E'
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''shared_organisations_touch'') THEN
        CREATE TRIGGER shared_organisations_touch
        BEFORE UPDATE ON shared.organisations
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''shared_person_profiles_touch'') THEN
        CREATE TRIGGER shared_person_profiles_touch
        BEFORE UPDATE ON shared.person_profiles
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''shared_party_contacts_touch'') THEN
        CREATE TRIGGER shared_party_contacts_touch
        BEFORE UPDATE ON shared.party_contacts
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
END;
';

COMMIT;
