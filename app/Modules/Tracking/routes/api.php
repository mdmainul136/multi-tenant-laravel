<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Tracking\Controllers\TrackingController;
use App\Modules\Tracking\Controllers\ProxyController;
use App\Modules\Tracking\Controllers\GatewayController;
use App\Modules\Tracking\Controllers\SignalsController;
use App\Modules\Tracking\Controllers\DiagnosticsController;
use App\Modules\Tracking\Controllers\InfrastructureController;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\AuthenticateToken;

// ── sGTM Internal Proxy Route (No Auth, identified by Container ID + API Secret) ──
Route::middleware([IdentifyTenant::class])->prefix('tracking/proxy')->group(function () {
    Route::post('/{containerId}', [ProxyController::class, 'handle']);
});

// ── Admin Management Routes ──────────────────────────────────────────────────────
Route::middleware([
    IdentifyTenant::class, 
    AuthenticateToken::class,
    'module.access:tracking',
    'quota.enforce'
])->prefix('tracking')->group(function () {
    
    // Container CRUD
    Route::get('/containers',                    [TrackingController::class, 'index']);
    Route::post('/containers',                   [TrackingController::class, 'store']);
    Route::get('/containers/{id}/stats',         [TrackingController::class, 'stats']);
    Route::get('/containers/{id}/logs',          [TrackingController::class, 'logs']);
    
    // Destinations
    Route::get('/containers/{id}/destinations',  [TrackingController::class, 'getDestinations']);
    Route::post('/containers/{id}/destinations', [TrackingController::class, 'addDestination']);
    
    // Usage Metering (Billing)
    Route::get('/containers/{id}/usage',         [TrackingController::class, 'usage']);
    Route::get('/containers/{id}/usage/daily',   [TrackingController::class, 'usageDaily']);
    
    // Analytics (Stape Analytics Equivalent)
    Route::get('/containers/{id}/analytics',     [TrackingController::class, 'analytics']);
    
    // Power-Ups
    Route::get('/power-ups',                     [TrackingController::class, 'powerUps']);
    Route::put('/containers/{id}/power-ups',     [TrackingController::class, 'updatePowerUps']);
    
    // Container Settings (custom_script, event_mappings, etc.)
    Route::put('/containers/{id}',               [TrackingController::class, 'update']);
    
    // Docker Control Plane (Orchestrator)
    Route::post('/containers/{id}/deploy',       [TrackingController::class, 'deploy']);
    Route::post('/containers/{id}/provision',    [TrackingController::class, 'provision']);
    Route::post('/containers/{id}/deprovision',  [TrackingController::class, 'deprovision']);
    Route::get('/containers/{id}/health',        [TrackingController::class, 'health']);
    Route::put('/containers/{id}/domain',        [TrackingController::class, 'updateDomain']);

    // Dedicated Gateways (Lightweight single-destination endpoints)
    Route::get('/gateways',                      [GatewayController::class, 'index']);
    Route::post('/gateways',                     [GatewayController::class, 'store']);
    Route::delete('/gateways/{id}',              [GatewayController::class, 'destroy']);

    // Signals Gateway (Meta Signals Gateway Equivalent)
    Route::post('/signals/send',                 [SignalsController::class, 'send']);
    Route::get('/signals/pipelines/{id}',        [SignalsController::class, 'getPipelines']);
    Route::put('/signals/pipelines/{id}',        [SignalsController::class, 'updatePipelines']);
    Route::post('/signals/validate',             [SignalsController::class, 'validateEvent']);
    Route::get('/signals/emq/{id}',              [SignalsController::class, 'getEMQ']);

    // CAPI Diagnostics & Dataset Quality (Meta Direct Integration)
    Route::get('/diagnostics/{id}/quality',       [DiagnosticsController::class, 'quality']);
    Route::get('/diagnostics/{id}/match-keys',    [DiagnosticsController::class, 'matchKeys']);
    Route::get('/diagnostics/{id}/acr',           [DiagnosticsController::class, 'additionalConversions']);
    Route::get('/diagnostics/{id}/integration-kit', [DiagnosticsController::class, 'integrationKit']);
    Route::post('/diagnostics/test-event',        [DiagnosticsController::class, 'testEvent']);
    Route::post('/diagnostics/emq-preview',       [DiagnosticsController::class, 'emqPreview']);

    // ── Phase 27: Infrastructure APIs ────────────────────────────────────────────

    // DLQ / Retry Queue
    Route::get('/dlq/{id}/stats',                 [InfrastructureController::class, 'dlqStats']);
    Route::post('/dlq/{id}/retry',                [InfrastructureController::class, 'dlqRetry']);
    Route::delete('/dlq/purge',                   [InfrastructureController::class, 'dlqPurge']);

    // Consent Management
    Route::post('/consent/{id}',                  [InfrastructureController::class, 'recordConsent']);
    Route::get('/consent/{id}/{visitorId}',       [InfrastructureController::class, 'getConsent']);
    Route::get('/consent/{id}/stats',             [InfrastructureController::class, 'consentStats']);
    Route::get('/consent/{id}/banner',            [InfrastructureController::class, 'consentBanner']);
    Route::delete('/consent/{id}/revoke',         [InfrastructureController::class, 'revokeConsent']);

    // Channel Health Dashboard
    Route::get('/health/{id}/dashboard',          [InfrastructureController::class, 'channelHealth']);
    Route::get('/health/{id}/alerts',             [InfrastructureController::class, 'channelAlerts']);

    // Attribution
    Route::get('/attribution/{id}',               [InfrastructureController::class, 'attribution']);
    Route::get('/attribution/{id}/paths',         [InfrastructureController::class, 'conversionPaths']);

    // Tag Management
    Route::get('/tags/{id}',                      [InfrastructureController::class, 'listTags']);
    Route::post('/tags/{id}',                     [InfrastructureController::class, 'createTag']);
    Route::put('/tags/items/{tagId}',             [InfrastructureController::class, 'updateTag']);
    Route::delete('/tags/items/{tagId}',          [InfrastructureController::class, 'deleteTag']);

    // Supported Destinations Registry
    Route::get('/destinations/supported',         [InfrastructureController::class, 'destinations']);
});
