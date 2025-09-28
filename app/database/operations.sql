-- Operational data schemas for governance, registry, compliance, HR, and document management.
-- Assumes shared.organisations exists and contains both legal entities and natural persons
-- (persons should use organisation_kind = 'person').

-- =====================
-- Corporate Governance
-- =====================
CREATE SCHEMA IF NOT EXISTS governance;

DROP TABLE IF EXISTS governance.meeting_participants;
DROP TABLE IF EXISTS governance.resolutions;
DROP TABLE IF EXISTS governance.governance_documents;
DROP TABLE IF EXISTS governance.meetings;
DROP SEQUENCE IF EXISTS governance_meeting_id_seq;
DROP SEQUENCE IF EXISTS governance_resolution_id_seq;
DROP SEQUENCE IF EXISTS governance_document_id_seq;

CREATE SEQUENCE governance_meeting_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;
CREATE SEQUENCE governance_resolution_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;
CREATE SEQUENCE governance_document_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;

CREATE TABLE governance.meetings (
    meeting_id bigint DEFAULT nextval('governance_meeting_id_seq') PRIMARY KEY,
    organisation_id bigint NOT NULL REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    meeting_code text,
    meeting_kind text NOT NULL,
    scheduled_at timestamptz NOT NULL,
    held_at timestamptz,
    location text,
    status text NOT NULL DEFAULT 'scheduled',
    agenda jsonb,
    minutes jsonb,
    meta jsonb DEFAULT '{}'::jsonb,
    CONSTRAINT meetings_kind_check CHECK (meeting_kind = ANY (ARRAY['board'::text, 'shareholder'::text, 'committee'::text, 'executive'::text, 'other'::text])),
    CONSTRAINT meetings_status_check CHECK (status = ANY (ARRAY['scheduled'::text, 'draft'::text, 'approved'::text, 'archived'::text, 'cancelled'::text])),
    CONSTRAINT meetings_meta_check CHECK (meta IS NULL OR jsonb_typeof(meta) = 'object'),
    CONSTRAINT meetings_agenda_check CHECK (agenda IS NULL OR jsonb_typeof(agenda) = 'array' OR jsonb_typeof(agenda) = 'object'),
    CONSTRAINT meetings_minutes_check CHECK (minutes IS NULL OR jsonb_typeof(minutes) = 'object')
);

CREATE INDEX governance_meetings_org_idx ON governance.meetings (organisation_id, scheduled_at);

CREATE TABLE governance.meeting_participants (
    meeting_id bigint NOT NULL REFERENCES governance.meetings(meeting_id) ON DELETE CASCADE,
    participant_id bigint NOT NULL REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    role text,
    attended boolean,
    meta jsonb DEFAULT '{}'::jsonb,
    CONSTRAINT meeting_participants_meta_check CHECK (meta IS NULL OR jsonb_typeof(meta) = 'object'),
    PRIMARY KEY (meeting_id, participant_id)
);

CREATE TABLE governance.resolutions (
    resolution_id bigint DEFAULT nextval('governance_resolution_id_seq') PRIMARY KEY,
    organisation_id bigint NOT NULL REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    meeting_id bigint REFERENCES governance.meetings(meeting_id) ON DELETE SET NULL,
    title text NOT NULL,
    resolution_type text NOT NULL,
    decision_date date,
    reference_code text,
    status text NOT NULL DEFAULT 'draft',
    body text,
    meta jsonb DEFAULT '{}'::jsonb,
    CONSTRAINT resolutions_type_check CHECK (resolution_type = ANY (ARRAY['board_resolution'::text, 'shareholder_resolution'::text, 'policy'::text, 'appointment'::text, 'delegation'::text, 'other'::text])),
    CONSTRAINT resolutions_status_check CHECK (status = ANY (ARRAY['draft'::text, 'proposed'::text, 'adopted'::text, 'rejected'::text, 'superseded'::text, 'archived'::text])),
    CONSTRAINT resolutions_meta_check CHECK (meta IS NULL OR jsonb_typeof(meta) = 'object')
);

