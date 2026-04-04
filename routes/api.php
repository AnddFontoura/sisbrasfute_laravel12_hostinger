<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\GamePositionController;
use App\Http\Controllers\MatchesController;
use App\Http\Controllers\ModalityController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamFinanceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('team')
        ->name('team.')
        ->controller(TeamController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('save', 'save');
            Route::post('/update/{teamId}', 'update')->middleware('isTeamManager');
            Route::get('show/{teamId}', 'show');
            Route::get('list/my-teams', 'listOfManagedTeamsByUser');
        });

    Route::prefix('team-finance')
        ->name('team-finance.')
        ->controller(TeamFinanceController::class)
        ->group(function () {
            Route::get('/{teamId}', 'index')->middleware('isTeamManager');
            Route::post('/{teamId}/save', 'save')->middleware('isTeamManager');
            Route::post('{teamId}/update/{id}', 'update')->middleware('isTeamManager');
            Route::get('{teamId}/show/{id}', 'show')->middleware('isTeamManager');
        });

    Route::prefix('matches')
        ->name('matches.')
        ->controller(MatchesController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('save', 'save');
            Route::post('/update/{matchId}', 'update')->middleware('isTeamAdmin');
            Route::get('show/{matchId}', 'show');
        });

    Route::prefix('player-profile')
        ->name('player-profile.')
        ->controller(PlayerController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('save', 'save');
            Route::get('show/{playerId}', 'show');
            Route::get('show', 'show');
        });

    Route::prefix('states')
        ->name('states.')
        ->controller(StateController::class)
        ->group(function () {
           Route::get('/list', 'list');
        });

    Route::prefix('cities')
        ->name('cities.')
        ->controller(CityController::class)
        ->group(function () {
            Route::get('/list', 'list');
        });

    Route::prefix('modalities')
        ->name('modalities.')
        ->controller(ModalityController::class)
        ->group(function () {
            Route::get('/list', 'list');
        });

    Route::prefix('game-positions')
        ->name('game-positions.')
        ->controller(GamePositionController::class)
        ->group(function () {
            Route::get('/list', 'list');
        });
});
