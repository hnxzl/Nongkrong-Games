@extends('layouts.app')

@section('title', 'ABC 5 Dasar - Tongkrongan Games')

@section('content')
<div class="flex-1 flex flex-col" 
     x-data="abcDasarGame()"
     x-init="init()">
    
    {{-- Top Bar --}}
    <header class="flex items-center justify-between p-3 bg-slate-800/80">
        <div class="flex items-center gap-2">
            <span class="text-lg">🔤</span>
            <span class="font-bold">ABC 5 Dasar</span>
        </div>
        
        <div class="text-sm">
            Ronde: <span class="font-bold" x-text="round">{{ $gameStatus['round'] }}</span>/{{ $gameStatus['total_rounds'] }}
        </div>
    </header>

    {{-- Letter & Category Display --}}
    <div class="p-4">
        <div class="card-game text-center">
            <p class="text-sm text-slate-400 mb-2">Huruf</p>
            <div class="w-24 h-24 bg-gradient-to-br from-orange-500 to-amber-400 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="text-5xl font-bold" x-text="letter">{{ $gameStatus['letter'] }}</span>
            </div>
            
            <p class="text-sm text-slate-400 mb-2">Kategori</p>
            <div class="bg-slate-700/50 rounded-xl px-4 py-3">
                <p class="text-xl font-bold" x-text="currentCategory">{{ $gameStatus['current_category'] }}</p>
            </div>
        </div>
    </div>

    {{-- Players Score --}}
    <div class="px-4 mb-4">
        <div class="flex gap-2 overflow-x-auto no-scrollbar">
            <template x-for="player in players" :key="player.id">
                <div class="flex-shrink-0 bg-slate-800/50 rounded-lg p-3 min-w-[80px] text-center">
                    <div class="avatar avatar-sm mx-auto mb-1">
                        <span x-text="player.name.charAt(0).toUpperCase()"></span>
                    </div>
                    <p class="text-xs truncate" x-text="player.name"></p>
                    <p class="text-lg font-bold text-primary-400" x-text="player.score">0</p>
                </div>
            </template>
        </div>
    </div>

    {{-- Game Phase Area --}}
    <div class="flex-1 flex flex-col items-center justify-center p-4">
        {{-- Playing Phase: Show buzzer --}}
        <div x-show="phase === 'playing'" class="text-center">
            <button 
                @click="claimAnswer()"
                :disabled="answeringPlayerId !== null"
                class="w-40 h-40 rounded-full bg-gradient-to-br from-red-500 to-red-600 
                       flex items-center justify-center text-white font-bold text-2xl
                       shadow-2xl active:scale-95 transition-transform
                       disabled:opacity-50 disabled:cursor-not-allowed
                       hover:from-red-400 hover:to-red-500">
                <span x-show="!answeringPlayerId">JAWAB!</span>
                <div x-show="answeringPlayerId" class="spinner w-8 h-8"></div>
            </button>
            <p class="text-slate-400 mt-4">Tekan tombol untuk menjawab!</p>
        </div>

        {{-- Answering Phase --}}
        <div x-show="phase === 'answering'" class="w-full max-w-sm text-center">
            <div x-show="answeringPlayerId === myId">
                {{-- Timer --}}
                <div x-data="timer(10)" 
                     x-init="start()"
                     @timer-ended="handleAnswerTimeout()"
                     class="timer mb-4 mx-auto" 
                     :class="timerClass">
                    <span x-text="displayTime"></span>
                </div>
                
                <form @submit.prevent="submitAnswer()" class="space-y-3">
                    <input 
                        type="text" 
                        x-model="myAnswer"
                        class="input text-center text-xl"
                        placeholder="Ketik jawabanmu..."
                        autofocus
                    >
                    <button type="submit" class="btn-primary w-full btn-lg">
                        Kirim!
                    </button>
                </form>
            </div>
            
            <div x-show="answeringPlayerId !== myId" class="text-center">
                <div class="spinner w-12 h-12 mx-auto mb-4"></div>
                <p class="text-lg">
                    <span class="font-bold" x-text="answeringPlayerName"></span> sedang menjawab...
                </p>
            </div>
        </div>

        {{-- Scoring Phase --}}
        <div x-show="phase === 'scoring'" class="w-full max-w-sm text-center">
            <div class="card mb-4">
                <p class="text-sm text-slate-400 mb-2">Jawaban:</p>
                <p class="text-2xl font-bold" x-text="lastAnswer">-</p>
                <p class="text-sm text-slate-400 mt-2">
                    oleh <span class="font-medium" x-text="answeringPlayerName"></span>
                </p>
            </div>

            <p class="text-slate-400 mb-4">Apakah jawaban ini benar?</p>

            <div x-show="answeringPlayerId !== myId" class="flex gap-3">
                <button 
                    @click="vote(true)"
                    :disabled="hasVoted"
                    class="btn-success flex-1 btn-lg disabled:opacity-50">
                    ✅ Benar
                </button>
                <button 
                    @click="vote(false)"
                    :disabled="hasVoted"
                    class="btn-danger flex-1 btn-lg disabled:opacity-50">
                    ❌ Salah
                </button>
            </div>

            <div x-show="answeringPlayerId === myId" class="text-slate-400">
                Menunggu voting pemain lain...
            </div>

            @if($room->host_id === Auth::id())
            <button @click="processVoting()" class="btn-ghost w-full mt-4">
                Proses Voting
            </button>
            @endif
        </div>
    </div>

    {{-- Game Over Modal --}}
    <div x-show="gameOver" 
         x-transition
         class="fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4">
        <div class="card max-w-sm w-full text-center">
            <div class="text-6xl mb-4">🏆</div>
            <h2 class="text-2xl font-bold mb-4">Game Selesai!</h2>
            
            <div class="space-y-2 mb-6">
                <template x-for="(player, index) in rankings" :key="player.id">
                    <div class="flex items-center gap-3 bg-slate-700/50 rounded-lg p-3">
                        <span class="text-2xl" x-text="index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : (index + 1)"></span>
                        <span class="flex-1 font-medium" x-text="player.name"></span>
                        <span class="text-primary-400 font-bold" x-text="player.score + ' poin'"></span>
                    </div>
                </template>
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
function abcDasarGame() {
    return {
        roomCode: '{{ $room->code }}',
        phase: '{{ $gameStatus['phase'] }}',
        round: {{ $gameStatus['round'] }},
        letter: '{{ $gameStatus['letter'] }}',
        currentCategory: '{{ $gameStatus['current_category'] }}',
        players: @json($gameStatus['players']),
        answeringPlayerId: {{ $gameStatus['answering_player_id'] ?? 'null' }},
        lastAnswer: '{{ $gameStatus['last_answer'] ?? '' }}',
        myId: {{ Auth::id() }},
        isHost: {{ $room->host_id === Auth::id() ? 'true' : 'false' }},

        myAnswer: '',
        hasVoted: false,
        gameOver: false,
        rankings: [],
        answeringPlayerName: '',

        init() {
            this.updateAnsweringPlayerName();

            if (window.Echo) {
                window.Echo.join(`room.${this.roomCode}`)
                    .listen('.game.state.updated', (e) => {
                        this.handleGameUpdate(e);
                    });
            }

            setInterval(() => this.fetchStatus(), 5000);
        },

        updateAnsweringPlayerName() {
            const player = this.players.find(p => p.id === this.answeringPlayerId);
            this.answeringPlayerName = player?.name || '';
        },

        async claimAnswer() {
            if (this.answeringPlayerId) return;

            try {
                const response = await fetch(`/games/abc-dasar/${this.roomCode}/claim`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const data = await response.json();
                if (data.success) {
                    this.phase = 'answering';
                    this.answeringPlayerId = this.myId;
                }
            } catch (error) {
                console.error('Claim answer error:', error);
            }
        },

        async submitAnswer() {
            if (!this.myAnswer) return;

            try {
                const response = await fetch(`/games/abc-dasar/${this.roomCode}/submit`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ answer: this.myAnswer })
                });

                const data = await response.json();
                if (data.success) {
                    this.lastAnswer = this.myAnswer;
                    this.myAnswer = '';
                }
            } catch (error) {
                console.error('Submit answer error:', error);
            }
        },

        async handleAnswerTimeout() {
            if (this.answeringPlayerId !== this.myId) return;

            try {
                await fetch(`/games/abc-dasar/${this.roomCode}/timeout`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
            } catch (error) {
                console.error('Timeout error:', error);
            }
        },

        async vote(isCorrect) {
            if (this.hasVoted || this.answeringPlayerId === this.myId) return;

            try {
                const response = await fetch(`/games/abc-dasar/${this.roomCode}/vote`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ is_correct: isCorrect })
                });

                const data = await response.json();
                if (data.success) {
                    this.hasVoted = true;
                }
            } catch (error) {
                console.error('Vote error:', error);
            }
        },

        async processVoting() {
            try {
                await fetch(`/games/abc-dasar/${this.roomCode}/process-voting`, {
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

        async fetchStatus() {
            try {
                const response = await fetch(`/games/abc-dasar/${this.roomCode}/status`);
                const data = await response.json();

                this.phase = data.phase;
                this.round = data.round;
                this.letter = data.letter;
                this.currentCategory = data.current_category;
                this.players = data.players;
                this.answeringPlayerId = data.answering_player_id;
                this.lastAnswer = data.last_answer || '';
                this.updateAnsweringPlayerName();
            } catch (error) {
                console.error('Fetch status error:', error);
            }
        },

        handleGameUpdate(event) {
            const { type, data } = event;

            switch (type) {
                case 'answer_claimed':
                    this.phase = 'answering';
                    this.answeringPlayerId = data.player_id;
                    this.answeringPlayerName = data.player_name;
                    break;

                case 'answer_submitted':
                    this.phase = 'scoring';
                    this.lastAnswer = data.answer;
                    this.hasVoted = false;
                    break;

                case 'answer_timeout':
                    this.phase = 'playing';
                    this.answeringPlayerId = null;
                    break;

                case 'voting_result':
                    if (data.game_over) {
                        this.gameOver = true;
                        this.rankings = data.rankings;
                    } else {
                        this.phase = 'playing';
                        this.answeringPlayerId = null;
                        this.hasVoted = false;
                        
                        if (data.new_round) {
                            this.round = data.round;
                            this.letter = data.letter;
                        }
                        this.currentCategory = data.category;
                    }
                    break;
            }
        }
    };
}
</script>
@endpush
