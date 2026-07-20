<?php

namespace App\Console\Commands\Concerns;

use App\Models\User;

/**
 * Resolves the --user option every command that reads or writes a specific
 * user's data declares in its own signature, so the two ways that
 * resolution can fail, the option missing entirely and the given email not
 * matching anyone on record, are reported the same way everywhere instead
 * of each command inventing its own wording.
 *
 * Split into two steps rather than one, instead of always going straight
 * to the database: a caller that can answer from something cheaper than a
 * user lookup, such as a cache keyed on the email itself, only needs
 * requiredUserEmail() up front and can defer findUserByEmail() to the
 * point it actually has no cheaper answer left to give.
 */
trait ResolvesUserOption
{
    private function requiredUserEmail(): string|false
    {
        $email = $this->option('user');

        if ($email === null) {
            $this->components->error('The --user option is required: no request can be answered without knowing whose data to consult.');

            return false;
        }

        return $email;
    }

    private function findUserByEmail(string $email): User|false
    {
        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No user found with email \"{$email}\".");

            return false;
        }

        return $user;
    }

    private function resolveUserOption(): User|false
    {
        $email = $this->requiredUserEmail();

        if ($email === false) {
            return false;
        }

        return $this->findUserByEmail($email);
    }
}
