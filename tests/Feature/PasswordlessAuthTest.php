<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Mail\LoginOtpMail;
use App\Models\OneTimePassword;
use App\Models\SecurityBreachLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordlessAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_login_page_as_guest(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('jG Filmmaker');
        $response->assertSee('Passwordless OTP');
    }

    public function test_generates_and_sends_otp_email_on_request(): void
    {
        Mail::fake();

        Livewire::test(Login::class)
            ->set('email', 'guionista@cine.local')
            ->call('requestOtp')
            ->assertHasNoErrors()
            ->assertSet('step', 'verify');

        Mail::assertSent(LoginOtpMail::class, function ($mail) {
            return $mail->hasTo('guionista@cine.local') && ! empty($mail->code);
        });

        $user = User::where('email', 'guionista@cine.local')->first();
        $this->assertNotNull($user);

        $otp = OneTimePassword::where('user_id', $user->id)->first();
        $this->assertNotNull($otp);
        $this->assertNull($otp->used_at);
        $this->assertTrue($otp->expires_at->isFuture());
    }

    public function test_local_environment_exposes_dev_code_in_interface(): void
    {
        Mail::fake();

        // En testing con devOtpCode activo en sesión o local
        $component = Livewire::test(Login::class);
        $component->isLocalEnv = true;

        $component->set('email', 'director@cine.local')
            ->call('requestOtp')
            ->assertSet('step', 'verify');

        $this->assertNotNull($component->get('devOtpCode'));

        $component->call('fillDevCode');
        $this->assertEquals($component->get('devOtpCode'), $component->get('verification_code'));
    }

    public function test_successful_otp_authentication_and_single_use(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'actriz@cine.local',
        ]);

        $code = 'ABCD-EFGH';
        $otp = OneTimePassword::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(15),
            'used_at' => null,
        ]);

        Livewire::test(Login::class)
            ->set('email', 'actriz@cine.local')
            ->set('step', 'verify')
            ->set('verification_code', 'ABCD-EFGH')
            ->call('verifyOtp')
            ->assertHasNoErrors()
            ->assertRedirect(route('projects.index'));

        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id, Auth::id());

        $otp->refresh();
        $this->assertNotNull($otp->used_at);
    }

    public function test_detects_replay_attack_and_logs_to_security_breach_logs(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'productor@cine.local',
        ]);

        $code = '1234-WXYZ';
        // OTP ya consumido previamente (used_at not null)
        OneTimePassword::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
            'used_at' => now()->subMinute(),
        ]);

        $this->assertEquals(0, SecurityBreachLog::count());

        Livewire::test(Login::class)
            ->set('email', 'productor@cine.local')
            ->set('step', 'verify')
            ->set('verification_code', '1234-WXYZ')
            ->call('verifyOtp')
            ->assertHasErrors(['verification_code']);

        $this->assertFalse(Auth::check());

        // Comprobar que se ha registrado la brecha en security_breach_logs
        $this->assertEquals(1, SecurityBreachLog::count());
        $breach = SecurityBreachLog::first();
        $this->assertEquals($user->id, $breach->user_id);
        $this->assertEquals('REUSED_OR_EXPIRED_TOKEN', $breach->reason);
    }

    public function test_detects_expired_token_as_security_breach(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'camara@cine.local',
        ]);

        $code = 'EXPI-RE01';
        // OTP caducado
        OneTimePassword::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->subMinutes(5),
            'used_at' => null,
        ]);

        Livewire::test(Login::class)
            ->set('email', 'camara@cine.local')
            ->set('step', 'verify')
            ->set('verification_code', 'EXPI-RE01')
            ->call('verifyOtp')
            ->assertHasErrors(['verification_code']);

        $this->assertFalse(Auth::check());
        $this->assertDatabaseHas('security_breach_logs', [
            'user_id' => $user->id,
            'reason' => 'REUSED_OR_EXPIRED_TOKEN',
        ]);
    }
}
