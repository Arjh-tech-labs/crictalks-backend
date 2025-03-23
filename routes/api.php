<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\TeamController;
use App\Http\Controllers\API\TournamentController;
use App\Http\Controllers\API\MatchController;
use App\Http\Controllers\API\ScoringController;
use App\Http\Controllers\API\LiveStreamController;
use App\Http\Controllers\API\VenueController;
use App\Http\Controllers\API\UserTypeController;
use App\Http\Controllers\API\OverlayTemplateController;
use App\Http\Controllers\API\StatisticsController;
use App\Http\Controllers\API\FirebaseAuthController;

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

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Firebase Authentication
Route::post('/firebase/verify-phone', [FirebaseAuthController::class, 'verifyPhone']);
Route::post('/firebase/register', [FirebaseAuthController::class, 'register']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/user/update-profile', [AuthController::class, 'updateProfile']);
    Route::post('/user/update-user-type-profile/{userTypeId}', [AuthController::class, 'updateUserTypeProfile']);
    Route::post('/user/add-user-type/{userTypeId}', [AuthController::class, 'addUserType']);

    // User Type routes
    Route::get('/user-types', [UserTypeController::class, 'index']);
    Route::get('/user-types/{id}', [UserTypeController::class, 'show']);

    // Team routes
    Route::get('/teams', [TeamController::class, 'index']);
    Route::post('/teams', [TeamController::class, 'store']);
    Route::get('/teams/{id}', [TeamController::class, 'show']);
    Route::put('/teams/{id}', [TeamController::class, 'update']);
    Route::delete('/teams/{id}', [TeamController::class, 'destroy']);
    Route::post('/teams/{id}/add-player', [TeamController::class, 'addPlayer']);
    Route::delete('/teams/{id}/remove-player/{userId}', [TeamController::class, 'removePlayer']);
    Route::put('/teams/{id}/update-player/{userId}', [TeamController::class, 'updatePlayer']);
    Route::get('/teams/{id}/players', [TeamController::class, 'getPlayers']);

    // Tournament routes
    Route::get('/tournaments', [TournamentController::class, 'index']);
    Route::post('/tournaments', [TournamentController::class, 'store']);
    Route::get('/tournaments/{id}', [TournamentController::class, 'show']);
    Route::put('/tournaments/{id}', [TournamentController::class, 'update']);
    Route::delete('/tournaments/{id}', [TournamentController::class, 'destroy']);
    Route::post('/tournaments/{id}/add-team', [TournamentController::class, 'addTeam']);
    Route::delete('/tournaments/{id}/remove-team/{teamId}', [TournamentController::class, 'removeTeam']);
    Route::put('/tournaments/{id}/update-team/{teamId}', [TournamentController::class, 'updateTeam']);
    Route::get('/tournaments/{id}/teams', [TournamentController::class, 'getTeams']);
    Route::get('/tournaments/{id}/points-table', [TournamentController::class, 'getPointsTable']);

    // Venue routes
    Route::get('/venues', [VenueController::class, 'index']);
    Route::post('/venues', [VenueController::class, 'store']);
    Route::get('/venues/{id}', [VenueController::class, 'show']);
    Route::put('/venues/{id}', [VenueController::class, 'update']);
    Route::delete('/venues/{id}', [VenueController::class, 'destroy']);

    // Match routes
    Route::get('/matches', [MatchController::class, 'index']);
    Route::post('/matches', [MatchController::class, 'store']);
    Route::get('/matches/{id}', [MatchController::class, 'show']);
    Route::put('/matches/{id}', [MatchController::class, 'update']);
    Route::delete('/matches/{id}', [MatchController::class, 'destroy']);
    Route::post('/matches/{id}/assign-scorer', [MatchController::class, 'assignScorer']);
    Route::post('/matches/{id}/assign-streamer', [MatchController::class, 'assignStreamer']);
    Route::put('/matches/{id}/live-stream-settings', [MatchController::class, 'updateLiveStreamSettings']);

    // Scoring routes
    Route::post('/innings/start', [ScoringController::class, 'startInnings']);
    Route::put('/innings/{id}/end', [ScoringController::class, 'endInnings']);
    Route::post('/innings/{id}/add-batsmen', [ScoringController::class, 'addBatsmen']);
    Route::post('/innings/{id}/add-bowlers', [ScoringController::class, 'addBowlers']);
    Route::post('/ball/record', [ScoringController::class, 'recordBall']);
    Route::get('/innings/{id}/details', [ScoringController::class, 'getInningsDetails']);
    Route::get('/match/{id}/score', [ScoringController::class, 'getMatchScore']);
    Route::get('/innings/{id}/current-batsmen', [ScoringController::class, 'getCurrentBatsmen']);
    Route::get('/innings/{id}/current-bowler', [ScoringController::class, 'getCurrentBowler']);
    Route::put('/batsman-innings/{id}/status', [ScoringController::class, 'updateBatsmanStatus']);
    Route::get('/batsman-innings/{id}/wagon-wheel', [ScoringController::class, 'getBatsmanWagonWheel']);
    Route::get('/innings/{id}/over/{overNumber}', [ScoringController::class, 'getOverDetails']);
    Route::get('/innings/{id}/partnerships', [ScoringController::class, 'getPartnerships']);

    // Live Stream routes
    Route::post('/youtube/create-stream', [LiveStreamController::class, 'createYouTubeStream']);
    Route::put('/youtube/update-stream/{id}', [LiveStreamController::class, 'updateYouTubeStream']);
    Route::post('/youtube/start-stream/{id}', [LiveStreamController::class, 'startYouTubeStream']);
    Route::post('/youtube/end-stream/{id}', [LiveStreamController::class, 'endYouTubeStream']);
    Route::get('/youtube/stream-status/{id}', [LiveStreamController::class, 'getYouTubeStreamStatus']);
    Route::get('/youtube/auth-url', [LiveStreamController::class, 'getYouTubeAuthUrl']);
    Route::post('/youtube/auth-callback', [LiveStreamController::class, 'handleYouTubeAuthCallback']);
    Route::get('/youtube/channel-info', [LiveStreamController::class, 'getYouTubeChannelInfo']);

    // Overlay Template routes
    Route::get('/overlay-templates', [OverlayTemplateController::class, 'index']);
    Route::post('/overlay-templates', [OverlayTemplateController::class, 'store']);
    Route::get('/overlay-templates/{id}', [OverlayTemplateController::class, 'show']);
    Route::put('/overlay-templates/{id}', [OverlayTemplateController::class, 'update']);
    Route::delete('/overlay-templates/{id}', [OverlayTemplateController::class, 'destroy']);
    Route::get('/overlay-templates/type/{type}', [OverlayTemplateController::class, 'getByType']);
    Route::get('/overlay-templates/default/{type}', [OverlayTemplateController::class, 'getDefaultByType']);

    // Statistics routes
    Route::get('/statistics/player/{id}', [StatisticsController::class, 'getPlayerStatistics']);
    Route::get('/statistics/team/{id}', [StatisticsController::class, 'getTeamStatistics']);
    Route::get('/statistics/tournament/{id}', [StatisticsController::class, 'getTournamentStatistics']);
    Route::get('/statistics/match/{id}', [StatisticsController::class, 'getMatchStatistics']);
    Route::get('/statistics/leaderboards', [StatisticsController::class, 'getLeaderboards']);
});

// Public match data
Route::get('/public/matches/live', [MatchController::class, 'getLiveMatches']);
Route::get('/public/match/{id}/score', [ScoringController::class, 'getPublicMatchScore']);
Route::get('/public/tournaments', [TournamentController::class, 'getPublicTournaments']);
Route::get('/public/tournament/{id}', [TournamentController::class, 'getPublicTournament']);

