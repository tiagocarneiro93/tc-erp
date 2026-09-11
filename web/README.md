# web

The tc-erp single-page application: Vite + React 19 + TypeScript (strict), TanStack Router/Query, Tailwind + shadcn/ui.

See the root `Makefile` for common commands (`make test-web`, `make lint`, `make e2e`, ...) and `docs/technical-scope.md` §11 for frontend conventions.

## Local development without Docker

```
pnpm install
pnpm dev            # Vite dev server on :5173, proxies /api to VITE_API_PROXY_TARGET (default http://localhost:8080)
pnpm test           # Vitest
pnpm e2e             # Playwright (requires the API + a seeded database, see docs/PLAN.md 0.12)
pnpm generate-client # regenerate src/api/generated.ts from ../api/openapi.json
```
