<?php

use Illuminate\Support\Facades\Route;

Route::get('/user', function () {
    return response()->json([
        'message' => 'Hello World'
    ]);
});
