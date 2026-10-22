<?php

namespace Database\Seeders;

use App\Models\LeadSource;
use App\Models\LeadStage;
use App\Models\LeadTag;
use App\Services\Crm\AdminOwnerResolver;
use Illuminate\Database\Seeder;

/** Seeds the shared CRM master data owned by Super Admin. */
class CrmAdminMasterDataSeeder extends Seeder
{
    public const STAGES = [
        ['name' => 'New', 'color' => '#4f46e5', 'is_default' => true],
        ['name' => 'Hot Buyer', 'color' => '#ef4444'],
        ['name' => 'Active Buyer', 'color' => '#0ea5e9'],
        ['name' => 'Active Seller', 'color' => '#14b8a6'],
        ['name' => 'Closed', 'color' => '#22c55e', 'is_closed' => true],
        ['name' => 'Negotiation', 'color' => '#8b5cf6'],
        ['name' => 'Dropped', 'color' => '#64748b', 'is_closed' => true],
        ['name' => 'Connected', 'color' => '#f59e0b'],
    ];

    public const TAGS = [
        ['name' => 'Buyer Lead', 'color' => '#0ea5e9'],
        ['name' => 'Seller Lead', 'color' => '#14b8a6'],
        ['name' => 'Hot Lead', 'color' => '#ef4444'],
        ['name' => 'Follow Up', 'color' => '#f59e0b'],
        ['name' => 'VIP', 'color' => '#8b5cf6'],
    ];

    public function run(): void
    {
        $admin = AdminOwnerResolver::resolve();
        $existingStageNames = LeadStage::where('portal_user_id', $admin->id)
            ->pluck('name')->map(fn ($name) => mb_strtolower(trim($name)))->all();
        $stageOrder = (int) LeadStage::where('portal_user_id', $admin->id)->max('order_index');

        foreach (static::STAGES as $stage) {
            if (in_array(mb_strtolower($stage['name']), $existingStageNames, true)) {
                continue;
            }

            LeadStage::create([
                'portal_user_id' => $admin->id,
                'name' => $stage['name'],
                'color' => $stage['color'],
                'order_index' => ++$stageOrder,
                'is_closed' => $stage['is_closed'] ?? false,
                'is_default' => $stage['is_default'] ?? false,
            ]);
        }

        // Existing Super Admin sources are preserved; missing system sources are added.
        LeadSource::seedDefaultsFor($admin);
        LeadSource::ensureSystemSources();

        $existingTagNames = LeadTag::where('portal_user_id', $admin->id)
            ->pluck('name')->map(fn ($name) => mb_strtolower(trim($name)))->all();
        foreach (static::TAGS as $tag) {
            if (in_array(mb_strtolower($tag['name']), $existingTagNames, true)) {
                continue;
            }

            LeadTag::create([
                'portal_user_id' => $admin->id,
                'name' => $tag['name'],
                'color' => $tag['color'],
            ]);
        }
    }
}
