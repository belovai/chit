<?php

declare(strict_types=1);

namespace Modules\Merchant\Tests\Unit;

use Modules\Merchant\Services\AddressNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AddressNormalizerTest extends TestCase
{
    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function addresses(): array
    {
        return [
            'accents and punctuation' => ['6723 Szeged, Szilléri sugár út 26.', '6723 szeged szilleri sugar ut 26'],
            'sgt abbreviation' => ['6723 Szeged Szilleri sgt. 26', '6723 szeged szilleri sugar ut 26'],
            'utca abbreviation' => ['Budapest, Váci u. 1.', 'budapest vaci utca 1'],
            'utca spelled out' => ['Budapest Vaci utca 1', 'budapest vaci utca 1'],
            'korut abbreviation' => ['Erzsébet krt. 5', 'erzsebet korut 5'],
            'korut spelled out' => ['Erzsébet körút 5', 'erzsebet korut 5'],
            'hrsz dropped' => ['2060 Bicske, Spar út 326/1. HRSZ', '2060 bicske spar ut 326 1'],
            'collapses whitespace' => ["  Fő   tér\t3 ", 'fo ter 3'],
            'null stays null' => [null, null],
            'empty becomes null' => ['', null],
            'punctuation only becomes null' => ['  ,.  ', null],
        ];
    }

    #[Test]
    #[DataProvider('addresses')]
    public function it_normalizes_addresses(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, AddressNormalizer::normalize($input));
    }

    #[Test]
    public function two_spellings_of_the_same_address_collide(): void
    {
        $this->assertSame(
            AddressNormalizer::normalize('6723 Szeged, Szilléri sugár út 26.'),
            AddressNormalizer::normalize('6723 SZEGED SZILLERI SGT 26'),
        );
    }
}
