<?php

namespace Tests\Feature;

use App\Models\CmsKit\Language;
use App\Models\Filter;
use App\Models\FilterValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** CRM › Master › Property Options: Super Admin manages the property form's dropdowns; nobody else can. */
class PropertyOptionsTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    public function test_super_admin_manages_options_and_the_form_uses_them(): void
    {
        Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'status' => true, 'is_default' => true]);
        Language::firstOrCreate(['code' => 'ar'], ['name' => 'Arabic', 'status' => true, 'is_default' => false]);
        $this->signIn($this->superAdmin(), 'cms');

        $this->get('/portal/crm/master/property-options')->assertOk()->assertSee('Property Options')->assertSee('Furnishing');
        $this->post('/portal/crm/master/property-options/property_type', ['translations' => ['en' => ['label' => 'Duplex'], 'ar' => ['label' => 'دوبلكس']]])
            ->assertRedirect();
        $duplex = FilterValue::where('value', 'duplex')->firstOrFail();
        $this->assertSame('دوبلكس', $duplex->translations['ar']['label']);

        // Duplicate code refused; rename keeps the code; used options can't be deleted.
        $this->post('/portal/crm/master/property-options/property_type', ['translations' => ['en' => ['label' => 'Duplex']]])->assertSessionHasErrors('value');
        $this->put("/portal/crm/master/property-options/property_type/{$duplex->id}", ['value' => 'duplex', 'translations' => ['en' => ['label' => 'Duplex Home']]])->assertRedirect();
        $this->assertSame('Duplex Home', $duplex->fresh()->translations['en']['label']);

        $agency = $this->agency();
        $this->property($agency, null, ['property_type' => 'duplex']);
        $this->deleteJson("/portal/crm/master/property-options/property_type/{$duplex->id}")->assertStatus(422);
        $this->postJson("/portal/crm/master/property-options/property_type/{$duplex->id}/toggle")->assertOk();
        $this->assertFalse($duplex->fresh()->status);

        // Furnishing list exists (migration) and is managed the same way.
        $furnishing = Filter::where('key', Filter::FURNISHING_KEY)->firstOrFail();
        $this->assertEqualsCanonicalizing(['furnished', 'semi_furnished', 'unfurnished'], $furnishing->values()->pluck('value')->all());
    }

    public function test_agents_and_agencies_cannot_manage_options_and_are_told_to_raise_a_ticket(): void
    {
        $agency = $this->agency();
        $this->signIn($agency);
        $this->get('/portal/crm/master/property-options')->assertForbidden();
        $this->post('/portal/crm/master/property-options/property_type', ['translations' => ['en' => ['label' => 'Hack']]])->assertForbidden();
        $this->get('/portal/properties')->assertDontSee('Property Options');
        $this->get('/portal/properties/create')->assertOk()->assertSee('Raise a ticket');
    }
}
