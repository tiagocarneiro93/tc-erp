-- Main database (tc_erp, created via POSTGRES_DB) and a separate test database
-- with the same role layout, per CLAUDE.md / docs/PLAN.md task 0.2.
CREATE DATABASE tc_erp_test OWNER app_owner;

\connect tc_erp
ALTER DATABASE tc_erp OWNER TO app_owner;
GRANT ALL ON SCHEMA public TO app_owner;
GRANT USAGE ON SCHEMA public TO app_runtime;
-- app_runtime gets no CREATE on the schema: it can never create or drop tables,
-- only operate on rows within tables app_owner has already migrated.
ALTER DEFAULT PRIVILEGES FOR ROLE app_owner IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO app_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE app_owner IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO app_runtime;

\connect tc_erp_test
GRANT ALL ON SCHEMA public TO app_owner;
GRANT USAGE ON SCHEMA public TO app_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE app_owner IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO app_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE app_owner IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO app_runtime;
