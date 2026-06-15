<?php

use App\Http\Controllers\Application\Auth\{LoginController , LogoutController, RegisterController , ResetPasswordController, SocialiteController, VerificationController};
use App\Http\Controllers\Application\Auth\ForgotPasswordController;
use App\Http\Controllers\Application\Book\BookController;
use App\Http\Controllers\Application\Book\BookHighlightController;
use App\Http\Controllers\Application\Book\BookReaderController;
use App\Http\Controllers\Application\Book\BookReadingProgressController;
use App\Http\Controllers\Application\Book\BookReviewController;
use App\Http\Controllers\Application\BroadCast\BroadCastController;
use App\Http\Controllers\Application\Cart\CartController;
use App\Http\Controllers\Application\Category\CategoryController;
use App\Http\Controllers\Application\Chat\ChatController;
use App\Http\Controllers\Application\Checkout\CheckoutController;
use App\Http\Controllers\Application\Community\CommentController;
use App\Http\Controllers\Application\Community\LikeController;
use App\Http\Controllers\Application\Community\PostController;
use App\Http\Controllers\Application\Community\ShareController;
use App\Http\Controllers\Application\Follow\FollowController;
use App\Http\Controllers\Application\Home\HomeController;
use App\Http\Controllers\Application\Notification\NotificationController;
use App\Http\Controllers\Application\Payment\PaymentController;
use App\Http\Controllers\Application\ReadingCouncil\ReadingCouncilController;
use App\Http\Controllers\Application\Setting\ChangePasswordController;
use App\Http\Controllers\Application\Shipping\ShippingAddressController;
use App\Http\Controllers\Application\Subscription\SubscriptionController;
use App\Http\Controllers\Application\User\UserController;
use App\Http\Controllers\Application\Community\SavedPostController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;


