<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProjectCategory;
use App\Livewire\Tv\TvWorkspace;
use App\Models\TvRundown;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class NativeTvConsoleUiAndSsoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.jg_agente.secret', 'test-iframe-sso-secret-1234567890');
    }

    public function test_tv_workspace_renders_and_hides_header_in_embed_mode(): void
    {
        $this->get(route('tv.index'))
            ->assertRedirect(route('login'));

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('tv.index'))
            ->assertOk()
            ->assertSee('Consola de Televisión IA')
            ->assertSee('Mis Proyectos');

        $this->actingAs($user)
            ->get(route('tv.index', ['embed' => 1]))
            ->assertOk()
            ->assertSee('Consola de Televisión IA')
            ->assertDontSee('Mis Proyectos');
    }

    public function test_tv_workspace_creates_rundown_linked_to_ai_video_project(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(TvWorkspace::class)
            ->set('newSourceKind', 'manual')
            ->set('newTitle', 'Noticiero IA Nocturno')
            ->set('newScriptText', 'Buenas noches, comenzamos el informativo de inteligencia artificial.')
            ->call('createRundown')
            ->assertHasNoErrors();

        $rundown = TvRundown::query()->where('title', 'Noticiero IA Nocturno')->first();

        $this->assertNotNull($rundown);
        $this->assertNotNull($rundown->project);
        $this->assertSame(ProjectCategory::AI_VIDEO, $rundown->project->categoryEnum());

        $component->assertSee(route('projects.show', $rundown->project_id), false);
    }

    public function test_iframe_sso_authenticates_admin_and_blocks_replay_tampering_and_open_redirect(): void
    {
        $secret = 'test-iframe-sso-secret-1234567890';
        $admin = User::factory()->create(['is_admin' => true]);

        $exp = (string) (time() + 60);
        $nonce = 'nonce-valid-1';
        $redirect = '/tv?embed=1';
        $sig = hash_hmac('sha256', "{$admin->email}|{$exp}|{$nonce}|{$redirect}", $secret);

        $validUrl = route('auth.iframe-sso', [
            'email' => $admin->email,
            'exp' => $exp,
            'nonce' => $nonce,
            'redirect' => $redirect,
            'sig' => $sig,
        ]);

        $this->get($validUrl)
            ->assertRedirect($redirect);

        $this->assertAuthenticatedAs($admin);

        Auth::logout();

        // Replay of same nonce must be blocked
        $this->get($validUrl)
            ->assertForbidden();

        // Tampered signature must be blocked
        $tamperedUrl = route('auth.iframe-sso', [
            'email' => $admin->email,
            'exp' => $exp,
            'nonce' => 'nonce-tampered-2',
            'redirect' => $redirect,
            'sig' => str_repeat('0', 64),
        ]);

        $this->get($tamperedUrl)
            ->assertForbidden();

        // External open redirect must be blocked even with valid HMAC
        $evilRedirect = 'https://evil.com/phishing';
        $evilNonce = 'nonce-evil-3';
        $evilSig = hash_hmac('sha256', "{$admin->email}|{$exp}|{$evilNonce}|{$evilRedirect}", $secret);

        $this->get(route('auth.iframe-sso', [
            'email' => $admin->email,
            'exp' => $exp,
            'nonce' => $evilNonce,
            'redirect' => $evilRedirect,
            'sig' => $evilSig,
        ]))->assertForbidden();
    }
}
