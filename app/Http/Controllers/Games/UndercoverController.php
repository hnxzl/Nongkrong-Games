<?php

namespace App\Http\Controllers\Games;

use App\Events\GameStateUpdated;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\Games\UndercoverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UndercoverController extends Controller
{
    protected UndercoverService $gameService;

    public function __construct(UndercoverService $gameService)
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
        $gameStatus = $this->gameService->getGameStatus($session, Auth::id());

        return view('games.undercover', compact('room', 'session', 'gameStatus'));
    }

    /**
     * Mulai fase voting
     */
    public function startVoting(string $code)
    {
        $room = Room::where('code', $code)->firstOrFail();
        $session = $room->activeSession;

        // Hanya host yang bisa memulai voting
        if ($room->host_id !== Auth::id()) {
            return response()->json(['error' => 'Hanya host yang bisa memulai voting'], 403);
        }

        $result = $this->gameService->startVoting($session);

        broadcast(new GameStateUpdated($code, 'voting_started', [
            'time_limit' => $result['time_limit'],
        ]));

        return response()->json($result);
    }

    /**
     * Submit vote
     */
    public function vote(Request $request, string $code)
    {
        $validated = $request->validate([
            'target_id' => 'required|integer',
        ]);

        $room = Room::where('code', $code)->firstOrFail();
        $session = $room->activeSession;

        $result = $this->gameService->submitVote($session, Auth::id(), $validated['target_id']);

        return response()->json($result);
    }

    /**
     * Proses hasil voting
     */
    public function processVoting(string $code)
    {
        $room = Room::where('code', $code)->firstOrFail();
        $session = $room->activeSession;

        // Hanya host
        if ($room->host_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $result = $this->gameService->processVotingResult($session);

        broadcast(new GameStateUpdated($code, 'voting_result', $result));

        return response()->json($result);
    }

    /**
     * Mr. White menebak kata
     */
    public function mrWhiteGuess(Request $request, string $code)
    {
        $validated = $request->validate([
            'word' => 'required|string|max:100',
        ]);

        $room = Room::where('code', $code)->firstOrFail();
        $session = $room->activeSession;

        $result = $this->gameService->mrWhiteGuess($session, Auth::id(), $validated['word']);

        broadcast(new GameStateUpdated($code, 'mr_white_guess', [
            'word' => $validated['word'],
            'correct' => $result['game_over'] && ($result['winning_team'] ?? '') === 'mr_white',
            'game_over' => $result['game_over'] ?? false,
            'result' => $result,
        ]));

        return response()->json($result);
    }

    /**
     * Get status game
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
}
