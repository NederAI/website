BEGIN;

-- Identity and access management (IAM) primitives.
CREATE SCHEMA IF NOT EXISTS identity;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_type t
        JOIN pg_namespace n ON n.oid = t.typnamespace
        WHERE n.nspname = 'identity' AND t.typname = 'account_status'
    ) THEN
        CREATE TYPE identity.account_status AS ENUM (
            'pending',
            'active',
            'suspended',
            'disabled'
        );
    END IF;
END
$$ LANGUAGE plpgsql;

CREATE TABLE IF NOT EXISTS identity.accounts (
    account_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organisation_id BIGINT REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE SET NULL,
    email CITEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    nickname TEXT NOT NULL,
    status identity.account_status NOT NULL DEFAULT 'pending',
    data_processing_purpose TEXT NOT NULL DEFAULT 'account_access',
    mfa_secret BYTEA,
    mfa_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    needs_password_reset BOOLEAN NOT NULL DEFAULT TRUE,
    last_authenticated_at TIMESTAMPTZ,
    last_seen_at TIMESTAMPTZ,
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

CREATE INDEX IF NOT EXISTS identity_account_events_idx ON identity.account_events (account_id, occurred_at DESC);

CREATE TABLE IF NOT EXISTS identity.roles (
    role_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    role_key TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    CONSTRAINT identity_roles_meta_object CHECK (JSONB_TYPEOF(meta) = 'object')
);

CREATE TABLE IF NOT EXISTS identity.account_roles (
    account_role_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    account_id BIGINT NOT NULL REFERENCES identity.accounts(account_id) ON DELETE CASCADE,
    role_id BIGINT NOT NULL REFERENCES identity.roles(role_id) ON DELETE CASCADE,
    granted_by BIGINT REFERENCES identity.accounts(account_id) ON DELETE SET NULL,
    granted_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    grant_reason TEXT,
    UNIQUE (account_id, role_id)
);

CREATE INDEX IF NOT EXISTS identity_account_roles_role_idx ON identity.account_roles (role_id);

DO LANGUAGE plpgsql E'
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''identity_accounts_touch'') THEN
        CREATE TRIGGER identity_accounts_touch
        BEFORE UPDATE ON identity.accounts
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''identity_roles_touch'') THEN
        CREATE TRIGGER identity_roles_touch
        BEFORE UPDATE ON identity.roles
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
END;
';

INSERT INTO identity.roles (role_key, name, description)
VALUES
    ('intranet.member', 'Intranet Member', 'Baseline access to the intranet shell.'),
    ('intranet.admin', 'Intranet Administrator', 'Administrative access to manage intranet configuration.')
ON CONFLICT (role_key) DO UPDATE
SET
    name = EXCLUDED.name,
    description = EXCLUDED.description,
    updated_at = NOW();

COMMIT;
