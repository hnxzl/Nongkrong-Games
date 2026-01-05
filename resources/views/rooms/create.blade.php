@extends('layouts.app')

@section('title', 'Buat Room - Tongkrongan Games')

@section('content')
<div class="flex-1 flex flex-col p-4">
    {{-- Header --}}
    <header class="flex items-center gap-4 mb-6">
        <a href="{{ route('home') }}" class="btn-ghost p-2 -ml-2">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-bold">Buat Room</h1>
            <p class="text-sm text-slate-400">{{ ucfirst(str_replace('_', ' ', $gameType)) }}</p>
        </div>
    </header>

    {{-- Form --}}
    <form action="{{ route('rooms.store') }}" method="POST" class="flex-1 flex flex-col">
        @csrf
        <input type="hidden" name="game_type" value="{{ $gameType }}">

        <div class="card mb-4">
            <label for="name" class="block text-sm font-medium text-slate-300 mb-2">
                Nama Room
            </label>
            <input 
                type="text" 
                id="name" 
                name="name" 
                class="input"
                placeholder="Misal: Tongkrongan Seru"
                required
                maxlength="100"
                value="{{ old('name', Auth::user()->name . "'s Room") }}"
            >
            @error('name')
                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Game Info --}}
        <div class="card mb-4">
            <h3 class="font-semibold mb-2">Info Game</h3>
            <div class="space-y-2 text-sm text-slate-400">
                @if($gameType === 'uno')
                    <p>🃏 Permainan kartu UNO klasik</p>
                    <p>👥 2-10 pemain</p>
                    <p>⏱️ 30 detik per giliran</p>
                @elseif($gameType === 'undercover')
                    <p>🕵️ Temukan Undercover & Mr. White</p>
                    <p>👥 3-10 pemain</p>
                    <p>⏱️ 60 detik diskusi, 30 detik voting</p>
                @elseif($gameType === 'last_letter')
                    <p>📝 Sambung kata dari huruf terakhir</p>
                    <p>👥 2-8 pemain</p>
                    <p>⏱️ 15 detik per giliran</p>
                @elseif($gameType === 'abc_dasar')
                    <p>🔤 Jawab sesuai kategori & huruf</p>
                    <p>👥 2-10 pemain</p>
                    <p>⏱️ 10 detik per jawaban</p>
                @endif
            </div>
        </div>

        <div class="flex-1"></div>

        <button type="submit" class="btn-primary btn-lg w-full">
            ✨ Buat Room
        </button>
    </form>
</div>
@endsection
