<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class ActivityLogObserver
{
    /**
     * Handle the ActivityLog "created" event.
     * Déclenche automatiquement les notifications et le broadcast.
     */
    public function created(ActivityLog $log): void
    {
        try {
            // 1. Déterminer les destinataires
            $admins = User::whereIn('role', ['super_admin', 'admin_global'])
                ->orWhere(function ($query) use ($log) {
                    $query->where('role', 'admin_campus')
                          ->where('campus_id', $log->campus_id);
                })
                ->get();

            // 2. Créer les notifications en base
            foreach ($admins as $admin) {
                Notification::create([
                    'user_id'   => $admin->id,
                    'type'      => 'activity_log',
                    'title'     => ucfirst($log->action) . ' - ' . ucfirst($log->target_type),
                    'message'   => $log->changes ?? "Action {$log->action} sur {$log->target_type}",
                    'link'      => "/{$log->target_type}s/{$log->target_id}",
                    'campus_id' => $log->campus_id,
                ]);
            }

            // 3. Broadcast Pusher (si configuré)
            // broadcast(new \App\Events\NewActivityLogEvent($log, $admins));

        } catch (\Throwable $e) {
            // On ne bloque jamais le flux principal si la notif échoue
            Log::error('ActivityLogObserver: Erreur notification', [
                'log_id' => $log->id,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}