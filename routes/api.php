<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Toby\Infrastructure\Http\Controllers\Api\CalculateVacationDaysController;

Route::middleware("auth:sanctum")->group(function (): void {
    Route::post("calculate-vacation-days", CalculateVacationDaysController::class);
});
