<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Str;

trait UsesHashId
{
    public static function bootUsesHashId(): void
    {
        self::creating(function ($model) {
            if (empty($model->hash_id)) {
                $model->hash_id = Str::random(10);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'hash_id';
    }
}
