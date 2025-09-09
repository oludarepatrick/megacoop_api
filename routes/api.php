<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use \App\Http\Controllers\User\AuthController; 
use \App\Http\Controllers\Admin\AdminLoginController as AdminAuthController;
use \App\Http\Controllers\Admin\AccessCodeController; 

Route::prefix('v1')->group( function(){
    
    Route::prefix('/user')->group( function() {
        Route::post('/login',[AuthController::class, 'login'])->name('login');
        //Route::post('/signup', [UserAuthController::class, 'create'])->name('user.sign-up');
        
        Route::middleware('auth:api')->group(function () {
            Route::post('/generate-code', [AccessCodeController::class, 'generateCode']);
            Route::get('/access-codes', [AccessCodeController::class, 'index']);
            Route::patch('/access-codes/{id}/status"', [AccessCodeController::class, 'updateStatus']);
            //Route::get('/logout', [UserAuthController::class, 'logout']); 
        });
    });

    Route::prefix('/admin')->group(function(){
        Route::post('/admin-login',[AdminAuthController::class, 'login'])->name('admin-login');
        // Route::post('/admin-forgot-password', [AdminAuthController::class, 'sendForgotPasswordToken']);
        // Route::post('/admin-verify-email', [AdminAuthController::class, 'resetPasswordVerify']);
        // Route::post('/admin-reset-password', [AdminAuthController::class, 'resetPassword']);
        Route::middleware('auth:api')->group( function ()
        {
            Route::get('/admin-logout', [AdminAuthController::class, 'logout']);
        });
    });

});


