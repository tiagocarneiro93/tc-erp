# Live AT test suite

Real calls to **AT's test webservices** — series (`registarSerie`, `anularSerie`,
`finalizarSerie`) and e-Fatura (`RegisterInvoice`, `RegisterWork`,
`ChangeWorkStatus`) — without going through the product: no database, no UI, no
company. It wires the *production* adapters (`SeriesWSClient`, `EFaturaWSClient`,
the request builder, the password cipher, the mutual-TLS transport) straight to
your environment.

It is a configuration of its own (`phpunit.live-at.dist.xml`), so `make test` and
CI never run it.

## Setup (once)

1. Put the files AT gave you in `api/var/at-webservices/` (git-ignored):
   `TesteWebservices.pfx` and AT's public key (`at-public-key.cer`).
2. Create `api/.env.test.local` (git-ignored; **not** `.env.local` — Symfony does not
   load that one under `APP_ENV=test`, which is what PHPUnit runs as):

   ```dotenv
   # the AT sub-user (profiles WFA + WSE) and the NIF the test documents are issued as
   AT_TEST_SUBUSER=508025095/1
   AT_TEST_PASSWORD=...
   AT_TEST_NIF=508025095

   # the certificate bundle
   AT_WEBSERVICES_CLIENT_CERT_PATH=var/at-webservices/TesteWebservices.pfx
   AT_WEBSERVICES_CLIENT_CERT_PASSWORD=...
   AT_PUBLIC_KEY_PATH=var/at-webservices/at-public-key.cer

   # the links — these are the defaults in .env; only override to change them
   AT_EFATURA_WEBSERVICE_ENDPOINT=https://servicos.portaldasfinancas.gov.pt:723/fatcorews/ws/
   AT_SERIES_WEBSERVICE_ENDPOINT=https://servicos.portaldasfinancas.gov.pt:722/SeriesWSService
   ```

3. `make test-at-live`

Paths are relative to `api/`. Anything missing is listed in the skip message.

## It cannot reach production

Before anything runs, both endpoints must be `https://servicos.portaldasfinancas.gov.pt`
on a **test** port (722 series, 723 send invoices, 725 query invoices). Anything else
— production is 422/423 — makes every test **fail** (not skip) with
"Refusing to run". `TestEndpointGuard` is the single place that decides this.

## What it does

| Test | What it proves | Configurable? |
|---|---|---|
| `SeriesLifecycleTest` | register → cancel; register → finalise (probes AT's rule 4047 at the first number, then finalises at 2) | **No — fixed.** Training FT series from 1, asserting AT's exact codes 2001 / 2003 / 2004. Only the series code changes per run (AT refuses one it has seen): `LV<run>…` |
| `DocumentCommunicationTest` | every scenario below is sent to the e-Fatura test service, in a series registered for the run; AT must accept it (or, for a scenario that says so, refuse it) | **Yes** — scenarios |

The scenarios (`scenarios.dist.php`): FT (plain, mixed VAT rates with a discount and an
exempt line, cancelled), FS, FR, NC and ND (referencing the run's FT), OR, PF, NE, an NE
moved to "fully converted" with `ChangeWorkStatus`, and an FT with deliberately wrong
totals that AT must reject. Amounts are never typed in: lines go through the real
`PriceCalculator`.

### Changing what is sent

Don't edit `scenarios.dist.php`. Create `tests/LiveAt/scenarios.local.php` (git-ignored):

```php
<?php
return [
    // change only what you name; the rest of FT-basic stays as shipped
    'FT-basic' => [
        'customer' => ['tax_id' => '508025095', 'country' => 'PT'],
        'lines' => [['quantity' => '3', 'unit_price' => '19.90', 'tax_code' => 'INT']],
    ],
    'NC-basic' => ['enabled' => false],
    // a brand new one
    'FT-bulk' => ['document_type' => 'FT', 'lines' => [['quantity' => '1000', 'unit_price' => '0.99']]],
];
```

All keys are documented at the top of `scenarios.dist.php`. Run a subset without
editing anything:

```
AT_TEST_ONLY=FT,NC-basic make test-at-live     # names or document types
```

A scenario that `references` another one (NC/ND → FT-basic) is skipped when that one
was not sent in the run.

## Where to look afterwards

Each run prints one line per exchange and writes `api/var/at-live/<time>-<run>/`:
`report.md` (step, operation, what was sent, verdict, AT's code and message) and, for
e-Fatura calls, `NN-<scenario>-<operation>.request.xml` / `.response.xml`. The password
material in the security header is blanked; the rest is not, so treat the folder as
sensitive. Series calls go through `ext-soap`, which does not expose the raw XML — the
report has the parameters and AT's answer instead.

## Things to know

- Needs `ext-soap` (series). The production PHP image has it; a PHP without it skips.
- The suite is **offline-checked** in the normal suite (`tests/Unit/LiveAt`): endpoint
  guard, configuration, scenario merging, and that every shipped scenario produces a
  request AT's own schema accepts.
- Documents are sent as *uncertified software* (certificate number 0, hash characters
  `0`), as the product does until certification.
- Documents go into normal (non-training) series, like the product does; the series
  lifecycle test uses a training series, the one proven on 2026-09-22.
- The sub-user's NIF is normally the part before the `/`; if `AT_TEST_NIF` differs the
  run starts with a warning and AT may reject the documents.
- Only continental VAT (`PT`) is supported by the scenarios' rate table.
