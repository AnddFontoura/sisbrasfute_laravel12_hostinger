<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\GamePositionController;
use App\Http\Controllers\MatchesController;
use App\Http\Controllers\MatchPositionController;
use App\Http\Controllers\PlayerSelfAssignController;
use App\Http\Controllers\ModalityController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\PlayerInvitationController;
use App\Http\Controllers\TeamApplicationController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamFinanceController;
use App\Http\Controllers\TeamFinanceReasonController;
use App\Http\Controllers\TeamMatchesController;
use App\Http\Controllers\TeamPlayerController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\TeamSearchPositionController;
use App\Http\Controllers\TeamTagController;
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
Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name('verification.verify');

Route::middleware('auth:api')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/email/resend-verification', [EmailVerificationController::class, 'resend']);

    Route::prefix('team')
        ->name('team.')
        ->controller(TeamController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('save', 'save')->middleware('emailVerified');
            Route::post('/update/{teamId}', 'save')->middleware('isTeamManager');
            Route::get('show/{teamId}', 'show');
            Route::get('list/my-teams', 'listOfManagedTeamsByUser');
        });

    Route::prefix('team-matches')
        ->name('team-matches.')
        ->controller(TeamMatchesController::class)
        ->group(function () {
            Route::post('/create', 'createTeamMatch')->middleware('isTeamManager');
            Route::post('/join', 'joinTeamMatch')->middleware('isTeamMember');
        });

    Route::prefix('team-player')
        ->name('team-player.')
        ->controller(TeamPlayerController::class)
        ->group(function () {
           Route::get('/{teamId}/list', 'index')->middleware('isTeamManager');
           Route::get('/{teamId}/show/{playerId}', 'show')->middleware('isTeamMember');
           Route::post('/{teamId}/save', 'save')->middleware('isTeamManager');
           Route::post('/{teamId}/update/{playerId}', 'save')->middleware('isTeamManager');
        });

    Route::prefix('team/{teamId}/tags')
        ->name('team-tags.')
        ->controller(TeamTagController::class)
        ->middleware('isTeamManager')
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::put('/{tagId}', 'update');
            Route::delete('/{tagId}', 'destroy');
        });

    Route::prefix('team-search-position')
        ->name('team-search-position.')
        ->controller(TeamSearchPositionController::class)
        ->group(function () {
            Route::get('/{teamId}/list', 'index')->middleware('isTeamManager');
            Route::get('/{teamId}/show/{playerId}', 'show')->middleware('isTeamMember');
            Route::post('/{teamId}/save', 'save')->middleware('isTeamManager');
            Route::delete('/{teamId}/delete/{id}', 'delete')->middleware('isTeamManager');
        });

    Route::prefix('team-application')
        ->name('team-application.')
        ->controller(TeamApplicationController::class)
        ->group(function (){
            Route::get('/{teamId}/list/{page?}', 'index')->middleware('isTeamManager');
            Route::post('/apply/save', 'save');
            Route::post('{teamId}/{teamApplicationId}/answer', 'answer');
        });

    Route::prefix('player-invitation')
        ->name('player-invitation.')
        ->controller(PlayerInvitationController::class)
        ->group(function () {
            Route::post('/{teamId}/send', 'send')->middleware('isTeamManager');
            Route::get('/{teamId}/list', 'index')->middleware('isTeamManager');
            Route::delete('/{teamId}/cancel/{invitationId}', 'cancel')->middleware('isTeamManager');
            Route::get('/received', 'received');
            Route::post('/{invitationId}/accept', 'accept');
        });

    Route::prefix('team-finance')
        ->name('team-finance.')
        ->controller(TeamFinanceController::class)
        ->group(function () {
            Route::get('/{teamId}', 'index')->middleware('isTeamManager');
            Route::post('/{teamId}/save', 'save')->middleware('isTeamManager');
            Route::post('{teamId}/update/{id}', 'save')->middleware('isTeamManager');
            Route::get('{teamId}/show/{id}', 'show')->middleware('isTeamManager');
        });

    Route::prefix('team/{teamId}/finance-reasons')
        ->name('team-finance-reasons.')
        ->controller(TeamFinanceReasonController::class)
        ->middleware('isTeamManager')
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::put('/{reasonId}', 'update');
            Route::delete('/{reasonId}', 'destroy');
        });

    Route::prefix('matches')
        ->name('matches.')
        ->controller(MatchesController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('save/{matchId?}', 'save')->middleware('emailVerified');
            Route::get('show/{matchId}', 'show');
            Route::get('{matchId}/players', [MatchPositionController::class, 'index']);
            Route::post('{matchId}/players/save', [MatchPositionController::class, 'save'])->middleware('isTeamAdmin');
            Route::post('{matchId}/players/{atribuicaoId}/payment', [MatchPositionController::class, 'updatePayment'])->middleware('isTeamAdmin');
            Route::post('{matchId}/players/self-assign', [PlayerSelfAssignController::class, 'store'])->middleware('isTeamMember');
            Route::delete('{matchId}/players/self-assign', [PlayerSelfAssignController::class, 'destroy'])->middleware('isTeamMember');
        });

    Route::prefix('player-profile')
        ->name('player-profile.')
        ->controller(PlayerController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('save', 'save')->middleware('emailVerified');
            Route::get('show/{playerId}', 'show');
            Route::get('show', 'show');
            Route::get('{playerId}/teams', 'getPlayerTeams');
            Route::get('{playerId}/matches', 'getPlayerMatches');
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

    Route::prefix('configuration')
        ->name('configuration.')
        ->controller(ConfigurationController::class)
        ->group(function () {
            Route::get('/', 'show');
            Route::post('/profile', 'updateProfile');
            Route::post('/password', 'updatePassword');
        });

    Route::prefix('admin')
        ->middleware('isAdmin')
        ->controller(AdminController::class)
        ->group(function () {
            Route::get('/users', 'users');
            Route::get('/users/{userId}', 'showUser');
            Route::get('/teams', 'teams');
            Route::delete('/teams/{teamId}/logo', 'removeTeamLogo');
            Route::delete('/teams/{teamId}/banner', 'removeTeamBanner');
            Route::get('/matches', 'matches');
        });
});
