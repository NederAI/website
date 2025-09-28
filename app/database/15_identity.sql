-- Identity and access management (IAM) primitives.
CREATE SCHEMA IF NOT EXISTS identity;

CREATE TYPE IF NOT EXISTS identity.account_status AS ENUM (
    'pending',
    'active',
    'suspended',
    'disabled'
);

CREATE TABLE IF NOT EXISTS identity.accounts (
    account_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organisation_id BIGINT REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE SET NULL,
    email CITEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    status identity.account_status NOT NULL DEFAULT 'pending',
    data_processing_purpose TEXT NOT NULL DEFAULT 'account_access',
    mfa_secret BYTEA,
    mfa_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    needs_password_reset BOOLEAN NOT NULL DEFAULT TRUE,
    last_authenticated_at TIMESTAMPTZ,
    consent_version TEXT,
    consent_signed_at TIMESTAMPTZ,
    privacy_meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ,
    CONSTRAINT accounts_privacy_meta_object CHECK (JSONB_TYPEOF(privacy_meta) = 'object')
);

CREATE TABLE IF NOT EXISTS identity.account_events (
    account_event_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    account_id BIGINT NOT NULL REFERENCES identity.accounts(account_id) ON DELETE CASCADE,
    event_kind TEXT NOT NULL,
    occurred_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    ip_address INET,
    user_agent TEXT,
    payload JSONB NOT NULL DEFAULT '{}'::JSONB,
    CONSTRAINT account_events_payload_object CHECK (JSONB_TYPEOF(payload) = 'object')
);

DO LANGUAGE plpgsql E'
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''identity_accounts_touch'') THEN
        CREATE TRIGGER identity_accounts_touch
        BEFORE UPDATE ON identity.accounts
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
END;
';