CREATE INDEX governance_resolutions_org_idx ON governance.resolutions (organisation_id, decision_date);
CREATE INDEX governance_resolutions_meeting_idx ON governance.resolutions (meeting_id);

CREATE TABLE governance.governance_documents (
    document_id bigint DEFAULT nextval('governance_document_id_seq') PRIMARY KEY,
    organisation_id bigint NOT NULL REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    doc_kind text NOT NULL,
    title text NOT NULL,
    version_label text,
    effective_at date,
    supersedes_document_id bigint REFERENCES governance.governance_documents(document_id) ON DELETE SET NULL,
    storage_uri text,
    meta jsonb DEFAULT '{}'::jsonb,
    CONSTRAINT governance_documents_kind_check CHECK (doc_kind = ANY (ARRAY['statute'::text, 'bylaw'::text, 'policy'::text, 'regulation'::text, 'charter'::text, 'manual'::text, 'other'::text])),
    CONSTRAINT governance_documents_meta_check CHECK (meta IS NULL OR jsonb_typeof(meta) = 'object')
);

CREATE INDEX governance_documents_org_idx ON governance.governance_documents (organisation_id, doc_kind, effective_at);

-- =====================
-- Entity Registry
-- =====================
CREATE SCHEMA IF NOT EXISTS registry;

DROP TABLE IF EXISTS registry.entity_licenses;
DROP TABLE IF EXISTS registry.entity_roles;
DROP TABLE IF EXISTS registry.entity_profile;
DROP SEQUENCE IF EXISTS registry_entity_role_id_seq;
DROP SEQUENCE IF EXISTS registry_entity_license_id_seq;

CREATE SEQUENCE registry_entity_role_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;
CREATE SEQUENCE registry_entity_license_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;

CREATE TABLE registry.entity_profile (
    organisation_id bigint PRIMARY KEY REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE CASCADE,
    registration_authority text DEFAULT 'KVK',
    registration_number text,
    vat_number text,
    legal_address jsonb,
    operating_address jsonb,
    inception_date date,
    dissolution_date date,
    industry_codes jsonb,
    licenses_summary jsonb,
    meta jsonb DEFAULT '{}'::jsonb,
    CONSTRAINT entity_profile_legal_addr_check CHECK (legal_address IS NULL OR jsonb_typeof(legal_address) = 'object'),
    CONSTRAINT entity_profile_operating_addr_check CHECK (operating_address IS NULL OR jsonb_typeof(operating_address) = 'object'),
    CONSTRAINT entity_profile_industry_check CHECK (industry_codes IS NULL OR jsonb_typeof(industry_codes) = 'array'),
    CONSTRAINT entity_profile_meta_check CHECK (meta IS NULL OR jsonb_typeof(meta) = 'object')
);

CREATE TABLE registry.entity_roles (
    entity_role_id bigint DEFAULT nextval('registry_entity_role_id_seq') PRIMARY KEY,
    organisation_id bigint NOT NULL REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE CASCADE,
    subject_id bigint NOT NULL REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    role_type text NOT NULL,
    role_status text NOT NULL DEFAULT 'active',
    start_date date,
    end_date date,
    appointment_reference text,
    meta jsonb DEFAULT '{}'::jsonb,
    CONSTRAINT entity_roles_type_check CHECK (role_type = ANY (ARRAY['director'::text, 'executive'::text, 'supervisor'::text, 'representative'::text, 'beneficial_owner'::text, 'secretary'::text, 'other'::text])),
    CONSTRAINT entity_roles_status_check CHECK (role_status = ANY (ARRAY['active'::text, 'pending'::text, 'resigned'::text, 'suspended'::text, 'terminated'::text])),
    CONSTRAINT entity_roles_meta_check CHECK (meta IS NULL OR jsonb_typeof(meta) = 'object')
);

