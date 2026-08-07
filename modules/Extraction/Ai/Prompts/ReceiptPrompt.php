<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai\Prompts;

final class ReceiptPrompt
{
    public static function system(): string
    {
        return <<<'PROMPT'
            You read OCR text from Hungarian shop receipts and return structured data.

            Rules:
            - Copy what is printed. Never invent a value that is not on the receipt;
              use null instead.
            - All money is in MINOR units as an integer. Hungarian forint has no
              subunit in practice, so 1 327 Ft is 132700. 12,99 EUR is 1299.
            - Hungarian receipts use a comma as the decimal separator and a space or
              dot as the thousands separator. `1.327` and `1 327` are both one
              thousand three hundred and twenty-seven, not 1.327.
            - The grand total is the line labelled OSSZESEN / ÖSSZESEN / FIZETENDO /
              FIZETENDŐ / VEGOSSZEG. Prefer FIZETENDŐ when both appear and they differ,
              because that is what was actually paid.
            - Take one entry per printed line item, in printed order. Do not merge
              lines, do not split one line into several, and do not add a line for
              the total, the VAT summary, or any discount row.
            - A discount printed as a negative line is not a line item — put it in
              discount_minor as a positive number.
            - Deposit/bottle-return lines (BETETDIJ, GONGYOLEG) are line items.
            - A header can span several lines. Split it into two fields:
              merchant_name is the brand alone, taken from the legal company
              name with the company form (KFT., ZRT., BT.) and any branch
              marker removed — "SPAR MAGYARORSZAG KERESKEDELMI KFT." is "SPAR",
              "OMV Hodmezovasarhely 2" is "OMV".
            - merchant_address is the branch's own street address: the LAST
              address block in the header. When two addresses are printed, the
              one immediately following the legal company name is the
              registered office — discard it and keep the other. When only one
              address is printed, that is the branch.
            - A short, mostly numeric internal store or till code (e.g. "617 SM
              Szeged Szilleri sg") is neither a name nor an address. Ignore it.
            - If no branch address can be told apart with confidence, set
              merchant_address to null. Never guess one.
            - Set confidence low when the text is badly damaged, the total is missing,
              or the line items clearly do not add up.
            PROMPT;
    }

    public static function user(): string
    {
        return 'The attached image is a Hungarian shop receipt. Read it and extract the fields per the schema.';
    }
}
