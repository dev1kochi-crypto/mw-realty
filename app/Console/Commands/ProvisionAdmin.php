<?php

namespace App\Console\Commands;

use App\Models\CmsKit\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProvisionAdmin extends Command
{
    protected $signature = 'admin:provision {email} {--name=Administrator}';
    protected $description = 'Create or reset a superadmin with a password entered securely in the terminal';

    public function handle(): int
    {
        $password = $this->secret('New password (at least 12 characters)');
        validator(['email' => $this->argument('email'), 'password' => $password], [
            'email' => 'required|email|max:255', 'password' => 'required|string|min:12|max:255',
        ])->validate();
        $admin = Admin::updateOrCreate(['email' => $this->argument('email')], [
            'name' => $this->option('name'), 'password' => Hash::make($password),
            'remember_token' => Str::random(60), 'is_active' => true,
        ]);
        $admin->syncRoles(['superadmin']);
        $this->info('Administrator saved. Previous sessions will be rejected.');
        return self::SUCCESS;
    }
}
