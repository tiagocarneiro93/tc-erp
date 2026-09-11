-- Login roles for tc-erp (CLAUDE.md — hard rules: multi-tenancy).
--
-- app_owner:   owns the schema, runs migrations (make migrate). Never used by the app at runtime.
-- app_runtime: the role the application connects as. No table ownership, no BYPASSRLS,
--              so PostgreSQL Row-Level Security policies always apply to it.
--
-- Passwords below are throwaway local-dev values, not used anywhere outside this Compose stack.
CREATE ROLE app_owner WITH LOGIN PASSWORD 'app_owner' NOSUPERUSER NOCREATEDB NOCREATEROLE;
CREATE ROLE app_runtime WITH LOGIN PASSWORD 'app_runtime' NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS;
