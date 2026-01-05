<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Rooms yang di-host oleh user ini
     */
    public function hostedRooms(): HasMany
    {
        return $this->hasMany(Room::class, 'host_id');
    }

    /**
     * Rooms yang diikuti oleh user (many-to-many via room_players)
     */
    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'room_players')
            ->withPivot(['turn_order', 'score', 'status', 'is_ready', 'role'])
            ->withTimestamps();
    }

    /**
     * Game states milik user
     */
    public function gameStates(): HasMany
    {
        return $this->hasMany(GameState::class);
    }

    /**
     * Game actions yang dilakukan user
     */
    public function gameActions(): HasMany
    {
        return $this->hasMany(GameAction::class);
    }
}
