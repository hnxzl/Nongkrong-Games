@extends('layouts.app')

@section('title', 'Last Letter - Tongkrongan Games')

@section('content')
<div class="flex-1 flex flex-col" 
     x-data="lastLetterGame()"
     x-init="init()">
    
    {{-- Top Bar --}}
    <header class="flex items-center justify-between p-3 bg-slate-800/80">
        <div class="flex items-center gap-2">
            <span class="text-lg">📝</span>
            <span class="font-bold">Last Letter</span>
        </div>
        
        {{-- Words Count --}}
        <div class="text-sm">
            Kata: <span class="font-bold" x-text="usedWordsCount">{{ $gameStatus['used_words_count'] }}</span>
        </div>
    </header>

    {{-- Players List --}}
    <div class="flex gap-2 p-3 overflow-x-auto no-scrollbar">
        @foreach($players as $player)
        <div class="flex-shrink-0 flex flex-col items-center p-2 rounded-lg min-w-[60px]
                    {{ $player['id'] === $gameStatus['current_player_id'] ? 'bg-primary-500/20 ring-2 ring-primary-500' : 'bg-slate-800/50' }}
                    {{ $player['status'] === 'eliminated' ? 'opacity-50' : '' }}">
            <div class="avatar avatar-sm mb-1">
                <span>{{ strtoupper(substr($player['name'], 0, 1)) }}</span>
            </div>
            <p class="text-xs truncate max-w-[60px]">{{ $player['name'] }}</p>
            @if($player['status'] === 'eliminated')
                <span class="text-xs text-red-400">❌</span>
            @endif
        </div>
        @endforeach
    </div>

    {{-- Main Game Area --}}
    <div class="flex-1 flex flex-col items-center justify-center p-4">
        {{-- Current Word Display --}}
        <div class="text-center mb-6">
            <p class="text-sm text-slate-400 mb-2">Kata Terakhir</p>
            <div class="bg-slate-800 rounded-2xl px-6 py-4 inline-block">
                <p class="text-3xl font-bold" x-text="currentWord">{{ $gameStatus['current_word'] }}</p>
            </div>
        </div>

        {{-- Next Letter Hint --}}
        <div class="text-center mb-6">
            <p class="text-sm text-slate-400 mb-2">Huruf Selanjutnya</p>
            <div class="w-20 h-20 bg-gradient-to-br from-primary-500 to-accent-500 rounded-full flex items-center justify-center">
                <span class="text-4xl font-bold uppercase" x-text="lastLetter">{{ $gameStatus['last_letter'] }}</span>
            </div>
        </div>

        {{-- Timer --}}
        <div x-data="timer({{ $gameStatus['turn_time_limit'] }})" 
             x-init="start()"
             @timer-ended="handleTimeout()"
             class="timer" 
             :class="timerClass">
            <span x-text="displayTime"></span>
        </div>

        {{-- Turn Indicator --}}
        <div class="mt-4 text-center">
            <p x-show="isMyTurn" class="text-primary-400 font-bold animate-pulse">
                Giliranmu! Ketik kata yang diawali huruf "<span x-text="lastLetter" class="uppercase"></span>"
            </p>
            <p x-show="!isMyTurn" class="text-slate-400">
                Giliran: <span class="font-bold" x-text="currentPlayerName"></span>
            </p>
        </div>
    </div>

    {{-- Input Area (Bottom) --}}
    <div class="bg-slate-800/90 p-4 pb-safe-bottom">
        <form @submit.prevent="submitWord()" class="flex gap-2">
            <input 
                type="text" 
                x-model="inputWord"
                :disabled="!isMyTurn || loading"
                class="input flex-1"
                placeholder="Ketik kata..."
                autocomplete="off"
                autocapitalize="none"
                spellcheck="false"
            >
            <button 
                type="submit"
                :disabled="!isMyTurn || !inputWord || loading"
                class="btn-primary disabled:opacity-50">
                <span x-show="!loading">Kirim</span>
                <div x-show="loading" class="spinner w-5 h-5"></div>
            </button>
        </form>
        
        {{-- Error Message --}}
        <p x-show="errorMessage" 
           x-text="errorMessage"
           x-transition
           class="text-red-400 text-sm mt-2 text-center"></p>
    </div>

    {{-- Game Over Modal --}}
    <div x-show="gameOver" 
         x-transition
         class="fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4">
        <div class="card max-w-xs w-full text-center">
            <div class="text-6xl mb-4">🎉</div>
            <h2 class="text-2xl font-bold mb-2">Game Selesai!</h2>
            <p class="text-lg mb-4">
                Pemenang: <span class="text-primary-400 font-bold" x-text="winner?.name"></span>
            </p>
            <a href="{{ route('home') }}" class="btn-primary w-full">
                Kembali ke Home
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function lastLetterGame() {
    return {
        roomCode: '{{ $room->code }}',
        currentWord: '{{ $gameStatus['current_word'] }}',
        lastLetter: '{{ $gameStatus['last_letter'] }}',
        usedWordsCount: {{ $gameStatus['used_words_count'] }},
        currentPlayerId: {{ $gameStatus['current_player_id'] ?? 'null' }},
        myId: {{ Auth::id() }},
        
        inputWord: '',
        loading: false,
        errorMessage: '',
        gameOver: false,
        winner: null,
        currentPlayerName: '',

        get isMyTurn() {
            return this.currentPlayerId === this.myId;
        },

        init() {
            this.updateCurrentPlayerName();
            
            // Subscribe to game updates
            if (window.Echo) {
                window.Echo.join(`room.${this.roomCode}`)
                    .listen('.game.state.updated', (e) => {
                        this.handleGameUpdate(e);
                    });
            }

            // Polling fallback
            setInterval(() => this.fetchStatus(), 3000);
        },

        updateCurrentPlayerName() {
            const players = @json($players);
            const current = players.find(p => p.id === this.currentPlayerId);
            this.currentPlayerName = current?.name || '';
        },

        async submitWord() {
            if (!this.isMyTurn || !this.inputWord || this.loading) return;

            this.loading = true;
            this.errorMessage = '';

            try {
                const response = await fetch(`/games/last-letter/${this.roomCode}/submit`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ word: this.inputWord })
                });

                const data = await response.json();

                if (data.success) {
                    this.currentWord = data.word;
                    this.lastLetter = data.last_letter;
                    this.usedWordsCount++;
                    this.currentPlayerId = data.next_player_id;
                    this.inputWord = '';
                    this.updateCurrentPlayerName();
                    
                    window.dispatchEvent(new CustomEvent('show-toast', {
                        detail: { message: '✅ Kata diterima!', type: 'success' }
                    }));
                } else {
                    this.errorMessage = data.error;
                }
            } catch (error) {
                console.error('Submit word error:', error);
                this.errorMessage = 'Terjadi kesalahan';
            } finally {
                this.loading = false;
            }
        },

        async handleTimeout() {
            if (!this.isMyTurn) return;
            
            try {
                const response = await fetch(`/games/last-letter/${this.roomCode}/timeout`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const data = await response.json();
                
                if (data.game_over) {
                    this.gameOver = true;
                    this.winner = data.winner;
                }
            } catch (error) {
                console.error('Timeout error:', error);
            }
        },

        async fetchStatus() {
            try {
                const response = await fetch(`/games/last-letter/${this.roomCode}/status`);
                const data = await response.json();

                this.currentWord = data.current_word;
                this.lastLetter = data.last_letter;
                this.usedWordsCount = data.used_words_count;
                this.currentPlayerId = data.current_player_id;
                this.updateCurrentPlayerName();

                if (data.phase === 'finished') {
                    this.gameOver = true;
                }
            } catch (error) {
                console.error('Fetch status error:', error);
            }
        },

        handleGameUpdate(event) {
            const { type, data } = event;

            switch (type) {
                case 'word_submitted':
                    this.currentWord = data.word;
                    this.lastLetter = data.last_letter;
                    this.usedWordsCount++;
                    this.currentPlayerId = data.next_player_id;
                    this.updateCurrentPlayerName();
                    
                    window.dispatchEvent(new CustomEvent('show-toast', {
                        detail: { message: `${data.player_name}: ${data.word}`, type: 'info' }
                    }));
                    break;

                case 'player_timeout':
                    if (data.game_over) {
                        this.gameOver = true;
                        this.winner = data.winner;
                    } else {
                        this.currentPlayerId = data.next_player_id;
                        this.updateCurrentPlayerName();
                    }
                    break;
            }
        }
    };
}
</script>
@endpush
