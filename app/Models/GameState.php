<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameState extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_session_id',
        'user_id',
        'state_type',
        'state_data',
    ];

    protected $casts = [
        'state_data' => 'array',
    ];

    /**
     * Tipe state yang tersedia
     */
    const TYPE_HAND = 'hand';           // Kartu di tangan (Uno)
    const TYPE_DECK = 'deck';           // Deck kartu (Uno)
    const TYPE_DISCARD = 'discard';     // Kartu buangan (Uno)
    const TYPE_WORD = 'word';           // Kata yang didapat (Undercover, Last Letter)
    const TYPE_ANSWER = 'answer';       // Jawaban pemain (ABC 5 Dasar)
    const TYPE_VOTE = 'vote';           // Vote pemain (Undercover, ABC)
    const TYPE_USED_WORDS = 'used_words'; // Kata yang sudah digunakan (Last Letter)

    /**
     * Game session yang memiliki state ini
     */
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    /**
     * User yang memiliki state ini (jika ada)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope berdasarkan tipe
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('state_type', $type);
    }

    /**
     * Scope berdasarkan user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Update state data
     */
    public function updateData(array $data): void
    {
        $this->state_data = array_merge($this->state_data ?? [], $data);
        $this->save();
    }

    /**
     * Tambah item ke state data (untuk array)
     */
    public function pushToData(string $key, $value): void
    {
        $data = $this->state_data ?? [];
        
        if (!isset($data[$key])) {
            $data[$key] = [];
        }
        
        $data[$key][] = $value;
        $this->state_data = $data;
        $this->save();
    }

    /**
     * Hapus item dari state data (untuk array)
     */
    public function removeFromData(string $key, $value): void
    {
        $data = $this->state_data ?? [];
        
        if (isset($data[$key]) && is_array($data[$key])) {
            $data[$key] = array_values(array_filter($data[$key], fn($item) => $item !== $value));
            $this->state_data = $data;
            $this->save();
        }
    }
}
