<?php

use App\Models\Room;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Channel untuk room game
 * Presence channel untuk mengetahui siapa saja yang ada di room
 */
Broadcast::channel('room.{code}', function ($user, $code) {
    $room = Room::where('code', $code)->first();
    
    if (!$room) {
        return false;
    }

    // Cek apakah user adalah member room ini
    $isMember = $room->players()->where('user_id', $user->id)->exists();
    
    if ($isMember) {
        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }

    return false;
});
