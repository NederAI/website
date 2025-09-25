-- Accounting schema for PostgreSQL using ReferentieGrootboekSchema (RGS)
-- Run this script once against the target database (psql -f accounting_schema.sql)

BEGIN;

CREATE SCHEMA IF NOT EXISTS accounting;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_type t
        JOIN pg_namespace n ON t.typnamespace = n.oid
        WHERE t.typname = 'debit_credit'
          AND n.nspname = 'accounting'
    ) THEN
        CREATE TYPE accounting.debit_credit AS ENUM ('debit', 'credit');
    END IF;
END$$;

CREATE TABLE IF NOT EXISTS accounting.rgs_nodes (
    code           VARCHAR(32) PRIMARY KEY,
    title_nl       TEXT        NOT NULL,
    title_en       TEXT,
    level          SMALLINT    NOT NULL CHECK (level >= 1),
    parent_code    VARCHAR(32) REFERENCES accounting.rgs_nodes(code) ON DELETE SET NULL,
    account_type   VARCHAR(16) NOT NULL CHECK (account_type IN ('asset','liability','equity','revenue','expense','memo')),
    function_label VARCHAR(64),
    is_postable    BOOLEAN     NOT NULL DEFAULT FALSE,
    version_tag    VARCHAR(32) DEFAULT NULL,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS accounting.ledger_accounts (
    id            BIGSERIAL PRIMARY KEY,
    code          VARCHAR(64) NOT NULL UNIQUE,
    name          TEXT        NOT NULL,
    rgs_code      VARCHAR(32) REFERENCES accounting.rgs_nodes(code) ON DELETE SET NULL,
    account_type  VARCHAR(16) NOT NULL CHECK (account_type IN ('asset','liability','equity','revenue','expense','memo')),
    currency      CHAR(3)     NOT NULL DEFAULT 'EUR',
    metadata      JSONB       NOT NULL DEFAULT '{}'::JSONB,
    archived_at   TIMESTAMPTZ,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS ledger_accounts_rgs_code_idx ON accounting.ledger_accounts(rgs_code);
CREATE INDEX IF NOT EXISTS ledger_accounts_type_idx ON accounting.ledger_accounts(account_type);

CREATE TABLE IF NOT EXISTS accounting.journals (
    id             BIGSERIAL PRIMARY KEY,
    code           VARCHAR(32) NOT NULL UNIQUE,
    name           TEXT        NOT NULL,
    description    TEXT,
    default_currency CHAR(3) NOT NULL DEFAULT 'EUR',
    allow_manual   BOOLEAN    NOT NULL DEFAULT TRUE,
    metadata       JSONB      NOT NULL DEFAULT '{}'::JSONB,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS accounting.journal_entries (
    id             BIGSERIAL PRIMARY KEY,
    journal_id     BIGINT     NOT NULL REFERENCES accounting.journals(id) ON DELETE RESTRICT,
    entry_date     DATE       NOT NULL,
    reference      VARCHAR(64),
    description    TEXT,
    currency       CHAR(3)    NOT NULL DEFAULT 'EUR',
    exchange_rate  NUMERIC(20,8) DEFAULT 1.0 CHECK (exchange_rate > 0),
    status         VARCHAR(16) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','posted','void')),
    posted_at      TIMESTAMPTZ,
    metadata       JSONB      NOT NULL DEFAULT '{}'::JSONB,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS journal_entries_journal_id_idx ON accounting.journal_entries(journal_id);
CREATE INDEX IF NOT EXISTS journal_entries_entry_date_idx ON accounting.journal_entries(entry_date);

CREATE TABLE IF NOT EXISTS accounting.journal_entry_lines (
    id             BIGSERIAL PRIMARY KEY,
    entry_id       BIGINT      NOT NULL REFERENCES accounting.journal_entries(id) ON DELETE CASCADE,
    account_id     BIGINT      NOT NULL REFERENCES accounting.ledger_accounts(id) ON DELETE RESTRICT,
    rgs_code       VARCHAR(32) REFERENCES accounting.rgs_nodes(code) ON DELETE SET NULL,
    description    TEXT,
    direction      accounting.debit_credit NOT NULL,
    amount         NUMERIC(20,5) NOT NULL CHECK (amount > 0),
    quantity       NUMERIC(20,5),
    metadata       JSONB       NOT NULL DEFAULT '{}'::JSONB,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS journal_entry_lines_entry_idx ON accounting.journal_entry_lines(entry_id);
CREATE INDEX IF NOT EXISTS journal_entry_lines_account_idx ON accounting.journal_entry_lines(account_id);

CREATE OR REPLACE FUNCTION accounting.touch_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at := now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS ledger_accounts_touch_updated_at ON accounting.ledger_accounts;
CREATE TRIGGER ledger_accounts_touch_updated_at
BEFORE UPDATE ON accounting.ledger_accounts
FOR EACH ROW EXECUTE FUNCTION accounting.touch_updated_at();

DROP TRIGGER IF EXISTS journals_touch_updated_at ON accounting.journals;
CREATE TRIGGER journals_touch_updated_at
BEFORE UPDATE ON accounting.journals
FOR EACH ROW EXECUTE FUNCTION accounting.touch_updated_at();

DROP TRIGGER IF EXISTS journal_entries_touch_updated_at ON accounting.journal_entries;
CREATE TRIGGER journal_entries_touch_updated_at
BEFORE UPDATE ON accounting.journal_entries
FOR EACH ROW EXECUTE FUNCTION accounting.touch_updated_at();

DROP TRIGGER IF EXISTS journal_entry_lines_touch_updated_at ON accounting.journal_entry_lines;
CREATE TRIGGER journal_entry_lines_touch_updated_at
BEFORE UPDATE ON accounting.journal_entry_lines
FOR EACH ROW EXECUTE FUNCTION accounting.touch_updated_at();

CREATE OR REPLACE FUNCTION accounting.ensure_journal_entry_balanced()
RETURNS TRIGGER AS $$
DECLARE
    target_entry BIGINT := COALESCE(NEW.entry_id, OLD.entry_id);
    delta NUMERIC(20,5);
    line_count INTEGER;
BEGIN
    SELECT
        COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END), 0),
        COUNT(*)
    INTO delta, line_count
    FROM accounting.journal_entry_lines
    WHERE entry_id = target_entry;

    IF line_count IS NULL OR line_count < 2 THEN
        RAISE EXCEPTION 'Journal entry % must contain at least two lines.', target_entry
            USING ERRCODE = '23514';
    END IF;

    IF delta IS NULL OR abs(delta) > 0.00001 THEN
        RAISE EXCEPTION 'Journal entry % is not balanced (difference = %).', target_entry, delta
            USING ERRCODE = '23514';
    END IF;

    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS journal_entry_balanced ON accounting.journal_entry_lines;
CREATE CONSTRAINT TRIGGER journal_entry_balanced
AFTER INSERT OR UPDATE OR DELETE ON accounting.journal_entry_lines
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION accounting.ensure_journal_entry_balanced();

CREATE OR REPLACE VIEW accounting.trial_balance AS
SELECT
    a.id AS account_id,
    a.code AS account_code,
    a.name AS account_name,
    a.account_type,
    a.currency,
    COALESCE(SUM(CASE WHEN l.direction = 'debit' THEN l.amount ELSE 0 END), 0) AS total_debit,
    COALESCE(SUM(CASE WHEN l.direction = 'credit' THEN l.amount ELSE 0 END), 0) AS total_credit,
    COALESCE(SUM(CASE WHEN l.direction = 'debit' THEN l.amount ELSE -l.amount END), 0) AS balance
FROM accounting.ledger_accounts a
LEFT JOIN accounting.journal_entry_lines l ON l.account_id = a.id
GROUP BY a.id, a.code, a.name, a.account_type, a.currency
ORDER BY a.code;

COMMIT;

