<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ActivityLogService
{
    public function log(
        string $action,
        string $targetType,
        int $targetId,
        ?string $targetName = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?string $changes = null,
        ?int $campusId = null
    ): void {
        $user = Auth::user();

        ActivityLog::create([
            'user_id'     => $user?->id,
            'user_role'   => $user?->role ?? 'system',
            'campus_id'   => $campusId,
            'action'      => $action,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'target_name' => $targetName,
            'old_data'    => $oldData,
            'new_data'    => $newData,
            'changes'     => $changes,
        ]);
    }
}