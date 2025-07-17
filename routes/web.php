<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\DriveController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index');
});

// Login
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::post('/login', [LoginController::class, 'login'])->name('login');

// Registro
Route::get('/register', [RegisterController::class, 'show'])->name('register');
Route::post('/register', [RegisterController::class, 'register'])->name('register');

// Dashboard (ejemplo protegido)
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Perfil (requiere estar logueado)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::post('/drive/authorize', [DriveController::class, 'authorizeService']);
    Route::post('/drive/main-folder', [DriveController::class, 'createMainFolder']);
    Route::post('/drive/set-main-folder', [DriveController::class, 'setMainFolder']);
    Route::post('/drive/subfolder', [DriveController::class, 'createSubfolder']);
});
