<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use App\Models\ScriptBlock;

class UserLockManager
{
    public function resolveUserLocks(ScriptBlock $block): array
    {
        $locks = $block->user_locks;
        if (is_array($locks)) {
            return $locks;
        }

        if (is_string($locks)) {
            $decoded = json_decode($locks, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            if (is_string($decoded)) {
                $second = json_decode($decoded, true);
                if (is_array($second)) {
                    return $second;
                }
            }
        }

        return [];
    }

    public function recordManualOverride(ScriptBlock $block, string $stage, string $originalText, string $overrideText): ScriptBlock
    {
        if ($originalText === $overrideText) {
            return $block;
        }

        return $block->mutateUserLocks(function (array $userLocks) use ($stage, $originalText, $overrideText) {
            $manualDiffs = $userLocks['manual_diffs'] ?? [];
            $manualDiffs[] = [
                'stage' => $stage,
                'original_ai' => $originalText,
                'user_override' => $overrideText,
                'applied_at' => now()->toIso8601String(),
            ];

            $lockedFields = $userLocks['locked_fields'] ?? [];
            if (! in_array($stage, $lockedFields, true)) {
                $lockedFields[] = $stage;
            }
            if ($stage === 'screenplay' && ! in_array('structured', $lockedFields, true)) {
                $lockedFields[] = 'structured';
            }

            $userLocks['manual_diffs'] = $manualDiffs;
            $userLocks['locked_fields'] = $lockedFields;

            return $userLocks;
        });
    }

    public function sanitizeUserLocksForAgent(array $userLocks): array
    {
        return [
            'pinned_terms' => (array) ($userLocks['pinned_terms'] ?? []),
            'manual_diffs' => (array) ($userLocks['manual_diffs'] ?? []),
            'locked_fields' => (array) ($userLocks['locked_fields'] ?? []),
        ];
    }

    public function archiveStageDiffs(array &$locks, string $stage): void
    {
        if (! empty($locks['manual_diffs'])) {
            $archived = (array) ($locks['archived_diffs'] ?? []);
            $activeDiffs = [];
            foreach ($locks['manual_diffs'] as $diff) {
                if (($diff['stage'] ?? $diff['target'] ?? null) === $stage) {
                    $diff['archived_at'] = now()->toIso8601String();
                    $archived[] = $diff;
                } else {
                    $activeDiffs[] = $diff;
                }
            }
            $locks['archived_diffs'] = $archived;
            $locks['manual_diffs'] = $activeDiffs;
        }

        if (! empty($locks['locked_fields'])) {
            $locks['locked_fields'] = array_values(array_filter($locks['locked_fields'], fn ($f) => $f !== $stage));
        }
    }
}
