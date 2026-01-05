<?php

namespace App\Services\Games;

use App\Models\GameSession;
use App\Models\GameState;
use App\Models\GameAction;
use App\Models\Room;
use App\Models\WordPair;
use Illuminate\Support\Facades\DB;

class UndercoverService
{
    /**
     * Mulai game Undercover
     */
    public function startGame(Room $room): GameSession
    {
        return DB::transaction(function () use ($room) {
            $players = $room->roomPlayers()->active()->get();
            $playerCount = $players->count();

            // Minimal 3 pemain
            if ($playerCount < 3) {
                throw new \Exception('Minimal 3 pemain untuk bermain Undercover');
            }

            // Tentukan jumlah Undercover dan Mr. White
            [$undercoverCount, $mrWhiteCount] = $this->calculateRoles($playerCount);

            // Ambil pasangan kata random
            $wordPair = WordPair::getRandomPair();
            if (!$wordPair) {
                throw new \Exception('Tidak ada pasangan kata tersedia');
            }

            // Buat game session
            $session = GameSession::create([
                'room_id' => $room->id,
                'round_number' => 1,
                'phase' => 'setup', // setup -> discussion -> voting -> (repeat until finished)
                'turn_number' => 0,
                'turn_time_limit' => config('games.undercover.discussion_time', 60),
                'meta_data' => [
                    'game_type' => 'undercover',
                    'word_pair_id' => $wordPair->id,
                    'civilian_word' => $wordPair->civilian_word,
                    'undercover_word' => $wordPair->undercover_word,
                ]
            ]);

            // Acak dan assign peran
            $shuffledPlayers = $players->shuffle();
            $roles = $this->assignRoles($shuffledPlayers, $undercoverCount, $mrWhiteCount);

            foreach ($shuffledPlayers as $index => $player) {
                $role = $roles[$index];
                $word = $this->getWordForRole($role, $wordPair);

                // Update role di room_players
                $player->update([
                    'role' => $role,
                    'turn_order' => $index + 1
                ]);

                // Simpan kata untuk pemain
                GameState::create([
                    'game_session_id' => $session->id,
                    'user_id' => $player->user_id,
                    'state_type' => GameState::TYPE_WORD,
                    'state_data' => [
                        'role' => $role,
                        'word' => $word
                    ]
                ]);
            }

            // Set pemain pertama
            $session->update([
                'current_player_id' => $shuffledPlayers->first()->user_id,
                'phase' => 'discussion',
                'turn_started_at' => now()
            ]);

            // Update room status
            $room->update(['status' => 'playing']);

            return $session;
        });
    }

    /**
     * Hitung jumlah Undercover dan Mr. White berdasarkan total pemain
     */
    protected function calculateRoles(int $playerCount): array
    {
        // Logika: 
        // 3-4 pemain: 1 Undercover, 0 Mr. White
        // 5-6 pemain: 1 Undercover, 1 Mr. White
        // 7-8 pemain: 2 Undercover, 1 Mr. White
        // 9-10 pemain: 2 Undercover, 1 Mr. White

        if ($playerCount <= 4) {
            return [1, 0];
        } elseif ($playerCount <= 6) {
            return [1, 1];
        } else {
            return [2, 1];
        }
    }

    /**
     * Assign peran ke pemain
     */
    protected function assignRoles($players, int $undercoverCount, int $mrWhiteCount): array
    {
        $roles = [];
        $totalPlayers = $players->count();

        // Assign Undercover
        for ($i = 0; $i < $undercoverCount; $i++) {
            $roles[] = 'undercover';
        }

        // Assign Mr. White
        for ($i = 0; $i < $mrWhiteCount; $i++) {
            $roles[] = 'mr_white';
        }

        // Sisanya Civilian
        $civilianCount = $totalPlayers - $undercoverCount - $mrWhiteCount;
        for ($i = 0; $i < $civilianCount; $i++) {
            $roles[] = 'civilian';
        }

        // Shuffle roles
        shuffle($roles);

        return $roles;
    }

    /**
     * Dapatkan kata berdasarkan peran
     */
    protected function getWordForRole(string $role, WordPair $wordPair): ?string
    {
        return match($role) {
            'civilian' => $wordPair->civilian_word,
            'undercover' => $wordPair->undercover_word,
            'mr_white' => null, // Mr. White tidak dapat kata
        };
    }