Route::group(['prefix'=> 'v1'] , function () {

    // Authentication
    Route::post('register' , [RegisterController::class , 'store']);

    Route::post('email/verify', [VerificationController::class, 'verify']);
    Route::post('/email/resend-verification', [VerificationController::class, 'resend'])->middleware('throttle:resend-verification');

    Route::post('login' , [LoginController::class , 'login']);

    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendOtp'])->middleware('throttle:3,1');
    Route::post('/forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
    Route::post('/forgot-password/resend-otp', [ForgotPasswordController::class, 'resendOtp'])->middleware('throttle:3,1');
    Route::post('/reset-password', [ResetPasswordController::class, 'resetWithinOtp']);

    Route::get('/socialite/{provider}' , [SocialiteController::class ,'login'] ) ;
    Route::get('redirect/{provider}' , [SocialiteController::class ,'redirect']) ;

    Route::post('logout' , [LogoutController::class , 'logout'])->middleware(['auth:user-api' , 'verified']);


    // Route of Guest

    // Home  & Books
    Route::get('home' , [HomeController::class , 'index']) ;
    Route::get('/home/categories/{category}/books',  [HomeController::class, 'categoryBooks']);
    Route::get('categories' , [CategoryController::class , 'index']) ;
    Route::get('categories/{category}/books' , [BookController::class , 'booksByCategory']);


    // filters
    Route::prefix('books')->group(function () {
        Route::get('/',        [BookController::class, 'index']);
        Route::get('/authors', [BookController::class, 'authors']);
        Route::get('/{book}', [BookController::class, 'show']);
    });


    // Carts & Checkout
    Route::post('/cart/update-items', [CartController::class, 'updateItems']);
    Route::post('/cart/update-actions', [CartController::class, 'updateAll']);
    Route::apiResource('carts' , CartController::class)->except(['update' , 'show']);
    Route::patch('carts/{cartId}/quantity', [CartController::class, 'updateQuantity']);
    Route::get('subscription-plans' , [SubscriptionController::class , 'index']) ;
    Route::get('books/{book}/preview/page/{page}', [BookReaderController::class, 'preview']);

    Route::post('/payments/webhook', [PaymentController::class, 'webhook']);
    Route::get('/payments/callback', [PaymentController::class, 'callback']);

    Route::prefix('councils')->group(function () {
        Route::get('/',          [ReadingCouncilController::class, 'index']);
        Route::get('/featured',  [ReadingCouncilController::class, 'featured']);
        Route::get('/{council}/members', [ReadingCouncilController::class, 'members']);
    }) ;

    // Auth
    Route::middleware(['auth:user-api' , 'verified'])->group(function () {

        // checkout and shipping
        Route::post('/checkout/preview' , [CheckoutController::class , 'preview']);
        Route::post('/checkout' , [CheckoutController::class , 'checkout']);
        Route::post('carts/apply-coupon' , [CartController::class , 'applyCoupon']);


        Route::get('/checkout/shipping-zones' , [CheckoutController::class , 'shippingZones'] );
        Route::apiResource('/shipping/addresses' , ShippingAddressController::class )->except('show');



        // access book
        Route::get('books/{book}/read/page/{page}' , [BookReaderController::class , 'page']);

        Route::get('books/{book}/page/{page}/image',    [BookReaderController::class, 'pageImage']);
        Route::get('books/{book}/preview/{page}/image', [BookReaderController::class, 'previewImage']);

        // Review Book
        Route::apiResource('books/{book}/reviews', BookReviewController::class)->except('show');

        // Hightlight
        Route::apiResource('books/{book}/read/highlights' , BookHighlightController::class)->only('store' , 'destroy') ;

        // Reading Progress
        Route::put('books/{book}/progress' , [BookReadingProgressController::class , 'update']) ;
        Route::get('books/{book}/progress' , [BookReadingProgressController::class , 'show']) ;

        // Subscription
        Route::post('subscription-preview', [SubscriptionController::class, 'preview']);
        Route::post('subscription-plans' , [SubscriptionController::class , 'store']);
        Route::post('subscription-plans/renew',  [SubscriptionController::class, 'renew']);
        Route::post('subscriptions/cancel', [SubscriptionController::class, 'cancel']);

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

        Route::get('saved-posts',           [SavedPostController::class, 'index']);
        Route::post('saved-posts/{post}',   [SavedPostController::class, 'toggle']);

        Route::get('/users/{id}/follow-toggle' , [FollowController::class , 'toggle']) ;
        Route::get('/users/{id}/followers' , [FollowController::class , 'followers']) ;
        Route::get('/users/{id}/following' , [FollowController::class , 'following']) ;
        Route::get('/users/{id}/follow-stats' , [FollowController::class , 'stats']) ;
        // Route::get('/me/mutual-follows' , [FollowController::class , 'mutualFollows']) ;


        // profile
        Route::prefix('user')->group(function () {
            Route::get('/profile', [UserController::class ,'profile']) ;
            Route::get('/profile/{id}', [UserController::class ,'anyProfile']) ;
            Route::put('/profile', [UserController::class ,'updateProfile']) ;
            Route::post('/profile/update', [UserController::class, 'updateProfileForApp']);
            Route::patch('change-password', [ChangePasswordController::class, 'update']) ;
        });

        Route::get('user/library', [BookReadingProgressController::class, 'library']);

        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/',    [NotificationController::class, 'index']);
            Route::get('/unread',  [NotificationController::class, 'unread']);
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('/{id}/mark-read',[NotificationController::class, 'markAsRead']);
            Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
            Route::delete('/{id}',  [NotificationController::class, 'destroy']);
        });
        Route::post('/broadcasting/auth', [BroadCastController::class , 'auth']);

        // Chat
        Route::prefix('chat')->group(function () {
            Route::get('/', [ChatController::class, 'index']);
            Route::post('/',  [ChatController::class, 'store']);
            Route::post('/mark-read', [ChatController::class, 'markAsRead']);
        });


        Route::prefix('councils')->group(function () {
            Route::get('/{council}', [ReadingCouncilController::class, 'show']);
            Route::post('/{council}/join',  [ReadingCouncilController::class, 'join']);
            Route::delete('/{council}/leave', [ReadingCouncilController::class, 'leave']);

            // comments
            Route::get('/{council}/comments',  [ReadingCouncilController::class, 'comments']);
            Route::post('/{council}/comments', [ReadingCouncilController::class, 'addComment']);
            Route::put('/{council}/comments/{comment}', [ReadingCouncilController::class, 'updateComment']);
            Route::delete('/{council}/comments/{comment}', [ReadingCouncilController::class, 'deleteComment']);
            Route::get('/comments/{comment}/replies',[ReadingCouncilController::class, 'replies']);

            // Likes on comments
            Route::post('/{comment}/like', [LikeController::class, 'likeCouncilComment']);


            Route::post('/',  [ReadingCouncilController::class, 'store']);
            Route::put('/{council}', [ReadingCouncilController::class, 'update']);
            Route::delete('/{council}', [ReadingCouncilController::class, 'destroy']);
        });

    });
}) ;



Route::get('send/mail', function () {
    Mail::raw('hello test mails', function ($message) {
        $message->to('mahmoudabdelrahim189@gmail.com')
                ->subject('Test Email');
    });

    return "Done!";
});
