<?php

namespace Database\Seeders;

use App\Models\Character;
use App\Models\Location;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\Shot;
use App\Models\Take;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Usuario del sistema
        $user = User::firstOrCreate(
            ['email' => 'filmmaker@cabocom.net'],
            [
                'name' => 'Javier Gálvez',
                'password' => Hash::make('secret1234'),
                'plan_tier' => 'pro',
                'paid_project_slots' => 5,
            ]
        );

        // 2. Proyecto Cinematográfico
        $project = Project::firstOrCreate(
            [
                'user_id' => $user->id,
                'title' => 'El Susurro del Viento',
            ],
            [
                'synopsis' => 'Un thriller psicológico ambientado en una ciudad costera donde los secretos emergen en cada plano.',
                'status' => 'in_progress',
            ]
        );

        // 3. Personaje en la Biblia del Proyecto
        $character = Character::firstOrCreate(
            [
                'project_id' => $project->id,
                'name' => 'Javier',
            ],
            [
                'bio' => 'Cineasta taciturno obsesionado con reconstruir un metraje perdido.',
                'psychology' => 'Analítico, hipervigilante, reservado.',
                'costume_notes' => 'Gabardina gris desgastada, camisa oscura.',
                'custom_attributes' => [
                    'archetype' => 'Protagonista',
                    'age' => 38,
                    'internal_conflict' => 'Culpabilidad no resuelta',
                ],
            ]
        );

        // 4. Localización en la Biblia del Proyecto
        $location = Location::firstOrCreate(
            [
                'project_id' => $project->id,
                'name' => 'BAR CENTRAL',
            ],
            [
                'type' => 'INT',
                'art_department_notes' => 'Barra de madera lustrada con marcas de vasos, cartel de neón azul parpadeante.',
                'lighting_notes' => 'Tungsteno puntual cálido sobre la barra contrastado con luz fría de fondo.',
                'address_reference' => 'Calle Mayor, 12',
            ]
        );

        // 5. Escena
        $scene = Scene::firstOrCreate(
            [
                'project_id' => $project->id,
                'scene_order' => 1,
            ],
            [
                'slugline' => 'INT. BAR CENTRAL - NOCHE 1',
                'location_id' => $location->id,
                'time_of_day' => 'NOCHE',
            ]
        );

        // 6. Bloque de Guión con User Locks
        $scriptBlock = ScriptBlock::firstOrCreate(
            [
                'scene_id' => $scene->id,
                'block_order' => 1,
            ],
            [
                'raw_text' => 'Javier está sentado solo en el bar cuando escucha un sonido seco que lo pone en alerta.',
                'structured_text' => 'Javier apoya el vaso en la barra. El tintineo de la campana de la entrada rompe el silencio.',
                'screenplay_content' => "INT. BAR CENTRAL - NOCHE 1\n\nJAVIER apoya el vaso en la barra de madera. La campana de la puerta tintinea.\n\nJAVIER\n(en voz baja)\nYa están aquí.",
                'user_locks' => [
                    'locked_fields' => ['dialogue_speaker', 'shot_breakdown'],
                    'manual_diffs' => [
                        [
                            'stage' => 'structured',
                            'original_ai' => 'Javier escucha ruido.',
                            'user_override' => 'Javier apoya el vaso en la barra.',
                            'applied_at' => '2026-10-09T18:30:00Z',
                        ],
                    ],
                    'pinned_terms' => [
                        'Javi' => 'Javier',
                    ],
                ],
                'stage' => 'screenplay',
            ]
        );

        // 7. Plano Técnico
        $shot = Shot::firstOrCreate(
            [
                'scene_id' => $scene->id,
                'shot_number' => 1,
            ],
            [
                'script_block_id' => $scriptBlock->id,
                'shot_type' => 'PM',
                'description' => 'Plano medio de Javier apoyando el vaso y reaccionando al tintineo de la campana.',
            ]
        );

        // 8. Tomas de Rodaje con Technical Meta
        Take::firstOrCreate(
            [
                'shot_id' => $shot->id,
                'take_number' => 1,
            ],
            [
                'file_path' => "media/projects/{$project->id}/scenes/{$scene->id}/takes/take-01.mp4",
                'is_good_take' => true,
                'editorial_notes' => 'Toma aprobada para corte definitivo. Excelente intensidad en la mirada.',
                'technical_meta' => [
                    'duration_seconds' => 8.4,
                    'resolution' => '3840x2160',
                    'codec' => 'prores422hq',
                    'fps' => 24.0,
                    'timecode_in' => '01:02:14:00',
                    'timecode_out' => '01:02:22:10',
                ],
            ]
        );

        Take::firstOrCreate(
            [
                'shot_id' => $shot->id,
                'take_number' => 2,
            ],
            [
                'file_path' => "media/projects/{$project->id}/scenes/{$scene->id}/takes/take-02.mp4",
                'is_good_take' => false,
                'editorial_notes' => 'Descarte: micro de pértiga asoma en margen superior.',
                'technical_meta' => [
                    'duration_seconds' => 7.1,
                    'resolution' => '3840x2160',
                    'codec' => 'prores422hq',
                    'fps' => 24.0,
                    'timecode_in' => '01:03:00:00',
                    'timecode_out' => '01:03:07:02',
                ],
            ]
        );
    }
}
