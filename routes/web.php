<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BulkAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Admin routes
    Route::get('/admin', [BulkAdminController::class, 'index'])->name('admin')->middleware('is_admin');
    Route::get('/admin/mail-logs', [BulkAdminController::class, 'mailLogs'])->name('admin.mail-logs')->middleware('is_admin');
    Route::post('/admin/user', [BulkAdminController::class, 'createUser'])->name('admin.user.create')->middleware('is_admin');
    Route::delete('/admin/user/{id}', [BulkAdminController::class, 'deleteUser'])->name('admin.user.delete')->middleware('is_admin');
    Route::get('/admin/domain/{id}/toggle', [BulkAdminController::class, 'toggleDomain'])->name('admin.domain.toggle')->middleware('is_admin');
});

require __DIR__.'/auth.php';
