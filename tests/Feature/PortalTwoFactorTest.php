<?php

namespace Tests\Feature;

use App\Mail\NewDeviceLoginCodeMail;
use App\Models\PortalUser;
use App\Services\TwoFactor\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\BuildsAgencies;
use Tests\TestCase;

/** Portal login: new-device email code, authenticator-app (TOTP) 2FA, recovery codes, company enforcement. */
class PortalTwoFactorTest extends TestCase
{
    use RefreshDatabase, BuildsAgencies;

    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.otp.enabled' => true]);
        Mail::fake();
    }

    public function test_totp_matches_rfc_6238_vectors(): void
    {
        // RFC 6238 appendix B, SHA-1 seed "12345678901234567890" (last 6 of the 8-digit values).
        $codeAt = new \ReflectionMethod(Totp::class, 'codeAt');
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $this->assertSame('287082', $codeAt->invoke(new Totp(), $secret, intdiv(59, 30)));
        $this->assertSame('081804', $codeAt->invoke(new Totp(), $secret, intdiv(1111111109, 30)));
        $this->assertSame('005924', $codeAt->invoke(new Totp(), $secret, intdiv(1234567890, 30)));
    }

    public function test_without_two_factor_the_password_alone_signs_in(): void
    {
        $agent = $this->independentAgent();

        $this->postJson('/portal/login', ['email' => $agent->email, 'password' => 'safe-password-123'])
            ->assertOk()->assertJsonMissingPath('challenge')->assertJsonPath('redirect', route('portal.dashboard'));
        $this->assertAuthenticatedAs($agent, 'portal');
        Mail::assertNothingSent();
    }

    public function test_new_device_needs_email_code_then_is_remembered(): void
    {
        [$agent, $secret] = $this->agentWithTwoFactor();

        $this->postJson('/portal/login', ['email' => $agent->email, 'password' => 'safe-password-123'])
            ->assertOk()->assertJson(['challenge' => 'email']);
        $this->assertGuest('portal');

        $this->postJson('/portal/login/verify-email', ['code' => '000000'])->assertStatus(422);
        $this->postJson('/portal/login/verify-email', ['code' => $this->sentLoginCode()])->assertJson(['challenge' => 'totp']);
        $response = $this->postJson('/portal/login/two-factor', ['code' => $this->currentCode($secret)])
            ->assertOk()->assertJsonPath('redirect', route('portal.dashboard'));
        $this->assertAuthenticatedAs($agent, 'portal');

        // Same browser next time: no email step, straight to the app code.
        $token = $response->getCookie('mw_portal_device_' . $agent->id)->getValue();
        $this->post('/portal/logout');
        $this->withCredentials()->withCookie('mw_portal_device_' . $agent->id, $token)
            ->postJson('/portal/login', ['email' => $agent->email, 'password' => 'safe-password-123'])
            ->assertOk()->assertJson(['challenge' => 'totp']);
    }

    public function test_two_factor_login_needs_email_then_app_code_and_blocks_replay(): void
    {
        [$agent, $secret] = $this->agentWithTwoFactor();

        $this->postJson('/portal/login', ['email' => $agent->email, 'password' => 'safe-password-123'])
            ->assertJson(['challenge' => 'email']);
        // Can't skip the email step.
        $this->postJson('/portal/login/two-factor', ['code' => $this->currentCode($secret)])->assertStatus(422)->assertJson(['restart' => true]);

        $this->postJson('/portal/login', ['email' => $agent->email, 'password' => 'safe-password-123']);
        $this->postJson('/portal/login/verify-email', ['code' => $this->sentLoginCode()])->assertJson(['challenge' => 'totp']);
        $this->assertGuest('portal');

        $this->postJson('/portal/login/two-factor', ['code' => '123456'])->assertStatus(422);
        $code = $this->currentCode($secret);
        $this->postJson('/portal/login/two-factor', ['code' => $code])->assertOk()->assertJsonPath('redirect', route('portal.dashboard'));
        $this->assertAuthenticatedAs($agent, 'portal');

        // The same code can't be used for a second login.
        $this->post('/portal/logout');
        $this->assertFalse($this->totpAccepts($agent->fresh(), $code));
    }

    public function test_recovery_code_works_once(): void
    {
        [$agent] = $this->agentWithTwoFactor();
        $recovery = $agent->two_factor_recovery_codes[0];

        $this->passEmailStep($agent);
        $this->postJson('/portal/login/two-factor', ['code' => strtolower($recovery)])->assertOk();
        $this->assertCount(7, $agent->fresh()->two_factor_recovery_codes);

        $this->post('/portal/logout');
        $this->passEmailStep($agent);
        $this->postJson('/portal/login/two-factor', ['code' => $recovery])->assertStatus(422);
    }

    public function test_too_many_wrong_codes_restarts_the_login(): void
    {
        [$agent] = $this->agentWithTwoFactor();
        $this->postJson('/portal/login', ['email' => $agent->email, 'password' => 'safe-password-123']);

        foreach (range(1, 4) as $_) {
            $this->postJson('/portal/login/verify-email', ['code' => '000000'])->assertStatus(422)->assertJsonMissingPath('restart');
        }
        $this->postJson('/portal/login/verify-email', ['code' => '000000'])->assertJson(['restart' => true]);
        $this->postJson('/portal/login/verify-email', ['code' => $this->sentLoginCode()])->assertJson(['restart' => true]);
        $this->assertGuest('portal');
    }

    public function test_setup_confirms_secret_and_shows_recovery_codes(): void
    {
        $agent = $this->independentAgent();
        $this->signIn($agent)->get('/portal/two-factor/setup')->assertOk()->assertSee('<svg', false);
        $secret = session('portal_2fa_pending_secret');

        $this->post('/portal/two-factor/confirm', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($agent->fresh()->hasTwoFactorEnabled());

        $this->post('/portal/two-factor/confirm', ['code' => $this->currentCode($secret)])
            ->assertRedirect()->assertSessionHas('two_factor_recovery_codes');
        $this->assertTrue($agent->fresh()->hasTwoFactorEnabled());
        $this->assertSame($secret, $agent->fresh()->two_factor_secret);
    }

    public function test_company_enforcement_forces_agents_to_set_up_and_blocks_disable(): void
    {
        [$agency] = $this->agentWithTwoFactor($this->agency());
        $agent = $this->memberAgent($agency);

        // Company must have it on itself before enforcing.
        $agency->forceFill(['two_factor_enforced' => true])->save();
        $this->assertTrue($agent->fresh()->twoFactorRequired());

        $this->signIn($agent->fresh())->get('/portal/dashboard')->assertRedirect(route('portal.two-factor.setup'));
        $this->get('/portal/two-factor/setup')->assertOk()->assertSee('Your company requires two-factor authentication');

        // Agency itself can't switch its own 2FA off while enforcing.
        $this->signIn($agency)->post('/portal/two-factor/disable', ['password' => 'safe-password-123']);
        $this->assertTrue($agency->fresh()->hasTwoFactorEnabled());
    }

    public function test_enforce_requires_own_two_factor_and_disable_requires_password(): void
    {
        // Company without its own 2FA: sent to set-up, and enforcement turns on once it's confirmed.
        $agency = $this->agency();
        $this->signIn($agency)->post('/portal/two-factor/enforce', ['enforce' => 1])->assertRedirect(route('portal.two-factor.setup'));
        $this->assertFalse($agency->fresh()->two_factor_enforced);
        $this->get('/portal/two-factor/setup')->assertSee('turns on automatically');
        $this->post('/portal/two-factor/confirm', ['code' => $this->currentCode(session('portal_2fa_pending_secret'))]);
        $this->assertTrue($agency->fresh()->two_factor_enforced);
        $this->assertTrue($agency->fresh()->hasTwoFactorEnabled());

        [$agent] = $this->agentWithTwoFactor();
        $this->signIn($agent)->post('/portal/two-factor/disable', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertTrue($agent->fresh()->hasTwoFactorEnabled());
        $this->post('/portal/two-factor/disable', ['password' => 'safe-password-123']);
        $this->assertFalse($agent->fresh()->hasTwoFactorEnabled());
    }

    public function test_new_registration_must_set_up_or_skip_before_using_the_portal(): void
    {
        $agent = $this->independentAgent(['otp_code' => \Illuminate\Support\Facades\Hash::make('4321'), 'otp_expires_at' => now()->addMinutes(5)]);

        $this->postJson('/portal/verify-otp', ['user_id' => $agent->id, 'code' => '4321'])
            ->assertOk()->assertJsonPath('redirect', route('portal.two-factor.setup', ['onboarding' => 1]));

        // Other menus bounce back to the 2FA step…
        $this->get('/portal/dashboard')->assertRedirect(route('portal.two-factor.setup', ['onboarding' => 1]));
        $this->get('/portal/security')->assertRedirect(route('portal.two-factor.setup', ['onboarding' => 1]));
        $this->get('/portal/two-factor/setup')->assertOk()->assertSee('Skip for now');

        // …until they skip it.
        $this->post('/portal/two-factor/skip')->assertRedirect(route('portal.dashboard'));
        $this->get('/portal/dashboard')->assertOk();
        $this->get('/portal/security')->assertOk();
    }

    public function test_security_page_renders_and_is_in_account_menu(): void
    {
        [$agency] = $this->agentWithTwoFactor($this->agency());
        $this->signIn($agency)->get('/portal/security')->assertOk()
            ->assertSee('2-Factor Authentication (My Company)')->assertSee('Configured')->assertSee('Reconfigure')->assertSee('Disable 2FA');

        $this->signIn($this->independentAgent())->get('/portal/dashboard')->assertOk()->assertSee(route('portal.security'));
        $this->get('/portal/security')->assertOk()->assertDontSee('2-Factor Authentication (My Company)')->assertSee('Set up');
    }

    public function test_reconfigure_keeps_old_app_until_new_one_is_verified(): void
    {
        [$agent, $oldSecret] = $this->agentWithTwoFactor();
        $this->signIn($agent)->post('/portal/two-factor/reconfigure', ['password' => 'safe-password-123'])
            ->assertRedirect(route('portal.two-factor.setup'));
        $this->get('/portal/two-factor/setup')->assertOk()->assertSee('Move 2FA to a new phone');
        $newSecret = session('portal_2fa_pending_secret');
        $this->assertNotSame($oldSecret, $newSecret);

        // Abandoned half-way: the old app still works.
        $this->assertSame($oldSecret, $agent->fresh()->two_factor_secret);
        $this->assertTrue($agent->fresh()->hasTwoFactorEnabled());

        $this->post('/portal/two-factor/confirm', ['code' => $this->currentCode($newSecret)])->assertSessionHas('two_factor_recovery_codes');
        $this->assertSame($newSecret, $agent->fresh()->two_factor_secret);
        $this->get('/portal/two-factor/setup')->assertOk();   // shows the recovery codes once
        $this->get('/portal/two-factor/setup')->assertRedirect(route('portal.security'));
    }

    /** @return array{0: PortalUser, 1: string} */
    private function agentWithTwoFactor(?PortalUser $user = null): array
    {
        $user ??= $this->independentAgent();
        $secret = (new Totp())->generateSecret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => ['AAAAA-BBBBB', 'CCCCC-DDDDD', 'EEEEE-FFFFF', 'GGGGG-HHHHH', 'IIIII-JJJJJ', 'KKKKK-LLLLL', 'MMMMM-NNNNN', 'OOOOO-PPPPP'],
            'two_factor_confirmed_at' => now(),
        ])->save();

        return [$user->fresh(), $secret];
    }

    private function passEmailStep(PortalUser $user): void
    {
        $this->postJson('/portal/login', ['email' => $user->email, 'password' => 'safe-password-123'])->assertJson(['challenge' => 'email']);
        $this->postJson('/portal/login/verify-email', ['code' => $this->sentLoginCode()])->assertJson(['challenge' => 'totp']);
    }

    private function sentLoginCode(): string
    {
        $code = null;
        Mail::assertSent(NewDeviceLoginCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;
            return true;
        });

        return $code;
    }

    private function currentCode(string $secret): string
    {
        return (new \ReflectionMethod(Totp::class, 'codeAt'))->invoke(new Totp(), $secret, intdiv(time(), 30));
    }

    private function totpAccepts(PortalUser $user, string $code): bool
    {
        return (new Totp())->verify($user->two_factor_secret, $code, $user->two_factor_last_step) !== null;
    }
}
