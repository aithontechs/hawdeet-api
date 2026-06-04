<?php

use App\Http\Controllers\Dashboard\Admins\AdminController;
use App\Http\Controllers\Dashboard\Auth\{AuthController ,ForgotPasswordController , LoginController , LogoutController , ResetPasswordController};
use App\Http\Controllers\Dashboard\Authorization\{PermissionController , RoleController };
use App\Http\Controllers\Dashboard\Book\BookController;
use App\Http\Controllers\Dashboard\Category\CategoryController;
use App\Http\Controllers\Dashboard\Chat\AdminChatController;
use App\Http\Controllers\Dashboard\Coupon\CouponController;
use App\Http\Controllers\Dashboard\Notification\AdminNotificationController;
use App\Http\Controllers\Dashboard\Order\OrderController;
use App\Http\Controllers\Dashboard\Settings\ProfileController;
use App\Http\Controllers\Dashboard\Shipping\ShippingZoneController;
use App\Http\Controllers\Dashboard\Subscription\{SubscriptionPlanController , UserSubscriptionController };
use App\Http\Controllers\Dashboard\User\UserController;
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
        Route::apiResource('users' , UserController::class) ;

        // ======= Books =========
        Route::apiResource('books' , BookController::class) ;
        Route::get('books/stream/{book}' , [BookController::class , 'streamFull']) ;
        Route::get('books/preview/{book}' , [BookController::class , 'streamPreview']) ;
        Route::patch('books/{book}/publish' , [BookController::class , 'publish']) ;

        // === Plans
        Route::apiResource('subscription-plans' , SubscriptionPlanController::class) ;

        // ==== User Subscription===
        Route::get('user-subscriptions/stats' , [UserSubscriptionController::class , 'stats']) ;
        Route::apiResource('user-subscriptions' , UserSubscriptionController::class)->except(['update' , 'destroy']) ;
        Route::patch('user-subscriptions/{user_subscriptions}/cancelled' , [UserSubscriptionController::class , 'cancel']) ;
        Route::patch('user-subscriptions/{user_subscriptions}/activate' , [UserSubscriptionController::class , 'activate']) ;


        // ===== Order ======
        Route::get('orders/stats' , [OrderController::class , 'stats']) ;
        Route::apiResource('orders' , OrderController::class)->only(['index' , 'show']) ;


        // ===== Coupons =====
        Route::apiResource('coupons' , CouponController::class) ;

        Route::apiResource('shipping-zones' , ShippingZoneController::class) ;

        Route::get('profile' , [ProfileController::class , 'profile']) ;
        Route::put('profile' , [ProfileController::class , 'updateProfile']) ;


        Route::apiResource('admins' , AdminController::class)->except(['show']) ;

        Route::post('/notifications/broadcast', [AdminNotificationController::class, 'broadcast']);


        Route::prefix('chat')->group(function () {
            Route::get('/conversations', [AdminChatController::class, 'conversations']);
            Route::get('/{user}', [AdminChatController::class, 'show']);
            Route::post('/{user}', [AdminChatController::class, 'store']);
            Route::post('/{user}/mark-read',[AdminChatController::class, 'markAsRead']);
        });



    });
});


