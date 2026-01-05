@extends('layouts.app')

@section('title', 'Login - Tongkrongan Games')

@section('content')
<div class="flex-1 flex flex-col items-center justify-center p-6">
    {{-- Logo --}}
    <div class="text-center mb-8">
        <h1 class="text-4xl font-game font-bold text-gradient mb-2">🎮 Tongkrongan Games</h1>
        <p class="text-slate-400">Main bareng teman di coffee shop!</p>
    </div>

    {{-- Login Form --}}
    <div class="card w-full max-w-sm">
        <form action="{{ route('login.guest') }}" method="POST" class="space-y-4">
            @csrf
            
            <div>
                <label for="name" class="block text-sm font-medium text-slate-300 mb-2">
                    Nama Kamu
                </label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    class="input"
                    placeholder="Masukkan nama..."
                    required
                    minlength="2"
                    maxlength="50"
                    autofocus
                    value="{{ old('name') }}"
                >
                @error('name')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn-primary w-full btn-lg">
                🚀 Mulai Main
            </button>
        </form>
    </div>

    {{-- Features --}}
    <div class="mt-8 grid grid-cols-2 gap-3 w-full max-w-sm text-center">
        <div class="bg-slate-800/50 rounded-xl p-3">
            <span class="text-2xl">🃏</span>
            <p class="text-xs text-slate-400 mt-1">UNO</p>
        </div>
        <div class="bg-slate-800/50 rounded-xl p-3">
            <span class="text-2xl">🕵️</span>
            <p class="text-xs text-slate-400 mt-1">Undercover</p>
        </div>
        <div class="bg-slate-800/50 rounded-xl p-3">
            <span class="text-2xl">📝</span>
            <p class="text-xs text-slate-400 mt-1">Last Letter</p>
        </div>
        <div class="bg-slate-800/50 rounded-xl p-3">
            <span class="text-2xl">🔤</span>
            <p class="text-xs text-slate-400 mt-1">ABC 5 Dasar</p>
        </div>
    </div>
</div>
@endsection
