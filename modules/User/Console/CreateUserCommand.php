<?php

declare(strict_types=1);

namespace Modules\User\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Modules\User\Actions\CreateUser;
use Modules\User\Requests\CreateUserRequest;

/**
 * Creates an account without the registration endpoint or mail - the path
 * for instances where registration is closed and no mailer is configured.
 */
final class CreateUserCommand extends Command
{
    protected $signature = 'user:create';

    protected $description = 'Create a user, prompting for name, email and password';

    public function handle(CreateUser $create): int
    {
        // Prompted, never an argument: a password passed on the command line
        // lands in the shell history and in the process list.
        $data = [
            'name' => (string) $this->ask('Name'),
            'email' => (string) $this->ask('Email'),
            'password' => (string) $this->secret('Password'),
        ];

        if ($data['password'] !== (string) $this->secret('Confirm password')) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }

        // Same rules as the HTTP endpoint; the coded messages are for the
        // frontend, so the console gets Laravel's readable defaults.
        $validator = Validator::make($data, (new CreateUserRequest)->rules());

        if ($validator->fails()) {
            $this->error(implode(' ', $validator->errors()->all()));

            return self::FAILURE;
        }

        $user = $create->handle($validator->validated());

        $this->info('Created user '.$user->email.' (#'.$user->id.').');

        return self::SUCCESS;
    }
}
