<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return 'welcome';
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'auth'
], function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->name('changePassword');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'user'
], function () {
    Route::get('/', [UserController::class, 'index'])->middleware('admin');
    Route::get('/{id}', [UserController::class, 'show'])->middleware('admin');
    Route::post('/', [UserController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [UserController::class, 'update'])->middleware('admin');
});