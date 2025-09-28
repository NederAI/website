-- Share register schema for NederAI group.
-- Requires shared.organisations to be present.

DROP VIEW IF EXISTS "shareholder_position";
DROP TABLE IF EXISTS "shareholder_position";

DROP TABLE IF EXISTS "share_event";
DROP SEQUENCE IF EXISTS share_event_share_event_id_seq;
CREATE SEQUENCE share_event_share_event_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;

DROP TABLE IF EXISTS "share_class";
DROP SEQUENCE IF EXISTS share_class_share_class_id_seq;
CREATE SEQUENCE share_class_share_class_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;

DROP TABLE IF EXISTS "company";
DROP SEQUENCE IF EXISTS company_company_id_seq;
CREATE SEQUENCE company_company_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;

CREATE TABLE "shares"."company" (
    "company_id" bigint DEFAULT nextval('company_company_id_seq') NOT NULL,
    "organisation_id" bigint NOT NULL,
    "legal_form" text NOT NULL,
    CONSTRAINT "company_pkey" PRIMARY KEY ("company_id"),
    CONSTRAINT "company_legal_form_check" CHECK (legal_form = ANY (ARRAY['BV'::text, 'Stichting'::text, 'NV'::text, 'CV'::text, 'VOF'::text, 'Other'::text]))
)
WITH (oids = false);

CREATE UNIQUE INDEX company_organisation_id_key ON shares.company USING btree (organisation_id);

INSERT INTO "company" ("company_id", "organisation_id", "legal_form") VALUES
(1,	1,	'Stichting'),
(2,	2,	'BV'),
(3,	3,	'BV'),
(4,	4,	'BV');

CREATE TABLE "shares"."share_class" (
    "share_class_id" bigint DEFAULT nextval('share_class_share_class_id_seq') NOT NULL,
    "company_id" bigint NOT NULL,
    "code" text NOT NULL,
    "description" text,
    "nominal_value" numeric(18,6) NOT NULL,
    "currency" text DEFAULT 'EUR' NOT NULL,
    "voting_rights" boolean DEFAULT true NOT NULL,
    "profit_rights" boolean DEFAULT true NOT NULL,
    CONSTRAINT "share_class_pkey" PRIMARY KEY ("share_class_id"),
    CONSTRAINT "share_class_nominal_value_check" CHECK (nominal_value > (0)::numeric)
)
WITH (oids = false);

CREATE UNIQUE INDEX share_class_company_id_code_key ON shares.share_class USING btree (company_id, code);

INSERT INTO "share_class" ("share_class_id", "company_id", "code", "description", "nominal_value", "currency", "voting_rights", "profit_rights") VALUES
(1,	2,	'A',	'Gewone aandelen Alliance',	1.000000,	'EUR',	't',	't'),
(2,	3,	'A',	'Gewone aandelen Institute',	1.000000,	'EUR',	't',	't'),
(3,	4,	'A',	'Gewone aandelen Commercial',	1.000000,	'EUR',	't',	't');

CREATE TABLE "shares"."share_event" (
    "share_event_id" bigint DEFAULT nextval('share_event_share_event_id_seq') NOT NULL,
    "company_id" bigint NOT NULL,
    "share_class_id" bigint NOT NULL,
    "event_date" date NOT NULL,
    "event_type" share_event_type NOT NULL,
    "from_party_id" bigint,
    "to_party_id" bigint,
    "quantity" numeric(24,6) NOT NULL,
    "paid_up_amount" numeric(18,2),
    "cert_from" text,
    "cert_to" text,
    "minutes_ref" text,
    "notes" text,
    "extra" jsonb,
    CONSTRAINT "share_event_pkey" PRIMARY KEY ("share_event_id"),
    CONSTRAINT "share_event_quantity_check" CHECK (quantity > (0)::numeric)
)
WITH (oids = false);

CREATE INDEX ix_share_event_company ON shares.share_event USING btree (company_id, event_date);

CREATE INDEX ix_share_event_holders ON shares.share_event USING btree (from_party_id, to_party_id);

