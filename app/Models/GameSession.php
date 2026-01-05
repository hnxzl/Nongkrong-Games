<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameSession extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'room_id',
        'round_number',
        'current_player_id',
        'phase',
        'turn_number',
        'direction',
        'turn_started_at',
        'turn_time_limit',
        'meta_data',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'turn_number' => 'integer',
        'turn_started_at' => 'datetime',
        'turn_time_limit' => 'integer',
        'meta_data' => 'array',
    ];

    /**
     * Room yang memiliki sesi ini
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Pemain yang sedang giliran
     */
    public function currentPlayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_player_id');
    }

    /**
     * Game states dalam sesi ini
     */
    public function gameStates(): HasMany
    {
        return $this->hasMany(GameState::class);
    }

    /**
     * Game actions dalam sesi ini
     */
    public function gameActions(): HasMany
    {
        return $this->hasMany(GameAction::class);
    }

    /**
     * Ambil state berdasarkan tipe
     */
    public function getStateByType(string $type, ?int $userId = null)
    {
        $query = $this->gameStates()->where('state_type', $type);
        
        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }

    /**
     * Cek apakah game sedang berlangsung
     */
    public function isPlaying(): bool
    {
        return $this->phase === 'playing';
    }

    /**
     * Cek apakah game sudah selesai
     */
    public function isFinished(): bool
    {
        return $this->phase === 'finished';
    }

    /**
     * Ganti arah permainan (untuk Uno Reverse)
     */
    public function reverseDirection(): void
    {
        $this->direction = $this->direction === 'clockwise' 
            ? 'counter_clockwise' 
            : 'clockwise';
        $this->save();
    }

    /**
     * Pindah ke pemain selanjutnya
     */
    public function nextPlayer(int $skip = 1): void
    {
        $players = $this->room->roomPlayers()
            ->active()
            ->orderBy('turn_order')
            ->get();

        if ($players->isEmpty()) {
            return;
        }

        $currentIndex = $players->search(function ($player) {
            return $player->user_id === $this->current_player_id;
        });

        if ($currentIndex === false) {
            $currentIndex = -1;
        }

        $playerCount = $players->count();
        
        if ($this->direction === 'clockwise') {
            $nextIndex = ($currentIndex + $skip) % $playerCount;
        } else {
            $nextIndex = ($currentIndex - $skip + $playerCount) % $playerCount;
        }

        $this->current_player_id = $players[$nextIndex]->user_id;
        $this->turn_number++;
        $this->turn_started_at = now();
        $this->save();
    }

    /**
     * Scope untuk sesi yang sedang berlangsung
     */
    public function scopePlaying($query)
    {
        return $query->where('phase', 'playing');
    }
}
