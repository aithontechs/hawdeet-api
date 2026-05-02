<?php

use App\Http\Controllers\Application\Auth\{LoginController , LogoutController, RegisterController , ResetPasswordController, SocialiteController, VerificationController};
use App\Http\Controllers\Application\Auth\ForgotPasswordController;
use App\Http\Controllers\Application\Book\BookController;
use App\Http\Controllers\Application\Cart\CartController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;


Route::group(['prefix'=> 'v1'] , function () {

    // Authentication
    Route::post('register' , [RegisterController::class , 'store']);
    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])->middleware('signed')->name('verification.verify') ;
    Route::post('/email/resend-verification', [VerificationController::class, 'resend'])->middleware('throttle:resend-verification');
    Route::post('login' , [LoginController::class , 'login']);
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])->middleware('throttle:3,1');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset']);
    Route::get('/socialite/{provider}' , [SocialiteController::class ,'login'] ) ;
    Route::get('redirect/{provider}' , [SocialiteController::class ,'redirect']) ;
    Route::post('logout' , [LogoutController::class , 'logout'])->middleware(['auth:user-api' , 'verified']);


    // Route of Guest

    // books
    Route::get('books' , [BookController::class , 'index']);
    Route::get('categories/{category}/books' , [BookController::class , 'booksByCategory']);


    // Carts
    Route::apiResource('carts' , CartController::class)->except(['update' , 'show']);
    Route::post('carts/checkout' , [CartController::class , 'checkout'])->middleware('auth:user-api') ;



}) ;



Route::get('send/mail', function () {
    Mail::raw('hello test mails', function ($message) {
        $message->to('mahmoudabdelrahim189@gmail.com')
                ->subject('Test Email');
    });

    return "Done!";
});
