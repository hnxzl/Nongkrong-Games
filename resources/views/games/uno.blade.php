@extends('layouts.app')

@section('title', 'UNO - Tongkrongan Games')

@section('content')
<div class="flex-1 flex flex-col" 
     x-data="unoGame()"
     x-init="init()">
    
    {{-- Top Bar --}}
    <header class="flex items-center justify-between p-3 bg-slate-800/80">
        <div class="flex items-center gap-2">
            <span class="text-lg">🃏</span>
            <span class="font-bold">UNO</span>
        </div>
        
        {{-- Deck Count --}}
        <div class="flex items-center gap-2 text-sm">
            <span>Deck:</span>
            <span class="font-bold" x-text="deckCount">{{ $gameStatus['deck_count'] }}</span>
        </div>

        {{-- Direction Indicator --}}
        <div class="flex items-center gap-1">
            <span x-text="direction === 'clockwise' ? '🔃' : '🔄'"></span>
        </div>
    </header>

    {{-- Other Players (top area) --}}
    <div class="flex-1 flex flex-col p-3 overflow-hidden">
        {{-- Other Players Cards Count --}}
        <div class="flex justify-center gap-4 mb-4 flex-wrap">
            <template x-for="player in otherPlayers" :key="player.user_id">
                <div :class="{'ring-2 ring-primary-400 ring-offset-2 ring-offset-slate-900': player.user_id === currentPlayerId}"
                     class="flex flex-col items-center bg-slate-800/50 rounded-lg p-2 min-w-[60px]">
                    <div class="avatar avatar-sm mb-1">
                        <span x-text="player.name.charAt(0).toUpperCase()"></span>
                    </div>
                    <p class="text-xs truncate max-w-[60px]" x-text="player.name"></p>
                    <p class="text-lg font-bold" x-text="player.card_count">0</p>
                </div>
            </template>
        </div>

        {{-- Center Play Area --}}
        <div class="flex-1 flex items-center justify-center">
            <div class="relative">
                {{-- Discard Pile (Top Card) --}}
                <div class="relative">
                    <img 
                        :src="getCardImage(topCard)" 
                        :alt="topCard"
                        class="uno-card w-24 h-36 object-contain shadow-2xl"
                        x-show="topCard"
                    >
                    
                    {{-- Current Color Indicator (for Wild) --}}
                    <div x-show="currentColor" 
                         :class="getColorClass(currentColor)"
                         class="absolute -bottom-2 -right-2 w-8 h-8 rounded-full border-2 border-white shadow-lg">
                    </div>
                </div>

                {{-- Current Turn Indicator --}}
                <div x-show="isMyTurn" 
                     class="absolute -top-8 left-1/2 -translate-x-1/2 bg-primary-500 text-white text-xs font-bold px-3 py-1 rounded-full animate-pulse">
                    Giliranmu!
                </div>
            </div>
        </div>
    </div>

    {{-- My Hand (Bottom) --}}
    <div class="bg-slate-800/90 pt-3 pb-safe-bottom">
        {{-- Action Buttons --}}
        <div class="flex justify-center gap-3 mb-3 px-3">
            <button 
                @click="drawCard()"
                :disabled="!isMyTurn || loading"
                class="btn-ghost btn-sm flex items-center gap-2 disabled:opacity-50">
                <span>📥</span>
                <span>Ambil Kartu</span>
            </button>
            
            <button 
                @click="sayUno()"
                :disabled="myHand.length !== 1"
                class="btn-danger btn-sm flex items-center gap-2 disabled:opacity-50">
                <span>🔊</span>
                <span>UNO!</span>
            </button>
        </div>

        {{-- Cards in Hand --}}
        <div class="card-hand">
            <template x-for="(card, index) in myHand" :key="index">
                <div 
                    @click="selectCard(card, index)"
                    :class="{
                        'selected': selectedCard === card,
                        'opacity-50': !canPlayCard(card) && isMyTurn
                    }"
                    class="uno-card flex-shrink-0 cursor-pointer">
                    <img 
                        :src="getCardImage(card)" 
                        :alt="card"
                        class="w-full h-full object-contain rounded-lg"
                    >
                </div>
            </template>
        </div>
    </div>

    {{-- Color Picker Modal (for Wild cards) --}}
    <div x-show="showColorPicker" 
         x-transition
         class="fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4">
        <div class="card max-w-xs w-full">
            <h3 class="text-center font-bold mb-4">Pilih Warna</h3>
            <div class="grid grid-cols-2 gap-3">
                <button @click="chooseColor('Red')" class="btn bg-uno-red text-white py-6 text-lg font-bold">
                    Merah
                </button>
                <button @click="chooseColor('Blue')" class="btn bg-uno-blue text-white py-6 text-lg font-bold">
                    Biru
                </button>
                <button @click="chooseColor('Green')" class="btn bg-uno-green text-white py-6 text-lg font-bold">
                    Hijau
                </button>
                <button @click="chooseColor('Yellow')" class="btn bg-uno-yellow text-slate-900 py-6 text-lg font-bold">
                    Kuning
                </button>
            </div>
            <button @click="showColorPicker = false; selectedCard = null" class="btn-ghost w-full mt-3">
                Batal
            </button>
        </div>
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
function unoGame() {
    return {
        roomCode: '{{ $room->code }}',
        myHand: @json($gameStatus['hand']),
        topCard: '{{ $gameStatus['top_card'] }}',
        currentColor: '{{ $gameStatus['current_color'] }}',
        deckCount: {{ $gameStatus['deck_count'] }},
        otherPlayers: @json($gameStatus['other_players']),
        currentPlayerId: {{ $gameStatus['current_player_id'] ?? 'null' }},
        direction: '{{ $gameStatus['direction'] }}',
        myId: {{ Auth::id() }},
        
        selectedCard: null,
        showColorPicker: false,
        pendingWildCard: null,
        loading: false,
        gameOver: false,
        winner: null,

        get isMyTurn() {
            return this.currentPlayerId === this.myId;
        },

        init() {
            // Subscribe to game updates
            if (window.Echo) {
                window.Echo.join(`room.${this.roomCode}`)
                    .listen('.game.state.updated', (e) => {
                        this.handleGameUpdate(e);
                    });
            }

            // Polling fallback
            setInterval(() => this.fetchStatus(), 5000);
        },

        getCardImage(card) {
            if (!card) return '';
            return `/images/uno-cards/${card}.png`;
        },

        getColorClass(color) {
            const classes = {
                'Red': 'bg-uno-red',
                'Blue': 'bg-uno-blue',
                'Green': 'bg-uno-green',
                'Yellow': 'bg-uno-yellow',
                'Black': 'bg-uno-black'
            };
            return classes[color] || 'bg-slate-500';
        },

        canPlayCard(card) {
            if (!this.topCard || !this.isMyTurn) return false;
            
            // Wild cards always playable
            if (card.startsWith('Wild')) return true;

            const [cardColor, cardValue] = card.split('_');
            const [topColor, topValue] = this.topCard.split('_');
            
            // Match current color (including wild chosen color)
            if (cardColor === this.currentColor) return true;
            
            // Match value
            if (cardValue === topValue) return true;
            
            return false;
        },

        selectCard(card, index) {
            if (!this.isMyTurn || this.loading) return;
            
            if (!this.canPlayCard(card)) {
                window.dispatchEvent(new CustomEvent('show-toast', {
                    detail: { message: 'Kartu tidak bisa dimainkan', type: 'error' }
                }));
                return;
            }

            this.selectedCard = card;

            // Check if wild card
            if (card.startsWith('Wild')) {
                this.pendingWildCard = card;
                this.showColorPicker = true;
            } else {
                this.playCard(card);
            }
        },

        chooseColor(color) {
            this.showColorPicker = false;
            if (this.pendingWildCard) {
                this.playCard(this.pendingWildCard, color);
                this.pendingWildCard = null;
            }
        },

        async playCard(card, chosenColor = null) {
            this.loading = true;
            try {
                const response = await fetch(`/games/uno/${this.roomCode}/play`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ card, chosen_color: chosenColor })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update local state
                    this.myHand = this.myHand.filter(c => c !== card);
                    this.topCard = card;
                    this.currentColor = data.current_color;
                    this.currentPlayerId = data.next_player_id;
                    
                    if (data.game_over) {
                        this.gameOver = true;
                        this.winner = data.winner;
                    }
                } else {
                    window.dispatchEvent(new CustomEvent('show-toast', {
                        detail: { message: data.error, type: 'error' }
                    }));
                }
            } catch (error) {
                console.error('Play card error:', error);
            } finally {
                this.loading = false;
                this.selectedCard = null;
            }
        },

        async drawCard() {
            if (!this.isMyTurn || this.loading) return;
            
            this.loading = true;
            try {
                const response = await fetch(`/games/uno/${this.roomCode}/draw`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.myHand.push(...data.drawn_cards);
                    this.deckCount--;
                    
                    if (!data.can_play) {
                        this.currentPlayerId = data.next_player_id;
                    }
                }
            } catch (error) {
                console.error('Draw card error:', error);
            } finally {
                this.loading = false;
            }
        },

        async sayUno() {
            try {
                const response = await fetch(`/games/uno/${this.roomCode}/uno`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    window.dispatchEvent(new CustomEvent('show-toast', {
                        detail: { message: 'UNO! 🎉', type: 'success' }
                    }));
                }
            } catch (error) {
                console.error('Say UNO error:', error);
            }
        },

        async fetchStatus() {
            try {
                const response = await fetch(`/games/uno/${this.roomCode}/status`);
                const data = await response.json();
                
                this.myHand = data.hand;
                this.topCard = data.top_card;
                this.currentColor = data.current_color;
                this.deckCount = data.deck_count;
                this.otherPlayers = data.other_players;
                this.currentPlayerId = data.current_player_id;
                this.direction = data.direction;
                
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
                case 'card_played':
                    this.topCard = data.card;
                    this.currentColor = data.current_color;
                    this.currentPlayerId = data.next_player_id;
                    
                    // Update other player card count
                    const player = this.otherPlayers.find(p => p.user_id === data.player_id);
                    if (player && data.cards_left !== null) {
                        player.card_count = data.cards_left;
                    }
                    
                    if (data.game_over) {
                        this.gameOver = true;
                        this.winner = data.winner;
                    }
                    break;
                    
                case 'card_drawn':
                    const drawnPlayer = this.otherPlayers.find(p => p.user_id === data.player_id);
                    if (drawnPlayer) {
                        drawnPlayer.card_count++;
                    }
                    this.currentPlayerId = data.next_player_id;
                    break;
                    
                case 'uno_called':
                    window.dispatchEvent(new CustomEvent('show-toast', {
                        detail: { message: `${data.player_name}: UNO! 🎉`, type: 'info' }
                    }));
                    break;
            }
        }
    };
}
</script>
@endpush
