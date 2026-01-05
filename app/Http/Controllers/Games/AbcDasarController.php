<?php

namespace App\Http\Controllers\Games;

use App\Events\GameStateUpdated;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\Games\AbcDasarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AbcDasarController extends Controller
{
    protected AbcDasarService $gameService;

    public function __construct(AbcDasarService $gameService)
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

        return view('games.abc-dasar', compact('room', 'session', 'gameStatus'));
    }

    /**
     * Claim untuk menjawab (tekan tombol "Jawab")
     */
    public function claimAnswer(string $code)
    {
        $room = Room::where('code', $code)->firstOrFail();
        $session = $room->activeSession;

        if (!$session) {
            return response()->json(['error' => 'Game tidak ditemukan'], 404);
        }

        $result = $this->gameService->claimAnswer($session, Auth::id());

        if ($result['success']) {
            broadcast(new GameStateUpdated($code, 'answer_claimed', [
                'player_id' => Auth::id(),
                'player_name' => Auth::user()->name,
                'time_limit' => $result['time_limit'],
            ]));
        }

        return response()->json($result);
    }

    /**
     * Submit jawaban
     */
    public function submitAnswer(Request $request, string $code)
    {
        $validated = $request->validate([
            'answer' => 'required|string|max:100',
        ]);

        $room = Room::where('code', $code)->firstOrFail();
        $session = $room->activeSession;

        if (!$session) {
            return response()->json(['error' => 'Game tidak ditemukan'], 404);
        }

        $result = $this->gameService->submitAnswer($session, Auth::id(), $validated['answer']);

        if ($result['success']) {
            broadcast(new GameStateUpdated($code, 'answer_submitted', [
                'player_id' => Auth::id(),
                'player_name' => Auth::user()->name,
                'answer' => $result['answer'],
                'category' => $result['category'],
            ]));
        }

        return response()->json($result);
    }

    /**
     * Handle timeout saat menjawab
     */
    public function answerTimeout(string $code)
    {
        $room = Room::where('code', $code)->firstOrFail();
        $session = $room->activeSession;

        $result = $this->gameService->handleAnswerTimeout($session);

        broadcast(new GameStateUpdated($code, 'answer_timeout', [
            'message' => 'Waktu habis!',
        ]));

        return response()->json($result);
    }

    /**
     * Vote jawaban (benar/salah)
     */
    public function vote(Request $request, string $code)
    {
        $validated = $request->validate([
            'is_correct' => 'required|boolean',
        ]);

        $room = Room::where('code', $code)->firstOrFail();
        $session = $room->activeSession;

        $result = $this->gameService->submitVote($session, Auth::id(), $validated['is_correct']);

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
     * Get status game
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
