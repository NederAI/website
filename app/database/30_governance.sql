-- Governance, meeting and decision registers.
CREATE SCHEMA IF NOT EXISTS governance;

CREATE TYPE IF NOT EXISTS governance.meeting_kind AS ENUM (
    'board',
    'supervisory_board',
    'shareholder',
    'circle',
    'committee',
    'executive',
    'other'
);

CREATE TYPE IF NOT EXISTS governance.meeting_status AS ENUM (
    'scheduled',
    'draft_minutes',
    'approved',
    'archived',
    'cancelled'
);

CREATE TABLE IF NOT EXISTS governance.meetings (
    meeting_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organisation_id BIGINT NOT NULL REFERENCES shared.organisations(organisation_id) ON DELETE CASCADE,
    meeting_code TEXT,
    meeting_kind governance.meeting_kind NOT NULL,
    scheduled_at TIMESTAMPTZ NOT NULL,
    held_at TIMESTAMPTZ,
    location TEXT,
    status governance.meeting_status NOT NULL DEFAULT 'scheduled',
    agenda JSONB,
    minutes JSONB,
    documents JSONB,
    confidentiality_level TEXT NOT NULL DEFAULT 'internal',
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT meetings_agenda_json CHECK (agenda IS NULL OR JSONB_TYPEOF(agenda) IN ('object','array')),
    CONSTRAINT meetings_minutes_json CHECK (minutes IS NULL OR JSONB_TYPEOF(minutes) = 'object'),
    CONSTRAINT meetings_documents_json CHECK (documents IS NULL OR JSONB_TYPEOF(documents) = 'array'),
    CONSTRAINT meetings_meta_object CHECK (JSONB_TYPEOF(meta) = 'object')
);

CREATE TABLE IF NOT EXISTS governance.meeting_attendance (
    meeting_id BIGINT NOT NULL REFERENCES governance.meetings(meeting_id) ON DELETE CASCADE,
    participant_id BIGINT NOT NULL REFERENCES shared.organisations(organisation_id) ON DELETE RESTRICT,
    role_description TEXT,
    invitation_status TEXT NOT NULL DEFAULT 'invited',
    attended BOOLEAN,
    proxy_for BIGINT REFERENCES shared.organisations(organisation_id) ON DELETE SET NULL,
    notes JSONB NOT NULL DEFAULT '{}'::JSONB,
    PRIMARY KEY (meeting_id, participant_id),
    CONSTRAINT meeting_attendance_notes_object CHECK (JSONB_TYPEOF(notes) = 'object')
);

CREATE TYPE IF NOT EXISTS governance.resolution_type AS ENUM (
    'policy',
    'appointment',
    'delegation',
    'budget',
    'project',
    'other'
);

CREATE TYPE IF NOT EXISTS governance.resolution_status AS ENUM (
    'draft',
    'proposed',
    'adopted',
    'rejected',
    'superseded',
    'archived'
);

CREATE TABLE IF NOT EXISTS governance.resolutions (
    resolution_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organisation_id BIGINT NOT NULL REFERENCES shared.organisations(organisation_id) ON DELETE CASCADE,
    meeting_id BIGINT REFERENCES governance.meetings(meeting_id) ON DELETE SET NULL,
    resolution_code TEXT,
    title TEXT NOT NULL,
    resolution_type governance.resolution_type NOT NULL,
    decision_date DATE,
    status governance.resolution_status NOT NULL DEFAULT 'draft',
    body TEXT,
    publication_uri TEXT,
    publication_status TEXT NOT NULL DEFAULT 'internal',
    meta JSONB NOT NULL DEFAULT '{}'::JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT resolutions_meta_object CHECK (JSONB_TYPEOF(meta) = 'object')
);

CREATE INDEX IF NOT EXISTS governance_resolutions_org_idx ON governance.resolutions (organisation_id, decision_date);

DO LANGUAGE plpgsql E'
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''governance_meetings_touch'') THEN
        CREATE TRIGGER governance_meetings_touch
        BEFORE UPDATE ON governance.meetings
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = ''governance_resolutions_touch'') THEN
        CREATE TRIGGER governance_resolutions_touch
        BEFORE UPDATE ON governance.resolutions
        FOR EACH ROW EXECUTE FUNCTION shared.touch_updated_at();
    END IF;
END;
';
