BEGIN;

-- Compliance obligations and fulfilment tracking.
CREATE SCHEMA IF NOT EXISTS compliance;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_type t
        JOIN pg_namespace n ON n.oid = t.typnamespace
        WHERE n.nspname = 'compliance' AND t.typname = 'obligation_kind'
    ) THEN
        CREATE TYPE compliance.obligation_kind AS ENUM (
            'statutory_report',
            'tax_filing',
            'audit',
            'policy_review',
            'licence_renewal',
            'other'
        );
    END IF;
END
$$ LANGUAGE plpgsql;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_type t
        JOIN pg_namespace n ON n.oid = t.typnamespace
        WHERE n.nspname = 'compliance' AND t.typname = 'obligation_status'
    ) THEN
        CREATE TYPE compliance.obligation_status AS ENUM (
            'active',
            'suspended',
            'retired'
        );
    END IF;
END
$$ LANGUAGE plpgsql;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_type t
        JOIN pg_namespace n ON n.oid = t.typnamespace
        WHERE n.nspname = 'compliance' AND t.typname = 'fulfilment_status'
    ) THEN
        CREATE TYPE compliance.fulfilment_status AS ENUM (
            'pending',
            'submitted',
            'accepted',
            'rejected',
            'waived',
            'cancelled'
        );
    END IF;
END
$$ LANGUAGE plpgsql;

CREATE TABLE IF NOT EXISTS compliance.obligations (
    obligation_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organisation_id BIGINT NOT NULL REFERENCES shared.organisations(organisation_id) ON DELETE CASCADE,
    role_assignment_id BIGINT REFERENCES registry.role_assignments(role_assignment_id) ON DELETE SET NULL,
    title TEXT NOT NULL,
    obligation_kind compliance.obligation_kind NOT NULL,
    jurisdiction CHAR(2) NOT NULL DEFAULT 'NL' REFERENCES shared.jurisdictions(country_code),
    legal_reference TEXT,
    frequency TEXT,
    first_due_on DATE,
    status compliance.obligation_status NOT NULL DEFAULT 'active',
    risk_rating TEXT,
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT compliance_obligations_meta_object CHECK (JSONB_TYPEOF(meta) = 'object')
);

CREATE TABLE IF NOT EXISTS compliance.fulfilments (
    fulfilment_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    obligation_id BIGINT NOT NULL REFERENCES compliance.obligations(obligation_id) ON DELETE CASCADE,
    period_start DATE,
    period_end DATE,
    due_on DATE NOT NULL,
    submitted_on DATE,
    status compliance.fulfilment_status NOT NULL DEFAULT 'pending',
    reference_code TEXT,
    submission_uri TEXT,
    submitted_by BIGINT REFERENCES identity.accounts(account_id) ON DELETE SET NULL,
    notes JSONB,
    attachments JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT fulfilments_notes_json CHECK (notes IS NULL OR JSONB_TYPEOF(notes) = 'object'),
    CONSTRAINT fulfilments_attachments_json CHECK (attachments IS NULL OR JSONB_TYPEOF(attachments) = 'array')
);

CREATE INDEX IF NOT EXISTS compliance_fulfilments_due_idx ON compliance.fulfilments (due_on, status);

DO LANGUAGE plpgsql E'
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''compliance_obligations_touch'') THEN
        CREATE TRIGGER compliance_obligations_touch
        BEFORE UPDATE ON compliance.obligations
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''compliance_fulfilments_touch'') THEN
        CREATE TRIGGER compliance_fulfilments_touch
        BEFORE UPDATE ON compliance.fulfilments
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
END;
';

COMMIT;
