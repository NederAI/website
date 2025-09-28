-- Shared organisation master data for NederAI group

CREATE SCHEMA IF NOT EXISTS shared;

DROP TABLE IF EXISTS shared.organisations CASCADE;
DROP SEQUENCE IF EXISTS shared.organisation_id_seq;
CREATE SEQUENCE shared.organisation_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;

CREATE TABLE shared.organisations (
    organisation_id bigint DEFAULT nextval('shared.organisation_id_seq') NOT NULL,
    org_key text,
    parent_organisation_id bigint,
    name text NOT NULL,
    legal_form text,
    organisation_kind text NOT NULL,
    country text DEFAULT 'NL' NOT NULL,
    kvk_number text,
    created_at timestamptz DEFAULT now() NOT NULL,
    updated_at timestamptz DEFAULT now() NOT NULL,
    CONSTRAINT organisations_pkey PRIMARY KEY (organisation_id),
    CONSTRAINT organisations_kind_check CHECK (organisation_kind = ANY (ARRAY['foundation'::text, 'company'::text, 'person'::text, 'circle'::text, 'other'::text])),
    CONSTRAINT organisations_org_key_key UNIQUE (org_key),
    CONSTRAINT organisations_parent_fkey FOREIGN KEY (parent_organisation_id) REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE INDEX organisations_parent_idx ON shared.organisations USING btree (parent_organisation_id);

INSERT INTO shared.organisations (organisation_id, org_key, parent_organisation_id, name, legal_form, organisation_kind, country, kvk_number, created_at, updated_at) VALUES
(1, 'stichting', NULL, 'Stichting NederAI', 'Stichting', 'foundation', 'NL', NULL, '2025-09-27 20:47:21.856067+00', '2025-09-27 20:47:21.856067+00'),
(2, 'alliance', 1, 'NederAI Alliance BV', 'BV', 'company', 'NL', NULL, '2025-09-27 20:48:28.030297+00', '2025-09-27 20:48:28.030297+00'),
(3, 'institute', 2, 'NederAI Institute BV', 'BV', 'company', 'NL', NULL, '2025-09-27 20:49:09.746872+00', '2025-09-27 20:49:09.746872+00'),
(4, 'commercial', 2, 'NederAI Commercial BV', 'BV', 'company', 'NL', NULL, '2025-09-27 20:49:49.905799+00', '2025-09-27 20:49:49.905799+00');

SELECT pg_catalog.setval('shared.organisation_id_seq', 4, true);
