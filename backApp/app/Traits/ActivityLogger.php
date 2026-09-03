<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait ActivityLogger
{
    protected function logActivity($action, $target, $oldData = null, $newData = null, $changes = null)
    {
        $user = Auth::user();

        ActivityLog::create([
            'user_id' => $user->id,
            'user_role' => $user->role,
            'campus_id' => $target->campus_id ?? null,
            'action' => $action,
            'target_type' => class_basename($target),
            'target_id' => $target->id,
            'target_name' => $target->student?->first_name . ' ' . $target->student?->last_name ?? $target->name ?? null,
            'old_data' => $oldData,
            'new_data' => $newData,
            'changes' => $changes,
        ]);
    }
}