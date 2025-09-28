-- Seed baseline chart of accounts records for key NederAI entities.
-- Requires shared core schema (10_shared_core.sql) and accounting schema (70_accounting.sql).

BEGIN;

WITH org_map AS (
    SELECT org_key, organisation_id
    FROM shared.organisations
    WHERE org_key IN ('stichting', 'alliance')
)
INSERT INTO accounting.accounts (
    organisation_id,
    code,
    rgs_code,
    name,
    description,
    account_kind,
    normal_side
)
SELECT
    o.organisation_id,
    seed.code,
    seed.rgs_code,
    seed.name,
    seed.description,
    seed.account_kind::accounting.account_kind,
    seed.normal_side::accounting.normal_side
FROM (
    VALUES
        ('stichting', 'BEiv', 'BEiv', 'Eigen vermogen stichting', 'Bestemmingsreserves en fondsen', 'balance', 'credit'),
        ('stichting', 'BFva', 'BFva', 'Financiële vaste activa', 'Langlopende financiële bezittingen, zoals deelnemingen en leningen.', 'balance', 'debit'),
        ('alliance', 'BFva', 'BFva', 'Financiële vaste activa', 'Langlopende financiële bezittingen, zoals deelnemingen en leningen.', 'balance', 'debit')
) AS seed(org_key, code, rgs_code, name, description, account_kind, normal_side)
JOIN org_map o ON o.org_key = seed.org_key
ON CONFLICT (organisation_id, code) DO UPDATE
SET
    rgs_code = EXCLUDED.rgs_code,
    name = EXCLUDED.name,
    description = EXCLUDED.description,
    account_kind = EXCLUDED.account_kind,
    normal_side = EXCLUDED.normal_side;

WITH detailed_seed AS (
    SELECT *
    FROM (
        VALUES
            ('stichting', 'BEivBer', 'BEiv', 'BEivBer', 'Bestemmingsreserves', 'Door bestuur zelf met een bestemming gemarkeerde reserves.', 'balance', 'credit'),
            ('stichting', 'BEivBef', 'BEiv', 'BEivBef', 'Bestemmingsfondsen', 'Vermogen met een externe bestemming (bijv. subsidies, donaties).', 'balance', 'credit'),
            ('stichting', 'BEivHer', 'BEiv', 'BEivHer', 'Herwaarderingsreserves', 'Reserve bij herwaardering van activa (Alliance).', 'balance', 'credit'),
            ('stichting', 'BEivOvr', 'BEiv', 'BEivOvr', 'Overige reserves', 'Verzamelpost en belangrijkste reserve: o.a. dividend van Alliance dat niet apart is geoormerkt.', 'balance', 'credit'),
            ('stichting', 'BEivWer', 'BEiv', 'BEivWer', 'Wettelijke reserves', 'Verplicht door wet, o.a. deelnemingsreserve bij niet-uitgekeerde winst in groep.', 'balance', 'credit'),
            ('stichting', 'BFvaGmg', 'BFva', 'BFvaGmg', 'Deelneming in groepsmaatschappijen', 'Waarde Alliance', 'balance', 'debit'),
            ('alliance', 'BFvaGmg', 'BFva', 'BFvaGmg', 'Deelneming in groepsmaatschappijen', 'Waarde werkmaatschappijen', 'balance', 'debit'),
            ('alliance', 'BFvaDee', 'BFva', 'BFvaDee', 'Deelnemingen', 'Waarde aandeel spin-offs', 'balance', 'debit')
    ) AS v(org_key, code, parent_code, rgs_code, name, description, account_kind, normal_side)
), org_lookup AS (
    SELECT org_key, organisation_id
    FROM shared.organisations
    WHERE org_key IN ('stichting', 'alliance')
)
INSERT INTO accounting.accounts (
    organisation_id,
    code,
    rgs_code,
    name,
    description,
    parent_account_id,
    account_kind,
    normal_side
)
SELECT
    o.organisation_id,
    seed.code,
    seed.rgs_code,
    seed.name,
    seed.description,
    parent.account_id,
    seed.account_kind::accounting.account_kind,
    seed.normal_side::accounting.normal_side
FROM detailed_seed seed
JOIN org_lookup o ON o.org_key = seed.org_key
JOIN accounting.accounts parent ON parent.organisation_id = o.organisation_id AND parent.code = seed.parent_code
ON CONFLICT (organisation_id, code) DO UPDATE
SET
    rgs_code = EXCLUDED.rgs_code,
    name = EXCLUDED.name,
    description = EXCLUDED.description,
    parent_account_id = EXCLUDED.parent_account_id,
    account_kind = EXCLUDED.account_kind,
    normal_side = EXCLUDED.normal_side;

COMMIT;
