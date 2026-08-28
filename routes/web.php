<?php

use App\Http\Controllers\ScaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

Route::middleware(['auth'])->group(function () {
    Route::get('/scale/weight', [ScaleController::class, 'weight'])->name('scale.weight');
    Route::get('/scale/weights', [ScaleController::class, 'weights'])->name('scale.weights');
});
