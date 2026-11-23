<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Recovery journey showcase (SIMULATED)
    |--------------------------------------------------------------------------
    |
    | In-memory demo timeline for the Rebill Recovery dashboard. Never written
    | to billing_ledger_entries and excluded from live KPI / export totals.
    | Enabled only in local by default (see allow_outside_local).
    |
    */
    'showcase' => [
        'enabled' => filter_var(env('REBILL_RECOVERY_SHOWCASE', false), FILTER_VALIDATE_BOOL),
        'allow_outside_local' => filter_var(env('REBILL_RECOVERY_SHOWCASE_ALLOW_PROD', false), FILTER_VALIDATE_BOOL),
    ],
];