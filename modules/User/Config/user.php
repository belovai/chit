<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Purge order
    |--------------------------------------------------------------------------
    |
    | On account deletion, the `users` cascade alone isn't enough: its tables
    | also reference each other, with RESTRICT (transaction_items -> products,
    | transactions -> merchants / merchant_locations). In a single cascade wave
    | Postgres would run into these, so we empty its own tables in this order —
    | the child rows (transaction_items, merchant_locations, receipt_corrections,
    | pipeline_run_steps, pipeline_artifacts) are handled by their own cascade.
    |
    | Every table has an `owner_id` column. Any new user-scoped table should be
    | added here too, in the position matching its dependencies.
    |
    */

    'purge_tables' => [
        'receipts',
        'transactions',
        'products',
        'merchants',
        'pipeline_runs',
    ],

];
