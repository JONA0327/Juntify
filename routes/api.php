<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyzerController;
use App\Http\Controllers\DriveController;
use App\Http\Controllers\PendingRecordingController;
use App\Http\Controllers\OrganizationActivityController;

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

Route::get('/analyzers', [AnalyzerController::class, 'list']);
Route::post('/drive/save-results', [DriveController::class, 'saveResults']);

Route::post('/drive/upload-pending-audio', [DriveController::class, 'uploadPendingAudio'])
    ->middleware(['web', 'auth']);

Route::get('/pending-recordings/{pendingRecording}', [PendingRecordingController::class, 'show'])
    ->middleware('auth:sanctum');

Route::get('/organization-activities', [OrganizationActivityController::class, 'index'])
    ->middleware(['web', 'auth']);
