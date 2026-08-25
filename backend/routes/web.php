<?php

use App\Http\Controllers\Admin\SessionLoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/admin/session-login', [SessionLoginController::class, 'store'])
    ->middleware(['web'])
    ->name('admin.session-login');
