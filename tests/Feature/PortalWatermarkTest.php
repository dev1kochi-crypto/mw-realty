<?php

namespace Tests\Feature;

use App\Models\PortalUser;
use App\Services\Watermark;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** Listing Settings → Watermark (CRM API): saving settings, agency agents read-only, stamping photos. */
class PortalWatermarkTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    private const URL = '/api/crm/listing-settings/watermark';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** The CRM app's Bearer token for this account. */
    private function api(PortalUser $user): static
    {
        return $this->withToken($user->createToken('test')->plainTextToken);
    }

    public function test_agency_saves_image_watermark_and_agents_see_it_read_only(): void
    {
        $agency = $this->agency();
        $this->api($agency)->getJson(self::URL)->assertOk()->assertJsonPath('read_only', false);

        $this->post(self::URL, [
            'enabled' => 1, 'type' => 'image', 'image' => UploadedFile::fake()->image('logo.png', 400, 120),
            'opacity' => 40, 'size' => 30, 'position' => 'br',
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('settings.has_image', true);

        $settings = $agency->fresh()->watermark;
        $this->assertTrue($settings['enabled']);
        $this->assertSame('br', $settings['position']);
        Storage::disk('local')->assertExists($settings['image']);
        $this->get(self::URL . '/image')->assertOk();

        // The agency's agents get the agency's watermark, and can't change it.
        $agent = $this->memberAgent($agency);
        $this->api($agent)->getJson(self::URL)->assertOk()->assertJsonPath('read_only', true);
        $this->postJson(self::URL, ['type' => 'text', 'text' => 'x', 'opacity' => 50, 'size' => 20, 'position' => 'mc'])->assertForbidden();
        $this->assertSame($agency->id, app(Watermark::class)->ownerFor($agent->fresh())->id);
    }

    public function test_locked_until_kyc_is_approved(): void
    {
        $agency = $this->agency(['status' => 'pending', 'kyc_review_status' => 'draft']);
        $this->api($agency)->getJson(self::URL)->assertForbidden();
        $this->postJson(self::URL, ['enabled' => 1, 'type' => 'text', 'text' => 'MW', 'opacity' => 40, 'size' => 30, 'position' => 'mc'])
            ->assertForbidden();
        $this->assertNull($agency->fresh()->watermark);
    }

    public function test_enabled_watermark_needs_an_image_or_text(): void
    {
        $agent = $this->independentAgent();
        $this->api($agent)->postJson(self::URL, ['enabled' => 1, 'type' => 'image', 'opacity' => 40, 'size' => 30, 'position' => 'mc'])
            ->assertJsonValidationErrors('image');
        $this->postJson(self::URL, ['enabled' => 1, 'type' => 'text', 'text' => ' ', 'opacity' => 40, 'size' => 30, 'position' => 'mc'])
            ->assertJsonValidationErrors('text');
        $this->assertNull($agent->fresh()->watermark);
    }

    public function test_active_settings_only_when_enabled_and_complete(): void
    {
        $wm = app(Watermark::class);
        $agent = $this->independentAgent(['watermark' => ['enabled' => false, 'type' => 'text', 'text' => 'MW']]);
        $this->assertNull($wm->activeSettings($agent));

        $agent->forceFill(['watermark' => ['enabled' => true, 'type' => 'text', 'text' => 'MW']])->save();
        $this->assertSame('MW', $wm->activeSettings($agent->fresh())['text']);

        $agent->forceFill(['watermark' => ['enabled' => true, 'type' => 'image', 'image' => 'watermarks/missing.png']])->save();
        $this->assertNull($wm->activeSettings($agent->fresh()));
    }

    public function test_text_watermark_is_stamped_at_the_chosen_corner(): void
    {
        $photo = imagecreatetruecolor(1000, 600);
        imagefill($photo, 0, 0, imagecolorallocate($photo, 0, 0, 0));

        app(Watermark::class)->apply($photo, array_merge(Watermark::DEFAULTS, [
            'enabled' => true, 'type' => 'text', 'text' => 'MW REALTY', 'color' => '#ffffff', 'opacity' => 100, 'size' => 40, 'position' => 'br',
        ]));

        $brightIn = function (int $x0, int $y0, int $x1, int $y1) use ($photo): int {
            $n = 0;
            for ($x = $x0; $x < $x1; $x += 2) {
                for ($y = $y0; $y < $y1; $y += 2) {
                    if ((imagecolorat($photo, $x, $y) & 0xFF) > 200) {
                        $n++;
                    }
                }
            }
            return $n;
        };

        $this->assertGreaterThan(100, $brightIn(560, 450, 1000, 600), 'text in the bottom-right corner');
        $this->assertSame(0, $brightIn(0, 0, 500, 300), 'nothing in the top-left');
    }
}
