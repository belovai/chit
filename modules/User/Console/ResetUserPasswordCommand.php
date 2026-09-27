<?php

declare(strict_types=1);

namespace Modules\User\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Modules\User\Actions\ChangeAccountPassword;
use Modules\User\Models\User;
use Modules\User\Requests\CreateUserRequest;

/**
 * Forgotten-password recovery without mail: an operator with shell access
 * sets a new password directly.
 */
final class ResetUserPasswordCommand extends Command
{
    protected $signature = 'user:reset-password
        {email : the user whose password is reset}';

    protected $description = 'Set a new password for a user, prompting for the password itself';

    public function handle(ChangeAccountPassword $change): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user with that email address.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('New password');

        if ($password !== (string) $this->secret('Confirm password')) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['password' => $password],
            ['password' => (new CreateUserRequest)->rules()['password']],
        );

        if ($validator->fails()) {
            $this->error(implode(' ', $validator->errors()->all()));

            return self::FAILURE;
        }

        // No current token in the console, so every existing session is
        // revoked - what a reset should do anyway.
        $change->handle($user, $password);

        $this->info('Password reset for '.$user->email.'.');

        return self::SUCCESS;
    }
}
