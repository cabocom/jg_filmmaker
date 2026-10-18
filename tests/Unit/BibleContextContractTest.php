<?php

namespace Tests\Unit;

use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BibleContextContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_character_to_agent_context_includes_canonical_attributes(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $character = Character::create([
            'project_id' => $project->id,
            'name' => 'Sara Connor',
            'bio' => 'Guerrera y madre protectora.',
            'psychology' => 'Alerta constante, hipervigilante.',
            'costume_notes' => 'Camiseta militar y botas tácticas.',
            'custom_attributes' => ['arc' => 'redención'],
        ]);

        $context = $character->toAgentContext();

        $this->assertSame('Sara Connor', $context['name']);
        $this->assertSame('Guerrera y madre protectora.', $context['bio']);
        $this->assertSame('Alerta constante, hipervigilante.', $context['psychology']);
        $this->assertSame('Camiseta militar y botas tácticas.', $context['costume_notes']);
        $this->assertSame(['arc' => 'redención'], $context['custom_attributes']);
    }

    public function test_location_to_agent_context_includes_canonical_attributes(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $location = Location::create([
            'project_id' => $project->id,
            'name' => 'Búnker Subterráneo',
            'type' => 'INT',
            'art_department_notes' => 'Paredes de hormigón desgastado con cables colgando.',
            'lighting_notes' => 'Tubos fluorescentes parpadeantes en tonos fríos.',
            'address_reference' => 'Nivel -2, Sector Norte',
        ]);

        $context = $location->toAgentContext();

        $this->assertSame('Búnker Subterráneo', $context['name']);
        $this->assertSame('INT', $context['type']);
        $this->assertSame('Paredes de hormigón desgastado con cables colgando.', $context['art_department_notes']);
        $this->assertSame('Tubos fluorescentes parpadeantes en tonos fríos.', $context['lighting_notes']);
        $this->assertSame('Nivel -2, Sector Norte', $context['address_reference']);
    }

    public function test_accessing_missing_attribute_on_character_throws_exception_in_non_production(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $created = Character::create([
            'project_id' => $project->id,
            'name' => 'John',
            'bio' => 'Líder de la resistencia.',
        ]);

        $this->expectException(MissingAttributeException::class);

        // Cuando preventAccessingMissingAttributes está activo, acceder a un atributo no seleccionado arroja MissingAttributeException
        $partial = Character::select(['id', 'name'])->find($created->id);
        $missing = $partial->bio;
    }
}