CREATE INDEX registry_entity_roles_org_idx ON registry.entity_roles (organisation_id, role_type, role_status);
CREATE INDEX registry_entity_roles_subject_idx ON registry.entity_roles (subject_id);

CREATE TABLE registry.entity_licenses (
    entity_license_id bigint DEFAULT nextval('registry_entity_license_id_seq') PRIMARY KEY,
    organisation_id bigint NOT NULL REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE CASCADE,
    license_type text NOT NULL,
    issuing_body text,
    license_reference text,
    issued_at date,
    expires_at date,
    status text NOT NULL DEFAULT 'active',
    meta jsonb DEFAULT '{}'::jsonb,
    CONSTRAINT entity_licenses_status_check CHECK (status = ANY (ARRAY['active'::text, 'expired'::text, 'revoked'::text, 'suspended'::text, 'draft'::text])),
    CONSTRAINT entity_licenses_meta_check CHECK (meta IS NULL OR jsonb_typeof(meta) = 'object')
);

CREATE INDEX registry_entity_licenses_org_idx ON registry.entity_licenses (organisation_id, status, expires_at);

-- =====================
-- Compliance Tracking
-- =====================
CREATE SCHEMA IF NOT EXISTS compliance;

DROP TABLE IF EXISTS compliance.fulfilments;
DROP TABLE IF EXISTS compliance.obligations;
DROP SEQUENCE IF EXISTS compliance_obligation_id_seq;
DROP SEQUENCE IF EXISTS compliance_fulfilment_id_seq;

CREATE SEQUENCE compliance_obligation_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;
CREATE SEQUENCE compliance_fulfilment_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;

CREATE TABLE compliance.obligations (
    obligation_id bigint DEFAULT nextval('compliance_obligation_id_seq') PRIMARY KEY,
    organisation_id bigint NOT NULL REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE CASCADE,
    title text NOT NULL,
    obligation_kind text NOT NULL,
    authority text,
    jurisdiction text DEFAULT 'NL',
    frequency text,
    first_due_on date,
    is_active boolean DEFAULT true NOT NULL,
    meta jsonb DEFAULT '{}'::jsonb,
    CONSTRAINT compliance_obligations_kind_check CHECK (obligation_kind = ANY (ARRAY['tax_filing'::text, 'annual_report'::text, 'audit'::text, 'license_renewal'::text, 'statutory_report'::text, 'other'::text])),
    CONSTRAINT compliance_obligations_meta_check CHECK (meta IS NULL OR jsonb_typeof(meta) = 'object')
);

CREATE INDEX compliance_obligations_org_idx ON compliance.obligations (organisation_id, obligation_kind);

CREATE TABLE compliance.fulfilments (
    fulfilment_id bigint DEFAULT nextval('compliance_fulfilment_id_seq') PRIMARY KEY,
    obligation_id bigint NOT NULL REFERENCES compliance.obligations(obligation_id) ON DELETE CASCADE,
    period_start date,
    period_end date,
    due_on date NOT NULL,
    submitted_on date,
    status text NOT NULL DEFAULT 'pending',
    reference_code text,
    notes jsonb,
    meta jsonb DEFAULT '{}'::jsonb,
    CONSTRAINT compliance_fulfilments_status_check CHECK (status = ANY (ARRAY['pending'::text, 'submitted'::text, 'accepted'::text, 'rejected'::text, 'waived'::text, 'cancelled'::text])),
    CONSTRAINT compliance_fulfilments_notes_check CHECK (notes IS NULL OR jsonb_typeof(notes) = 'object'),
    CONSTRAINT compliance_fulfilments_meta_check CHECK (meta IS NULL OR jsonb_typeof(meta) = 'object')
);

CREATE INDEX compliance_fulfilments_obligation_idx ON compliance.fulfilments (obligation_id, due_on);
CREATE INDEX compliance_fulfilments_status_idx ON compliance.fulfilments (status);

