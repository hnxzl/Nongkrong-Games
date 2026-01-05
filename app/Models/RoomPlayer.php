<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomPlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'user_id',
        'turn_order',
        'score',
        'status',
        'is_ready',
        'role',
    ];

    protected $casts = [
        'turn_order' => 'integer',
        'score' => 'integer',
        'is_ready' => 'boolean',
    ];

    /**
     * Room
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * User/Player
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cek apakah pemain aktif
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Cek apakah pemain ter-eliminasi
     */
    public function isEliminated(): bool
    {
        return $this->status === 'eliminated';
    }

    /**
     * Scope untuk pemain aktif
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
