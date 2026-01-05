<?php

namespace App\Events;

use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameStateUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $roomCode;
    public string $eventType;
    public array $data;

    public function __construct(string $roomCode, string $eventType, array $data)
    {
        $this->roomCode = $roomCode;
        $this->eventType = $eventType;
        $this->data = $data;
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('room.' . $this->roomCode),
        ];
    }

    public function broadcastAs(): string
    {
        return 'game.state.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->eventType,
            'data' => $this->data,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
