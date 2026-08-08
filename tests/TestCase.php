<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Modules\User\Models\User;

abstract class TestCase extends BaseTestCase
{
    /**
     * A Sanctum token for the given user. Lives here rather than in one test
     * class because every module's HTTP tests need it.
     */
    protected function tokenFor(User $user): string
    {
        return $user->createToken('api')->plainTextToken;
    }
}
