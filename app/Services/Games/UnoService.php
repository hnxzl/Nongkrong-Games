<?php

namespace App\Services\Games;

use App\Models\GameSession;
use App\Models\GameState;
use App\Models\GameAction;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class UnoService
{
    /**
     * Definisi kartu Uno
     * Format: [color]_[value] atau Wild/Wild_Draw
     */
    protected array $colors = ['Red', 'Blue', 'Green', 'Yellow'];
    protected array $numbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    protected array $actionCards = ['Skip', 'Reverse', 'Draw'];
    protected array $wildCards = ['Wild', 'Wild_Draw'];

    /**
     * Generate deck kartu Uno lengkap
     */
    public function generateDeck(): array
    {
        $deck = [];

        foreach ($this->colors as $color) {
            // Kartu angka 0 (1 per warna)
            $deck[] = "{$color}_0";

            // Kartu angka 1-9 (2 per warna)
            for ($i = 1; $i <= 9; $i++) {
                $deck[] = "{$color}_{$i}";
                $deck[] = "{$color}_{$i}";
            }

            // Kartu aksi (2 per warna)
            foreach ($this->actionCards as $action) {
                $deck[] = "{$color}_{$action}";
                $deck[] = "{$color}_{$action}";
            }
        }

        // Wild cards (4 masing-masing)
        for ($i = 0; $i < 4; $i++) {
            $deck[] = 'Wild';
            $deck[] = 'Wild_Draw';
        }

        // Shuffle deck
        shuffle($deck);

        return $deck;
    }

    /**
     * Parse kartu menjadi array [color, value]
     */
    public function parseCard(string $card): array
    {
        if (str_starts_with($card, 'Wild_Draw')) {
            return ['color' => 'Black', 'value' => 'Draw', 'type' => 'wild_draw'];
        }

        if ($card === 'Wild') {
            return ['color' => 'Black', 'value' => 'Wild', 'type' => 'wild'];
        }

        $parts = explode('_', $card, 2);
        $color = $parts[0];
        $value = $parts[1] ?? '';

        $type = 'number';
        if (in_array($value, $this->actionCards)) {
            $type = strtolower($value);
        }

        return ['color' => $color, 'value' => $value, 'type' => $type];
    }

    /**
     * Mulai game Uno
     */
    public function startGame(Room $room): GameSession
    {
        return DB::transaction(function () use ($room) {
            // Generate dan shuffle deck
            $deck = $this->generateDeck();

            // Buat game session
            $session = GameSession::create([
                'room_id' => $room->id,
                'round_number' => 1,
                'phase' => 'playing',
                'turn_number' => 1,
                'direction' => 'clockwise',
                'turn_time_limit' => config('games.uno.turn_time_limit', 30),
                'turn_started_at' => now(),
                'meta_data' => [
                    'game_type' => 'uno',
                    'draw_stack' => 0,
                    'current_color' => null,
                ]
            ]);

            // Set urutan giliran pemain
            $players = $room->roomPlayers()->active()->get()->shuffle();
            foreach ($players as $index => $player) {
                $player->update(['turn_order' => $index + 1]);
            }

            // Bagikan kartu ke setiap pemain (7 kartu)
            $startingCards = config('games.uno.starting_cards', 7);
            foreach ($players as $player) {
                $hand = array_splice($deck, 0, $startingCards);

                GameState::create([
                    'game_session_id' => $session->id,
                    'user_id' => $player->user_id,
                    'state_type' => GameState::TYPE_HAND,
                    'state_data' => ['cards' => $hand]
                ]);
            }

            // Ambil kartu pertama untuk discard pile (pastikan bukan wild)
            $firstCard = null;
            foreach ($deck as $index => $card) {
                if (!str_starts_with($card, 'Wild')) {
                    $firstCard = $card;
                    array_splice($deck, $index, 1);
                    break;
                }
            }

            // Simpan deck sisa
            GameState::create([
                'game_session_id' => $session->id,
                'user_id' => null,
                'state_type' => GameState::TYPE_DECK,
                'state_data' => ['cards' => $deck]
            ]);

            // Simpan discard pile
            GameState::create([
                'game_session_id' => $session->id,
                'user_id' => null,
                'state_type' => GameState::TYPE_DISCARD,
                'state_data' => ['cards' => [$firstCard]]
            ]);

            // Set pemain pertama dan warna awal
            $firstPlayer = $players->first();
            $parsedFirstCard = $this->parseCard($firstCard);

            $session->update([
                'current_player_id' => $firstPlayer->user_id,
                'meta_data' => array_merge($session->meta_data, [
                    'current_color' => $parsedFirstCard['color']
                ])
            ]);

            // Update room status
            $room->update(['status' => 'playing']);

            return $session;
        });
    }

    /**
     * Cek apakah kartu bisa dimainkan
     */
    public function canPlayCard(string $card, string $topCard, ?string $currentColor = null): bool
    {
        $played = $this->parseCard($card);
        $top = $this->parseCard($topCard);

        // Wild cards selalu bisa dimainkan
        if ($played['type'] === 'wild' || $played['type'] === 'wild_draw') {
            return true;
        }

        // Cek warna (gunakan currentColor jika ada, untuk wild)
        $colorToMatch = $currentColor ?? $top['color'];
        if ($played['color'] === $colorToMatch) {
            return true;
        }

        // Cek value yang sama
        if ($played['value'] === $top['value']) {
            return true;
        }

        return false;
    }

    /**
     * Mainkan kartu
     */
    public function playCard(
        GameSession $session, 
        int $userId, 
        string $card, 
        ?string $chosenColor = null
    ): array {
        // Validasi giliran
        if ($session->current_player_id !== $userId) {
            return ['success' => false, 'error' => 'Bukan giliran kamu!'];
        }

        if ($session->phase !== 'playing') {
            return ['success' => false, 'error' => 'Game tidak sedang berlangsung'];
        }

        // Ambil tangan pemain
        $handState = $session->gameStates()
            ->where('user_id', $userId)
            ->where('state_type', GameState::TYPE_HAND)
            ->first();

        $hand = $handState->state_data['cards'] ?? [];

        // Cek pemain punya kartu ini
        $cardIndex = array_search($card, $hand);
        if ($cardIndex === false) {
            return ['success' => false, 'error' => 'Kamu tidak punya kartu ini'];
        }

        // Ambil kartu teratas discard pile
        $discardState = $session->gameStates()
            ->where('state_type', GameState::TYPE_DISCARD)
            ->first();

        $discardPile = $discardState->state_data['cards'] ?? [];
        $topCard = end($discardPile);
        $currentColor = $session->meta_data['current_color'] ?? null;

        // Cek apakah kartu bisa dimainkan
        if (!$this->canPlayCard($card, $topCard, $currentColor)) {
            return ['success' => false, 'error' => 'Kartu tidak bisa dimainkan'];
        }

        // Parse kartu yang dimainkan
        $parsedCard = $this->parseCard($card);

        // Validasi wild card harus pilih warna
        if (($parsedCard['type'] === 'wild' || $parsedCard['type'] === 'wild_draw') 
            && empty($chosenColor)) {
            return ['success' => false, 'error' => 'Pilih warna untuk Wild card'];
        }

        return DB::transaction(function () use (
            $session, $userId, $card, $cardIndex, $hand, 
            $handState, $discardState, $discardPile, $parsedCard, $chosenColor
        ) {
            // Hapus kartu dari tangan
            array_splice($hand, $cardIndex, 1);
            $handState->update(['state_data' => ['cards' => $hand]]);

            // Tambah ke discard pile
            $discardPile[] = $card;
            $discardState->update(['state_data' => ['cards' => $discardPile]]);

            // Update warna saat ini
            $newColor = $chosenColor ?? $parsedCard['color'];
            $metaData = $session->meta_data;
            $metaData['current_color'] = $newColor;

            // Log action
            GameAction::log(
                $session->id,
                $userId,
                GameAction::ACTION_PLAY_CARD,
                $session->turn_number,
                ['card' => $card, 'chosen_color' => $chosenColor]
            );

            // Cek menang
            if (count($hand) === 0) {
                return $this->handleWin($session, $userId);
            }

            // Handle efek kartu aksi
            $skipNext = false;
            $drawAmount = 0;

            switch ($parsedCard['type']) {
                case 'reverse':
                    $session->reverseDirection();
                    // Di 2 pemain, reverse = skip
                    if ($session->room->roomPlayers()->active()->count() === 2) {
                        $skipNext = true;
                    }
                    break;

                case 'skip':
                    $skipNext = true;
                    break;

                case 'draw': // +2
                    $drawAmount = 2;
                    $skipNext = true;
                    break;

                case 'wild_draw': // +4
                    $drawAmount = 4;
                    $skipNext = true;
                    break;
            }

            $session->update(['meta_data' => $metaData]);

            // Handle draw cards untuk pemain berikutnya
            if ($drawAmount > 0) {
                $session->nextPlayer();
                $this->drawCards($session, $session->current_player_id, $drawAmount);
            }

            // Pindah giliran
            $skipCount = $skipNext ? 2 : 1;
            if ($drawAmount === 0) {
                $session->nextPlayer($skipCount);
            } elseif ($skipNext) {
                $session->nextPlayer();
            }

            return [
                'success' => true,
                'card' => $card,
                'cards_left' => count($hand),
                'next_player_id' => $session->current_player_id,
                'current_color' => $newColor,
                'effect' => $parsedCard['type']
            ];
        });
    }

    /**
     * Ambil kartu dari deck
     */
    public function drawCards(GameSession $session, int $userId, int $count = 1): array
    {
        $deckState = $session->gameStates()
            ->where('state_type', GameState::TYPE_DECK)
            ->first();

        $deck = $deckState->state_data['cards'] ?? [];

        // Jika deck habis, shuffle discard pile
        if (count($deck) < $count) {
            $deck = $this->reshuffleDiscard($session, $deck);
        }

        // Ambil kartu
        $drawnCards = array_splice($deck, 0, min($count, count($deck)));

        // Update deck
        $deckState->update(['state_data' => ['cards' => $deck]]);

        // Tambah ke tangan pemain
        $handState = $session->gameStates()
            ->where('user_id', $userId)
            ->where('state_type', GameState::TYPE_HAND)
            ->first();

        $hand = $handState->state_data['cards'] ?? [];
        $hand = array_merge($hand, $drawnCards);
        $handState->update(['state_data' => ['cards' => $hand]]);

        return $drawnCards;
    }

    /**
     * Pemain mengambil kartu (action)
     */
    public function playerDraw(GameSession $session, int $userId): array
    {
        if ($session->current_player_id !== $userId) {
            return ['success' => false, 'error' => 'Bukan giliran kamu!'];
        }

        $drawnCards = $this->drawCards($session, $userId, 1);

        // Log action
        GameAction::log(
            $session->id,
            $userId,
            GameAction::ACTION_DRAW_CARD,
            $session->turn_number,
            ['count' => 1]
        );

        // Cek apakah kartu bisa langsung dimainkan
        $discardState = $session->gameStates()
            ->where('state_type', GameState::TYPE_DISCARD)
            ->first();
        $topCard = end($discardState->state_data['cards']);
        $currentColor = $session->meta_data['current_color'] ?? null;

        $canPlay = !empty($drawnCards) && $this->canPlayCard($drawnCards[0], $topCard, $currentColor);

        // Jika tidak bisa main, giliran pindah
        if (!$canPlay) {
            $session->nextPlayer();
        }

        return [
            'success' => true,
            'drawn_cards' => $drawnCards,
            'can_play' => $canPlay,
            'next_player_id' => $session->current_player_id
        ];
    }

    /**
     * Reshuffle discard pile menjadi deck baru
     */
    protected function reshuffleDiscard(GameSession $session, array $currentDeck): array
    {
        $discardState = $session->gameStates()
            ->where('state_type', GameState::TYPE_DISCARD)
            ->first();

        $discardPile = $discardState->state_data['cards'] ?? [];

        // Simpan kartu teratas
        $topCard = array_pop($discardPile);

        // Shuffle sisa kartu
        shuffle($discardPile);

        // Gabung dengan deck yang ada
        $newDeck = array_merge($currentDeck, $discardPile);

        // Update discard pile (hanya kartu teratas)
        $discardState->update(['state_data' => ['cards' => [$topCard]]]);

        return $newDeck;
    }

    /**
     * Handle pemain menang
     */
    protected function handleWin(GameSession $session, int $winnerId): array
    {
        $session->update(['phase' => 'finished']);
        $session->room->update(['status' => 'finished']);

        // Update skor
        $session->room->roomPlayers()
            ->where('user_id', $winnerId)
            ->increment('score', 10);

        $winner = $session->room->roomPlayers()
            ->where('user_id', $winnerId)
            ->with('user')
            ->first();

        return [
            'success' => true,
            'game_over' => true,
            'winner' => [
                'id' => $winnerId,
                'name' => $winner->user->name
            ]
        ];
    }

    /**
     * Teriak UNO!
     */
    public function sayUno(GameSession $session, int $userId): array
    {
        $handState = $session->gameStates()
            ->where('user_id', $userId)
            ->where('state_type', GameState::TYPE_HAND)
            ->first();

        $cardCount = count($handState->state_data['cards'] ?? []);

        if ($cardCount !== 1) {
            return ['success' => false, 'error' => 'Kamu harus punya 1 kartu untuk teriak UNO!'];
        }

        GameAction::log(
            $session->id,
            $userId,
            GameAction::ACTION_SAY_UNO,
            $session->turn_number,
            []
        );

        return ['success' => true, 'message' => 'UNO!'];
    }

    /**
     * Ambil status game
     */
    public function getGameStatus(GameSession $session, int $userId): array
    {
        // Tangan pemain sendiri
        $handState = $session->gameStates()
            ->where('user_id', $userId)
            ->where('state_type', GameState::TYPE_HAND)
            ->first();

        // Discard pile
        $discardState = $session->gameStates()
            ->where('state_type', GameState::TYPE_DISCARD)
            ->first();

        // Deck count
        $deckState = $session->gameStates()
            ->where('state_type', GameState::TYPE_DECK)
            ->first();

        // Jumlah kartu pemain lain
        $otherPlayersCards = $session->gameStates()
            ->where('state_type', GameState::TYPE_HAND)
            ->where('user_id', '!=', $userId)
            ->with('user')
            ->get()
            ->map(fn($state) => [
                'user_id' => $state->user_id,
                'name' => $state->user->name,
                'card_count' => count($state->state_data['cards'] ?? [])
            ]);

        $discardCards = $discardState->state_data['cards'] ?? [];

        return [
            'hand' => $handState->state_data['cards'] ?? [],
            'top_card' => end($discardCards) ?: null,
            'current_color' => $session->meta_data['current_color'] ?? null,
            'deck_count' => count($deckState->state_data['cards'] ?? []),
            'other_players' => $otherPlayersCards,
            'current_player_id' => $session->current_player_id,
            'direction' => $session->direction,
            'turn_number' => $session->turn_number,
            'phase' => $session->phase
        ];
    }

    /**
     * Ambil path gambar kartu
     */
    public function getCardImagePath(string $card): string
    {
        // Kartu format: Color_Value -> resources/assets/Uno Game Assets/Color_Value.png
        return "assets/Uno Game Assets/{$card}.png";
    }
}
