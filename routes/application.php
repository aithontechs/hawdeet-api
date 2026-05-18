<?php

use App\Http\Controllers\Application\Auth\{LoginController , LogoutController, RegisterController , ResetPasswordController, SocialiteController, VerificationController};
use App\Http\Controllers\Application\Auth\ForgotPasswordController;
use App\Http\Controllers\Application\Book\BookController;
use App\Http\Controllers\Application\Book\BookHighlightController;
use App\Http\Controllers\Application\Book\BookReaderController;
use App\Http\Controllers\Application\Book\BookReadingProgressController;
use App\Http\Controllers\Application\Book\BookReviewController;
use App\Http\Controllers\Application\Cart\CartController;
use App\Http\Controllers\Application\Community\CommentController;
use App\Http\Controllers\Application\Community\LikeController;
use App\Http\Controllers\Application\Community\PostController;
use App\Http\Controllers\Application\Community\ShareController;
use App\Http\Controllers\Application\Payment\PaymentController;
use App\Http\Controllers\Application\Subscription\SubscriptionController;
use App\Http\Controllers\Application\User\UserController;
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


    //Guest
    // Carts & Checkout
    Route::apiResource('carts' , CartController::class)->except(['update' , 'show']);
    Route::get('subscription-plans' , [SubscriptionController::class , 'index']) ;
    Route::get('books/{book}/preview/page/{page}', [BookReaderController::class, 'preview']);


    // Auth
    Route::middleware(['auth:user-api' , 'verified'])->group(function () {
        Route::post('carts/checkout' , [CartController::class , 'checkout']);
        Route::post('carts/apply-coupon' , [CartController::class , 'applyCoupon']);

        // access book
        Route::get('books/{book}/read/page/{page}' , [BookReaderController::class , 'page']);

        // Review Book
        Route::apiResource('books/{book}/reviews', BookReviewController::class)->except('show');

        // Hightlight
        Route::apiResource('books/{book}/read/highlights' , BookHighlightController::class)->only('store' , 'destroy') ;

        // Reading Progress
        Route::put('books/{book}/progress' , [BookReadingProgressController::class , 'update']) ;
        Route::get('books/{book}/progress' , [BookReadingProgressController::class , 'show']) ;

        // Subscription
        Route::post('subscription-plans' , [SubscriptionController::class , 'store']);
        Route::post('subscription-plans/renew',  [SubscriptionController::class, 'renew']);

        Route::get('payment/{payment}' , [PaymentController::class , 'pay'])->name('payment.pay') ; // test only

        // Community ( Posts / Likes / Comments / Share )
        Route::apiResource('posts' , PostController::class) ;

        Route::apiResource('posts/{post}/comments' , CommentController::class)->except('update' , 'destroy' , 'show') ;
        Route::put('comments/{comment}' , [CommentController::class , 'update']);
        Route::delete('comments/{comment}' , [CommentController::class , 'destroy']);
        Route::get('comments/{comment}/replies', [CommentController::class, 'replies']);

        Route::post('posts/{post}/like' , [LikeController::class , 'likePost'])->middleware('throttle:30,1'); ;
        Route::post('comments/{comment}/like' , [LikeController::class , 'likeComment'])->middleware('throttle:30,1'); ;

        Route::post('posts/{post}/share' , [ShareController::class , 'share']);
        Route::delete('posts/{post}/share' , [ShareController::class , 'unshare']);



        Route::prefix('user')->group(function () {
            Route::get('/profile', [UserController::class ,'profile']) ;
            Route::put('/profile', [UserController::class ,'updateProfile']) ;

        });
        // Route::get('user/library', [BookReadingProgressController::class, 'library']);
    });
}) ;



Route::get('send/mail', function () {
    Mail::raw('hello test mails', function ($message) {
        $message->to('mahmoudabdelrahim189@gmail.com')
                ->subject('Test Email');
    });

    return "Done!";
});
