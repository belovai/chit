<?php

declare(strict_types=1);

namespace Modules\Extraction\Ai\Prompts;

use Modules\Extraction\Enums\DocumentType;

/** Provider-neutral prompt content. No provider name or API concept appears here. */
final class ClassificationPrompt
{
    public static function system(): string
    {
        return <<<'PROMPT'
            You classify OCR text from Hungarian household documents.

            Decide which kind of document the text came from:
            - receipt: a shop, fuel station or restaurant receipt for a one-off purchase.
            - utility_bill: a periodic bill from a utility or telecom provider. These
              name a billing period, a customer/account number, and often a meter reading.
            - unknown: anything else, or text too damaged to tell.

            The text comes from OCR and will contain recognition errors. Judge by the
            document's structure and vocabulary, not by whether every character is right.
            If the evidence is thin, say so with a low confidence rather than guessing
            confidently.
            PROMPT;
    }

    public static function user(string $ocrText, ?DocumentType $hint = null): string
    {
        $hintLine = $hint === null || $hint === DocumentType::Unknown
            ? ''
            : "The person who uploaded it said this is a `{$hint->value}`. Treat that as a strong prior, but report what the text actually shows — if it contradicts them, say so and lower your confidence.\n\n";

        return $hintLine."OCR text:\n\n<document>\n{$ocrText}\n</document>";
    }
}
