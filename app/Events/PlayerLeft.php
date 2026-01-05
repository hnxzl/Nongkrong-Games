<?php

namespace App\Events;

use App\Models\Room;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerLeft implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Room $room;
    public int $playerId;

    public function __construct(Room $room, int $playerId)
    {
        $this->room = $room;
        $this->playerId = $playerId;
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('room.' . $this->room->code),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'left_player_id' => $this->playerId,
            'players' => $this->room->players()->with('user:id,name')->get()->map(fn($rp) => [
                'id' => $rp->user_id,
                'name' => $rp->user->name,
                'is_ready' => $rp->is_ready,
            ]),
        ];
    }
}
