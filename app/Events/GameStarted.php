<?php

namespace App\Events;

use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public GameSession $session;
    public string $roomCode;

    public function __construct(GameSession $session, string $roomCode)
    {
        $this->session = $session;
        $this->roomCode = $roomCode;
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('room.' . $this->roomCode),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'game_session_id' => $this->session->id,
            'game_type' => $this->session->room->game_type,
            'game_url' => route('games.' . $this->session->room->game_type, ['code' => $this->roomCode]),
        ];
    }
}
