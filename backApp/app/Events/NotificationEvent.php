<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $type;
    public $title;
    public $message;
    public $link;
    public $campusId;
    public $recipients;

    public function __construct($type, $title, $message, $link = null, $campusId = null, $recipients = [])
    {
        $this->type = $type;
        $this->title = $title;
        $this->message = $message;
        $this->link = $link;
        $this->campusId = $campusId;
        $this->recipients = $recipients;
    }

    public function broadcastOn()
    {
        $channels = [];
        foreach ($this->recipients as $recipient) {
            $channels[] = new PrivateChannel('user.' . $recipient->id);
        }
        return $channels;
    }

    public function broadcastWith()
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'link' => $this->link,
            'campus_id' => $this->campusId,
            'created_at' => now()->toDateTimeString(),
        ];
    }
}