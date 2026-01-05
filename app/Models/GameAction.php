<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_session_id',
        'user_id',
        'action_type',
        'action_data',
        'turn_number',
    ];

    protected $casts = [
        'action_data' => 'array',
        'turn_number' => 'integer',
    ];

    /**
     * Tipe aksi yang tersedia
     */
    const ACTION_PLAY_CARD = 'play_card';       // Main kartu (Uno)
    const ACTION_DRAW_CARD = 'draw_card';       // Ambil kartu (Uno)
    const ACTION_SAY_UNO = 'say_uno';           // Teriak "UNO!"
    const ACTION_SUBMIT_WORD = 'submit_word';   // Submit kata (Last Letter)
    const ACTION_SUBMIT_ANSWER = 'submit_answer'; // Submit jawaban (ABC 5 Dasar)
    const ACTION_VOTE = 'vote';                 // Vote pemain (Undercover)
    const ACTION_DISCUSS = 'discuss';           // Chat diskusi
    const ACTION_SKIP_TURN = 'skip_turn';       // Skip giliran

    /**
     * Game session yang memiliki aksi ini
     */
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    /**
     * User yang melakukan aksi
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope berdasarkan tipe aksi
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('action_type', $type);
    }

    /**
     * Log aksi baru
     */
    public static function log(
        string $gameSessionId,
        int $userId,
        string $actionType,
        int $turnNumber,
        ?array $actionData = null
    ): self {
        return self::create([
            'game_session_id' => $gameSessionId,
            'user_id' => $userId,
            'action_type' => $actionType,
            'turn_number' => $turnNumber,
            'action_data' => $actionData,
        ]);
    }
}
