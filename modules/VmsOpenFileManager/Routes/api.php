<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['api']], function () {
    Route::get('/vmsopenfilemanager/test', function () {
        return response()->json(['message' => 'API working']);
    });
});