<?php

namespace App\Http\Controllers\Games;

use App\Events\GameStateUpdated;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\Games\UnoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UnoController extends Controller
{
    protected UnoService $gameService;

    public function __construct(UnoService $gameService)
    {
        $this->gameService = $gameService;
    }

    /**
     * Tampilkan halaman game Uno
     */
    public function show(string $code)
    {
        $room = Room::where('code', $code)
            ->with(['activeSession', 'roomPlayers.user'])
            ->firstOrFail();

        if (!$room->activeSession) {
            return redirect()->route('rooms.lobby', $code)
                ->withErrors(['error' => 'Game belum dimulai']);
        }

        $session = $room->activeSession;
        $gameStatus = $this->gameService->getGameStatus($session, Auth::id());

        $players = $room->roomPlayers()
            ->with('user')
            ->orderBy('turn_order')
            ->get()
            ->map(fn($p) => [
                'id' => $p->user_id,
                'name' => $p->user->name,
                'is_current' => $p->user_id === $session->current_player_id,
            ]);

        return view('games.uno', compact('room', 'session', 'gameStatus', 'players'));
    }

    /**
     * Mainkan kartu
     */
    public function playCard(Request $request, string $code)
    {
        $validated = $request->validate([
            'card' => 'required|string',
            'chosen_color' => 'nullable|string|in:Red,Blue,Green,Yellow',
        ]);

        $room = Room::where('code', $code)->firstOrFail();
        $session = $room->activeSession;

        if (!$session) {
            return response()->json(['error' => 'Game tidak ditemukan'], 404);
        }

        $result = $this->gameService->playCard(
            $session,
            Auth::id(),
            $validated['card'],
            $validated['chosen_color'] ?? null
        );

        if ($result['success']) {
            broadcast(new GameStateUpdated($code, 'card_played', [
                'player_id' => Auth::id(),
                'player_name' => Auth::user()->name,
                'card' => $result['card'],
                'cards_left' => $result['cards_left'] ?? null,
                'next_player_id' => $result['next_player_id'] ?? null,
                'current_color' => $result['current_color'] ?? null,
                'effect' => $result['effect'] ?? null,
                'game_over' => $result['game_over'] ?? false,
                'winner' => $result['winner'] ?? null,
            ]));
        }

        return response()->json($result);
    }

    /**
     * Ambil kartu dari deck
     */
    public function drawCard(string $code)
    {
        $room = Room::where('code', $code)->firstOrFail();
        $session = $room->activeSession;

        if (!$session) {
            return response()->json(['error' => 'Game tidak ditemukan'], 404);
        }

        $result = $this->gameService->playerDraw($session, Auth::id());

        if ($result['success']) {
            broadcast(new GameStateUpdated($code, 'card_drawn', [
                'player_id' => Auth::id(),
                'player_name' => Auth::user()->name,
                'can_play' => $result['can_play'],
                'next_player_id' => $result['next_player_id'],
            ]))->toOthers(); // Tidak broadcast ke pemain yang ambil
        }

        return response()->json($result);
    }

    /**
     * Teriak UNO!
     */
    public function sayUno(string $code)
    {
        $room = Room::where('code', $code)->firstOrFail();
        $session = $room->activeSession;

        if (!$session) {
            return response()->json(['error' => 'Game tidak ditemukan'], 404);
        }

        $result = $this->gameService->sayUno($session, Auth::id());

        if ($result['success']) {
            broadcast(new GameStateUpdated($code, 'uno_called', [
                'player_id' => Auth::id(),
                'player_name' => Auth::user()->name,
            ]));
        }

        return response()->json($result);
    }

    /**
     * Get status game saat ini (untuk polling/refresh)
     */
    public function status(string $code)
    {
        $room = Room::where('code', $code)->firstOrFail();
        $session = $room->activeSession;

        if (!$session) {
            return response()->json(['error' => 'Game tidak ditemukan'], 404);
        }

        return response()->json($this->gameService->getGameStatus($session, Auth::id()));
    }

    /**
     * Get path gambar kartu
     */
    public function cardImage(string $card)
    {
        $path = $this->gameService->getCardImagePath($card);
        return response()->json(['path' => asset($path)]);
    }
}
