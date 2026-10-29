<?php

use App\Http\Controllers\Agent\AgentController;
use App\Http\Controllers\Agent\AgentOAuthController;
use App\Http\Controllers\Agent\AgentSessionController;
use App\Http\Middleware\Agent\AgentTransport;
use App\Http\Middleware\Agent\AuthenticateAgent;
use App\Http\Middleware\Agent\AuthenticateAgentOAuth;
use App\Http\Controllers\LauncherController;
use App\Http\Middleware\AuthenticateLauncher;
use App\Http\Middleware\LauncherTransport;
use Illuminate\Support\Facades\Route;

Route::prefix('agent')->middleware([AgentTransport::class, 'throttle:agent-ip'])->group(function () {
    Route::post('oauth/token', [AgentOAuthController::class, 'token'])->middleware('throttle:agent-enroll');
    Route::middleware(AuthenticateAgentOAuth::class)->group(function () {
        Route::post('oauth/revoke', [AgentOAuthController::class, 'revoke']);
        Route::get('me', [AgentOAuthController::class, 'me']);
        Route::get('station', [AgentOAuthController::class, 'station']);
        Route::get('station/configuration', [AgentController::class, 'configuration']);
        Route::post('sessions/start', [AgentSessionController::class, 'start']);
        Route::post('sessions/{sessionId}/end', [AgentSessionController::class, 'end'])->whereUuid('sessionId');
        Route::post('sessions/{sessionId}/transmission/start', [AgentSessionController::class, 'transmissionStart'])->whereUuid('sessionId');
        Route::post('sessions/{sessionId}/transmission/stop', [AgentSessionController::class, 'transmissionStop'])->whereUuid('sessionId');
    });
    Route::post('enroll', [AgentController::class, 'enroll'])->middleware('throttle:agent-enroll');
    foreach (['heartbeat' => 'heartbeat', 'events' => 'event', 'commands/{id}/result' => 'result'] as $path => $action) {
        $ability = $action === 'result' ? 'results' : ($action === 'event' ? 'events' : 'heartbeat');
        Route::post($path, [AgentController::class, $action])->middleware([AuthenticateAgent::class.':'.$ability]);
    }
    Route::get('configuration', [AgentController::class, 'configuration'])->middleware([AuthenticateAgent::class.':configuration']);
});

Route::prefix('launcher')->middleware([LauncherTransport::class, 'throttle:agent-ip'])->group(function () {
    Route::post('enrollment', [LauncherController::class, 'enroll'])->middleware('throttle:agent-enroll');
    Route::middleware(AuthenticateLauncher::class)->group(function () {
        Route::get('station', [LauncherController::class, 'station']);
        Route::post('heartbeat', [LauncherController::class, 'heartbeat']);
    });
});
