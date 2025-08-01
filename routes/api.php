<?php

use App\Http\Controllers\AudithController;
use App\Http\Controllers\BudgetAudithController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\BudgetDeliveryDataController;
use App\Http\Controllers\BudgetStatusController;
use App\Http\Controllers\BulkPriceUpdateController;
use App\Http\Controllers\CacheController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventTypeController;
use App\Http\Controllers\LocalityController;
use App\Http\Controllers\PawnHourPriceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PaymentStatusController;
use App\Http\Controllers\PaymentTypeController;
use App\Http\Controllers\PlaceCollectionTypeController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\PlacesAreaController;
use App\Http\Controllers\PlaceTypeController;
use App\Http\Controllers\ProductAttributeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductFurnitureController;
use App\Http\Controllers\ProductLineController;
use App\Http\Controllers\ProductPriceController;
use App\Http\Controllers\ProductProductsController;
use App\Http\Controllers\ProductTypeController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\TollController;
use App\Http\Controllers\TransportationController;
use App\Http\Controllers\UserTypeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;

Route::get('/clear-cache', [CacheController::class, 'clearCache'])->name('clearCache');

Route::get('/', function () {
    return 'welcome';
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'auth'
], function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('resetPassword');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->name('changePassword');
});

//USER
Route::group([
    'middleware' => 'api',
    'prefix' => 'user'
], function () {
    Route::get('/', [UserController::class, 'index'])->middleware('admin');
    Route::get('/{id}', [UserController::class, 'show'])->middleware('admin');
    Route::post('/', [UserController::class, 'store'])->middleware('admin');
    Route::put('/own', [UserController::class, 'updateOwn'])->middleware('admin');
    Route::put('/{id}', [UserController::class, 'update'])->middleware('admin');
});

