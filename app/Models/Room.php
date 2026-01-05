<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Room extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'host_id',
        'game_type',
        'status',
        'max_players',
        'min_players',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'max_players' => 'integer',
        'min_players' => 'integer',
    ];

    /**
     * Generate kode room unik 6 karakter
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($room) {
            if (empty($room->code)) {
                $room->code = self::generateUniqueCode();
            }
        });
    }

    /**
     * Host room
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /**
     * Pemain dalam room (many-to-many via room_players)
     */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'room_players')
            ->withPivot(['turn_order', 'score', 'status', 'is_ready', 'role'])
            ->withTimestamps();
    }

    /**
     * Room players (pivot model)
     */
    public function roomPlayers(): HasMany
    {
        return $this->hasMany(RoomPlayer::class);
    }

    /**
     * Game sessions dalam room
     */
    public function gameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    /**
     * Sesi game aktif
     */
    public function activeSession(): HasOne
    {
        return $this->hasOne(GameSession::class)
            ->whereNot('phase', 'finished')
            ->latest();
    }

    /**
     * Cek apakah room penuh
     */
    public function isFull(): bool
    {
        return $this->players()->count() >= $this->max_players;
    }

    /**
     * Cek apakah room bisa mulai game
     */
    public function canStartGame(): bool
    {
        $playerCount = $this->players()->count();
        return $playerCount >= $this->min_players && 
               $this->status === 'waiting' &&
               $this->players()->wherePivot('is_ready', true)->count() === $playerCount;
    }

    /**
     * Scope untuk room yang sedang menunggu
     */
    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    /**
     * Scope untuk room berdasarkan game type
     */
    public function scopeGameType($query, string $type)
    {
        return $query->where('game_type', $type);
    }
}
