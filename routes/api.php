<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\PawnHourPriceController;
use App\Http\Controllers\PlaceCollectionTypeController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\PlaceTypeController;
use App\Http\Controllers\TollController;
use App\Http\Controllers\TransportationController;
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

//CLIENT

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

//PLACE

Route::group([
    'middleware' => 'api',
    'prefix' => 'places'
], function () {
    Route::get('/', [PlaceController::class, 'index'])->middleware('admin');
    Route::get('/{id}', [PlaceController::class, 'show'])->middleware('admin');
    Route::post('/', [PlaceController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [PlaceController::class, 'update'])->middleware('admin');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'place-type'
], function () {
    Route::get('/', [PlaceTypeController::class, 'index'])->middleware('admin');
    Route::post('/', [PlaceTypeController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [PlaceTypeController::class, 'update'])->middleware('admin');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'places-collections-types'
], function () {
    Route::get('/', [PlaceCollectionTypeController::class, 'index'])->middleware('admin');
    Route::post('/', [PlaceCollectionTypeController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [PlaceCollectionTypeController::class, 'update'])->middleware('admin');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'tolls'
], function () {
    Route::get('/', [TollController::class, 'index'])->middleware('admin');
    Route::post('/', [TollController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [TollController::class, 'update'])->middleware('admin');
});

// Transportation

Route::group([
    'middleware' => 'api',
    'prefix' => 'transportation'
], function () {
    Route::get('/', [TransportationController::class, 'index'])->middleware('admin');
    Route::get('/{id}', [TransportationController::class, 'show'])->middleware('admin');
    Route::post('/', [TransportationController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [TransportationController::class, 'update'])->middleware('admin');
});

// Pawn hour price

Route::group([
    'middleware' => 'api',
    'prefix' => 'pawn-hour-price'
], function () {
    Route::get('/', [PawnHourPriceController::class, 'index'])->middleware('admin');
    Route::post('/', [PawnHourPriceController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [PawnHourPriceController::class, 'update'])->middleware('admin');
});