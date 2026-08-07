<?php

declare(strict_types=1);

namespace Modules\User\Events;

/**
 * A törlésre ítélt felhasználó adatainak takarítása előtt sül el. A User modul
 * nem ismerheti a rá épülő modulokat, ezért a saját fájljait mindegyik modul
 * maga takarítja el egy listenerben — a DB sorokat a `users` cascade viszi.
 */
final class UserPurging
{
    public function __construct(public readonly int $userId) {}
}
