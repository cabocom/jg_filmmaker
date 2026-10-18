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
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
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
        app()->detectEnvironment(fn () => 'local');
        config(['app.debug' => true]);

        $component = Livewire::test(Login::class);

        $component->set('email', 'director@cine.local')
            ->call('requestOtp')
            ->assertSet('step', 'verify');

        $this->assertNotNull($component->get('devOtpCode'));

        $component->call('fillDevCode');
        $this->assertEquals($component->get('devOtpCode'), $component->get('verification_code'));
    }

    public function test_production_environment_never_exposes_dev_code_to_client(): void
    {
        Mail::fake();
        app()->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);

        $component = Livewire::test(Login::class);

        $component->set('email', 'director@cine.local')
            ->call('requestOtp')
            ->assertSet('step', 'verify');

        $this->assertNull($component->get('devOtpCode'));
        $component->assertDontSee('MODO DESARROLLO LOCAL');
    }

    public function test_locked_properties_cannot_be_mutated_by_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(Login::class)
            ->set('devOtpCode', 'PWND-1337');
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
            ->set('verification_code', 'EXPI-RE01')
            ->call('verifyOtp')
            ->assertHasErrors(['verification_code']);

        $this->assertFalse(Auth::check());
        $this->assertDatabaseHas('security_breach_logs', [
            'user_id' => $user->id,
            'reason' => 'REUSED_OR_EXPIRED_TOKEN',
        ]);
    }

    public function test_request_otp_invalidates_previous_active_otps(): void
    {
        Mail::fake();

        $email = 'doble_otp@cine.local';
        $component = Livewire::test(Login::class)->set('email', $email);

        // Primer OTP
        $component->call('requestOtp');
        $user = User::where('email', $email)->first();
        $firstOtp = OneTimePassword::where('user_id', $user->id)->first();
        $this->assertNull($firstOtp->used_at);

        // Segundo OTP (debe revocar el primero)
        $component->call('changeEmail')->set('email', $email)->call('requestOtp');
        $firstOtp->refresh();
        $this->assertNotNull($firstOtp->used_at);

        $activeOtps = OneTimePassword::where('user_id', $user->id)->whereNull('used_at')->count();
        $this->assertSame(1, $activeOtps);
    }

    public function test_request_otp_throttled_per_destination_email_against_mail_bombing(): void
    {
        Mail::fake();
        $email = 'bombing_victim@cine.local';

        $component = Livewire::test(Login::class)->set('email', $email);

        // Intentos 1, 2, 3 pasan
        $component->call('requestOtp')->assertHasNoErrors();
        $component->call('changeEmail')->set('email', $email)->call('requestOtp')->assertHasNoErrors();
        $component->call('changeEmail')->set('email', $email)->call('requestOtp')->assertHasNoErrors();

        // Intento 4 queda bloqueado por rate limit de email
        $component->call('changeEmail')->set('email', $email)->call('requestOtp')
            ->assertHasErrors(['email']);
    }

    public function test_verify_otp_invalidates_and_logs_breach_after_five_failed_attempts(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'bruteforce@cine.local']);

        $canonical = 'SECR1234';
        $hash = hash_hmac('sha256', $canonical, (string) config('app.key'));
        $otp = OneTimePassword::create([
            'user_id' => $user->id,
            'code_hash' => $hash,
            'expires_at' => now()->addMinutes(15),
            'used_at' => null,
            'attempts' => 0,
        ]);

        $component = Livewire::test(Login::class)
            ->set('email', 'bruteforce@cine.local');

        // Realizar 5 intentos erróneos
        for ($i = 1; $i <= 5; $i++) {
            $component->set('verification_code', 'WRON-000'.$i)->call('verifyOtp');
        }

        $otp->refresh();
        $this->assertNotNull($otp->used_at);
        $this->assertDatabaseHas('security_breach_logs', [
            'user_id' => $user->id,
            'reason' => 'OTP_MAX_ATTEMPTS_EXCEEDED',
        ]);
    }

    public function test_prunable_query_identifies_expired_otps(): void
    {
        $user = User::factory()->create();

        $oldOtp = OneTimePassword::create([
            'user_id' => $user->id,
            'code_hash' => 'hash1',
            'expires_at' => now()->subDays(2),
        ]);
        $oldOtp->forceFill(['created_at' => now()->subDays(2)])->save();

        $recentOtp = OneTimePassword::create([
            'user_id' => $user->id,
            'code_hash' => 'hash2',
            'expires_at' => now()->addMinutes(15),
        ]);

        $prunableIds = (new OneTimePassword)->prunable()->pluck('id')->toArray();

        $this->assertContains($oldOtp->id, $prunableIds);
        $this->assertNotContains($recentOtp->id, $prunableIds);
    }
}
