-- Adminer 5.4.0 PostgreSQL 17.6 dump

DROP FUNCTION IF EXISTS "enforce_balance_entry_zero";;
CREATE FUNCTION "enforce_balance_entry_zero" () RETURNS trigger LANGUAGE plpgsql AS '
DECLARE
  sum_cents bigint;
BEGIN
  /*
    Compute signed sum for BALANCE accounts in this entry in cents to avoid float issues.
    Debit = +amount; Credit = -amount.
  */
  SELECT COALESCE(SUM(
           CASE l.dc WHEN ''D'' THEN (l.amount*100)::bigint ELSE -(l.amount*100)::bigint END
         ), 0)
    INTO sum_cents
  FROM lines l
  JOIN accounts a ON a.account_id = l.account_id
  WHERE l.entry_id = COALESCE(NEW.entry_id, OLD.entry_id)
    AND a.kind = ''balance'';

  IF sum_cents <> 0 THEN
    RAISE EXCEPTION
      ''Entry %: balance-sheet lines must net to 0. Current net = % cents'',
      COALESCE(NEW.entry_id, OLD.entry_id), sum_cents
      USING ERRCODE = ''23514''; -- check_violation
  END IF;

  RETURN NULL; -- statement-level trigger
END ';

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

INSERT INTO "accounts" ("account_id", "org_key", "rgs_code", "title", "description", "parent_account_id", "kind", "normal_side", "is_active") VALUES
(2,	'stichting',	'BEiv',	'Eigen vermogen stichting',	'Bestemmingsreserves en fondsen',	NULL,	'balance',	'credit',	't'),
(3,	'stichting',	'BEivBer',	'Bestemmingsreserves',	'Door bestuur zelf met een bestemming gemarkeerde reserves.',	2,	'balance',	'credit',	't'),
(5,	'stichting',	'BEivBef',	'Bestemmingsfondsen',	'Vermogen met een externe bestemming (bijv. subsidies, donaties).',	2,	'balance',	'credit',	't'),
(7,	'stichting',	'BEivHer',	'Herwaarderingsreserves',	'Reserve bij herwaardering van activa (Alliance).',	2,	'balance',	'credit',	't'),
(8,	'stichting',	'BEivOvr',	'Overige reserves',	'Verzamelpost en belangrijkste reserve: o.a. dividend van Alliance dat niet apart is geoormerkt.',	2,	'balance',	'credit',	't'),
(6,	'stichting',	'BEivWer',	'Wettelijke reserves',	'Verplicht door wet, o.a. deelnemingsreserve bij niet-uitgekeerde winst in groep.',	2,	'balance',	'credit',	't'),
(10,	'stichting',	'BFvaGmg',	'Deelneming in groepsmaatschappijen',	'Waarde Alliance',	9,	'balance',	'debit',	't'),
(9,	'stichting',	'BFva',	'Financiële vaste activa',	'Langlopende financiële bezittingen, zoals deelnemingen en leningen.',	NULL,	'balance',	'debit',	't'),
(11,	'alliance',	'BFva',	'Financiële vaste activa',	'Langlopende financiële bezittingen, zoals deelnemingen en leningen.',	NULL,	'balance',	'debit',	't'),
(12,	'alliance',	'BFvaGmg',	'Deelneming in groepsmaatschappijen',	'Waarde Werkmaatschappijen',	11,	'balance',	'debit',	't'),
(13,	'alliance',	'BFvaDee',	'Deelnemingen',	'Waarde aandeel spin-offs',	11,	'balance',	'debit',	't');

ALTER TABLE ONLY "accounting"."accounts" ADD CONSTRAINT "accounts_org_key_fkey" FOREIGN KEY (org_key) REFERENCES orgs(org_key) ON UPDATE CASCADE ON DELETE RESTRICT NOT DEFERRABLE;
ALTER TABLE ONLY "accounting"."accounts" ADD CONSTRAINT "accounts_parent_account_id_fkey" FOREIGN KEY (parent_account_id) REFERENCES accounts(account_id) ON UPDATE CASCADE ON DELETE RESTRICT NOT DEFERRABLE;

-- 2025-09-28 11:37:43 UTC

