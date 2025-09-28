-- General ledger structure with NL RGS alignment fields.
CREATE SCHEMA IF NOT EXISTS accounting;

CREATE TYPE IF NOT EXISTS accounting.account_kind AS ENUM (
    'balance',
    'result'
);

CREATE TYPE IF NOT EXISTS accounting.normal_side AS ENUM (
    'debit',
    'credit'
);

CREATE TYPE IF NOT EXISTS accounting.batch_status AS ENUM (
    'open',
    'posted',
    'archived'
);

CREATE TABLE IF NOT EXISTS accounting.accounts (
    account_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organisation_id BIGINT NOT NULL REFERENCES shared.organisations(organisation_id) ON DELETE CASCADE,
    code TEXT NOT NULL,
    rgs_code TEXT,
    name TEXT NOT NULL,
    description TEXT,
    parent_account_id BIGINT REFERENCES accounting.accounts(account_id) ON DELETE RESTRICT,
    account_kind accounting.account_kind NOT NULL,
    normal_side accounting.normal_side NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    CONSTRAINT accounting_accounts_meta_object CHECK (JSONB_TYPEOF(meta) = 'object'),
    CONSTRAINT accounting_accounts_parent_self CHECK (parent_account_id IS NULL OR parent_account_id <> account_id)
);

CREATE UNIQUE INDEX IF NOT EXISTS accounting_accounts_org_code_idx ON accounting.accounts (organisation_id, code);

CREATE TABLE IF NOT EXISTS accounting.journal_batches (
    batch_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organisation_id BIGINT NOT NULL REFERENCES shared.organisations(organisation_id) ON DELETE CASCADE,
    parent_batch_id BIGINT REFERENCES accounting.journal_batches(batch_id) ON DELETE SET NULL,
    title TEXT NOT NULL,
    period_start DATE,
    period_end DATE,
    status accounting.batch_status NOT NULL DEFAULT 'open',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    CONSTRAINT journal_batches_meta_object CHECK (JSONB_TYPEOF(meta) = 'object')
);

CREATE INDEX IF NOT EXISTS journal_batches_org_idx ON accounting.journal_batches (organisation_id, status);

CREATE TABLE IF NOT EXISTS accounting.journal_entries (
    entry_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    batch_id BIGINT NOT NULL REFERENCES accounting.journal_batches(batch_id) ON DELETE CASCADE,
    organisation_id BIGINT NOT NULL REFERENCES shared.organisations(organisation_id) ON DELETE CASCADE,
    entry_no BIGINT,
    entry_date DATE NOT NULL,
    document_reference TEXT,
    description TEXT,
    posted_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    CONSTRAINT journal_entries_meta_object CHECK (JSONB_TYPEOF(meta) = 'object')
);

CREATE UNIQUE INDEX IF NOT EXISTS journal_entries_batch_entry_no_idx ON accounting.journal_entries (batch_id, entry_no);

CREATE TABLE IF NOT EXISTS accounting.journal_lines (
    line_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    entry_id BIGINT NOT NULL REFERENCES accounting.journal_entries(entry_id) ON DELETE CASCADE,
    organisation_id BIGINT NOT NULL REFERENCES shared.organisations(organisation_id) ON DELETE CASCADE,
    account_id BIGINT NOT NULL REFERENCES accounting.accounts(account_id) ON DELETE RESTRICT,
    direction CHAR(1) NOT NULL CHECK (direction IN ('D','C')),
    amount NUMERIC(18,2) NOT NULL CHECK (amount > 0),
    memo TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    CONSTRAINT journal_lines_meta_object CHECK (JSONB_TYPEOF(meta) = 'object')
);

CREATE INDEX IF NOT EXISTS journal_lines_entry_idx ON accounting.journal_lines (entry_id);
CREATE INDEX IF NOT EXISTS journal_lines_account_idx ON accounting.journal_lines (account_id);

CREATE OR REPLACE VIEW accounting.trial_balance AS
SELECT
    le.organisation_id,
    le.entry_date,
    ln.account_id,
    SUM(CASE WHEN ln.direction = 'D' THEN ln.amount ELSE 0 END) AS debit,
    SUM(CASE WHEN ln.direction = 'C' THEN ln.amount ELSE 0 END) AS credit
FROM accounting.journal_entries le
JOIN accounting.journal_lines ln ON ln.entry_id = le.entry_id
GROUP BY le.organisation_id, le.entry_date, ln.account_id;

DO LANGUAGE plpgsql E'
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''accounting_accounts_touch'') THEN
        CREATE TRIGGER accounting_accounts_touch
        BEFORE UPDATE ON accounting.accounts
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''accounting_journal_batches_touch'') THEN
        CREATE TRIGGER accounting_journal_batches_touch
        BEFORE UPDATE ON accounting.journal_batches
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''accounting_journal_entries_touch'') THEN
        CREATE TRIGGER accounting_journal_entries_touch
        BEFORE UPDATE ON accounting.journal_entries
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
END;
';
