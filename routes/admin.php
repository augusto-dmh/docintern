<?php

use App\Http\Controllers\Admin\QueueHealthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('admin/queue-health', QueueHealthController::class)
        ->name('admin.queue-health');
});
