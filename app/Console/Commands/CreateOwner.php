<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('app:create-owner {email} {--name=Owner}')]
#[Description('Create the initial private owner account')]
class CreateOwner extends Command
{
    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->components->error('An owner account already exists.');

            return self::FAILURE;
        }

        $email = Str::lower((string) $this->argument('email'));
        $name = (string) $this->option('name');
        $validator = Validator::make(compact('email', 'name'), [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password (minimum 12 characters)');
        $passwordConfirmation = (string) $this->secret('Confirm password');

        if (Str::length($password) < 12) {
            $this->components->error('The password must be at least 12 characters.');

            return self::FAILURE;
        }

        if ($password !== $passwordConfirmation) {
            $this->components->error('The password confirmation does not match.');

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->components->info('Owner account created.');

        return self::SUCCESS;
    }
}
