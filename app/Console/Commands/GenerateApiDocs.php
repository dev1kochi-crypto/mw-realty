<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateApiDocs extends Command
{
    protected $signature = 'docs:generate';

    protected $description = 'Regenerate both API docs: frontend (/docs/frontend) and CRM (/docs/crm)';

    public function handle(): int
    {
        // A cached config would hide edits to config/scribe*.php.
        $this->call('config:clear');
        $this->call('scribe:generate', ['--force' => true]);
        $this->call('scribe:generate', ['--config' => 'scribe_crm', '--force' => true]);

        return self::SUCCESS;
    }
}
