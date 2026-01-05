<?php

namespace App\Services\Games;

use App\Models\GameSession;
use App\Models\GameState;
use App\Models\GameAction;
use App\Models\Room;
use App\Services\KbbiService;
use Illuminate\Support\Facades\DB;

class LastLetterService
{
    protected KbbiService $kbbiService;

    public function __construct(KbbiService $kbbiService)
    {
        $this->kbbiService = $kbbiService;
    }

    /**
     * Mulai game Last Letter
     */
    public function startGame(Room $room): GameSession
    {
        return DB::transaction(function () use ($room) {
            // Buat game session
            $session = GameSession::create([
                'room_id' => $room->id,
                'round_number' => 1,
                'phase' => 'playing',
                'turn_number' => 1,
                'turn_time_limit' => config('games.last_letter.turn_time_limit', 15),
                'turn_started_at' => now(),
                'meta_data' => [
                    'game_type' => 'last_letter'
                ]
            ]);

            // Set urutan giliran pemain
            $players = $room->roomPlayers()->active()->get()->shuffle();
            foreach ($players as $index => $player) {
                $player->update(['turn_order' => $index + 1]);
            }

            // Set current player (pemain pertama)
            $firstPlayer = $players->first();
            $session->update(['current_player_id' => $firstPlayer->user_id]);

            // Buat state untuk kata yang digunakan
            $startWord = $this->kbbiService->getRandomStartWord();
            GameState::create([
                'game_session_id' => $session->id,
                'user_id' => null,
                'state_type' => GameState::TYPE_USED_WORDS,
                'state_data' => [
                    'words' => [$startWord],
                    'current_word' => $startWord
                ]
            ]);

            // Update room status
            $room->update(['status' => 'playing']);

            return $session;
        });
    }

    /**
     * Submit kata dari pemain
     */
    public function submitWord(GameSession $session, int $userId, string $word): array
    {
        // Cek giliran
        if ($session->current_player_id !== $userId) {
            return [
                'success' => false,
                'error' => 'Bukan giliran kamu!'
            ];
        }

        // Cek fase game
        if ($session->phase !== 'playing') {
            return [
                'success' => false,
                'error' => 'Game tidak sedang berlangsung'
            ];
        }

        // Ambil state kata
        $wordState = $session->gameStates()
            ->where('state_type', GameState::TYPE_USED_WORDS)
            ->first();

        $usedWords = $wordState->state_data['words'] ?? [];
        $currentWord = $wordState->state_data['current_word'] ?? '';

        // Validasi kata
        $validation = $this->kbbiService->validateLastLetterWord(
            $word,
            $currentWord,
            $usedWords
        );

        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error']
            ];
        }

        // Kata valid, update state
        $normalizedWord = mb_strtolower(trim($word));
        $usedWords[] = $normalizedWord;

        $wordState->update([
            'state_data' => [
                'words' => $usedWords,
                'current_word' => $normalizedWord
            ]
        ]);

        // Log action
        GameAction::log(
            $session->id,
            $userId,
            GameAction::ACTION_SUBMIT_WORD,
            $session->turn_number,
            [
                'word' => $normalizedWord,
                'definition' => $validation['definition']
            ]
        );

        // Pindah ke pemain selanjutnya
        $session->nextPlayer();

        return [
            'success' => true,
            'word' => $normalizedWord,
            'definition' => $validation['definition'],
            'next_player_id' => $session->current_player_id,
            'last_letter' => $this->kbbiService->getLastLetter($normalizedWord)
        ];
    }

    /**
     * Handle timeout (pemain tidak menjawab tepat waktu)
     */
    public function handleTimeout(GameSession $session): array
    {
        $eliminatedPlayerId = $session->current_player_id;

        // Eliminasi pemain
        $session->room->roomPlayers()
            ->where('user_id', $eliminatedPlayerId)
            ->update(['status' => 'eliminated']);

        // Log action
        GameAction::log(
            $session->id,
            $eliminatedPlayerId,
            GameAction::ACTION_SKIP_TURN,
            $session->turn_number,
            ['reason' => 'timeout']
        );

        // Cek sisa pemain
        $activePlayers = $session->room->roomPlayers()->active()->count();

        if ($activePlayers <= 1) {
            // Game selesai, ada pemenang
            return $this->endGame($session);
        }

        // Lanjut ke pemain berikutnya
        $session->nextPlayer();

        return [
            'success' => true,
            'eliminated_player_id' => $eliminatedPlayerId,
            'game_over' => false,
            'next_player_id' => $session->current_player_id
        ];
    }

    /**
     * Akhiri game
     */
    public function endGame(GameSession $session): array
    {
        $winner = $session->room->roomPlayers()
            ->active()
            ->with('user')
            ->first();

        $session->update(['phase' => 'finished']);
        $session->room->update(['status' => 'finished']);

        // Update skor pemenang
        if ($winner) {
            $winner->increment('score', 10);
        }

        return [
            'success' => true,
            'game_over' => true,
            'winner' => $winner ? [
                'id' => $winner->user_id,
                'name' => $winner->user->name
            ] : null
        ];
    }

    /**
     * Ambil status game saat ini
     */
    public function getGameStatus(GameSession $session): array
    {
        $wordState = $session->gameStates()
            ->where('state_type', GameState::TYPE_USED_WORDS)
            ->first();

        return [
            'current_word' => $wordState->state_data['current_word'] ?? '',
            'last_letter' => $this->kbbiService->getLastLetter($wordState->state_data['current_word'] ?? ''),
            'used_words_count' => count($wordState->state_data['words'] ?? []),
            'current_player_id' => $session->current_player_id,
            'turn_number' => $session->turn_number,
            'phase' => $session->phase,
            'turn_started_at' => $session->turn_started_at?->toIso8601String(),
            'turn_time_limit' => $session->turn_time_limit
        ];
    }
}
