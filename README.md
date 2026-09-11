# tc-erp

Multi-tenant SaaS invoicing and ERP platform for Portuguese SMEs, built to be
certified by the Autoridade Tributária (AT).

- Technical scope: [`docs/technical-scope.md`](docs/technical-scope.md)
- Implementation plan: [`docs/PLAN.md`](docs/PLAN.md)
- Instructions for Claude Code: [`CLAUDE.md`](CLAUDE.md)

## Repository layout

```
/api        Symfony application (the core)
/web        React SPA
/docs       scope, plan, phase plans, ADRs, legal sources
/docker     Dockerfiles, init scripts (DB roles), config
Makefile    entry point for all common commands
```

## Getting started

```
make up      # start the local environment
make help    # list all available commands
```
