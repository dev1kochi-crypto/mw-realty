<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateApiDocs extends Command
{
    protected $signature = 'docs:generate';

    protected $description = 'Regenerate the API docs: frontend (/docs/frontend), CRM (/docs/crm), the two mobile apps\' (/docs/app-customer, /docs/app-agent) and the latest updates (/docs/updates)';

    public function handle(): int
    {
        // A cached config would hide edits to config/scribe*.php.
        $this->call('config:clear');
        $this->call('scribe:generate', ['--force' => true]);
        $this->call('scribe:generate', ['--config' => 'scribe_crm', '--force' => true]);
        // Mobile apps: only the endpoints their screens call (config/scribe_app_*.php).
        $this->call('scribe:generate', ['--config' => 'scribe_app_customer', '--force' => true]);
        $this->call('scribe:generate', ['--config' => 'scribe_app_agent', '--force' => true]);
        // Only the endpoints of the latest updates, by feature (config/scribe_updates.php).
        $this->call('scribe:generate', ['--config' => 'scribe_updates', '--force' => true]);

        return self::SUCCESS;
    }
}
