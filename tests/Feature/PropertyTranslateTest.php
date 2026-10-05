<?php

namespace Tests\Feature;

use App\Models\CmsKit\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** Property form auto-fill: POST /portal/properties/translate → Google Translate. */
class PropertyTranslateTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    protected function setUp(): void
    {
        parent::setUp();
        Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'status' => true, 'is_default' => true]);
        Language::firstOrCreate(['code' => 'ar'], ['name' => 'Arabic', 'status' => true]);
        config(['services.translate.driver' => 'google']); // tests pick their driver explicitly, whatever .env says
    }

    public function test_translates_text_and_html_fields_into_each_target(): void
    {
        config(['services.google_translate.key' => 'test-key']);
        Http::fake(function (HttpRequest $request) {
            parse_str(str_replace('q=', 'q[]=', $request->body()), $body);
            $prefix = $body['format'] === 'html' ? 'HTML-' : 'AR-';
            return Http::response(['data' => ['translations' => array_map(fn ($q) => ['translatedText' => $prefix . $q], $body['q'])]]);
        });

        $this->signIn($this->agency())->postJson('/portal/properties/translate', [
            'source' => 'en', 'targets' => ['ar'],
            'fields' => ['title' => 'Sea View Villa', 'key_features' => '', 'description' => '<p>Big pool</p>', 'city' => 'Dubai', 'not_allowed' => 'x'],
        ])->assertOk()->assertExactJson(['translations' => ['ar' => [
            'title' => 'AR-Sea View Villa', 'key_features' => '', 'city' => 'AR-Dubai', 'description' => 'HTML-<p>Big pool</p>',
        ]]]);

        // One request for the plain-text fields, one for the HTML one; empty strings never sent.
        Http::assertSentCount(2);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->body(), 'format=text') && !str_contains($r->body(), 'key_features') && substr_count($r->body(), '&q=') === 2);
    }

    public function test_mymemory_driver_needs_no_key_chunks_long_text_and_keeps_html(): void
    {
        config(['services.translate.driver' => 'mymemory', 'services.google_translate.key' => null]);
        Http::fake(function (HttpRequest $request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);
            $this->assertSame('en|ar', $query['langpair']);
            $this->assertLessThanOrEqual(500, strlen($query['q']));
            return Http::response(['responseStatus' => 200, 'responseData' => ['translatedText' => '[' . strtoupper($query['q']) . ']']]);
        });

        $long = trim(str_repeat('Sea view. ', 80)); // ~800 bytes → split into 2 pieces
        $this->signIn($this->agency())->postJson('/portal/properties/translate', [
            'source' => 'en', 'targets' => ['ar'],
            'fields' => ['title' => 'Villa', 'key_features' => $long, 'description' => '<p>Big <b>pool</b> &amp; gym</p>'],
        ])->assertOk()
            ->assertJsonPath('translations.ar.title', '[VILLA]')
            ->assertJsonPath('translations.ar.description', '<p>[BIG] <b>[POOL]</b> [&amp; GYM]</p>');

        Http::assertSentCount(6); // title, 2 key_features pieces, 3 description text nodes
    }

    public function test_mymemory_quota_message_is_treated_as_a_failure(): void
    {
        config(['services.translate.driver' => 'mymemory']);
        Http::fake(['*' => Http::response(['responseStatus' => 429, 'responseData' => ['translatedText' => 'MYMEMORY WARNING: YOU USED ALL AVAILABLE FREE TRANSLATIONS FOR TODAY']])]);

        $this->signIn($this->agency())->postJson('/portal/properties/translate', ['source' => 'en', 'targets' => ['ar'], 'fields' => ['title' => 'Villa']])
            ->assertStatus(502);
    }

    public function test_reports_when_auto_translate_is_not_configured(): void
    {
        config(['services.google_translate.key' => null]);
        Http::fake();

        $this->signIn($this->agency())->postJson('/portal/properties/translate', ['source' => 'en', 'targets' => ['ar'], 'fields' => ['title' => 'Villa']])
            ->assertStatus(503)->assertJsonFragment(['message' => 'Auto-translate isn\'t set up yet (GOOGLE_TRANSLATE_API_KEY). You can still type each language by hand.']);
        Http::assertNothingSent();
    }

    public function test_validates_languages_and_requires_login(): void
    {
        $this->postJson('/portal/properties/translate', ['source' => 'en', 'targets' => ['ar'], 'fields' => ['title' => 'x']])->assertRedirect(route('portal.login'));
        $this->signIn($this->agency())->postJson('/portal/properties/translate', ['source' => 'en', 'targets' => ['xx'], 'fields' => ['title' => 'x']])
            ->assertJsonValidationErrors('targets.0');
    }

    public function test_form_shows_language_switcher(): void
    {
        $this->signIn($this->agency())->get('/portal/properties/create')->assertOk()
            ->assertSee('id="propertyLangBar"', false)->assertSee('Auto-fill from English');
    }
}
