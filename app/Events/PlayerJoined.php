<?php

namespace App\Events;

use App\Models\Room;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerJoined implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Room $room;
    public array $player;

    public function __construct(Room $room, array $player)
    {
        $this->room = $room;
        $this->player = $player;
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
            'player' => $this->player,
            'players' => $this->room->players()->with('user:id,name')->get()->map(fn($rp) => [
                'id' => $rp->user_id,
                'name' => $rp->user->name,
                'is_ready' => $rp->is_ready,
                'is_host' => $rp->user_id === $this->room->host_id,
            ]),
        ];
    }
}
