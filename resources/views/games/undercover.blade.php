@extends('layouts.app')

@section('title', 'Undercover - Tongkrongan Games')

@section('content')
<div class="flex-1 flex flex-col" 
     x-data="undercoverGame()"
     x-init="init()">
    
    {{-- Top Bar --}}
    <header class="flex items-center justify-between p-3 bg-slate-800/80">
        <div class="flex items-center gap-2">
            <span class="text-lg">🕵️</span>
            <span class="font-bold">Undercover</span>
        </div>
        
        <div class="text-sm">
            Ronde: <span class="font-bold" x-text="round">{{ $gameStatus['round'] }}</span>
        </div>
    </header>

    {{-- My Word Card --}}
    <div class="p-4">
        <div class="card-game text-center">
            <p class="text-sm text-slate-400 mb-2">Kata Rahasiamu</p>
            <div x-show="myRole !== 'mr_white'" class="bg-gradient-to-r from-primary-500 to-accent-500 rounded-xl p-4">
                <p class="text-2xl font-bold" x-text="myWord || '???'">{{ $gameStatus['my_word'] ?? '???' }}</p>
            </div>
            <div x-show="myRole === 'mr_white'" class="bg-gradient-to-r from-slate-600 to-slate-700 rounded-xl p-4">
                <p class="text-2xl font-bold">???</p>
                <p class="text-sm text-slate-400 mt-1">Kamu Mr. White! Berpura-puralah.</p>
            </div>
            
            {{-- Role Badge --}}
            <div class="mt-3">
                <span x-show="myRole === 'civilian'" class="badge badge-success">Civilian</span>
                <span x-show="myRole === 'undercover'" class="badge badge-warning">Undercover</span>
                <span x-show="myRole === 'mr_white'" class="badge badge-danger">Mr. White</span>
            </div>
        </div>
    </div>

    {{-- Players Grid --}}
    <div class="flex-1 p-4 overflow-y-auto">
        <h3 class="font-semibold mb-3">Pemain</h3>
        <div class="grid grid-cols-2 gap-3">
            <template x-for="player in activePlayers" :key="player.id">
                <div 
                    @click="selectVote(player.id)"
                    :class="{
                        'ring-2 ring-primary-500': selectedVote === player.id,
                        'opacity-50 pointer-events-none': player.is_me
                    }"
                    class="card flex flex-col items-center py-4 cursor-pointer active:scale-95 transition-transform">
                    <div class="avatar mb-2">
                        <span x-text="player.name.charAt(0).toUpperCase()"></span>
                    </div>
                    <p class="font-medium text-sm" x-text="player.name"></p>
                    <span x-show="player.is_me" class="text-xs text-slate-400">(Kamu)</span>
                </div>
            </template>
        </div>

        {{-- Eliminated Players --}}
        <div x-show="eliminatedPlayers.length > 0" class="mt-4">
            <h4 class="text-sm text-slate-400 mb-2">Tereliminasi</h4>
            <div class="flex flex-wrap gap-2">
                <template x-for="player in eliminatedPlayers" :key="player.id">
                    <div class="flex items-center gap-2 bg-red-500/20 rounded-full px-3 py-1">
                        <span class="text-sm" x-text="player.name"></span>
                        <span class="text-xs text-slate-400" x-text="'(' + player.role + ')'"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Phase-specific UI --}}
    <div class="bg-slate-800/90 p-4 pb-safe-bottom">
        {{-- Discussion Phase --}}
        <div x-show="phase === 'discussion'" class="text-center">
            <div x-data="timer({{ $gameStatus['turn_time_limit'] }})" 
                 x-init="start()"
                 class="timer mb-3 mx-auto" 
                 :class="timerClass">
                <span x-text="displayTime"></span>
            </div>
            <p class="text-slate-400 mb-3">Diskusikan siapa yang mencurigakan!</p>
            
            @if($room->host_id === Auth::id())
            <button @click="startVoting()" class="btn-primary w-full">
                Mulai Voting
            </button>
            @else
            <p class="text-sm text-slate-400">Menunggu host memulai voting...</p>
            @endif
        </div>

        {{-- Voting Phase --}}
        <div x-show="phase === 'voting'" class="text-center">
            <p class="text-slate-400 mb-3">Pilih pemain yang ingin dieliminasi!</p>
            
            <button 
                @click="submitVote()"
                :disabled="!selectedVote || hasVoted"
                class="btn-primary w-full disabled:opacity-50">
                <span x-text="hasVoted ? 'Sudah Vote' : 'Vote'"></span>
            </button>

            @if($room->host_id === Auth::id())
            <button @click="processVoting()" class="btn-ghost w-full mt-2">
                Proses Voting
            </button>
            @endif
        </div>
    </div>

    {{-- Mr. White Guess Modal --}}
    <div x-show="showMrWhiteGuess" 
         x-transition
         class="fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4">
        <div class="card max-w-xs w-full">
            <h3 class="text-center font-bold mb-4">Mr. White Menebak!</h3>
            <p class="text-sm text-slate-400 text-center mb-4">
                Tebak kata yang dimiliki Civilian untuk menang!
            </p>
            <input 
                type="text" 
                x-model="mrWhiteGuess"
                class="input mb-3"
                placeholder="Ketik tebakan..."
            >
            <button @click="submitMrWhiteGuess()" class="btn-primary w-full">
                Tebak!
            </button>
        </div>
    </div>

    {{-- Game Over Modal --}}
    <div x-show="gameOver" 
         x-transition
         class="fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4">
        <div class="card max-w-sm w-full text-center">
            <div class="text-6xl mb-4">🎭</div>
            <h2 class="text-2xl font-bold mb-2">Game Selesai!</h2>
            <p class="text-lg mb-2">
                Pemenang: <span class="text-primary-400 font-bold" x-text="winningTeam"></span>
            </p>
            <div class="bg-slate-700/50 rounded-lg p-3 mb-4">
                <p class="text-sm text-slate-400">Kata Civilian</p>
                <p class="font-bold" x-text="revealedCivilianWord"></p>
                <p class="text-sm text-slate-400 mt-2">Kata Undercover</p>
                <p class="font-bold" x-text="revealedUndercoverWord"></p>
            </div>
            <a href="{{ route('home') }}" class="btn-primary w-full">
                Kembali ke Home
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function undercoverGame() {
    return {
        roomCode: '{{ $room->code }}',
        myRole: '{{ $gameStatus['my_role'] }}',
        myWord: '{{ $gameStatus['my_word'] ?? '' }}',
        phase: '{{ $gameStatus['phase'] }}',
        round: {{ $gameStatus['round'] }},
        activePlayers: @json($gameStatus['active_players']),
        eliminatedPlayers: @json($gameStatus['eliminated_players']),
        myId: {{ Auth::id() }},
        isHost: {{ $room->host_id === Auth::id() ? 'true' : 'false' }},

        selectedVote: null,
        hasVoted: false,
        showMrWhiteGuess: false,
        mrWhiteGuess: '',
        gameOver: false,
        winningTeam: '',
        revealedCivilianWord: '',
        revealedUndercoverWord: '',

        init() {
            if (window.Echo) {
                window.Echo.join(`room.${this.roomCode}`)
                    .listen('.game.state.updated', (e) => {
                        this.handleGameUpdate(e);
                    });
            }

            setInterval(() => this.fetchStatus(), 5000);
        },

        selectVote(playerId) {
            if (this.phase !== 'voting' || playerId === this.myId) return;
            this.selectedVote = playerId;
        },

        async startVoting() {
            try {
                await fetch(`/games/undercover/${this.roomCode}/start-voting`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
            } catch (error) {
                console.error('Start voting error:', error);
            }
        },

        async submitVote() {
            if (!this.selectedVote || this.hasVoted) return;

            try {
                const response = await fetch(`/games/undercover/${this.roomCode}/vote`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ target_id: this.selectedVote })
                });

                const data = await response.json();
                if (data.success) {
                    this.hasVoted = true;
                }
            } catch (error) {
                console.error('Submit vote error:', error);
            }
        },

        async processVoting() {
            try {
                await fetch(`/games/undercover/${this.roomCode}/process-voting`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
            } catch (error) {
                console.error('Process voting error:', error);
            }
        },

        async submitMrWhiteGuess() {
            if (!this.mrWhiteGuess) return;

            try {
                const response = await fetch(`/games/undercover/${this.roomCode}/mr-white-guess`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ word: this.mrWhiteGuess })
                });

                const data = await response.json();
                this.showMrWhiteGuess = false;
            } catch (error) {
                console.error('Mr White guess error:', error);
            }
        },

        async fetchStatus() {
            try {
                const response = await fetch(`/games/undercover/${this.roomCode}/status`);
                const data = await response.json();

                this.phase = data.phase;
                this.round = data.round;
                this.activePlayers = data.active_players;
                this.eliminatedPlayers = data.eliminated_players;
            } catch (error) {
                console.error('Fetch status error:', error);
            }
        },

        handleGameUpdate(event) {
            const { type, data } = event;

            switch (type) {
                case 'voting_started':
                    this.phase = 'voting';
                    this.selectedVote = null;
                    this.hasVoted = false;
                    break;

                case 'voting_result':
                    if (data.game_over) {
                        this.gameOver = true;
                        this.winningTeam = data.winning_team;
                        this.revealedCivilianWord = data.civilian_word;
                        this.revealedUndercoverWord = data.undercover_word;
                    } else if (data.mr_white_guess && data.eliminated?.id === this.myId) {
                        this.showMrWhiteGuess = true;
                    } else {
                        this.phase = 'discussion';
                        this.round = data.round || this.round + 1;
                        this.selectedVote = null;
                        this.hasVoted = false;
                        
                        if (data.eliminated) {
                            this.eliminatedPlayers.push(data.eliminated);
                            this.activePlayers = this.activePlayers.filter(p => p.id !== data.eliminated.id);
                        }
                    }
                    break;

                case 'mr_white_guess':
                    if (data.game_over) {
                        this.gameOver = true;
                        this.winningTeam = data.result.winning_team;
                        this.revealedCivilianWord = data.result.civilian_word;
                        this.revealedUndercoverWord = data.result.undercover_word;
                    }
                    break;
            }
        }
    };
}
</script>
@endpush
