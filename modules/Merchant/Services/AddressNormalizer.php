<?php

declare(strict_types=1);

namespace Modules\Merchant\Services;

/**
 * Turns a printed address into a comparison key. Two spellings of one branch
 * ("Szilléri sugár út" and "Szilleri sgt.") must produce the same string, or
 * every receipt from that shop creates another location row.
 */
final class AddressNormalizer
{
    /** @var array<string, string> */
    private const ACCENTS = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o',
        'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
    ];

    /**
     * Token-for-token expansions. An empty replacement drops the token.
     *
     * @var array<string, string>
     */
    private const TOKENS = [
        'u' => 'utca',
        'sgt' => 'sugar ut',
        'krt' => 'korut',
        'hrsz' => '',
    ];

    public static function normalize(?string $address): ?string
    {
        if ($address === null) {
            return null;
        }

        $value = strtr(mb_strtolower($address), self::ACCENTS);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        $tokens = [];

        foreach (explode(' ', $value) as $token) {
            if ($token === '') {
                continue;
            }

            $replacement = self::TOKENS[$token] ?? $token;

            if ($replacement === '') {
                continue;
            }

            $tokens[] = $replacement;
        }

        $normalized = implode(' ', $tokens);

        return $normalized === '' ? null : $normalized;
    }
}
