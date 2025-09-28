-- Corporate registry data (roles, mandates, licences).
CREATE SCHEMA IF NOT EXISTS registry;

CREATE TYPE IF NOT EXISTS registry.role_type AS ENUM (
    'director',
    'executive',
    'supervisor',
    'representative',
    'beneficial_owner',
    'secretary',
    'advisor',
    'other'
);

CREATE TYPE IF NOT EXISTS registry.role_status AS ENUM (
    'active',
    'pending',
    'resigned',
    'suspended',
    'terminated'
);

CREATE TABLE IF NOT EXISTS registry.role_assignments (
    role_assignment_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organisation_id BIGINT NOT NULL REFERENCES shared.organisations(organisation_id) ON DELETE CASCADE,
    role_holder_id BIGINT NOT NULL REFERENCES shared.organisations(organisation_id) ON DELETE RESTRICT,
    role_type registry.role_type NOT NULL,
    status registry.role_status NOT NULL DEFAULT 'active',
    appointment_reference TEXT,
    start_date DATE NOT NULL,
    end_date DATE,
    legal_basis TEXT,
    responsibilities JSONB NOT NULL DEFAULT '{}'::JSONB,
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT role_assignments_responsibilities_object CHECK (JSONB_TYPEOF(responsibilities) = 'object'),
    CONSTRAINT role_assignments_meta_object CHECK (JSONB_TYPEOF(meta) = 'object')
);

CREATE TABLE IF NOT EXISTS registry.licence_records (
    licence_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organisation_id BIGINT NOT NULL REFERENCES shared.organisations(organisation_id) ON DELETE CASCADE,
    licence_type TEXT NOT NULL,
    issuing_body TEXT,
    licence_reference TEXT,
    jurisdiction CHAR(2) NOT NULL DEFAULT 'NL' REFERENCES shared.jurisdictions(country_code),
    issued_on DATE,
    valid_from DATE,
    expires_on DATE,
    status TEXT NOT NULL DEFAULT 'active',
    conditions JSONB NOT NULL DEFAULT '{}'::JSONB,
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT licence_records_conditions_object CHECK (JSONB_TYPEOF(conditions) = 'object'),
    CONSTRAINT licence_records_meta_object CHECK (JSONB_TYPEOF(meta) = 'object')
);

CREATE INDEX IF NOT EXISTS licence_records_org_status_idx ON registry.licence_records (organisation_id, status);

DO LANGUAGE plpgsql E'
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''registry_role_assignments_touch'') THEN
        CREATE TRIGGER registry_role_assignments_touch
        BEFORE UPDATE ON registry.role_assignments
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''registry_licence_records_touch'') THEN
        CREATE TRIGGER registry_licence_records_touch
        BEFORE UPDATE ON registry.licence_records
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
END;
';
