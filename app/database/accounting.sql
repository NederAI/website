CREATE EXTENSION IF NOT EXISTS ltree;

CREATE TABLE accounting.organizations (
  id bigserial PRIMARY KEY,
  code varchar(32) UNIQUE NOT NULL,
  name text NOT NULL,
  parent_id bigint REFERENCES accounting.organizations(id) ON DELETE SET NULL,
  path ltree,
  currency char(3) NOT NULL DEFAULT 'EUR',
  metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE accounting.entries (
  id bigserial PRIMARY KEY,
  org_id bigint NOT NULL REFERENCES accounting.organizations(id) ON DELETE RESTRICT,
  entry_date date NOT NULL,
  status varchar(16) NOT NULL DEFAULT 'draft'
    CHECK (status IN ('draft','posted','void')),
  reference varchar(64),
  description text,
  currency char(3) NOT NULL DEFAULT 'EUR',
  exchange_rate numeric(20,8) NOT NULL DEFAULT 1.0 CHECK (exchange_rate > 0),
  intercompany_id uuid, -- optioneel: koppel voor intercompany
  metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
  posted_at timestamptz,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE accounting.accounts (
  id bigserial PRIMARY KEY,
  org_id bigint NOT NULL REFERENCES accounting.organizations(id) ON DELETE RESTRICT,
  code varchar(64) NOT NULL,
  name text NOT NULL,
  type varchar(16) NOT NULL CHECK (type IN ('asset','liability','equity','revenue','expense','memo')),
  rgs_code varchar(32),
  currency char(3) NOT NULL DEFAULT 'EUR',
  metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
  archived_at timestamptz,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (org_id, code)
);


####


-- === A) Fixes t.o.v. vorige script =========================================

-- 1) CHECK met subselect is ongeldig -> vervang door trigger
ALTER TABLE accounting.lines DROP CONSTRAINT IF EXISTS lines_org_matches_entry;

CREATE OR REPLACE FUNCTION accounting._line_org_matches_entry()
RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  IF (SELECT org_id FROM accounting.entries WHERE id = NEW.entry_id) <> NEW.org_id THEN
    RAISE EXCEPTION 'Line.org_id (%) verschilt van Entry.org_id (%)', NEW.org_id,
      (SELECT org_id FROM accounting.entries WHERE id = NEW.entry_id);
  END IF;
  RETURN NEW;
END $$;

DROP TRIGGER IF EXISTS lines_entry_org_guard ON accounting.lines;
CREATE TRIGGER lines_entry_org_guard
BEFORE INSERT OR UPDATE ON accounting.lines
FOR EACH ROW EXECUTE FUNCTION accounting._line_org_matches_entry();

-- 2) Balanscontrole uitstellen tot commit (handig bij batch inserts)
DROP TRIGGER IF EXISTS lines_balancing_guard ON accounting.lines;
CREATE CONSTRAINT TRIGGER lines_balancing_guard
AFTER INSERT OR UPDATE OR DELETE ON accounting.lines
DEFERRABLE INITIALLY DEFERRED
FOR EACH ROW EXECUTE FUNCTION accounting.ensure_balanced_batches();

-- === B) Organisaties: 3 lagen ==============================================

-- Codes en paden:
-- nederai (Stichting) -> nederai.alliance (HoldCo) -> nederai.alliance.institute / .commercial
WITH s AS (
  INSERT INTO accounting.organizations(code,name,parent_id,path,currency)
  VALUES ('STICHTING','Stichting NederAI',NULL, text2ltree('nederai'),'EUR')
  ON CONFLICT (code) DO NOTHING
  RETURNING id
), s_id AS (
  SELECT id FROM s
  UNION ALL
  SELECT id FROM accounting.organizations WHERE code='STICHTING'
), a AS (
  INSERT INTO accounting.organizations(code,name,parent_id,path,currency)
  SELECT 'ALLIANCE','NederAI Alliance B.V.', id, text2ltree('nederai.alliance'),'EUR' FROM s_id
  ON CONFLICT (code) DO NOTHING
  RETURNING id
), a_id AS (
  SELECT id FROM a
  UNION ALL
  SELECT id FROM accounting.organizations WHERE code='ALLIANCE'
), i AS (
  INSERT INTO accounting.organizations(code,name,parent_id,path,currency)
  SELECT 'INSTITUTE','NederAI Institute B.V.', id, text2ltree('nederai.alliance.institute'),'EUR' FROM a_id
  ON CONFLICT (code) DO NOTHING
  RETURNING id
), c AS (
  INSERT INTO accounting.organizations(code,name,parent_id,path,currency)
  SELECT 'COMMERCIAL','NederAI Commercial B.V.', id, text2ltree('nederai.alliance.commercial'),'EUR' FROM a_id
  ON CONFLICT (code) DO NOTHING
  RETURNING id
)
SELECT 1;

