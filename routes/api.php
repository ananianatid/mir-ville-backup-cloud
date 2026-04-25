<?php

use App\Http\Controllers\Api\BackupController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.token')->group(function (): void {
    Route::post('/v1/backup', [BackupController::class, 'store'])->name('api.backup.store');
    Route::get('/v1/backup/latest', [BackupController::class, 'latest'])->name('api.backup.latest');
    Route::get('/v1/backups', [BackupController::class, 'index'])->name('api.backup.index');
    Route::get('/v1/backup/{backup}', [BackupController::class, 'download'])->name('api.backup.download');
});
