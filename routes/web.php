<?php

use App\Http\Controllers\WaterBalanceController;

Route::get('/', [WaterBalanceController::class, 'index']);
Route::get('/api/lokasi', [WaterBalanceController::class, 'getLokasiByPG']);
Route::get('/api/water-balance-data', [WaterBalanceController::class, 'getDataByLokasi']);
Route::get('/api/pg-summary', [WaterBalanceController::class, 'getSummaryByPG']);
Route::get('/api/pg-irrigation-monthly', [WaterBalanceController::class, 'getMonthlyIrrigationByPG']);