//USER TYPE
Route::group([
    'middleware' => 'api',
    'prefix' => 'user-type'
], function () {
    Route::get('/', [UserTypeController::class, 'index'])->middleware('admin');
    Route::post('/', [UserTypeController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [UserTypeController::class, 'update'])->middleware('admin');
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
    Route::get('/export', [PlaceController::class, 'export'])->middleware('admin');
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
    'prefix' => 'places-areas'
], function () {
    Route::get('/', [PlacesAreaController::class, 'index'])->middleware('admin');
    Route::post('/', [PlacesAreaController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [PlacesAreaController::class, 'update'])->middleware('admin');
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

// Audith

Route::group([
    'middleware' => 'api',
    'prefix' => 'audith'
], function () {
    Route::get('/', [AudithController::class, 'index'])->middleware('admin');
});

// Province

Route::group([
    'middleware' => 'api',
    'prefix' => 'province'
], function () {
    Route::get('/', [ProvinceController::class, 'index'])->middleware('admin');
    Route::get('/{id}', [ProvinceController::class, 'show'])->middleware('admin');
});

// Locality

Route::group([
    'middleware' => 'api',
    'prefix' => 'locality'
], function () {
    Route::get('/{id}', [LocalityController::class, 'show'])->middleware('admin');
});

// Product

Route::group([
    'middleware' => 'api',
    'prefix' => 'product-line'
], function () {
    Route::get('/', [ProductLineController::class, 'index'])->middleware('admin');
    Route::post('/', [ProductLineController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [ProductLineController::class, 'update'])->middleware('admin');
    Route::delete('/{id}', [ProductLineController::class, 'destroy'])->middleware('admin');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'product-type'
], function () {
    Route::get('/', [ProductTypeController::class, 'index'])->middleware('admin');
    Route::post('/', [ProductTypeController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [ProductTypeController::class, 'update'])->middleware('admin');
    Route::delete('/{id}', [ProductTypeController::class, 'destroy'])->middleware('admin');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'product-furniture'
], function () {
    Route::get('/', [ProductFurnitureController::class, 'index'])->middleware('admin');
    Route::post('/', [ProductFurnitureController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [ProductFurnitureController::class, 'update'])->middleware('admin');
    Route::delete('/{id}', [ProductFurnitureController::class, 'destroy'])->middleware('admin');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'product-attribute'
], function () {
    Route::get('/', [ProductAttributeController::class, 'index'])->middleware('admin');
    Route::post('/', [ProductAttributeController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [ProductAttributeController::class, 'update'])->middleware('admin');
    Route::delete('/{id}', [ProductAttributeController::class, 'destroy'])->middleware('admin');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'products'
], function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{id}', [ProductController::class, 'show'])->middleware('admin');
    Route::post('/', [ProductController::class, 'store'])->middleware('admin');
    Route::post('/{id}', [ProductController::class, 'update'])->middleware('admin');
    Route::put('/status/{id}', [ProductController::class, 'updateStatus'])->middleware('admin');
    Route::get('/stock/report', [ProductController::class, 'report7Days'])->middleware('admin');
    Route::get('/stock/calendar', [ProductController::class, 'reportMonth'])->middleware('admin');
    Route::get('/stock/export', [ProductController::class, 'exportReport7Days'])->middleware('admin');
});

// Budget
Route::group([
    'middleware' => 'api',
    'prefix' => 'budget-status'
], function () {
    Route::get('/', [BudgetStatusController::class, 'index']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'budgets'
], function () {
    Route::get('/', [BudgetController::class, 'index'])->middleware('admin');
    Route::get('/{id}', [BudgetController::class, 'show'])->middleware('admin');
    Route::get('/pdf/{id}', [BudgetController::class, 'generatePdf'])->middleware('admin');
    Route::post('/', [BudgetController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [BudgetController::class, 'update'])->middleware('admin');
    Route::post('/resend/{id}', [BudgetController::class, 'resendEmail'])->middleware('admin');
    Route::put('/observations/{id}', [BudgetController::class, 'updateObservations'])->middleware('admin');
    Route::put('/status/{id}', [BudgetController::class, 'updateStatus'])->middleware('admin');
    Route::put('/contact/{id}', [BudgetController::class, 'updateContact'])->middleware('admin');
    Route::post('/check-stock', [BudgetController::class, 'checkStock'])->middleware('admin');
    Route::post('/check-price', [BudgetController::class, 'checkPrice'])->middleware('admin');
    Route::post('/sendMails/{id}', [BudgetController::class, 'sendMails'])->middleware('admin');
    Route::get('/generate-pdf-delivery-information/{id}', [BudgetController::class, 'generatePdfDeliveryInformation'])->middleware('admin');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'budgets-audith'
], function () {
    Route::get('/{id}', [BudgetAudithController::class, 'index'])->middleware('admin');
});

// bulk-price-updates
Route::group([
    'middleware' => 'api',
    'prefix' => 'bulk-price-updates'
], function () {
    Route::get('/', [BulkPriceUpdateController::class, 'index'])->middleware('admin');
    Route::post('/', [BulkPriceUpdateController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [BulkPriceUpdateController::class, 'update'])->middleware('admin');
    Route::delete('/{id}', [BulkPriceUpdateController::class, 'destroy'])->middleware('admin');
});

// product-prices
Route::group([
    'middleware' => 'api',
    'prefix' => 'product-prices'
], function () {
    Route::get('/by-date', [ProductPriceController::class, 'getPricesByDate'])->middleware('admin');
    Route::get('/export-prices-by-date', [ProductPriceController::class, 'exportPricesByDate'])->middleware('admin');
});

// Event
Route::group([
    'middleware' => 'api',
    'prefix' => 'event'
], function () {
    Route::get('/', [EventController::class, 'index'])->middleware('admin');
    Route::get('/export-events-by-date', [EventController::class, 'exportEvents'])->middleware('admin');
    Route::get('/{id}', [EventController::class, 'show'])->middleware('admin');
});

// Event Type
Route::group([
    'middleware' => 'api',
    'prefix' => 'event-type'
], function () {
    Route::get('/', [EventTypeController::class, 'index']);
    Route::post('/', [EventTypeController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [EventTypeController::class, 'update'])->middleware('admin');
});

// Budget Delivery Data
Route::group([
    'middleware' => 'api',
    'prefix' => 'budget-delivery-data'
], function () {
    Route::post('/', [BudgetDeliveryDataController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [BudgetDeliveryDataController::class, 'update'])->middleware('admin');
});

// Payment
Route::group([
    'middleware' => 'api',
    'prefix' => 'payment-type'
], function () {
    Route::get('/', [PaymentTypeController::class, 'index']);
    Route::post('/', [PaymentTypeController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [PaymentTypeController::class, 'update'])->middleware('admin');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'payment-method'
], function () {
    Route::get('/', [PaymentMethodController::class, 'index']);
    Route::post('/', [PaymentMethodController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [PaymentMethodController::class, 'update'])->middleware('admin');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'payment-status'
], function () {
    Route::get('/', [PaymentStatusController::class, 'index']);
    Route::post('/', [PaymentStatusController::class, 'store'])->middleware('admin');
    Route::put('/{id}', [PaymentStatusController::class, 'update'])->middleware('admin');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'payment'
], function () {
    Route::get('/', [PaymentController::class, 'index'])->middleware('admin');
    Route::post('/', [PaymentController::class, 'store'])->middleware('admin');
    Route::put('/update-status/{id}', [PaymentController::class, 'updateStatus'])->middleware('admin');
});