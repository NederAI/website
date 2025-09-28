-- People and payroll registers with guarded personal data fields.
CREATE SCHEMA IF NOT EXISTS hr;

CREATE TYPE IF NOT EXISTS hr.employment_type AS ENUM (
    'employee',
    'contractor',
    'temporary',
    'board_member',
    'intern'
);

CREATE TYPE IF NOT EXISTS hr.employment_status AS ENUM (
    'active',
    'on_leave',
    'terminated',
    'suspended',
    'prospect'
);

CREATE TABLE IF NOT EXISTS hr.employees (
    employee_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organisation_id BIGINT NOT NULL REFERENCES shared.organisations(organisation_id) ON DELETE CASCADE,
    person_id BIGINT NOT NULL REFERENCES shared.organisations(organisation_id) ON DELETE RESTRICT,
    employment_type hr.employment_type NOT NULL,
    status hr.employment_status NOT NULL DEFAULT 'active',
    employment_start_date DATE NOT NULL,
    employment_end_date DATE,
    fte NUMERIC(5,4) CHECK (fte IS NULL OR fte >= 0),
    role_title TEXT,
    privacy_purpose TEXT NOT NULL DEFAULT 'hr_management',
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT hr_employees_meta_object CHECK (JSONB_TYPEOF(meta) = 'object')
);

CREATE TABLE IF NOT EXISTS hr.contracts (
    contract_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    employee_id BIGINT NOT NULL REFERENCES hr.employees(employee_id) ON DELETE CASCADE,
    contract_type TEXT NOT NULL,
    signed_on DATE,
    start_on DATE NOT NULL,
    end_on DATE,
    salary_amount NUMERIC(18,2),
    salary_currency CHAR(3) NOT NULL DEFAULT 'EUR',
    salary_period TEXT,
    benefits JSONB,
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT hr_contracts_benefits_json CHECK (benefits IS NULL OR JSONB_TYPEOF(benefits) = 'object'),
    CONSTRAINT hr_contracts_meta_object CHECK (JSONB_TYPEOF(meta) = 'object')
);

CREATE TABLE IF NOT EXISTS hr.payroll_runs (
    payroll_run_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organisation_id BIGINT NOT NULL REFERENCES shared.organisations(organisation_id) ON DELETE CASCADE,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    processed_at TIMESTAMPTZ,
    status TEXT NOT NULL DEFAULT 'draft',
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT hr_payroll_runs_meta_object CHECK (JSONB_TYPEOF(meta) = 'object')
);

CREATE TABLE IF NOT EXISTS hr.payroll_lines (
    payroll_line_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    payroll_run_id BIGINT NOT NULL REFERENCES hr.payroll_runs(payroll_run_id) ON DELETE CASCADE,
    employee_id BIGINT NOT NULL REFERENCES hr.employees(employee_id) ON DELETE CASCADE,
    gross_amount NUMERIC(18,2) NOT NULL CHECK (gross_amount >= 0),
    net_amount NUMERIC(18,2),
    tax_withheld NUMERIC(18,2),
    social_security NUMERIC(18,2),
    adjustments JSONB,
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT hr_payroll_lines_adjustments_json CHECK (adjustments IS NULL OR JSONB_TYPEOF(adjustments) = 'object'),
    CONSTRAINT hr_payroll_lines_meta_object CHECK (JSONB_TYPEOF(meta) = 'object')
);

CREATE INDEX IF NOT EXISTS hr_payroll_lines_employee_idx ON hr.payroll_lines (employee_id);

DO LANGUAGE plpgsql E'
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''hr_employees_touch'') THEN
        CREATE TRIGGER hr_employees_touch
        BEFORE UPDATE ON hr.employees
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''hr_contracts_touch'') THEN
        CREATE TRIGGER hr_contracts_touch
        BEFORE UPDATE ON hr.contracts
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''hr_payroll_runs_touch'') THEN
        CREATE TRIGGER hr_payroll_runs_touch
        BEFORE UPDATE ON hr.payroll_runs
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''hr_payroll_lines_touch'') THEN
        CREATE TRIGGER hr_payroll_lines_touch
        BEFORE UPDATE ON hr.payroll_lines
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
END;
';
