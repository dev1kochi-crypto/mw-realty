<?php

namespace Database\Seeders;

use App\Models\LeadSource;
use App\Models\LeadStage;
use App\Models\LeadTag;
use App\Services\Crm\AdminOwnerResolver;
use Illuminate\Database\Seeder;

/**
 * Seeds the initial CRM master data (Stages/Tags/Sources) owned by the shared
 * "Admin" PortalUser row, reusing the same portal_user_id ownership column
 * every other CRM owner uses — see AdminOwnerResolver. Idempotent: skipped
 * per-type if that type already has rows for this owner, so re-running never
 * duplicates or reorders existing records.
 */
class CrmAdminMasterDataSeeder extends Seeder
{
    /** Stage funnel for the Admin owner — name, color, and whether it's a terminal/closed stage. */
    public const STAGES = [
        ['name' => 'New', 'color' => '#4f46e5'],
        ['name' => 'Connected', 'color' => '#f59e0b'],
        ['name' => 'Negotiation', 'color' => '#8b5cf6'],
        ['name' => 'Closed', 'color' => '#14b8a6', 'is_closed' => true],
        ['name' => 'Dropped', 'color' => '#ef4444', 'is_closed' => true],
    ];

    public function run(): void
    {
        $admin = AdminOwnerResolver::resolve();

        if (!LeadStage::where('portal_user_id', $admin->id)->exists()) {
            foreach (static::STAGES as $index => $stage) {
                LeadStage::create([
                    'portal_user_id' => $admin->id,
                    'name' => $stage['name'],
                    'color' => $stage['color'],
                    'order_index' => $index + 1,
                    'is_closed' => $stage['is_closed'] ?? false,
                    'is_default' => $index === 0,
                ]);
            }
        }

        if (!LeadTag::where('portal_user_id', $admin->id)->exists()) {
            foreach (range(1, 5) as $i) {
                LeadTag::create([
                    'portal_user_id' => $admin->id,
                    'name' => "Tag {$i}",
                    'color' => '#14b8a6',
                ]);
            }
        }

        LeadSource::seedDefaultsFor($admin);
    }
}
