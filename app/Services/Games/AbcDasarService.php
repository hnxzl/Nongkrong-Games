<?php

namespace App\Services\Games;

use App\Models\GameSession;
use App\Models\GameState;
use App\Models\GameAction;
use App\Models\Room;
use App\Models\AbcCategory;
use Illuminate\Support\Facades\DB;

class AbcDasarService
{
    protected array $letters = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 
        'K', 'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U', 
        'W', 'Y' // Skip Q, V, X, Z karena jarang ada kata Indonesia
    ];

    /**
     * Mulai game ABC 5 Dasar
     */
    public function startGame(Room $room): GameSession
    {
        return DB::transaction(function () use ($room) {
            $players = $room->roomPlayers()->active()->get();

            // Ambil kategori random
            $categories = AbcCategory::getRandomCategories(5);
            if ($categories->count() < 5) {
                throw new \Exception('Tidak cukup kategori tersedia');
            }

            // Pilih huruf random
            $letter = $this->letters[array_rand($this->letters)];

            // Buat game session
            $session = GameSession::create([
                'room_id' => $room->id,
                'round_number' => 1,
                'phase' => 'setup',
                'turn_number' => 0,
                'turn_time_limit' => config('games.abc_dasar.answer_time', 10),
                'meta_data' => [
                    'game_type' => 'abc_dasar',
                    'letter' => $letter,
                    'categories' => $categories->pluck('name')->toArray(),
                    'total_rounds' => config('games.abc_dasar.rounds', 5),
                    'current_category_index' => 0,
                    'answering_player_id' => null,
                ]
            ]);

            // Set urutan giliran
            foreach ($players->shuffle() as $index => $player) {
                $player->update(['turn_order' => $index + 1]);
            }

            // Update room status
            $room->update(['status' => 'playing']);

            // Langsung mulai ronde pertama
            $session->update([
                'phase' => 'playing',
                'turn_started_at' => now()
            ]);

            return $session;
        });
    }

    /**
     * Pemain menekan tombol "Jawab"
     */
    public function claimAnswer(GameSession $session, int $userId): array
    {
        if ($session->phase !== 'playing') {
            return ['success' => false, 'error' => 'Game tidak sedang berlangsung'];
        }

        $metaData = $session->meta_data;

        // Cek apakah ada yang sudah claim
        if (!empty($metaData['answering_player_id'])) {
            return ['success' => false, 'error' => 'Ada pemain lain yang sedang menjawab'];
        }

        // Cek pemain masih aktif
        $player = $session->room->roomPlayers()
            ->where('user_id', $userId)
            ->active()
            ->first();

        if (!$player) {
            return ['success' => false, 'error' => 'Kamu tidak bisa menjawab'];
        }

        // Set pemain sebagai yang menjawab
        $metaData['answering_player_id'] = $userId;
        $session->update([
            'meta_data' => $metaData,
            'phase' => 'answering',
            'turn_started_at' => now(),
            'turn_time_limit' => config('games.abc_dasar.answer_time', 10)
        ]);

        return [
            'success' => true,
            'player_id' => $userId,
            'time_limit' => $session->turn_time_limit
        ];
    }

    /**
     * Submit jawaban
     */
    public function submitAnswer(GameSession $session, int $userId, string $answer): array
    {
        if ($session->phase !== 'answering') {
            return ['success' => false, 'error' => 'Bukan fase menjawab'];
        }

        $metaData = $session->meta_data;

        if ($metaData['answering_player_id'] !== $userId) {
            return ['success' => false, 'error' => 'Bukan giliran kamu menjawab'];
        }

        $letter = $metaData['letter'];
        $categoryIndex = $metaData['current_category_index'];
        $category = $metaData['categories'][$categoryIndex];

        // Validasi huruf awal
        $normalizedAnswer = trim($answer);
        $firstLetter = mb_strtoupper(mb_substr($normalizedAnswer, 0, 1));

        if ($firstLetter !== $letter) {
            return [
                'success' => false,
                'error' => "Jawaban harus diawali huruf '{$letter}'"
            ];
        }

        // Simpan jawaban untuk voting
        GameState::create([
            'game_session_id' => $session->id,
            'user_id' => $userId,
            'state_type' => GameState::TYPE_ANSWER,
            'state_data' => [
                'answer' => $normalizedAnswer,
                'category' => $category,
                'round' => $session->round_number
            ]
        ]);

        // Log action
        GameAction::log(
            $session->id,
            $userId,
            GameAction::ACTION_SUBMIT_ANSWER,
            $session->turn_number,
            ['answer' => $normalizedAnswer, 'category' => $category]
        );

        // Mulai fase voting
        $session->update([
            'phase' => 'scoring',
            'turn_started_at' => now(),
            'turn_time_limit' => config('games.abc_dasar.voting_time', 15)
        ]);

        return [
            'success' => true,
            'answer' => $normalizedAnswer,
            'category' => $category,
            'phase' => 'scoring'
        ];
    }

    /**
     * Handle timeout saat menjawab
     */
    public function handleAnswerTimeout(GameSession $session): array
    {
        $metaData = $session->meta_data;
        $metaData['answering_player_id'] = null;

        $session->update([
            'meta_data' => $metaData,
            'phase' => 'playing',
            'turn_started_at' => now()
        ]);

        return [
            'success' => true,
            'message' => 'Waktu habis, kesempatan terbuka kembali'
        ];
    }

    /**
     * Submit vote (benar/salah)
     */
    public function submitVote(GameSession $session, int $voterId, bool $isCorrect): array
    {
        if ($session->phase !== 'scoring') {
            return ['success' => false, 'error' => 'Bukan fase voting'];
        }

        $metaData = $session->meta_data;
        $answeringPlayerId = $metaData['answering_player_id'];

        // Tidak bisa vote untuk diri sendiri
        if ($voterId === $answeringPlayerId) {
            return ['success' => false, 'error' => 'Tidak bisa vote jawaban sendiri'];
        }

        // Simpan vote
        GameState::updateOrCreate(
            [
                'game_session_id' => $session->id,
                'user_id' => $voterId,
                'state_type' => GameState::TYPE_VOTE,
            ],
            [
                'state_data' => [
                    'is_correct' => $isCorrect,
                    'round' => $session->round_number
                ]
            ]
        );

        return ['success' => true];
    }

    /**
     * Proses hasil voting
     */
    public function processVotingResult(GameSession $session): array
    {
        $metaData = $session->meta_data;
        $answeringPlayerId = $metaData['answering_player_id'];

        // Hitung vote
        $votes = $session->gameStates()
            ->where('state_type', GameState::TYPE_VOTE)
            ->get();

        $correctVotes = $votes->filter(fn($v) => $v->state_data['is_correct'] ?? false)->count();
        $totalVotes = $votes->count();

        // Mayoritas menentukan
        $isAnswerCorrect = $correctVotes > ($totalVotes / 2);

        // Update skor jika benar
        if ($isAnswerCorrect && $answeringPlayerId) {
            $session->room->roomPlayers()
                ->where('user_id', $answeringPlayerId)
                ->increment('score', 1);
        }

        // Hapus votes untuk ronde ini
        GameState::where('game_session_id', $session->id)
            ->where('state_type', GameState::TYPE_VOTE)
            ->delete();

        // Cek apakah lanjut ke kategori berikutnya atau ronde baru
        return $this->proceedToNext($session, $isAnswerCorrect);
    }

    /**
     * Lanjut ke kategori/ronde berikutnya
     */
    protected function proceedToNext(GameSession $session, bool $wasCorrect): array
    {
        $metaData = $session->meta_data;
        $currentCategoryIndex = $metaData['current_category_index'];
        $categories = $metaData['categories'];
        $totalRounds = $metaData['total_rounds'];

        // Reset answering player
        $metaData['answering_player_id'] = null;

        // Lanjut ke kategori berikutnya
        $currentCategoryIndex++;

        if ($currentCategoryIndex >= count($categories)) {
            // Semua kategori selesai, cek ronde
            if ($session->round_number >= $totalRounds) {
                // Game selesai
                return $this->endGame($session);
            }

            // Ronde baru dengan huruf baru
            $newLetter = $this->letters[array_rand($this->letters)];
            $metaData['letter'] = $newLetter;
            $metaData['current_category_index'] = 0;

            $session->update([
                'meta_data' => $metaData,
                'round_number' => $session->round_number + 1,
                'phase' => 'playing',
                'turn_started_at' => now()
            ]);

            return [
                'success' => true,
                'new_round' => true,
                'round' => $session->round_number,
                'letter' => $newLetter,
                'category' => $categories[0]
            ];
        }

        // Lanjut kategori
        $metaData['current_category_index'] = $currentCategoryIndex;

        $session->update([
            'meta_data' => $metaData,
            'phase' => 'playing',
            'turn_started_at' => now()
        ]);

        return [
            'success' => true,
            'new_round' => false,
            'was_correct' => $wasCorrect,
            'category' => $categories[$currentCategoryIndex],
            'letter' => $metaData['letter']
        ];
    }

    /**
     * Akhiri game
     */
    protected function endGame(GameSession $session): array
    {
        $session->update(['phase' => 'finished']);
        $session->room->update(['status' => 'finished']);

        // Ambil ranking
        $rankings = $session->room->roomPlayers()
            ->with('user')
            ->orderByDesc('score')
            ->get()
            ->map(fn($p) => [
                'id' => $p->user_id,
                'name' => $p->user->name,
                'score' => $p->score
            ]);

        return [
            'success' => true,
            'game_over' => true,
            'rankings' => $rankings
        ];
    }

    /**
     * Ambil status game
     */
    public function getGameStatus(GameSession $session): array
    {
        $metaData = $session->meta_data;
        $categories = $metaData['categories'] ?? [];
        $currentCategoryIndex = $metaData['current_category_index'] ?? 0;

        // Ambil skor semua pemain
        $players = $session->room->roomPlayers()
            ->with('user')
            ->orderByDesc('score')
            ->get()
            ->map(fn($p) => [
                'id' => $p->user_id,
                'name' => $p->user->name,
                'score' => $p->score
            ]);

        // Ambil jawaban terakhir jika ada
        $lastAnswer = $session->gameStates()
            ->where('state_type', GameState::TYPE_ANSWER)
            ->latest()
            ->first();

        return [
            'phase' => $session->phase,
            'round' => $session->round_number,
            'total_rounds' => $metaData['total_rounds'] ?? 5,
            'letter' => $metaData['letter'] ?? '',
            'current_category' => $categories[$currentCategoryIndex] ?? '',
            'categories' => $categories,
            'answering_player_id' => $metaData['answering_player_id'] ?? null,
            'last_answer' => $lastAnswer ? $lastAnswer->state_data['answer'] : null,
            'players' => $players,
            'turn_started_at' => $session->turn_started_at?->toIso8601String(),
            'turn_time_limit' => $session->turn_time_limit
        ];
    }
}