-- === C) Minimum rekeningschema per org (bank, intercompany, mgmt fee) ======

WITH orgs AS (
  SELECT id, code FROM accounting.organizations
  WHERE code IN ('ALLIANCE','INSTITUTE','COMMERCIAL')
),
ins AS (
  INSERT INTO accounting.accounts(org_id,code,name,type,currency)
  SELECT o.id,'1000','Bank',               'asset','EUR' FROM orgs o
  UNION ALL
  SELECT o.id,'1600','Due from related',   'asset','EUR' FROM orgs o
  UNION ALL
  SELECT o.id,'2600','Due to related',     'liability','EUR' FROM orgs o
  UNION ALL
  SELECT (SELECT id FROM orgs WHERE code='ALLIANCE'),'7000','Management fee revenue','revenue','EUR'
  UNION ALL
  SELECT (SELECT id FROM orgs WHERE code='INSTITUTE'),'4400','Management fee expense','expense','EUR'
  UNION ALL
  SELECT (SELECT id FROM orgs WHERE code='COMMERCIAL'),'4400','Management fee expense','expense','EUR'
  ON CONFLICT (org_id,code) DO NOTHING
  RETURNING 1
)
SELECT 1;

-- Handige views voor ids
CREATE OR REPLACE VIEW tmp_org_ids AS
SELECT code, id AS org_id FROM accounting.organizations
WHERE code IN ('STICHTING','ALLIANCE','INSTITUTE','COMMERCIAL');

CREATE OR REPLACE VIEW tmp_acc AS
SELECT a.org_id, a.code, a.id AS account_id
FROM accounting.accounts a
WHERE (a.org_id, a.code) IN (
  SELECT org_id, '1000' FROM tmp_org_ids WHERE code IN ('ALLIANCE','INSTITUTE','COMMERCIAL')
  UNION ALL SELECT org_id, '1600' FROM tmp_org_ids WHERE code IN ('ALLIANCE','INSTITUTE','COMMERCIAL')
  UNION ALL SELECT org_id, '2600' FROM tmp_org_ids WHERE code IN ('ALLIANCE','INSTITUTE','COMMERCIAL')
)
OR (a.org_id, a.code) IN (
  SELECT org_id, '7000' FROM tmp_org_ids WHERE code='ALLIANCE'
)
OR (a.org_id, a.code) IN (
  SELECT org_id, '4400' FROM tmp_org_ids WHERE code IN ('INSTITUTE','COMMERCIAL')
);

-- === D) Voorbeeld: hiërarchische batch + intercompany (sept 2025) ===========

DO $$
DECLARE
  v_alliance bigint := (SELECT org_id FROM tmp_org_ids WHERE code='ALLIANCE');
  v_institute bigint := (SELECT org_id FROM tmp_org_ids WHERE code='INSTITUTE');
  v_commercial bigint := (SELECT org_id FROM tmp_org_ids WHERE code='COMMERCIAL');

  acc_all_due  bigint := (SELECT account_id FROM tmp_acc WHERE org_id=v_alliance  AND code='1600');
  acc_all_rev  bigint := (SELECT account_id FROM tmp_acc WHERE org_id=v_alliance  AND code='7000');

  acc_inst_exp bigint := (SELECT account_id FROM tmp_acc WHERE org_id=v_institute AND code='4400');
  acc_inst_due bigint := (SELECT account_id FROM tmp_acc WHERE org_id=v_institute AND code='2600');

  acc_comm_exp bigint := (SELECT account_id FROM tmp_acc WHERE org_id=v_commercial AND code='4400');
  acc_comm_due bigint := (SELECT account_id FROM tmp_acc WHERE org_id=v_commercial AND code='2600');

  e_all bigint; e_inst bigint; e_comm bigint;
  ic uuid := gen_random_uuid();
