<?php

use Illuminate\Support\Facades\Route;
use Dashed\DashedCore\Middleware\AdminMiddleware;
use Dashed\DashedEcommerceVeloyd\Controllers\VeloydController;

Route::middleware(['web', AdminMiddleware::class])->prefix('dashed/veloyd')->group(function () {
    Route::get('/download-labels', [VeloydController::class, 'downloadLabels'])->name('dashed.veloyd.download-labels');
});
