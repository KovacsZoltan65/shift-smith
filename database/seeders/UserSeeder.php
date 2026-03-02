<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('seeding.user_email', 'user@shift-smith.com');
        $password = config('seeding.user_password', 'user');

        $user = User::firstOrCreate(
            [
                'email' => $email
            ],
            [
                'name' => 'User',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        if(!$user->hasRole('user')) {
            $user->assignRole('user');
        }
    }
}