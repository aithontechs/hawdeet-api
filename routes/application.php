<?php

use App\Http\Controllers\Application\Auth\{LoginController , LogoutController, RegisterController , ResetPasswordController, SocialiteController, VerificationController};
use App\Http\Controllers\Application\Auth\ForgotPasswordController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;


// Authentication
Route::group(['prefix'=> 'v1'] , function () {
    Route::post('register' , [RegisterController::class , 'store']);
    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])->middleware('signed')->name('verification.verify') ;
    Route::post('/email/resend-verification', [VerificationController::class, 'resend'])->middleware('throttle:resend-verification');
    Route::post('login' , [LoginController::class , 'login']);
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])->middleware('throttle:3,1');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset']);
    Route::get('/socialite/{provider}' , [SocialiteController::class ,'login'] ) ;
    Route::get('redirect/{provider}' , [SocialiteController::class ,'redirect']) ;


    // http://127.0.0.1:8000/app/redirect/google
    Route::post('logout' , [LogoutController::class , 'logout'])->middleware(['auth:user-api' , 'verified']);
}) ;


Route::get('send/mail', function () {
    Mail::raw('hello test mails', function ($message) {
        $message->to('mahmoudabdelrahim189@gmail.com')
                ->subject('Test Email');
    });

    return "Done!";
});
