-- Equity and certificate registers for the NederAI group.
CREATE SCHEMA IF NOT EXISTS shares;

CREATE TYPE IF NOT EXISTS shares.event_type AS ENUM (
    'ISSUE',
    'TRANSFER',
    'CANCEL',
    'SPLIT',
    'CONSOLIDATE',
    'PLEDGE',
    'PLEDGE_RELEASE',
    'USUFRUCT',
    'USUFRUCT_RELEASE'
);

CREATE TABLE IF NOT EXISTS shares.companies (
    company_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organisation_id BIGINT NOT NULL UNIQUE REFERENCES shared.organisations(organisation_id) ON DELETE CASCADE,
    legal_form TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    CONSTRAINT shares_companies_meta_object CHECK (JSONB_TYPEOF(meta) = 'object')
);

CREATE TABLE IF NOT EXISTS shares.share_classes (
    share_class_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES shares.companies(company_id) ON DELETE CASCADE,
    code TEXT NOT NULL,
    description TEXT,
    nominal_value NUMERIC(18,6) NOT NULL CHECK (nominal_value > 0),
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    voting_rights BOOLEAN NOT NULL DEFAULT TRUE,
    profit_rights BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    CONSTRAINT share_classes_meta_object CHECK (JSONB_TYPEOF(meta) = 'object')
);

CREATE UNIQUE INDEX IF NOT EXISTS share_classes_company_code_idx ON shares.share_classes (company_id, code);

CREATE TABLE IF NOT EXISTS shares.share_events (
    share_event_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES shares.companies(company_id) ON DELETE CASCADE,
    share_class_id BIGINT NOT NULL REFERENCES shares.share_classes(share_class_id) ON DELETE CASCADE,
    event_date DATE NOT NULL,
    event_type shares.event_type NOT NULL,
    from_party_id BIGINT REFERENCES shared.organisations(organisation_id) ON DELETE SET NULL,
    to_party_id BIGINT REFERENCES shared.organisations(organisation_id) ON DELETE SET NULL,
    quantity NUMERIC(24,6) NOT NULL CHECK (quantity > 0),
    paid_up_amount NUMERIC(18,2),
    certificate_from TEXT,
    certificate_to TEXT,
    minutes_reference TEXT,
    notes TEXT,
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT share_events_meta_object CHECK (JSONB_TYPEOF(meta) = 'object')
);

CREATE INDEX IF NOT EXISTS share_events_company_idx ON shares.share_events (company_id, event_date);
CREATE INDEX IF NOT EXISTS share_events_party_idx ON shares.share_events (from_party_id, to_party_id);

CREATE OR REPLACE VIEW shares.shareholder_positions AS
WITH movements AS (
    SELECT
        e.company_id,
        e.share_class_id,
        e.to_party_id AS party_id,
        CASE WHEN e.event_type IN ('ISSUE','TRANSFER','SPLIT','CONSOLIDATE','PLEDGE_RELEASE','USUFRUCT_RELEASE') THEN e.quantity ELSE 0 END AS qty_plus,
        0::NUMERIC(24,6) AS qty_minus
    FROM shares.share_events e
    WHERE e.to_party_id IS NOT NULL
    UNION ALL
    SELECT
        e.company_id,
        e.share_class_id,
        e.from_party_id AS party_id,
        0::NUMERIC(24,6) AS qty_plus,
        CASE WHEN e.event_type IN ('TRANSFER','CANCEL','PLEDGE','USUFRUCT') THEN e.quantity ELSE 0 END AS qty_minus
    FROM shares.share_events e
    WHERE e.from_party_id IS NOT NULL
)
SELECT
    company_id,
    share_class_id,
    party_id,
    SUM(qty_plus - qty_minus) AS quantity
FROM movements
GROUP BY company_id, share_class_id, party_id
HAVING SUM(qty_plus - qty_minus) <> 0;

DO LANGUAGE plpgsql E'
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''shares_companies_touch'') THEN
        CREATE TRIGGER shares_companies_touch
        BEFORE UPDATE ON shares.companies
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''shares_share_classes_touch'') THEN
        CREATE TRIGGER shares_share_classes_touch
        BEFORE UPDATE ON shares.share_classes
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''shares_share_events_touch'') THEN
        CREATE TRIGGER shares_share_events_touch
        BEFORE UPDATE ON shares.share_events
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
END;
';
