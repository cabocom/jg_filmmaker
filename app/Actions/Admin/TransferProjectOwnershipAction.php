<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\AdminAuditLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class TransferProjectOwnershipAction
{
    public function execute(string $projectId, string $targetUserId, ?string $adminId = null, ?string $ipAddress = null): Project
    {
        $project = Project::findOrFail($projectId);
        $targetUser = User::findOrFail($targetUserId);

        if ($project->user_id === $targetUser->id) {
            throw new InvalidArgumentException('El usuario seleccionado ya es el titular de este proyecto.');
        }

        $previousOwnerId = $project->user_id;

        DB::transaction(function () use ($project, $targetUser, $previousOwnerId, $adminId, $ipAddress) {
            $project->user_id = $targetUser->id;
            $project->save();

            AdminAuditLog::create([
                'user_id' => $adminId ?? auth()->id(),
                'action' => 'transfer_project',
                'target_type' => Project::class,
                'target_id' => $project->id,
                'details' => [
                    'project_title' => $project->title,
                    'previous_owner_id' => $previousOwnerId,
                    'new_owner_id' => $targetUser->id,
                    'new_owner_email' => $targetUser->email,
                ],
                'ip_address' => $ipAddress ?? request()?->ip(),
            ]);
        });

        return $project;
    }
}