    /**
     * Mulai fase voting
     */
    public function startVoting(GameSession $session): array
    {
        $session->update([
            'phase' => 'voting',
            'turn_started_at' => now(),
            'turn_time_limit' => config('games.undercover.voting_time', 30)
        ]);

        // Reset votes
        GameState::where('game_session_id', $session->id)
            ->where('state_type', GameState::TYPE_VOTE)
            ->delete();

        return [
            'success' => true,
            'phase' => 'voting',
            'time_limit' => $session->turn_time_limit
        ];
    }

    /**
     * Submit vote
     */
    public function submitVote(GameSession $session, int $voterId, int $targetId): array
    {
        if ($session->phase !== 'voting') {
            return ['success' => false, 'error' => 'Bukan fase voting'];
        }

        // Cek voter masih aktif
        $voter = $session->room->roomPlayers()
            ->where('user_id', $voterId)
            ->active()
            ->first();

        if (!$voter) {
            return ['success' => false, 'error' => 'Kamu sudah tereliminasi'];
        }

        // Cek target masih aktif
        $target = $session->room->roomPlayers()
            ->where('user_id', $targetId)
            ->active()
            ->first();

        if (!$target) {
            return ['success' => false, 'error' => 'Target tidak valid'];
        }

        // Tidak bisa vote diri sendiri
        if ($voterId === $targetId) {
            return ['success' => false, 'error' => 'Tidak bisa vote diri sendiri'];
        }

        // Simpan/update vote
        GameState::updateOrCreate(
            [
                'game_session_id' => $session->id,
                'user_id' => $voterId,
                'state_type' => GameState::TYPE_VOTE,
            ],
            [
                'state_data' => ['target_id' => $targetId]
            ]
        );

        // Log action
        GameAction::log(
            $session->id,
            $voterId,
            GameAction::ACTION_VOTE,
            $session->turn_number,
            ['target_id' => $targetId]
        );

        return ['success' => true, 'message' => 'Vote berhasil'];
    }

    /**
     * Hitung hasil voting dan eliminasi
     */
    public function processVotingResult(GameSession $session): array
    {
        $votes = $session->gameStates()
            ->where('state_type', GameState::TYPE_VOTE)
            ->get();

        // Hitung vote per target
        $voteCount = [];
        foreach ($votes as $vote) {
            $targetId = $vote->state_data['target_id'];
            $voteCount[$targetId] = ($voteCount[$targetId] ?? 0) + 1;
        }

        if (empty($voteCount)) {
            // Tidak ada vote, lanjut ke ronde berikutnya
            return $this->startNextRound($session);
        }

        // Cari pemain dengan vote terbanyak
        $maxVotes = max($voteCount);
        $eliminated = array_keys(array_filter($voteCount, fn($v) => $v === $maxVotes));

        // Jika ada tie, random pilih satu
        $eliminatedId = $eliminated[array_rand($eliminated)];

        // Eliminasi pemain
        $eliminatedPlayer = $session->room->roomPlayers()
            ->where('user_id', $eliminatedId)
            ->first();

        $eliminatedPlayer->update(['status' => 'eliminated']);

        // Cek kondisi menang
        $gameResult = $this->checkWinCondition($session, $eliminatedPlayer);

        if ($gameResult['game_over']) {
            return $gameResult;
        }

        // Lanjut ke ronde berikutnya
        return $this->startNextRound($session, $eliminatedPlayer);
    }

    /**
     * Cek kondisi menang
     */
    protected function checkWinCondition(GameSession $session, $eliminatedPlayer): array
    {
        $activePlayers = $session->room->roomPlayers()->active()->get();
        
        $activeUndercover = $activePlayers->where('role', 'undercover')->count();
        $activeMrWhite = $activePlayers->where('role', 'mr_white')->count();
        $activeCivilians = $activePlayers->where('role', 'civilian')->count();

        $eliminatedRole = $eliminatedPlayer->role;

        // Mr. White tereliminasi - boleh tebak kata
        if ($eliminatedRole === 'mr_white') {
            return [
                'game_over' => false,
                'mr_white_guess' => true,
                'eliminated' => [
                    'id' => $eliminatedPlayer->user_id,
                    'role' => $eliminatedRole
                ]
            ];
        }

        // Semua Undercover dan Mr. White tereliminasi = Civilian menang
        if ($activeUndercover === 0 && $activeMrWhite === 0) {
            return $this->endGame($session, 'civilian');
        }

        // Undercover/Mr. White sama atau lebih dari Civilian = Undercover menang
        if (($activeUndercover + $activeMrWhite) >= $activeCivilians) {
            return $this->endGame($session, 'undercover');
        }

        return [
            'game_over' => false,
            'eliminated' => [
                'id' => $eliminatedPlayer->user_id,
                'role' => $eliminatedRole
            ]
        ];
    }

