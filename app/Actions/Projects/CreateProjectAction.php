<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\DTOs\Projects\CreateProjectDTO;
use App\Enums\BlockStage;
use App\Enums\ProjectCategory;
use App\Models\Project;
use App\Models\Scene;
use App\Models\ScriptBlock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class CreateProjectAction
{
    /**
     * Create a new project with optional quota enforcement and initial scene/block.
     */
    public function execute(User $owner, CreateProjectDTO $dto, bool $enforcePersonalQuota = false): Project
    {
        if ($enforcePersonalQuota && $dto->category === ProjectCategory::PERSONAL) {
            $personalCount = $owner->projects()
                ->where('category', ProjectCategory::PERSONAL->value)
                ->count();
            $limit = $owner->paid_project_slots ?? 5;

            if ($personalCount >= $limit) {
                throw new RuntimeException('Personal project quota exceeded.');
            }
        }

        return DB::transaction(function () use ($owner, $dto): Project {
            $project = Project::create([
                'user_id' => $owner->id,
                'title' => $dto->title,
                'synopsis' => $dto->synopsis,
                'status' => $dto->status,
                'category' => $dto->category->value,
                'storyboard_style' => $dto->storyboardStyle,
            ]);

            if ($dto->rawText !== null && trim($dto->rawText) !== '') {
                $slugline = ($dto->slugline !== null && trim($dto->slugline) !== '')
                    ? trim($dto->slugline)
                    : 'INT. ESTUDIO BROADCAST - DÍA';

                $scene = Scene::create([
                    'project_id' => $project->id,
                    'scene_order' => 1,
                    'slugline' => $slugline,
                    'time_of_day' => 'DÍA',
                ]);

                ScriptBlock::create([
                    'scene_id' => $scene->id,
                    'block_order' => 1,
                    'stage' => BlockStage::RAW->value,
                    'raw_text' => trim($dto->rawText),
                ]);
            }

            return $project;
        });
    }

    /**
     * Resolve an explicit owner by ID, an admin user, or the broadcast system user.
     */
    public function resolveSystemOwner(?string $userId = null): User
    {
        if ($userId !== null && $userId !== '') {
            return User::query()->findOrFail($userId);
        }

        /** @var User|null $admin */
        $admin = User::query()->where('is_admin', true)->first();
        if ($admin !== null) {
            return $admin;
        }

        return User::firstOrCreate(
            ['email' => 'broadcast-system@jg-filmmaker.local'],
            [
                'name' => 'Broadcast System',
                'password' => bcrypt('broadcast_secret_'.bin2hex(random_bytes(16))),
                'is_admin' => true,
                'plan_tier' => 'pro',
            ]
        );
    }
}
