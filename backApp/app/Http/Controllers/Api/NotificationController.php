<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Events\NotificationEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $notifications = Notification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json([
                'message' => 'Notifications récupérées avec succès',
                'data' => $notifications
            ]);

        } catch (Exception $e) {
            Log::error('NotificationController::index - Erreur', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Erreur lors de la récupération des notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function markAsRead(Request $request, $id)
    {
        try {
            $user = $request->user();
            $notification = Notification::where('user_id', $user->id)
                ->where('id', $id)
                ->firstOrFail();

            $notification->is_read = true;
            $notification->read_at = now();
            $notification->save();

            return response()->json([
                'message' => 'Notification marquée comme lue',
                'data' => $notification
            ]);

        } catch (Exception $e) {
            Log::error('NotificationController::markAsRead - Erreur', [
                'id' => $id,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Notification non trouvée',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function markAllAsRead(Request $request)
    {
        try {
            $user = $request->user();

            Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);

            return response()->json([
                'message' => 'Toutes les notifications ont été marquées comme lues'
            ]);

        } catch (Exception $e) {
            Log::error('NotificationController::markAllAsRead - Erreur', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Erreur lors du marquage des notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Méthode helper pour envoyer une notification
    public static function send($type, $title, $message, $link = null, $campusId = null, $roles = ['super_admin', 'admin_global', 'admin_campus'])
    {
        try {
            // Récupérer les destinataires
            $query = User::whereIn('role', $roles);

            if ($campusId && !in_array('super_admin', $roles)) {
                $query->where(function ($q) use ($campusId) {
                    $q->where('campus_id', $campusId)
                      ->orWhere('role', 'admin_global');
                });
            }

            $recipients = $query->get();

            // Stocker en BD
            foreach ($recipients as $recipient) {
                Notification::create([
                    'user_id' => $recipient->id,
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'link' => $link,
                    'campus_id' => $campusId,
                ]);
            }

            // Broadcast Pusher
            broadcast(new NotificationEvent($type, $title, $message, $link, $campusId, $recipients));

            return true;

        } catch (Exception $e) {
            Log::error('NotificationController::send - Erreur', [
                'message' => $e->getMessage()
            ]);
            return false;
        }
    }
}