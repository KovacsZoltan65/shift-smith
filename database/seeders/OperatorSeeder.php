<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OperatorSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('seeding.operator_email', 'operator@shift-smith.com');
        $password = config('seeding.operator_password', 'operator');

        $user = User::firstOrCreate(
            [
                'email' => $email
            ],
            [
                'name' => 'Operator',
                'password' => Hash::make($password),
            ]
        );

        if(!$user->hasRole('operator')) {
            $user->assignRole('operator');
        }
    }
}