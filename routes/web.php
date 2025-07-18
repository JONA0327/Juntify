<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\DriveController;
use App\Http\Controllers\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index');
});

// Rutas de Auth
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register']);

Route::middleware('auth')->group(function () {
    // Perfil
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // OAuth Google Drive
    Route::get('/drive/authorize', [GoogleAuthController::class, 'redirect'])
         ->name('drive.authorize');
    Route::get('/drive/callback', [GoogleAuthController::class, 'callback'])
         ->name('drive.callback');

    // Rutas POST para manejo de carpetas
    Route::post('/drive/main-folder',    [DriveController::class, 'createMainFolder'])
         ->name('drive.createMainFolder');
    Route::post('/drive/set-main-folder',[DriveController::class, 'setMainFolder'])
         ->name('drive.setMainFolder');
    Route::post('/drive/subfolder',      [DriveController::class, 'createSubfolder'])
         ->name('drive.createSubfolder');
});