BEGIN
  -- Entries
  INSERT INTO accounting.entries(org_id,entry_date,status,reference,description,intercompany_id)
  VALUES (v_alliance,  DATE '2025-09-30','draft','MGMT-SEP-2025','Management fee allocation Sep 2025', ic)
  RETURNING id INTO e_all;

  INSERT INTO accounting.entries(org_id,entry_date,status,reference,description,intercompany_id)
  VALUES (v_institute, DATE '2025-09-30','draft','MGMT-SEP-2025','Management fee (Alliance)', ic)
  RETURNING id INTO e_inst;

  INSERT INTO accounting.entries(org_id,entry_date,status,reference,description,intercompany_id)
  VALUES (v_commercial,DATE '2025-09-30','draft','MGMT-SEP-2025','Management fee (Alliance)', ic)
  RETURNING id INTO e_comm;

  -- Alliance: batch root + 2 subbatches (inst/comm), elk balanced
  INSERT INTO accounting.lines(entry_id,org_id,node,path,description,require_balanced)
  VALUES
    (e_all,v_alliance,'group','root.0001','Mgmt fee Sep 2025',true),
    (e_all,v_alliance,'group','root.0001.inst','Inst subtree',true),
    (e_all,v_alliance,'group','root.0001.comm','Comm subtree',true);

  -- Alliance -> Institute (10.000)
  INSERT INTO accounting.lines(entry_id,org_id,node,path,account_id,direction,amount,metadata,description)
  VALUES
    (e_all,v_alliance,'line','root.0001.inst.0001',acc_all_due,'debit', 10000, jsonb_build_object('counterparty_org_id',v_institute),'Due from INSTITUTE'),
    (e_all,v_alliance,'line','root.0001.inst.0002',acc_all_rev,'credit', 10000, '{}','Mgmt fee revenue (INSTITUTE)');

  -- Alliance -> Commercial (15.000)
  INSERT INTO accounting.lines(entry_id,org_id,node,path,account_id,direction,amount,metadata,description)
  VALUES
    (e_all,v_alliance,'line','root.0001.comm.0001',acc_all_due,'debit', 15000, jsonb_build_object('counterparty_org_id',v_commercial),'Due from COMMERCIAL'),
    (e_all,v_alliance,'line','root.0001.comm.0002',acc_all_rev,'credit', 15000, '{}','Mgmt fee revenue (COMMERCIAL)');

  -- Institute: batch (balanced)
  INSERT INTO accounting.lines(entry_id,org_id,node,path,description,require_balanced)
  VALUES (e_inst,v_institute,'group','root.0001','Mgmt fee Sep 2025',true);

  INSERT INTO accounting.lines(entry_id,org_id,node,path,account_id,direction,amount,metadata,description)
  VALUES
    (e_inst,v_institute,'line','root.0001.0001',acc_inst_exp,'debit', 10000, '{}','Mgmt fee expense'),
    (e_inst,v_institute,'line','root.0001.0002',acc_inst_due,'credit', 10000, jsonb_build_object('counterparty_org_id',v_alliance),'Due to ALLIANCE');

  -- Commercial: batch (balanced)
  INSERT INTO accounting.lines(entry_id,org_id,node,path,description,require_balanced)
  VALUES (e_comm,v_commercial,'group','root.0001','Mgmt fee Sep 2025',true);

  INSERT INTO accounting.lines(entry_id,org_id,node,path,account_id,direction,amount,metadata,description)
  VALUES
    (e_comm,v_commercial,'line','root.0001.0001',acc_comm_exp,'debit', 15000, '{}','Mgmt fee expense'),
    (e_comm,v_commercial,'line','root.0001.0002',acc_comm_due,'credit', 15000, jsonb_build_object('counterparty_org_id',v_alliance),'Due to ALLIANCE');
END $$;

-- === E) Spin-off / deelneming onder ALLIANCE (sjabloon) =====================
-- Voeg een entiteit toe onder ALLIANCE met eigen administratie:
-- INSERT INTO accounting.organizations(code,name,parent_id,path,currency)
-- SELECT 'VENTURES1','NederAI Ventures I B.V.', a.org_id, text2ltree('nederai.alliance.ventures1'),'EUR'
-- FROM tmp_org_ids a WHERE a.code='ALLIANCE';