-- =====================
-- Human Resources & Payroll
-- =====================
CREATE SCHEMA IF NOT EXISTS hr;

DROP TABLE IF EXISTS hr.payroll_lines;
DROP TABLE IF EXISTS hr.payroll_runs;
DROP TABLE IF EXISTS hr.contracts;
DROP TABLE IF EXISTS hr.employees;
DROP SEQUENCE IF EXISTS hr_employee_id_seq;
DROP SEQUENCE IF EXISTS hr_contract_id_seq;
DROP SEQUENCE IF EXISTS hr_payroll_run_id_seq;
DROP SEQUENCE IF EXISTS hr_payroll_line_id_seq;

CREATE SEQUENCE hr_employee_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;
CREATE SEQUENCE hr_contract_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;
CREATE SEQUENCE hr_payroll_run_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;
CREATE SEQUENCE hr_payroll_line_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;

CREATE TABLE hr.employees (
    employee_id bigint DEFAULT nextval('hr_employee_id_seq') PRIMARY KEY,
    organisation_id bigint NOT NULL REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE CASCADE,
    person_id bigint NOT NULL REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    employee_number text,
    employment_type text NOT NULL,
    status text NOT NULL DEFAULT 'active',
    employment_start_date date NOT NULL,
    employment_end_date date,
    fte numeric(5,4),
    meta jsonb DEFAULT '{}'::jsonb,
    CONSTRAINT hr_employees_type_check CHECK (employment_type = ANY (ARRAY['employee'::text, 'contractor'::text, 'temporary'::text, 'board_member'::text, 'executive'::text, 'other'::text])),
    CONSTRAINT hr_employees_status_check CHECK (status = ANY (ARRAY['active'::text, 'on_leave'::text, 'terminated'::text, 'suspended'::text, 'prospect'::text])),
    CONSTRAINT hr_employees_meta_check CHECK (meta IS NULL OR jsonb_typeof(meta) = 'object'),
    CONSTRAINT hr_employees_fte_check CHECK (fte IS NULL OR fte >= 0)
);

CREATE UNIQUE INDEX hr_employees_org_number_idx ON hr.employees (organisation_id, employee_number) WHERE employee_number IS NOT NULL;
CREATE INDEX hr_employees_person_idx ON hr.employees (person_id);

CREATE TABLE hr.contracts (
    contract_id bigint DEFAULT nextval('hr_contract_id_seq') PRIMARY KEY,
    employee_id bigint NOT NULL REFERENCES hr.employees(employee_id) ON DELETE CASCADE,
    contract_type text NOT NULL,
    signed_at date,
    start_date date NOT NULL,
    end_date date,
    salary_amount numeric(18,2),
    salary_currency text DEFAULT 'EUR',
    salary_period text,
    benefits jsonb,
    terms_uri text,
    meta jsonb DEFAULT '{}'::jsonb,
    CONSTRAINT hr_contracts_type_check CHECK (contract_type = ANY (ARRAY['indefinite'::text, 'fixed_term'::text, 'contractor'::text, 'internship'::text, 'board'::text, 'other'::text])),
    CONSTRAINT hr_contracts_benefits_check CHECK (benefits IS NULL OR jsonb_typeof(benefits) = 'object'),
    CONSTRAINT hr_contracts_meta_check CHECK (meta IS NULL OR jsonb_typeof(meta) = 'object')
);

CREATE INDEX hr_contracts_employee_idx ON hr.contracts (employee_id, start_date);

CREATE TABLE hr.payroll_runs (
    payroll_run_id bigint DEFAULT nextval('hr_payroll_run_id_seq') PRIMARY KEY,
    organisation_id bigint NOT NULL REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE CASCADE,
    period_start date NOT NULL,
    period_end date NOT NULL,
    processed_at timestamptz,
    status text NOT NULL DEFAULT 'draft',
    meta jsonb DEFAULT '{}'::jsonb,
    CONSTRAINT hr_payroll_runs_status_check CHECK (status = ANY (ARRAY['draft'::text, 'processing'::text, 'completed'::text, 'posted'::text, 'voided'::text])),
    CONSTRAINT hr_payroll_runs_meta_check CHECK (meta IS NULL OR jsonb_typeof(meta) = 'object')
);

