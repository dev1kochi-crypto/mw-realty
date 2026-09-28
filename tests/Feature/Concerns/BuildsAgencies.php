<?php

namespace Tests\Feature\Concerns;

use App\Models\AgencyAgent;
use App\Models\CmsKit\Admin;
use App\Models\CmsKit\Language;
use App\Models\Filter;
use App\Models\FilterValue;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\PortalUser;
use App\Models\Property;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Fixtures for the agency / agent / lead-assignment feature tests. */
trait BuildsAgencies
{
    protected function plan(array $attributes = []): Plan
    {
        return Plan::create(array_merge([
            'translations' => ['en' => ['name' => 'Agency Pro']],
            'price' => 0, 'billing_cycle' => 'monthly', 'status' => true,
            'property_limit' => null, 'agent_limit' => 10,
        ], $attributes));
    }

    protected function agency(array $attributes = [], ?Plan $plan = null): PortalUser
    {
        return PortalUser::create(array_merge([
            'type' => 'company', 'name' => 'Owner', 'company_name' => 'Agency ' . uniqid(),
            'email' => uniqid('agency') . '@example.test', 'password' => 'safe-password-123',
            'status' => 'approved', 'is_active' => true, 'plan_id' => ($plan ?? $this->plan())->id,
        ], $attributes));
    }

    protected function independentAgent(array $attributes = []): PortalUser
    {
        return PortalUser::create(array_merge([
            'type' => 'agent', 'name' => 'Agent ' . uniqid(), 'email' => uniqid('agent') . '@example.test',
            'password' => 'safe-password-123', 'status' => 'approved', 'is_active' => true,
            'plan_id' => $this->plan(['agent_limit' => 0])->id,
        ], $attributes));
    }

    /** An agent that is an approved, active member of $agency (or with the given membership status). */
    protected function memberAgent(PortalUser $agency, string $status = AgencyAgent::APPROVED, array $attributes = []): PortalUser
    {
        $agent = $this->independentAgent($attributes);
        AgencyAgent::create([
            'agency_id' => $agency->id, 'agent_id' => $agent->id, 'status' => $status,
            'initiated_by' => 'agency', 'approved_at' => now(), 'joined_at' => now(),
        ]);
        if (in_array($status, AgencyAgent::MEMBER_STATUSES, true)) {
            $agent->forceFill(['company_id' => $agency->id])->save();
        }

        return $agent->fresh();
    }

    protected function property(?PortalUser $owner, ?PortalUser $agent = null, array $attributes = []): Property
    {
        return Property::create(array_merge([
            'portal_user_id' => $owner?->id, 'agent_id' => $agent?->id, 'slug' => uniqid('listing-'),
            'reference_no' => uniqid('P'), 'translations' => ['en' => ['title' => 'Listing']], 'status' => true,
        ], $attributes));
    }

    protected function superAdmin(): Admin
    {
        $admin = Admin::create(['name' => 'Super', 'email' => uniqid() . '@example.test', 'password' => Hash::make('safe-password-123'), 'is_active' => true]);
        $permissions = ['portal-accounts.view', 'portal-accounts.edit'];
        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'cms');
        }
        $role = Role::findOrCreate('superadmin', 'cms');
        $role->syncPermissions($permissions);
        $admin->assignRole($role);

        return $admin;
    }

    protected function signIn($user, string $guard = 'portal'): static
    {
        return $this->actingAs($user, $guard)->withSession(['password_hash_' . $guard => $user->getAuthPassword()]);
    }

    /** Public enquiry exactly as the property page submits it. */
    protected function enquire(Property $property, string $name = 'Buyer'): Lead
    {
        $this->postJson('/leads/capture', [
            'property_id' => $property->id, 'name' => $name, 'email' => 'buyer@example.test', 'message' => 'Interested',
        ])->assertOk();

        return Lead::latest('id')->firstOrFail();
    }

    /** A valid create/update payload for the portal property form. */
    protected function propertyPayload(array $overrides = []): array
    {
        Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'status' => true, 'is_default' => true]);
        foreach (['property_type' => 'villa', 'listing_type' => 'sale'] as $key => $value) {
            $filter = Filter::firstOrCreate(['key' => $key], ['type' => 'select', 'status' => true, 'translations' => ['en' => ['label' => $key]]]);
            $filter->update(['status' => true]);
            FilterValue::firstOrCreate(['filter_id' => $filter->id, 'value' => $value], ['status' => true, 'translations' => ['en' => ['label' => $value]]]);
        }

        return array_merge([
            'translations' => ['en' => ['title' => 'Luxury Villa', 'address' => '1 Palm', 'city' => 'Dubai', 'country' => 'UAE']],
            'slug' => uniqid('villa-'), 'latitude' => 25.1, 'longitude' => 55.1, 'price' => 1000000,
            'property_type' => 'villa', 'listing_type' => 'sale', 'status' => '1',
        ], $overrides);
    }
}
