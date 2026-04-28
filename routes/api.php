<?php

use App\Http\Controllers\Dashboard\Auth\AuthController;
use App\Http\Controllers\Dashboard\Auth\ForgotPasswordController;
use App\Http\Controllers\Dashboard\Auth\LoginController;
use App\Http\Controllers\Dashboard\Auth\LogoutController;
use App\Http\Controllers\Dashboard\Auth\ResetPasswordController;
use App\Http\Controllers\Dashboard\Authorization\PermissionController;
use App\Http\Controllers\Dashboard\Authorization\RoleController;
use App\Http\Controllers\Dashboard\Category\CategoryController;
use Illuminate\Support\Facades\Route;



Route::group(['prefix'=> 'v1/admin'], function () {

    // Authentication of system
    Route::post('register',[AuthController::class , 'register'])->name('register') ;
    Route::post('login',[LoginController::class , 'login'])->middleware('throttle:login') ;
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])->middleware('throttle:3,1');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset']);
    Route::post('logout',[LogoutController::class , 'logout'])->name('logout')->middleware('auth:admin-api') ;

    // Authorization
    Route::group(['middleware'=> 'auth:admin-api'], function () {
        Route::apiResource('roles' , RoleController::class) ;
        Route::apiResource('permissions' , PermissionController::class) ;
        Route::apiResource('categories' , CategoryController::class) ;


    });
});


