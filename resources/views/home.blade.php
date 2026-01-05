@extends('layouts.app')

@section('title', 'Tongkrongan Games')

@section('content')
<div class="flex-1 flex flex-col p-4 safe-area-bottom">
    {{-- Header --}}
    <header class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-game font-bold text-gradient">🎮 Tongkrongan</h1>
            <p class="text-sm text-slate-400">Halo, {{ Auth::user()->name }}!</p>
        </div>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="btn-ghost btn-sm">
                Keluar
            </button>
        </form>
    </header>

    {{-- Quick Actions --}}
    <div class="grid grid-cols-2 gap-3 mb-6">
        <a href="{{ route('rooms.join-form') }}" class="card-game flex flex-col items-center py-6 active:scale-95 transition-transform">
            <span class="text-4xl mb-2">🚪</span>
            <span class="font-semibold">Join Room</span>
            <span class="text-xs text-slate-400">Pakai kode</span>
        </a>
        <div class="card-game flex flex-col items-center py-6 opacity-50">
            <span class="text-4xl mb-2">➕</span>
            <span class="font-semibold">Buat Room</span>
            <span class="text-xs text-slate-400">Pilih game</span>
        </div>
    </div>

    {{-- Game Selection --}}
    <h2 class="text-lg font-semibold mb-4">Pilih Game</h2>
    
    <div class="space-y-3 flex-1 overflow-y-auto pb-4">
        {{-- UNO --}}
        <a href="{{ route('rooms.create', 'uno') }}" 
           class="card-game flex items-center gap-4 active:scale-[0.98] transition-transform">
            <div class="w-16 h-16 bg-gradient-to-br from-uno-red to-uno-yellow rounded-xl flex items-center justify-center text-3xl">
                🃏
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-lg">UNO</h3>
                <p class="text-sm text-slate-400">2-10 pemain • Kartu klasik</p>
            </div>
            <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </a>

        {{-- Undercover --}}
        <a href="{{ route('rooms.create', 'undercover') }}" 
           class="card-game flex items-center gap-4 active:scale-[0.98] transition-transform">
            <div class="w-16 h-16 bg-gradient-to-br from-purple-600 to-pink-500 rounded-xl flex items-center justify-center text-3xl">
                🕵️
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-lg">Undercover & Mr. White</h3>
                <p class="text-sm text-slate-400">3-10 pemain • Tebak kata</p>
            </div>
            <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </a>

        {{-- Last Letter --}}
        <a href="{{ route('rooms.create', 'last_letter') }}" 
           class="card-game flex items-center gap-4 active:scale-[0.98] transition-transform">
            <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-teal-500 rounded-xl flex items-center justify-center text-3xl">
                📝
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-lg">Last Letter</h3>
                <p class="text-sm text-slate-400">2-8 pemain • Sambung kata</p>
            </div>
            <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </a>

        {{-- ABC 5 Dasar --}}
        <a href="{{ route('rooms.create', 'abc_dasar') }}" 
           class="card-game flex items-center gap-4 active:scale-[0.98] transition-transform">
            <div class="w-16 h-16 bg-gradient-to-br from-orange-500 to-amber-400 rounded-xl flex items-center justify-center text-3xl">
                🔤
            </div>
            <div class="flex-1">
                <h3 class="font-bold text-lg">ABC 5 Dasar</h3>
                <p class="text-sm text-slate-400">2-10 pemain • Kecepatan</p>
            </div>
            <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </a>
    </div>
</div>
@endsection
