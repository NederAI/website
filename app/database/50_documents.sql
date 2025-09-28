BEGIN;

-- Structured document storage links with retention metadata.
CREATE SCHEMA IF NOT EXISTS documents;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_type t
        JOIN pg_namespace n ON n.oid = t.typnamespace
        WHERE n.nspname = 'documents' AND t.typname = 'classification_level'
    ) THEN
        CREATE TYPE documents.classification_level AS ENUM (
            'public',
            'internal',
            'confidential',
            'restricted'
        );
    END IF;
END
$$ LANGUAGE plpgsql;

CREATE TABLE IF NOT EXISTS documents.files (
    document_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organisation_id BIGINT REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE SET NULL,
    uploaded_by BIGINT REFERENCES identity.accounts(account_id) ON UPDATE CASCADE ON DELETE SET NULL,
    source_module TEXT,
    title TEXT NOT NULL,
    storage_uri TEXT NOT NULL UNIQUE,
    checksum TEXT,
    mime_type TEXT,
    file_size BIGINT,
    classification documents.classification_level NOT NULL DEFAULT 'internal',
    retention_class TEXT NOT NULL DEFAULT 'default_7y',
    retention_expires_on DATE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    CONSTRAINT documents_files_meta_object CHECK (JSONB_TYPEOF(meta) = 'object')
);

CREATE TABLE IF NOT EXISTS documents.file_links (
    file_link_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    document_id BIGINT NOT NULL REFERENCES documents.files(document_id) ON DELETE CASCADE,
    target_schema TEXT,
    target_table TEXT,
    target_identifier TEXT,
    purpose TEXT NOT NULL,
    legal_basis TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    CONSTRAINT document_links_meta_object CHECK (JSONB_TYPEOF(meta) = 'object')
);

CREATE INDEX IF NOT EXISTS file_links_target_idx ON documents.file_links (target_schema, target_table, target_identifier);

DO LANGUAGE plpgsql E'
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''documents_files_touch'') THEN
        CREATE TRIGGER documents_files_touch
        BEFORE UPDATE ON documents.files
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
END;
';

COMMIT;
