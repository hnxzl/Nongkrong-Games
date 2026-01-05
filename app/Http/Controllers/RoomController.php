<?php

namespace App\Http\Controllers;

use App\Events\GameStarted;
use App\Events\PlayerJoined;
use App\Events\PlayerLeft;
use App\Models\Room;
use App\Models\RoomPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    /**
     * Tampilkan halaman home dengan pilihan game
     */
    public function index()
    {
        return view('home');
    }

    /**
     * Tampilkan form buat room
     */
    public function create(string $gameType)
    {
        $validGameTypes = ['uno', 'undercover', 'last_letter', 'abc_dasar'];
        
        if (!in_array($gameType, $validGameTypes)) {
            abort(404);
        }

        return view('rooms.create', compact('gameType'));
    }

    /**
     * Simpan room baru
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'game_type' => ['required', Rule::in(['uno', 'undercover', 'last_letter', 'abc_dasar'])],
        ]);

        $room = Room::create([
            'name' => $validated['name'],
            'game_type' => $validated['game_type'],
            'host_id' => Auth::id(),
            'max_players' => config("games.room.max_players.{$validated['game_type']}", 10),
            'min_players' => config("games.room.min_players.{$validated['game_type']}", 2),
        ]);

        // Host otomatis join room
        $room->players()->attach(Auth::id(), [
            'is_ready' => true,
            'status' => 'active',
        ]);

        return redirect()->route('rooms.lobby', $room->code);
    }

    /**
     * Tampilkan form join room
     */
    public function joinForm()
    {
        return view('rooms.join');
    }

    /**
     * Join ke room dengan kode
     */
    public function join(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $room = Room::where('code', strtoupper($validated['code']))->first();

        if (!$room) {
            return back()->withErrors(['code' => 'Room tidak ditemukan']);
        }

        if ($room->status !== 'waiting') {
            return back()->withErrors(['code' => 'Game sudah dimulai']);
        }

        if ($room->isFull()) {
            return back()->withErrors(['code' => 'Room sudah penuh']);
        }

        // Cek jika sudah di room ini
        if ($room->players()->where('user_id', Auth::id())->exists()) {
            return redirect()->route('rooms.lobby', $room->code);
        }

        // Join room
        $room->players()->attach(Auth::id(), [
            'is_ready' => false,
            'status' => 'active',
        ]);

        // Broadcast event
        broadcast(new PlayerJoined($room, [
            'id' => Auth::id(),
            'name' => Auth::user()->name,
        ]))->toOthers();

        return redirect()->route('rooms.lobby', $room->code);
    }

    /**
     * Tampilkan lobby room
     */
    public function lobby(string $code)
    {
        $room = Room::where('code', $code)
            ->with(['host', 'players'])
            ->firstOrFail();

        // Cek akses
        if (!$room->players()->where('user_id', Auth::id())->exists()) {
            return redirect()->route('rooms.join-form')
                ->withErrors(['code' => 'Kamu tidak ada di room ini']);
        }

        $players = $room->roomPlayers()
            ->with('user')
            ->get()
            ->map(fn($rp) => [
                'id' => $rp->user_id,
                'name' => $rp->user->name,
                'is_ready' => $rp->is_ready,
                'is_host' => $rp->user_id === $room->host_id,
            ]);

        $isHost = $room->host_id === Auth::id();
        $myPlayer = $room->roomPlayers()->where('user_id', Auth::id())->first();

        return view('rooms.lobby', compact('room', 'players', 'isHost', 'myPlayer'));
    }

    /**
     * Toggle ready status
     */
    public function toggleReady(Request $request, string $code)
    {
        $room = Room::where('code', $code)->firstOrFail();

        $player = $room->roomPlayers()
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $player->update(['is_ready' => !$player->is_ready]);

        broadcast(new PlayerJoined($room, [
            'id' => Auth::id(),
            'name' => Auth::user()->name,
        ]))->toOthers();

        return response()->json(['is_ready' => $player->is_ready]);
    }

    /**
     * Mulai game (host only)
     */
    public function start(string $code)
    {
        $room = Room::where('code', $code)->firstOrFail();

        // Validasi host
        if ($room->host_id !== Auth::id()) {
            return response()->json(['error' => 'Hanya host yang bisa memulai game'], 403);
        }

        // Validasi bisa mulai
        if (!$room->canStartGame()) {
            return response()->json(['error' => 'Belum semua pemain ready atau jumlah pemain tidak cukup'], 400);
        }

        // Start game berdasarkan tipe
        $session = $this->startGameByType($room);

        // Broadcast
        broadcast(new GameStarted($session, $room->code));

        return response()->json([
            'success' => true,
            'redirect' => route('games.' . $room->game_type, $room->code),
        ]);
    }

    /**
     * Start game berdasarkan tipe
     */
    protected function startGameByType(Room $room)
    {
        return match($room->game_type) {
            'uno' => app(\App\Services\Games\UnoService::class)->startGame($room),
            'undercover' => app(\App\Services\Games\UndercoverService::class)->startGame($room),
            'last_letter' => app(\App\Services\Games\LastLetterService::class)->startGame($room),
            'abc_dasar' => app(\App\Services\Games\AbcDasarService::class)->startGame($room),
            default => throw new \Exception('Game type tidak valid'),
        };
    }

    /**
     * Keluar dari room
     */
    public function leave(string $code)
    {
        $room = Room::where('code', $code)->firstOrFail();
        $userId = Auth::id();

        // Hapus dari room
        $room->players()->detach($userId);

        // Jika host yang keluar, transfer ke pemain lain atau hapus room
        if ($room->host_id === $userId) {
            $newHost = $room->roomPlayers()->first();

            if ($newHost) {
                $room->update(['host_id' => $newHost->user_id]);
            } else {
                // Tidak ada pemain lagi, hapus room
                $room->delete();
            }
        }

        // Broadcast
        if ($room->exists) {
            broadcast(new PlayerLeft($room, $userId))->toOthers();
        }

        return redirect()->route('home');
    }
}
