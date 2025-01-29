<?php

use App\Http\Controllers\ClientController;
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
    Route::get('/type', [UserController::class, 'getAllUserType'])->middleware('admin');
    Route::post('/type', [UserController::class, 'storeUserType'])->middleware('admin');
    Route::put('/type/{id}', [UserController::class, 'updateUserType'])->middleware('admin');
    Route::get('/{id}', [UserController::class, 'show'])->middleware('admin');
    Route::post('/', [UserController::class, 'store']);
    Route::put('/{id}', [UserController::class, 'update'])->middleware('admin');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'client'
], function () {
    Route::get('/type', [ClientController::class, 'getAllClientType'])->middleware('admin');
    Route::post('/type', [ClientController::class, 'storeClientType'])->middleware('admin');
    Route::put('/type/{id}', [ClientController::class, 'updateClientType'])->middleware('admin');
    Route::get('/classes', [ClientController::class, 'getAllClientClasses'])->middleware('admin');
    Route::post('/classes', [ClientController::class, 'storeClientClasses'])->middleware('admin');
    Route::put('/classes/{id}', [ClientController::class, 'updateClientClasses'])->middleware('admin');
    Route::get('/', [ClientController::class, 'index'])->middleware('admin');
    Route::get('/{id}', [ClientController::class, 'show'])->middleware('admin');
    Route::post('/', [ClientController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [ClientController::class, 'update'])->middleware('admin');
});