CREATE INDEX hr_payroll_runs_org_idx ON hr.payroll_runs (organisation_id, period_start, period_end);

CREATE TABLE hr.payroll_lines (
    payroll_line_id bigint DEFAULT nextval('hr_payroll_line_id_seq') PRIMARY KEY,
    payroll_run_id bigint NOT NULL REFERENCES hr.payroll_runs(payroll_run_id) ON DELETE CASCADE,
    employee_id bigint NOT NULL REFERENCES hr.employees(employee_id) ON DELETE CASCADE,
    gross_amount numeric(18,2) NOT NULL,
    net_amount numeric(18,2),
    tax_withheld numeric(18,2),
    social_security numeric(18,2),
    other_amounts jsonb,
    meta jsonb DEFAULT '{}'::jsonb,
    CONSTRAINT hr_payroll_lines_other_check CHECK (other_amounts IS NULL OR jsonb_typeof(other_amounts) = 'object'),
    CONSTRAINT hr_payroll_lines_meta_check CHECK (meta IS NULL OR jsonb_typeof(meta) = 'object')
);

CREATE INDEX hr_payroll_lines_run_idx ON hr.payroll_lines (payroll_run_id);
CREATE INDEX hr_payroll_lines_employee_idx ON hr.payroll_lines (employee_id);

-- =====================
-- Document Management
-- =====================
CREATE SCHEMA IF NOT EXISTS documents;

DROP TABLE IF EXISTS documents.references;
DROP TABLE IF EXISTS documents.files;
DROP SEQUENCE IF EXISTS documents_file_id_seq;
DROP SEQUENCE IF EXISTS documents_reference_id_seq;

CREATE SEQUENCE documents_file_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;
CREATE SEQUENCE documents_reference_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;

CREATE TABLE documents.files (
    document_id bigint DEFAULT nextval('documents_file_id_seq') PRIMARY KEY,
    organisation_id bigint REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE SET NULL,
    uploaded_by bigint REFERENCES shared.organisations(organisation_id) ON UPDATE CASCADE ON DELETE SET NULL,
    source_module text,
    title text NOT NULL,
    file_uri text NOT NULL,
    version_label text,
    classification text DEFAULT 'internal',
    checksum text,
    uploaded_at timestamptz NOT NULL DEFAULT now(),
    meta jsonb DEFAULT '{}'::jsonb,
    CONSTRAINT documents_files_classification_check CHECK (classification = ANY (ARRAY['public'::text, 'internal'::text, 'confidential'::text, 'restricted'::text])),
    CONSTRAINT documents_files_meta_check CHECK (meta IS NULL OR jsonb_typeof(meta) = 'object')
);

CREATE INDEX documents_files_org_idx ON documents.files (organisation_id, classification);
CREATE INDEX documents_files_module_idx ON documents.files (source_module);

CREATE TABLE documents.references (
    document_reference_id bigint DEFAULT nextval('documents_reference_id_seq') PRIMARY KEY,
    document_id bigint NOT NULL REFERENCES documents.files(document_id) ON DELETE CASCADE,
    reference_type text NOT NULL,
    target_schema text,
    target_table text,
    target_identifier text,
    reference_meta jsonb DEFAULT '{}'::jsonb,
    CONSTRAINT documents_references_meta_check CHECK (reference_meta IS NULL OR jsonb_typeof(reference_meta) = 'object')
);

CREATE INDEX documents_references_doc_idx ON documents.references (document_id);
CREATE INDEX documents_references_target_idx ON documents.references (target_schema, target_table, target_identifier);
