<?php

namespace App\Events;

use App\Models\Registration;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NewRegistrationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $registration;
    public $admins;

    public function __construct($registration, $admins)
    {
        $this->registration = $registration;
        $this->admins = $admins;
        Log::info('📢 NewRegistrationEvent créé', [
            'registration_id' => $registration->id,
            'admins_count' => $admins->count()
        ]);
    }

    public function broadcastOn()
    {
        $channels = [];
        foreach ($this->admins as $admin) {
            $channels[] = new PrivateChannel('user.' . $admin->id);
        }
        return $channels;
    }

    public function broadcastWith()
    {
        return [
            'type' => 'new_registration',
            'title' => 'Nouvelle inscription',
            'message' => $this->registration->student->first_name . ' ' . $this->registration->student->last_name . ' s\'est inscrit(e)',
            'link' => '/registrations/' . $this->registration->id,
            'registration_id' => $this->registration->id,
            'campus_id' => $this->registration->campus_id,
            'created_at' => now()->toDateTimeString(),
        ];
    }
}