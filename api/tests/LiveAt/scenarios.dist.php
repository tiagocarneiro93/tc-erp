<?php

declare(strict_types=1);

/**
 * The documents the live-AT suite sends, in the order it sends them (a
 * scenario can only reference — `references` — one that came before it).
 *
 * To change a document, don't edit this file: create `scenarios.local.php`
 * (git-ignored) returning an array shaped like this one. A name that exists
 * here is overridden key by key; a new name adds a scenario; `'enabled' =>
 * false` drops one. `AT_TEST_ONLY=FT,NC-basic make test-at-live` runs just
 * those scenarios (names or document types).
 *
 * Keys per scenario (all optional except `document_type` and `lines`):
 *
 *   document_type          FT | FS | FR | NC | ND | OR | PF | NE
 *   lines                  list of { quantity, unit_price, tax_region (PT|PT-AC|PT-MA),
 *                          tax_code (NOR|INT|RED|ISE), exemption (e.g. M07, required for ISE),
 *                          discount_percent }  — defaults: 1 × 100.00, PT, NOR
 *   customer               { tax_id, country } — default 999999990 / PT (consumidor final)
 *   status                 N | A (a document cancelled before it was ever communicated)
 *   pricing_mode           net | gross          rounding_method   per_line | per_group
 *   references             scenario names this document references (credit/debit notes)
 *   then_change_status_to  F → after registering, send ChangeWorkStatus (working documents)
 *   expect                 accepted (default) | rejected (a deliberately wrong document)
 *   tamper                 gross_total_plus_one → corrupt the totals so AT must refuse
 *   issue_date             Y-m-d, default today
 *
 * Amounts are never written here — they come out of the real PriceCalculator.
 * The series each document lives in are not configurable: see SeriesLifecycleTest
 * and DocumentCommunicationTest (one series per document type, registered per run).
 */
return [
    'FT-basic' => [
        'document_type' => 'FT',
        'customer' => ['tax_id' => '999999990', 'country' => 'PT'],
        'lines' => [['quantity' => '1', 'unit_price' => '100.00', 'tax_code' => 'NOR']],
    ],
    'FT-mixed-vat' => [
        'document_type' => 'FT',
        'lines' => [
            ['quantity' => '2', 'unit_price' => '49.99', 'tax_code' => 'NOR'],
            ['quantity' => '3', 'unit_price' => '12.50', 'tax_code' => 'INT'],
            ['quantity' => '1', 'unit_price' => '8.00', 'tax_code' => 'RED'],
            ['quantity' => '1', 'unit_price' => '30.00', 'tax_code' => 'ISE', 'exemption' => 'M07'],
            ['quantity' => '4', 'unit_price' => '10.00', 'tax_code' => 'NOR', 'discount_percent' => '10'],
        ],
    ],
    'FT-cancelled' => [
        'document_type' => 'FT',
        'status' => 'A',
        'lines' => [['quantity' => '1', 'unit_price' => '20.00', 'tax_code' => 'NOR']],
    ],
    'FS-basic' => [
        'document_type' => 'FS',
        'lines' => [['quantity' => '1', 'unit_price' => '12.30', 'tax_code' => 'NOR']],
    ],
    'FR-basic' => [
        'document_type' => 'FR',
        'lines' => [['quantity' => '1', 'unit_price' => '250.00', 'tax_code' => 'NOR']],
    ],
    'NC-basic' => [
        'document_type' => 'NC',
        'references' => ['FT-basic'],
        'lines' => [['quantity' => '1', 'unit_price' => '100.00', 'tax_code' => 'NOR']],
    ],
    'ND-basic' => [
        'document_type' => 'ND',
        'references' => ['FT-basic'],
        'lines' => [['quantity' => '1', 'unit_price' => '15.00', 'tax_code' => 'NOR']],
    ],
    'OR-basic' => [
        'document_type' => 'OR',
        'lines' => [['quantity' => '5', 'unit_price' => '20.00', 'tax_code' => 'NOR']],
    ],
    'PF-basic' => [
        'document_type' => 'PF',
        'lines' => [['quantity' => '10', 'unit_price' => '9.90', 'tax_code' => 'NOR']],
    ],
    'NE-basic' => [
        'document_type' => 'NE',
        'lines' => [['quantity' => '2', 'unit_price' => '75.00', 'tax_code' => 'NOR']],
    ],
    'NE-fully-converted' => [
        'document_type' => 'NE',
        'then_change_status_to' => 'F',
        'lines' => [['quantity' => '1', 'unit_price' => '60.00', 'tax_code' => 'NOR']],
    ],
    'FT-wrong-totals' => [
        'document_type' => 'FT',
        'expect' => 'rejected',
        'tamper' => 'gross_total_plus_one',
        'lines' => [['quantity' => '1', 'unit_price' => '100.00', 'tax_code' => 'NOR']],
    ],
];
