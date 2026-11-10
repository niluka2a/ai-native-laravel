<?php

use App\Http\Controllers\RequestsController;
use Illuminate\Support\Facades\Route;

Route::get('requests', [RequestsController::class, 'list']);
Route::get('requests/history', [RequestsController::class, 'history']);
Route::get('requests/{id}', [RequestsController::class, 'show']);
Route::post('requests', [RequestsController::class, 'store']);
Route::patch('requests/{id}/status', [RequestsController::class, 'updateStatus']);
Route::post('requests/classify', [RequestsController::class, 'classify']);
