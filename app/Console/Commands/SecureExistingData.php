<?php

namespace App\Console\Commands;

use App\Models\CmsKit\Admin;
use App\Models\PortalUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SecureExistingData extends Command
{
    protected $signature = 'admin:secure-existing-data';
    protected $description = 'Privatize existing KYC files and replace known default admin passwords';

    public function handle(): int
    {
        $moved = 0;
        PortalUser::orderBy('id')->chunkById(100, function ($users) use (&$moved) {
            foreach ($users as $user) {
                foreach (PortalUser::DOCUMENT_FIELDS as $field) {
                    $path = $user->{$field};
                    if (!$path || !Storage::disk('public')->exists($path)) continue;
                    if (!str_starts_with($path, 'portal-kyc/')) throw new \RuntimeException('Unexpected KYC path; manual review required.');
                    $stream = Storage::disk('public')->readStream($path);
                    try {
                        Storage::disk('kyc')->put($path, $stream);
                    } finally {
                        if (is_resource($stream)) fclose($stream);
                    }
                    if (hash('sha256', Storage::disk('public')->get($path)) !== hash('sha256', Storage::disk('kyc')->get($path))) {
                        throw new \RuntimeException('KYC copy verification failed.');
                    }
                    if (!Storage::disk('public')->delete($path)) throw new \RuntimeException('Unable to remove public KYC copy.');
                    $moved++;
                }
            }
        });
        $rotated = 0;
        foreach (Admin::all() as $admin) {
            if (!Hash::check('password', $admin->password)) continue;
            $password = Str::password(24);
            // Store recovery material before rotating; never print passwords into logs.
            $path = 'admin-recovery/admin-'.$admin->id.'.txt';
            if (!Storage::disk('kyc')->put($path, $admin->email.PHP_EOL.$password.PHP_EOL)) {
                throw new \RuntimeException('Could not save private recovery credentials.');
            }
            $admin->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            $rotated++;
        }
        $this->info("Private documents secured: {$moved}. Default passwords rotated: {$rotated}.");
        if ($rotated) $this->info('Recovery credentials: storage/app/private/admin-recovery/. Remove them after signing in and setting your own password.');
        return self::SUCCESS;
    }
}
