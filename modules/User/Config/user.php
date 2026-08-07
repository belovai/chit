<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Purge order
    |--------------------------------------------------------------------------
    |
    | Fiók törlésekor a `users` cascade önmagában nem elég: a hozzá tartozó
    | táblák egymásra is hivatkoznak, méghozzá RESTRICT-tel (transaction_items
    | -> products, transactions -> merchants / merchant_locations). Egyetlen
    | cascade hullámban a Postgres ezekbe beleütközik, ezért a saját tábláit
    | ebben a sorrendben ürítjük — a gyerek sorokat (transaction_items,
    | merchant_locations, receipt_corrections, pipeline_run_steps,
    | pipeline_artifacts) a saját cascade-jük viszi.
    |
    | Mindegyik táblának `owner_id` oszlopa van. Új, felhasználóhoz kötött
    | tábla ide is kerüljön be, a függőségeinek megfelelő helyre.
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
