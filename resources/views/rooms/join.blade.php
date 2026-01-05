@extends('layouts.app')

@section('title', 'Join Room - Tongkrongan Games')

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
            <h1 class="text-xl font-bold">Join Room</h1>
            <p class="text-sm text-slate-400">Masukkan kode room</p>
        </div>
    </header>

    {{-- Form --}}
    <form action="{{ route('rooms.join') }}" method="POST" class="flex-1 flex flex-col">
        @csrf

        <div class="card mb-4">
            <label for="code" class="block text-sm font-medium text-slate-300 mb-2 text-center">
                Kode Room (6 karakter)
            </label>
            <input 
                type="text" 
                id="code" 
                name="code" 
                class="input input-code"
                placeholder="ABCDEF"
                required
                minlength="6"
                maxlength="6"
                autocomplete="off"
                autocapitalize="characters"
                spellcheck="false"
                value="{{ old('code') }}"
            >
            @error('code')
                <p class="mt-2 text-sm text-red-400 text-center">{{ $message }}</p>
            @enderror
        </div>

        <p class="text-center text-sm text-slate-400 mb-6">
            Minta kode room dari host yang membuat room
        </p>

        <div class="flex-1"></div>

        <button type="submit" class="btn-primary btn-lg w-full">
            🚀 Join Room
        </button>
    </form>
</div>
@endsection

@push('scripts')
<script>
    // Auto uppercase input
    document.getElementById('code').addEventListener('input', function(e) {
        this.value = this.value.toUpperCase();
    });
</script>
@endpush
