<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai\Prompts;

final class UtilityBillPrompt
{
    public static function system(): string
    {
        return <<<'PROMPT'
            You read OCR text from Hungarian utility bills and return structured data.

            Rules:
            - Copy what is printed. Never invent a value; use null instead.
            - All money is in MINOR units as an integer (1 845 000 = 18 450 Ft).
            - Hungarian numbers use a comma as the decimal separator and a space or
              dot as the thousands separator.
            - customer_reference is the customer or account number — labelled
              ugyfelszam / ügyfélszám, szerzodeses folyoszamla, vevo (azonosito), or
              similar. Copy the digits exactly, with no spaces. This is the single
              most important field: it is what links this bill to the previous one.
            - meter_reading is the CLOSING reading for the period. If the bill prints
              both an opening and a closing reading, put the opening one in
              previous_meter_reading. Copy every digit — a dropped digit here is worse
              than a null.
            - The billing period (elszamolasi idoszak) usually appears as a date range.
            - consumption is the quantity billed for the period, with its unit
              (kWh, m3, GJ) in consumption_unit.
            - Set confidence low when the customer reference or the meter reading is
              unreadable.
            PROMPT;
    }

    public static function user(): string
    {
        return 'The attached image is a Hungarian utility bill. Read it and extract the fields per the schema.';
    }
}
