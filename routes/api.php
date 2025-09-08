<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\AuthController; 
use \App\Http\Controllers\Admin\AccessCodeController; 

Route::prefix('v1')->group( function(){
    Route::post('/login',[AuthController::class, 'login'])->name('login');

    Route::prefix('/admin')->group( function() {
        //Route::post('/signup', [UserAuthController::class, 'create'])->name('user.sign-up');
        
        Route::middleware('auth:api')->group(function () {
            Route::post('/generate-code', [AccessCodeController::class, 'generateCode']);
            Route::get('/access-codes', [AccessCodeController::class, 'index']);
            Route::patch('/access-codes/{id}/status"', [AccessCodeController::class, 'updateStatus']);
            //Route::get('/logout', [UserAuthController::class, 'logout']); 
        });
    });

});
