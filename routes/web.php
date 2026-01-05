<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\Games\AbcDasarController;
use App\Http\Controllers\Games\LastLetterController;
use App\Http\Controllers\Games\UndercoverController;
use App\Http\Controllers\Games\UnoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Tongkrongan Games - Routes
|
*/

// Auth Routes (Guest Login)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login/guest', [AuthController::class, 'guestLogin'])->name('login.guest');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected Routes
Route::middleware('auth')->group(function () {
    // Home
    Route::get('/', [RoomController::class, 'index'])->name('home');

    // Room Management
    Route::prefix('rooms')->name('rooms.')->group(function () {
        Route::get('/create/{gameType}', [RoomController::class, 'create'])->name('create');
        Route::post('/', [RoomController::class, 'store'])->name('store');
        Route::get('/join', [RoomController::class, 'joinForm'])->name('join-form');
        Route::post('/join', [RoomController::class, 'join'])->name('join');
        Route::get('/{code}', [RoomController::class, 'lobby'])->name('lobby');
        Route::post('/{code}/ready', [RoomController::class, 'toggleReady'])->name('toggle-ready');
        Route::post('/{code}/start', [RoomController::class, 'start'])->name('start');
        Route::post('/{code}/leave', [RoomController::class, 'leave'])->name('leave');
    });

    // Game Routes
    Route::prefix('games')->name('games.')->group(function () {
        // Uno
        Route::prefix('uno/{code}')->name('uno')->group(function () {
            Route::get('/', [UnoController::class, 'show']);
            Route::post('/play', [UnoController::class, 'playCard'])->name('.play');
            Route::post('/draw', [UnoController::class, 'drawCard'])->name('.draw');
            Route::post('/uno', [UnoController::class, 'sayUno'])->name('.say-uno');
            Route::get('/status', [UnoController::class, 'status'])->name('.status');
        });

        // Last Letter
        Route::prefix('last-letter/{code}')->name('last_letter')->group(function () {
            Route::get('/', [LastLetterController::class, 'show']);
            Route::post('/submit', [LastLetterController::class, 'submitWord'])->name('.submit');
            Route::post('/timeout', [LastLetterController::class, 'timeout'])->name('.timeout');
            Route::get('/status', [LastLetterController::class, 'status'])->name('.status');
        });

        // Undercover
        Route::prefix('undercover/{code}')->name('undercover')->group(function () {
            Route::get('/', [UndercoverController::class, 'show']);
            Route::post('/start-voting', [UndercoverController::class, 'startVoting'])->name('.start-voting');
            Route::post('/vote', [UndercoverController::class, 'vote'])->name('.vote');
            Route::post('/process-voting', [UndercoverController::class, 'processVoting'])->name('.process-voting');
            Route::post('/mr-white-guess', [UndercoverController::class, 'mrWhiteGuess'])->name('.mr-white-guess');
            Route::get('/status', [UndercoverController::class, 'status'])->name('.status');
        });

        // ABC 5 Dasar
        Route::prefix('abc-dasar/{code}')->name('abc_dasar')->group(function () {
            Route::get('/', [AbcDasarController::class, 'show']);
            Route::post('/claim', [AbcDasarController::class, 'claimAnswer'])->name('.claim');
            Route::post('/submit', [AbcDasarController::class, 'submitAnswer'])->name('.submit');
            Route::post('/timeout', [AbcDasarController::class, 'answerTimeout'])->name('.timeout');
            Route::post('/vote', [AbcDasarController::class, 'vote'])->name('.vote');
            Route::post('/process-voting', [AbcDasarController::class, 'processVoting'])->name('.process-voting');
            Route::get('/status', [AbcDasarController::class, 'status'])->name('.status');
        });
    });
});
