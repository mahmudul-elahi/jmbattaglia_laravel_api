<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api-explorer', function () {
    abort_unless(app()->environment('local', 'testing'), 404);

    return view('api-explorer');
})->name('api.explorer');