INSERT INTO "share_event" ("share_event_id", "company_id", "share_class_id", "event_date", "event_type", "from_party_id", "to_party_id", "quantity", "paid_up_amount", "cert_from", "cert_to", "minutes_ref", "notes", "extra") VALUES
(1,	2,	1,	'2025-01-01',	'ISSUE',	NULL,	1,	1000.000000,	1000.00,	NULL,	NULL,	'Akte oprichting Alliance',	NULL,	NULL),
(2,	3,	2,	'2025-01-02',	'ISSUE',	NULL,	2,	1000.000000,	1000.00,	NULL,	NULL,	'Akte oprichting Institute',	NULL,	NULL),
(3,	4,	3,	'2025-01-02',	'ISSUE',	NULL,	2,	1000.000000,	1000.00,	NULL,	NULL,	'Akte oprichting Commercial',	NULL,	NULL);

CREATE TABLE "shareholder_position" ("company_id" bigint, "share_class_id" bigint, "party_id" bigint, "quantity" numeric);


ALTER TABLE ONLY "shares"."company" ADD CONSTRAINT "company_organisation_id_fkey" FOREIGN KEY (organisation_id) REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE RESTRICT NOT DEFERRABLE;

ALTER TABLE ONLY "shares"."share_class" ADD CONSTRAINT "share_class_company_id_fkey" FOREIGN KEY (company_id) REFERENCES company(company_id) ON DELETE CASCADE NOT DEFERRABLE;

ALTER TABLE ONLY "shares"."share_event" ADD CONSTRAINT "share_event_company_id_fkey" FOREIGN KEY (company_id) REFERENCES company(company_id) ON DELETE CASCADE NOT DEFERRABLE;
ALTER TABLE ONLY "shares"."share_event" ADD CONSTRAINT "share_event_from_party_id_fkey" FOREIGN KEY (from_party_id) REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE RESTRICT NOT DEFERRABLE;
ALTER TABLE ONLY "shares"."share_event" ADD CONSTRAINT "share_event_share_class_id_fkey" FOREIGN KEY (share_class_id) REFERENCES share_class(share_class_id) ON DELETE RESTRICT NOT DEFERRABLE;
ALTER TABLE ONLY "shares"."share_event" ADD CONSTRAINT "share_event_to_party_id_fkey" FOREIGN KEY (to_party_id) REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE RESTRICT NOT DEFERRABLE;

DROP TABLE IF EXISTS "shareholder_position";
CREATE VIEW "shareholder_position" AS WITH legs AS (
         SELECT e.company_id,
            e.share_class_id,
            e.to_party_id AS party_id,
            (
                CASE
                    WHEN (e.event_type = ANY (ARRAY['ISSUE'::share_event_type, 'TRANSFER'::share_event_type, 'SPLIT'::share_event_type, 'CONSOLIDATE'::share_event_type, 'PLEDGE_RELEASE'::share_event_type, 'USUFRUCT_RELEASE'::share_event_type])) THEN e.quantity
                    ELSE (0)::numeric
                END - (
                CASE
                    WHEN (e.event_type = 'CANCEL'::share_event_type) THEN 0
                    ELSE 0
                END)::numeric) AS qty_plus,
            (0)::numeric AS qty_minus
           FROM share_event e
          WHERE (e.to_party_id IS NOT NULL)
        UNION ALL
         SELECT e.company_id,
            e.share_class_id,
            e.from_party_id AS party_id,
            (0)::numeric AS qty_plus,
                CASE
                    WHEN (e.event_type = ANY (ARRAY['TRANSFER'::share_event_type, 'CANCEL'::share_event_type])) THEN e.quantity
                    ELSE (0)::numeric
                END AS qty_minus
           FROM share_event e
          WHERE (e.from_party_id IS NOT NULL)
        )
 SELECT company_id,
    share_class_id,
    party_id,
    COALESCE(sum((qty_plus - qty_minus)), (0)::numeric) AS quantity
   FROM legs l
  GROUP BY company_id, share_class_id, party_id
 HAVING (COALESCE(sum((qty_plus - qty_minus)), (0)::numeric) <> (0)::numeric)
  ORDER BY company_id, share_class_id, party_id;
