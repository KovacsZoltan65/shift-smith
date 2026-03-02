<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('seeding.superadmin_email', 'superadmin@shift-smith.com');
        $password = config('seeding.superadmin_password', 'superadmin');

        $user = User::firstOrCreate(
            [
                'email' => $email
            ],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        if(!$user->hasRole('superadmin')) {
            $user->assignRole('superadmin');
        }
    }
}
