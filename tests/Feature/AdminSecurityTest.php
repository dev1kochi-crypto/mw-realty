<?php

namespace Tests\Feature;

use App\Models\CmsKit\Admin;
use App\Models\CmsKit\Language;
use App\Models\Plan;
use App\Models\PortalUser;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions = [], bool $super = false): Admin
    {
        $user = Admin::create(['name' => 'Reviewer', 'email' => uniqid().'@example.test', 'password' => Hash::make('safe-password-123'), 'is_active' => true]);
        foreach ($permissions as $name) Permission::findOrCreate($name, 'cms');
        if ($super) {
            $role = Role::findOrCreate('superadmin', 'cms');
            $role->syncPermissions($permissions);
            $user->assignRole($role);
        } else {
            $user->givePermissionTo($permissions);
        }
        return $user;
    }

    private function signIn($user, string $guard = 'cms'): static
    {
        return $this->actingAs($user, $guard)->withSession(['password_hash_'.$guard => $user->getAuthPassword()]);
    }

    private function portal(array $attributes = []): PortalUser
    {
        return PortalUser::create(array_merge(['type' => 'agent', 'name' => 'Agent', 'email' => uniqid().'@example.test', 'password' => 'safe-password-123', 'status' => 'approved', 'is_active' => true], $attributes));
    }

    public function test_role_viewer_cannot_modify_permissions(): void
    {
        $user = $this->admin(['roles.view', 'permissions.view']);
        $this->signIn($user)->postJson('/admin/permissions', ['name' => 'injected.permission'])->assertForbidden();
        $this->assertDatabaseMissing('permissions', ['name' => 'injected.permission']);
    }

    public function test_user_editor_cannot_take_over_superadmin(): void
    {
        $super = $this->admin([], true);
        $editor = $this->admin(['users.view', 'users.edit']);
        $this->signIn($editor)->putJson('/admin/admins/'.$super->id, ['name' => 'Changed'])->assertForbidden();
        $this->assertSame('Reviewer', $super->fresh()->name);
    }

    public function test_disabled_portal_session_is_rejected(): void
    {
        $user = $this->portal();
        $this->signIn($user, 'portal');
        $user->update(['is_active' => false]);
        $this->getJson('/portal/properties')->assertUnauthorized();
    }

    public function test_changed_password_invalidates_existing_session(): void
    {
        $user = $this->portal();
        $this->signIn($user, 'portal');
        $user->update(['password' => 'different-secure-password']);
        $this->getJson('/portal/properties')->assertUnauthorized();
    }

    public function test_restricted_dashboard_does_not_include_global_metrics(): void
    {
        $this->signIn($this->admin())->get('/admin')->assertOk()->assertSee('Choose a section')->assertDontSee('Est. MRR');
    }

    public function test_document_upload_is_private_and_resets_verification(): void
    {
        Storage::fake('public');
        Storage::fake('kyc');
        $user = $this->portal(['document_status' => ['passport_document' => ['status' => 'verified']]]);
        $admin = $this->admin(['portal-accounts.view', 'portal-accounts.edit'], true);
        $this->signIn($admin)->postJson('/admin/portal-accounts/'.$user->id.'/documents/passport_document', [
            'document' => UploadedFile::fake()->create('passport.pdf', 10, 'application/pdf'),
        ])->assertOk();
        $path = $user->fresh()->passport_document;
        Storage::disk('kyc')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        $this->assertSame('pending', $user->fresh()->documentStatus('passport_document')['status']);
        $this->get('/admin/portal-accounts/'.$user->id.'/documents/passport_document')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_documents_require_permission_and_cannot_be_addressed_by_another_portal_user(): void
    {
        $owner = $this->portal();
        $this->get('/admin/portal-accounts/'.$owner->id.'/documents/passport_document')->assertRedirect();
        $this->signIn($this->admin())->get('/admin/portal-accounts/'.$owner->id.'/documents/passport_document')->assertForbidden();
    }

    public function test_metadata_rejects_non_image_upload(): void
    {
        $admin = $this->admin(['metadata.view', 'metadata.edit'], true);
        $this->signIn($admin)->putJson('/admin/metadata/1', ['og_image' => UploadedFile::fake()->create('payload.php', 10, 'application/x-httpd-php')])->assertUnprocessable();
    }

    public function test_property_requires_translations_and_cannot_exceed_quota(): void
    {
        Language::create(['name' => 'English', 'code' => 'en', 'status' => true, 'is_default' => true]);
        $plan = Plan::create(['price' => 0, 'billing_cycle' => 'free', 'property_limit' => 1, 'status' => true]);
        $user = $this->portal(['plan_id' => $plan->id]);
        $this->signIn($user, 'portal')->postJson('/portal/properties', [])->assertUnprocessable()->assertJsonValidationErrors('translations');
        $this->postJson('/portal/properties', ['translations' => ['en' => ['title' => 'Listing']], 'status' => '1'])->assertRedirect();
        $this->assertDatabaseCount('properties', 1);
        $this->postJson('/portal/properties', ['translations' => ['en' => ['title' => 'Second']]])->assertRedirect();
        $this->assertDatabaseCount('properties', 1);
    }

    public function test_cross_owner_property_update_is_denied(): void
    {
        $owner = $this->portal();
        $property = Property::create(['portal_user_id' => $owner->id, 'slug' => 'owned', 'translations' => ['en' => ['title' => 'Owned']]]);
        $this->signIn($this->portal(), 'portal')->putJson('/portal/properties/'.$property->id, ['translations' => ['en' => ['title' => 'Changed']]])->assertNotFound();
        $this->assertSame('Owned', $property->fresh()->getTranslation('title', 'en'));
    }

    public function test_payment_retries_preserve_original_amount_and_date(): void
    {
        $plan = Plan::create(['translations' => ['en' => ['name' => 'Paid']], 'price' => 100, 'billing_cycle' => 'monthly', 'status' => true]);
        $user = $this->portal(['plan_id' => $plan->id]);
        $this->signIn($this->admin(['portal-accounts.view', 'portal-accounts.edit'], true));
        $url = '/admin/portal-accounts/'.$user->id.'/payment-status';
        $this->postJson($url, ['payment_status' => 'paid'])->assertOk();
        $plan->update(['price' => 250]);
        $this->postJson($url, ['payment_status' => 'paid'])->assertOk();
        $this->assertDatabaseCount('plan_payments', 1);
        $this->assertDatabaseHas('plan_payments', ['amount' => 100, 'portal_user_id' => $user->id]);
    }

    public function test_get_requests_cannot_generate_files(): void
    {
        $this->signIn($this->admin(['sitemap.view', 'sitemap.edit', 'llms-txt.view', 'llms-txt.edit'], true));
        $this->get('/admin/sitemap/generate')->assertStatus(405);
        $this->get('/admin/seo/llms-txt/generate')->assertStatus(405);
    }

    public function test_malformed_bulk_action_is_rejected(): void
    {
        $this->signIn($this->admin(['communities.view', 'communities.delete', 'communities.edit'], true))
            ->postJson('/admin/communities/bulk-action', ['action' => 'unknown', 'ids' => [1]])->assertUnprocessable();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) $this->postJson('/portal/login', ['email' => 'missing@example.test', 'password' => 'wrong']);
        $this->postJson('/portal/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_reseeding_does_not_reset_an_existing_password(): void
    {
        $admin = $this->admin([], true);
        $before = $admin->password;
        $this->seed(\CMS\SiteManager\Database\Seeders\CmsRolesPermissionsSeeder::class);
        $this->assertSame($before, $admin->fresh()->password);
        $this->assertSame([], config('cms-kit.permissions.users'));
    }
}
