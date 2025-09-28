# NederAI Database Modules

This directory contains modular PostgreSQL DDL scripts that provision the
NederAI data platform. Each script has a numeric prefix to clarify execution
order and to keep cross-schema dependencies explicit. Run them with psql -f
from the repository root or via your preferred migration tooling.

The design follows a few guiding principles:

* PostgreSQL native – every definition targets PostgreSQL 14+ features such as identity columns and JSONB; there are no MySQL remnants.
* Data minimisation – personal data lives in dedicated tables with explicit purpose tracking and soft deletion support to meet GDPR/AVG obligations.
* Jurisdiction aware – reusable reference data lists EU/EEA Member States so cross-border compliance checks stay simple and auditable.
* Flexible modules – schemas are isolated (shared, identity, registry, etc.) so future services can evolve independently.

### Execution order

1. 00_extensions.sql
2. 10_shared_core.sql
3. 15_identity.sql
4. 20_registry.sql
5. 30_governance.sql
6. 40_compliance.sql
7. 50_documents.sql
8. 60_hr.sql
9. 70_accounting.sql
10. 80_equity.sql

All scripts are idempotent where PostgreSQL allows (CREATE ... IF NOT EXISTS)
so they can be re-run safely in development environments. Constraints and
reference data rely on earlier modules, so keep the order intact during
provisioning.
