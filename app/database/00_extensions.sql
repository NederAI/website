BEGIN;

-- Core extensions needed by the NederAI platform. Keep this script first.
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS citext;

COMMIT;
