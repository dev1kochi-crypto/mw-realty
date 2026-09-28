<?php

namespace Tests\Feature;

use App\Models\CmsKit\Enquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** POST /api/contact — the /contact page form and the home page "Get in touch" form. */
class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_form_saves_an_enquiry(): void
    {
        Mail::fake();

        $this->postJson('/api/contact', ['name' => 'Sara', 'email' => 'sara@example.test', 'message' => 'Hello', 'source' => 'contact'])
            ->assertOk();

        $this->assertDatabaseHas('enquiries', ['email' => 'sara@example.test', 'page_source' => 'Contact Page', 'message' => 'Hello']);
    }

    public function test_home_form_saves_interest_and_message_is_optional(): void
    {
        Mail::fake();

        $this->postJson('/api/contact', ['name' => 'Omar', 'email' => 'omar@example.test', 'phone' => '+971500000000', 'interest' => 'rent', 'source' => 'home'])
            ->assertOk();

        $enquiry = Enquiry::where('email', 'omar@example.test')->firstOrFail();
        $this->assertSame('Home Page', $enquiry->page_source);
        $this->assertSame('Interested in: Renting', $enquiry->message);
        $this->assertSame(['interest' => 'Renting'], $enquiry->extra_fields);
    }

    public function test_message_is_required_without_an_interest_and_interest_is_validated(): void
    {
        $this->postJson('/api/contact', ['name' => 'X', 'email' => 'x@example.test'])->assertJsonValidationErrors('message');
        $this->postJson('/api/contact', ['name' => 'X', 'email' => 'x@example.test', 'interest' => 'steal'])->assertJsonValidationErrors('interest');
        $this->assertSame(0, Enquiry::count());
    }
}
