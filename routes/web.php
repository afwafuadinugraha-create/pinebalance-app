<?php

use App\Http\Controllers\WaterBalanceController;

Route::get('/', [WaterBalanceController::class, 'index']);
Route::get('/api/lokasi', [WaterBalanceController::class, 'getLokasiByPG']);
Route::get('/api/water-balance-data', [WaterBalanceController::class, 'getDataByLokasi']);
Route::get('/api/pg-summary', [WaterBalanceController::class, 'getSummaryByPG']);
Route::get('/api/pg-irrigation-monthly', [WaterBalanceController::class, 'getMonthlyIrrigationByPG']);
Route::get('/api/water-balance/template', [WaterBalanceController::class, 'downloadTemplate']);
Route::get('/api/water-balance/export', [WaterBalanceController::class, 'exportExcel']);
Route::post('/api/water-balance/import', [WaterBalanceController::class, 'importExcel']);