    /**
     * Mr. White menebak kata
     */
    public function mrWhiteGuess(GameSession $session, int $userId, string $guessedWord): array
    {
        $player = $session->room->roomPlayers()
            ->where('user_id', $userId)
            ->first();

        if ($player->role !== 'mr_white') {
            return ['success' => false, 'error' => 'Kamu bukan Mr. White'];
        }

        $civilianWord = $session->meta_data['civilian_word'];
        $isCorrect = mb_strtolower(trim($guessedWord)) === mb_strtolower($civilianWord);

        if ($isCorrect) {
            // Mr. White menang!
            return $this->endGame($session, 'mr_white');
        }

        // Tebakan salah, lanjut game
        return $this->startNextRound($session);
    }

    /**
     * Mulai ronde berikutnya
     */
    protected function startNextRound(GameSession $session, $eliminatedPlayer = null): array
    {
        $session->update([
            'phase' => 'discussion',
            'round_number' => $session->round_number + 1,
            'turn_started_at' => now(),
            'turn_time_limit' => config('games.undercover.discussion_time', 60)
        ]);

        return [
            'game_over' => false,
            'phase' => 'discussion',
            'round' => $session->round_number,
            'eliminated' => $eliminatedPlayer ? [
                'id' => $eliminatedPlayer->user_id,
                'role' => $eliminatedPlayer->role
            ] : null
        ];
    }

    /**
     * Akhiri game
     */
    protected function endGame(GameSession $session, string $winningTeam): array
    {
        $session->update(['phase' => 'finished']);
        $session->room->update(['status' => 'finished']);

        // Update skor pemenang
        $winners = $session->room->roomPlayers()
            ->where('role', $winningTeam)
            ->orWhere(function ($query) use ($winningTeam) {
                if ($winningTeam === 'undercover') {
                    $query->where('role', 'mr_white');
                }
            })
            ->get();

        foreach ($winners as $winner) {
            $winner->increment('score', 10);
        }

        return [
            'game_over' => true,
            'winning_team' => $winningTeam,
            'civilian_word' => $session->meta_data['civilian_word'],
            'undercover_word' => $session->meta_data['undercover_word'],
            'winners' => $winners->map(fn($w) => [
                'id' => $w->user_id,
                'role' => $w->role
            ])
        ];
    }

    /**
     * Ambil status game untuk pemain tertentu
     */
    public function getGameStatus(GameSession $session, int $userId): array
    {
        // Ambil info pemain ini
        $playerState = $session->gameStates()
            ->where('user_id', $userId)
            ->where('state_type', GameState::TYPE_WORD)
            ->first();

        $myRole = $playerState->state_data['role'] ?? null;
        $myWord = $playerState->state_data['word'] ?? null;

        // Ambil semua pemain aktif
        $activePlayers = $session->room->roomPlayers()
            ->active()
            ->with('user')
            ->get()
            ->map(fn($p) => [
                'id' => $p->user_id,
                'name' => $p->user->name,
                'is_me' => $p->user_id === $userId
            ]);

        // Ambil pemain tereliminasi
        $eliminatedPlayers = $session->room->roomPlayers()
            ->where('status', 'eliminated')
            ->with('user')
            ->get()
            ->map(fn($p) => [
                'id' => $p->user_id,
                'name' => $p->user->name,
                'role' => $p->role
            ]);

        return [
            'my_role' => $myRole,
            'my_word' => $myWord,
            'phase' => $session->phase,
            'round' => $session->round_number,
            'active_players' => $activePlayers,
            'eliminated_players' => $eliminatedPlayers,
            'turn_started_at' => $session->turn_started_at?->toIso8601String(),
            'turn_time_limit' => $session->turn_time_limit
        ];
    }
}
