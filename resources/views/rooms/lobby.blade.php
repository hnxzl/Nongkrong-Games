@extends('layouts.app')

@section('title', 'Lobby - ' . $room->name)

@section('content')
<div class="flex-1 flex flex-col p-4" 
     x-data="lobbyRoom()"
     x-init="init()">
    
    {{-- Header --}}
    <header class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <form action="{{ route('rooms.leave', $room->code) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="btn-ghost p-2 -ml-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
            </form>
            <div>
                <h1 class="text-lg font-bold truncate max-w-[150px]">{{ $room->name }}</h1>
                <p class="text-xs text-slate-400">{{ ucfirst(str_replace('_', ' ', $room->game_type)) }}</p>
            </div>
        </div>
        
        {{-- Room Code --}}
        <div class="text-right">
            <p class="text-xs text-slate-400">Kode Room</p>
            <p class="text-xl font-bold font-mono tracking-wider text-primary-400">{{ $room->code }}</p>
        </div>
    </header>

    {{-- Share Button --}}
    <button @click="shareRoom()" class="card flex items-center justify-center gap-2 py-3 mb-4 active:scale-95 transition-transform">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
        </svg>
        <span class="font-medium">Share ke Teman</span>
    </button>

    {{-- Players List --}}
    <div class="card flex-1 overflow-hidden flex flex-col mb-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold">Pemain (<span x-text="players.length">{{ count($players) }}</span>/{{ $room->max_players }})</h3>
            <span class="badge badge-primary">Min. {{ $room->min_players }}</span>
        </div>
        
        <div class="flex-1 overflow-y-auto space-y-2">
            <template x-for="player in players" :key="player.id">
                <div class="flex items-center gap-3 p-2 rounded-lg bg-slate-700/50">
                    <div class="avatar avatar-sm">
                        <span x-text="player.name.charAt(0).toUpperCase()"></span>
                    </div>
                    <div class="flex-1">
                        <p class="font-medium" x-text="player.name"></p>
                        <p class="text-xs text-slate-400" x-show="player.is_host">👑 Host</p>
                    </div>
                    <div>
                        <span x-show="player.is_ready" class="badge badge-success">Ready</span>
                        <span x-show="!player.is_ready" class="badge badge-warning">Waiting</span>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- Action Buttons --}}
    <div class="space-y-3">
        @if($isHost)
            {{-- Host: Start Game --}}
            <button 
                @click="startGame()" 
                :disabled="!canStart"
                class="btn-primary btn-lg w-full disabled:opacity-50">
                <span x-show="!starting">🎮 Mulai Game</span>
                <span x-show="starting" class="flex items-center justify-center gap-2">
                    <div class="spinner w-5 h-5"></div>
                    Memulai...
                </span>
            </button>
            <p x-show="!allReady" class="text-center text-sm text-slate-400">
                Tunggu semua pemain ready
            </p>
        @else
            {{-- Player: Ready Toggle --}}
            <button 
                @click="toggleReady()" 
                :class="isReady ? 'btn-success' : 'btn-primary'"
                class="btn-lg w-full">
                <span x-text="isReady ? '✅ Ready!' : '🙋 Ready?'"></span>
            </button>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
function lobbyRoom() {
    return {
        players: @json($players),
        isHost: @json($isHost),
        isReady: @json($myPlayer->is_ready ?? false),
        roomCode: '{{ $room->code }}',
        starting: false,

        get allReady() {
            return this.players.every(p => p.is_ready);
        },

        get canStart() {
            return this.allReady && 
                   this.players.length >= {{ $room->min_players }} &&
                   !this.starting;
        },

        init() {
            // Subscribe to room channel
            if (window.Echo) {
                window.Echo.join(`room.${this.roomCode}`)
                    .here((users) => {
                        console.log('Users in room:', users);
                    })
                    .joining((user) => {
                        console.log('User joined:', user);
                        this.fetchPlayers();
                    })
                    .leaving((user) => {
                        console.log('User left:', user);
                        this.fetchPlayers();
                    })
                    .listen('PlayerJoined', (e) => {
                        this.players = e.players;
                    })
                    .listen('PlayerLeft', (e) => {
                        this.players = e.players;
                    })
                    .listen('GameStarted', (e) => {
                        window.location.href = e.game_url;
                    });
            }
        },

        async toggleReady() {
            try {
                const response = await fetch(`/rooms/${this.roomCode}/ready`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();
                this.isReady = data.is_ready;
            } catch (error) {
                console.error('Toggle ready error:', error);
            }
        },

        async startGame() {
            if (!this.canStart) return;
            
            this.starting = true;
            try {
                const response = await fetch(`/rooms/${this.roomCode}/start`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();
                
                if (data.redirect) {
                    window.location.href = data.redirect;
                } else if (data.error) {
                    alert(data.error);
                    this.starting = false;
                }
            } catch (error) {
                console.error('Start game error:', error);
                this.starting = false;
            }
        },

        async fetchPlayers() {
            // Refresh players list
            try {
                const response = await fetch(`/rooms/${this.roomCode}/status`);
                const data = await response.json();
                if (data.players) {
                    this.players = data.players;
                }
            } catch (error) {
                console.error('Fetch players error:', error);
            }
        },

        shareRoom() {
            const shareData = {
                title: 'Tongkrongan Games',
                text: `Join game saya di Tongkrongan Games! Kode: ${this.roomCode}`,
                url: window.location.origin + '/rooms/join'
            };

            if (navigator.share) {
                navigator.share(shareData);
            } else {
                navigator.clipboard.writeText(`Join game saya! Kode: ${this.roomCode}`);
                window.dispatchEvent(new CustomEvent('show-toast', {
                    detail: { message: 'Kode disalin!', type: 'success' }
                }));
            }
        }
    };
}
</script>
@endpush
