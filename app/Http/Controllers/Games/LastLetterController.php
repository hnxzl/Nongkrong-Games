<?php

namespace App\Http\Controllers\Games;

use App\Events\GameStateUpdated;
use App\Http\Controllers\Controller;
use App\Models\GameSession;
use App\Models\Room;
use App\Services\Games\LastLetterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LastLetterController extends Controller
{
    protected LastLetterService $gameService;

    public function __construct(LastLetterService $gameService)
    {
        $this->gameService = $gameService;
    }

    /**
     * Tampilkan halaman game
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
        $gameStatus = $this->gameService->getGameStatus($session);

        $players = $room->roomPlayers()
            ->with('user')
            ->orderBy('turn_order')
            ->get()
            ->map(fn($p) => [
                'id' => $p->user_id,
                'name' => $p->user->name,
                'status' => $p->status,
                'is_current' => $p->user_id === $session->current_player_id,
            ]);

        return view('games.last-letter', compact('room', 'session', 'gameStatus', 'players'));
    }

    /**
     * Submit kata
     */
    public function submitWord(Request $request, string $code)
    {
        $validated = $request->validate([
            'word' => 'required|string|max:100',
        ]);

        $room = Room::where('code', $code)->firstOrFail();
        $session = $room->activeSession;

        if (!$session) {
            return response()->json(['error' => 'Game tidak ditemukan'], 404);
        }

        $result = $this->gameService->submitWord($session, Auth::id(), $validated['word']);

        if ($result['success']) {
            // Broadcast update
            broadcast(new GameStateUpdated($code, 'word_submitted', [
                'word' => $result['word'],
                'definition' => $result['definition'] ?? null,
                'player_id' => Auth::id(),
                'player_name' => Auth::user()->name,
                'next_player_id' => $result['next_player_id'],
                'last_letter' => $result['last_letter'],
            ]));
        }

        return response()->json($result);
    }

    /**
     * Handle timeout
     */
    public function timeout(string $code)
    {
        $room = Room::where('code', $code)->firstOrFail();
        $session = $room->activeSession;

        // Hanya bisa di-trigger oleh current player atau host
        if ($session->current_player_id !== Auth::id() && $room->host_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $result = $this->gameService->handleTimeout($session);

        // Broadcast update
        broadcast(new GameStateUpdated($code, 'player_timeout', [
            'eliminated_player_id' => $result['eliminated_player_id'] ?? null,
            'game_over' => $result['game_over'],
            'winner' => $result['winner'] ?? null,
            'next_player_id' => $result['next_player_id'] ?? null,
        ]));

        return response()->json($result);
    }

    /**
     * Get status game saat ini
     */
    public function status(string $code)
    {
        $room = Room::where('code', $code)->firstOrFail();
        $session = $room->activeSession;

        if (!$session) {
            return response()->json(['error' => 'Game tidak ditemukan'], 404);
        }

        return response()->json($this->gameService->getGameStatus($session));
    }
}
