<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('seeding.admin_email', 'admin@shift-smith.com');
        $password = config('seeding.admin_password', 'admin');

        $user = User::firstOrCreate(
            [
                'email' => $email
            ],
            [
                'name' => 'Admin',
                'password' => Hash::make($password),
            ]
        );

        if(!$user->hasRole('admin')) {
            $user->assignRole('admin');
        }
    }


    public function run_old(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@shift-smith.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('v9dJY#IzSV4!*Sv3%mUM'),
            ]
        );

        if (! $user->hasRole('admin')) {
            $user->assignRole('admin');
        }
    }
}
