-- Adminer 5.4.0 PostgreSQL 17.6 dump
-- Depends on shared.organisations for organisation hierarchy

DROP TABLE IF EXISTS "accounts";
DROP SEQUENCE IF EXISTS accounts_account_id_seq;
CREATE SEQUENCE accounts_account_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;

CREATE TABLE "accounting"."accounts" (
    "account_id" bigint DEFAULT nextval('accounts_account_id_seq') NOT NULL,
    "org_key" character varying NOT NULL,
    "rgs_code" character varying NOT NULL,
    "title" text NOT NULL,
    "description" text,
    "parent_account_id" bigint,
    "kind" text NOT NULL,
    "normal_side" text NOT NULL,
    "is_active" boolean DEFAULT true NOT NULL,
    CONSTRAINT "accounts_pkey" PRIMARY KEY ("account_id"),
    CONSTRAINT "accounts_kind_check" CHECK (kind = ANY (ARRAY['balance'::text, 'result'::text])),
    CONSTRAINT "accounts_normal_side_check" CHECK (normal_side = ANY (ARRAY['debit'::text, 'credit'::text]))
)
WITH (oids = false);

CREATE UNIQUE INDEX accounts_org_key_rgs_code_key ON accounting.accounts USING btree (org_key, rgs_code);

CREATE INDEX accounts_org_parent_idx ON accounting.accounts USING btree (org_key, parent_account_id);


DROP TABLE IF EXISTS "batches";
DROP SEQUENCE IF EXISTS batches_batch_id_seq;
CREATE SEQUENCE batches_batch_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;

CREATE TABLE "accounting"."batches" (
    "batch_id" bigint DEFAULT nextval('batches_batch_id_seq') NOT NULL,
    "org_key" character varying NOT NULL,
    "parent_batch_id" bigint,
    "title" text NOT NULL,
    "description" text,
    "period_start" date,
    "period_end" date,
    "status" text DEFAULT 'open' NOT NULL,
    "created_at" timestamptz DEFAULT now() NOT NULL,
    CONSTRAINT "batches_pkey" PRIMARY KEY ("batch_id"),
    CONSTRAINT "batches_status_check" CHECK (status = ANY (ARRAY['open'::text, 'posted'::text, 'archived'::text]))
)
WITH (oids = false);

CREATE INDEX batches_org_parent_idx ON accounting.batches USING btree (org_key, parent_batch_id);


DROP TABLE IF EXISTS "entries";
DROP SEQUENCE IF EXISTS entries_entry_id_seq;
CREATE SEQUENCE entries_entry_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;

CREATE TABLE "accounting"."entries" (
    "entry_id" bigint DEFAULT nextval('entries_entry_id_seq') NOT NULL,
    "org_key" character varying NOT NULL,
    "batch_id" bigint NOT NULL,
    "entry_no" bigint,
    "entry_date" date NOT NULL,
    "doc_no" text,
    "description" text,
    "posted_at" timestamptz,
    CONSTRAINT "entries_pkey" PRIMARY KEY ("entry_id")
)
WITH (oids = false);

CREATE UNIQUE INDEX entries_batch_id_entry_no_key ON accounting.entries USING btree (batch_id, entry_no);


DROP TABLE IF EXISTS "lines";
DROP SEQUENCE IF EXISTS lines_line_id_seq;
CREATE SEQUENCE lines_line_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;

CREATE TABLE "accounting"."lines" (
    "line_id" bigint DEFAULT nextval('lines_line_id_seq') NOT NULL,
    "org_key" character varying NOT NULL,
    "entry_id" bigint NOT NULL,
    "account_id" bigint NOT NULL,
    "dc" character(1) NOT NULL,
    "amount" numeric(18,2) NOT NULL,
    "memo" text,
    CONSTRAINT "lines_pkey" PRIMARY KEY ("line_id"),
    CONSTRAINT "lines_dc_check" CHECK (dc = ANY (ARRAY['D'::bpchar, 'C'::bpchar])),
    CONSTRAINT "lines_amount_check" CHECK (amount > (0)::numeric)
)
WITH (oids = false);

CREATE INDEX lines_entry_idx ON accounting.lines USING btree (entry_id);

CREATE INDEX lines_account_idx ON accounting.lines USING btree (account_id);


DROP VIEW IF EXISTS "accounting"."orgs";
CREATE VIEW "accounting"."orgs" AS
SELECT
    o.org_key,
    parent.org_key AS parent_org_key,
    o.name,
    o.legal_form,
    o.created_at
FROM shared.organisations o
LEFT JOIN shared.organisations parent ON parent.organisation_id = o.parent_organisation_id
WHERE o.org_key IS NOT NULL;

ALTER TABLE ONLY "accounting"."accounts" ADD CONSTRAINT "accounts_org_key_fkey" FOREIGN KEY (org_key) REFERENCES shared.organisations(org_key) ON UPDATE CASCADE ON DELETE RESTRICT NOT DEFERRABLE;
ALTER TABLE ONLY "accounting"."accounts" ADD CONSTRAINT "accounts_parent_account_id_fkey" FOREIGN KEY (parent_account_id) REFERENCES accounts(account_id) ON UPDATE CASCADE ON DELETE RESTRICT NOT DEFERRABLE;

ALTER TABLE ONLY "accounting"."batches" ADD CONSTRAINT "batches_org_key_fkey" FOREIGN KEY (org_key) REFERENCES shared.organisations(org_key) ON UPDATE CASCADE ON DELETE RESTRICT NOT DEFERRABLE;
ALTER TABLE ONLY "accounting"."batches" ADD CONSTRAINT "batches_parent_batch_id_fkey" FOREIGN KEY (parent_batch_id) REFERENCES batches(batch_id) ON UPDATE CASCADE ON DELETE RESTRICT NOT DEFERRABLE;

ALTER TABLE ONLY "accounting"."entries" ADD CONSTRAINT "entries_batch_id_fkey" FOREIGN KEY (batch_id) REFERENCES batches(batch_id) ON UPDATE CASCADE ON DELETE RESTRICT NOT DEFERRABLE;
ALTER TABLE ONLY "accounting"."entries" ADD CONSTRAINT "entries_org_key_fkey" FOREIGN KEY (org_key) REFERENCES shared.organisations(org_key) ON UPDATE CASCADE ON DELETE RESTRICT NOT DEFERRABLE;

ALTER TABLE ONLY "accounting"."lines" ADD CONSTRAINT "lines_account_id_fkey" FOREIGN KEY (account_id) REFERENCES accounts(account_id) ON UPDATE CASCADE ON DELETE RESTRICT NOT DEFERRABLE;
ALTER TABLE ONLY "accounting"."lines" ADD CONSTRAINT "lines_entry_id_fkey" FOREIGN KEY (entry_id) REFERENCES entries(entry_id) ON UPDATE CASCADE ON DELETE CASCADE NOT DEFERRABLE;
ALTER TABLE ONLY "accounting"."lines" ADD CONSTRAINT "lines_org_key_fkey" FOREIGN KEY (org_key) REFERENCES shared.organisations(org_key) ON UPDATE CASCADE ON DELETE RESTRICT NOT DEFERRABLE;

-- 2025-09-28 10:03:54 UTC

