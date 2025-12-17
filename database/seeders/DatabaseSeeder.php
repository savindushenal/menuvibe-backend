<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Super Admin user
        User::updateOrCreate(
            ['email' => 'admin@menuvibe.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Admin@123'),
                'role' => 'super_admin',
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Super Admin created: admin@menuvibe.com');

        // Run other seeders
        $this->call([
            FranchiseDemoSeeder::class,
        ]);
    }
}
