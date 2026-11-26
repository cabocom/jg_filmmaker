<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProjectCategory;
use App\Livewire\Tv\TvWorkspace;
use App\Models\Project;
use App\Models\TvRundown;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NativeTvConsoleUiAndSsoTest extends TestCase
{
    use RefreshDatabase;

    private const string SSO_SECRET = 'test-iframe-sso-secret-1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.jg_agente.secret', self::SSO_SECRET);
    }

    public function test_tv_console_requires_admin_and_renders_embed_mode_without_header(): void
    {
        $standardUser = User::factory()->create(['is_admin' => false]);
        $adminUser = User::factory()->create(['is_admin' => true]);

        $this->actingAs($standardUser)
            ->get('/tv')
            ->assertForbidden();

        $this->actingAs($adminUser)
            ->get('/tv')
            ->assertOk()
            ->assertSee('<header', false);

        $this->actingAs($adminUser)
            ->get('/tv?embed=1')
            ->assertOk()
            ->assertDontSee('<header', false);
    }

    public function test_tv_workspace_livewire_creates_rundown_and_links_studio_project(): void
    {
        $adminUser = User::factory()->create(['is_admin' => true]);

        $component = Livewire::actingAs($adminUser)
            ->test(TvWorkspace::class)
            ->set('newSourceKind', 'manual')
            ->set('newType', 'pill')
            ->set('newTitle', 'Escaleta Test Estudio')
            ->set('newScriptText', 'Guion de prueba para verificar enlace directo al Estudio.')
            ->call('createRundown')
            ->assertHasNoErrors();

        /** @var TvRundown|null $rundown */
        $rundown = TvRundown::query()->where('title', 'Escaleta Test Estudio')->first();

        $this->assertNotNull($rundown);
        $this->assertNotNull($rundown->project_id);

        /** @var Project|null $project */
        $project = Project::query()->find($rundown->project_id);

        $this->assertNotNull($project);
        $this->assertSame(ProjectCategory::AI_VIDEO, $project->category);
        $this->assertSame('Escaleta Test Estudio', $project->title);

        $component
            ->assertSeeHtml(route('projects.show', $rundown->project_id))
            ->assertSee('Abrir en Estudio');
    }

    public function test_iframe_sso_authenticates_admin_and_blocks_replay_tampering_and_open_redirect(): void
    {
        $adminUser = User::factory()->create([
            'email' => 'admin-sso@example.com',
            'is_admin' => true,
        ]);
        $nonAdminUser = User::factory()->create([
            'email' => 'user-sso@example.com',
            'is_admin' => false,
        ]);

        $exp = (string) (time() + 60);
        $nonce = 'nonce-valid-001';
        $redirect = '/tv?embed=1';

        $validUrl = $this->buildSsoUrl($adminUser->email, $exp, $nonce, $redirect);

        $this->get($validUrl)
            ->assertRedirect($redirect);

        $this->assertAuthenticatedAs($adminUser);

        // Replay del mismo nonce debe ser bloqueado
        $this->post('/logout');
        $this->assertGuest();

        $this->get($validUrl)
            ->assertForbidden();
        $this->assertGuest();

        // Firma alterada debe ser bloqueada
        $tamperedUrl = $this->buildSsoUrl($adminUser->email, $exp, 'nonce-tampered-002', $redirect, 'tampered-signature');
        $this->get($tamperedUrl)
            ->assertForbidden();
        $this->assertGuest();

        // Open redirect externo debe ser bloqueado incluso con firma válida
        $evilUrlHttps = $this->buildSsoUrl($adminUser->email, $exp, 'nonce-evil-003', 'https://evil.com');
        $this->get($evilUrlHttps)
            ->assertForbidden();
        $this->assertGuest();

        $evilUrlProtocolRelative = $this->buildSsoUrl($adminUser->email, $exp, 'nonce-evil-004', '//evil.com');
        $this->get($evilUrlProtocolRelative)
            ->assertForbidden();
        $this->assertGuest();

        // Usuario no administrador debe ser bloqueado incluso con firma válida
        $nonAdminUrl = $this->buildSsoUrl($nonAdminUser->email, $exp, 'nonce-nonadmin-005', $redirect);
        $this->get($nonAdminUrl)
            ->assertForbidden();
        $this->assertGuest();
    }

    private function buildSsoUrl(
        string $email,
        string $exp,
        string $nonce,
        string $redirect,
        ?string $overrideSig = null,
    ): string {
        $sig = $overrideSig ?? hash_hmac('sha256', "{$email}|{$exp}|{$nonce}|{$redirect}", self::SSO_SECRET);

        return '/auth/iframe-sso?'.http_build_query([
            'email' => $email,
            'exp' => $exp,
            'nonce' => $nonce,
            'redirect' => $redirect,
            'sig' => $sig,
        ]);
    }
}
