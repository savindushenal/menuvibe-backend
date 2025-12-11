<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create-super 
                            {email? : The email of the super admin}
                            {--promote : Promote an existing user to super admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new super admin user or promote an existing user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $promote = $this->option('promote');

        if ($promote && $email) {
            return $this->promoteUser($email);
        }

        return $this->createNewSuperAdmin($email);
    }

    private function promoteUser(string $email)
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email {$email} not found.");
            return 1;
        }

        if ($user->isSuperAdmin()) {
            $this->info("User {$email} is already a super admin.");
            return 0;
        }

        $user->update(['role' => User::ROLE_SUPER_ADMIN]);

        $this->info("User {$email} has been promoted to super admin.");
        return 0;
    }

    private function createNewSuperAdmin(?string $email)
    {
        $email = $email ?? $this->ask('Enter email address');
        
        if (User::where('email', $email)->exists()) {
            if ($this->confirm("User with email {$email} already exists. Do you want to promote them to super admin?")) {
                return $this->promoteUser($email);
            }
            return 1;
        }

        $name = $this->ask('Enter name');
        $password = $this->secret('Enter password');

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->info("Super admin created successfully!");
        $this->table(['ID', 'Name', 'Email', 'Role'], [
            [$user->id, $user->name, $user->email, $user->role]
        ]);

        return 0;
    }
}
