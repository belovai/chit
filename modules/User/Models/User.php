<?php

declare(strict_types=1);

namespace Modules\User\Models;

use App\Traits\UsesHashId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\User\Database\Factories\UserFactory;
use Modules\User\Enums\UserRole;

#[Hidden(
    'password',
    'remember_token',
)]
#[Fillable(
    'name',
    'email',
    'password',
    'role',
    'last_login_at',
)]
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, UsesHashId;

    #[\Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role->equals(UserRole::Admin);
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
