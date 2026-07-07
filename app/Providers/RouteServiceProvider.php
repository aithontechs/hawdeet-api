<?php

namespace App\Providers;

use App\Services\Setting\SettingService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/application.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // application
        RateLimiter::for('login', function (Request $request) {
            $key = strtolower(trim($request->login)) . '|' . $request->ip();

                $settingService = app(SettingService::class);
                $maxAttempts = (int) $settingService->get('max_failed_login_attempts', 5);
                $banMinutes  = (int) $settingService->get('temporary_ban_duration_minutes', 15);
                return [

                    Limit::perMinutes($banMinutes, $maxAttempts)->by('long:' . $key)->response(function (Request $request, array $headers) {
                        $seconds = $headers['Retry-After'] ?? 0;

                        $time = $seconds >= 60 ? ceil($seconds / 60) . ' دقيقة' : $seconds . ' ثانية';

                            return response()->json([
                                'success' => false,
                                'message' => "تم حظر الدخول لتجاوز الحد المسموح بعدد المحاولات الممكنه يمكنك المحاولة بعد {$time}"
                            ], 429, $headers);
                        }),

                    Limit::perMinutes(2, 3)->by('short:' . $key)->response(function (Request $request, array $headers) {
                            $seconds = $headers['Retry-After'] ?? 0;
                            return response()->json([
                                'success' => false,
                                'message' => "تم حظر الدخول لتجاوز الحد المسموح بعدد المحاولات الممكنه يمكنك المحاولة بعد {$seconds} ثانية"
                            ], 429, $headers);
                        }),

                ];
        });

        RateLimiter::for('resend-verification', function (Request $request) {
            return Limit::perMinutes(2, 1)
                ->by($request->input('email') ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please wait before requesting another email',
                    ], 429);
                });
        });


        RateLimiter::for('login-admin', function (Request $request) {
            $key = strtolower(trim($request->email)) . '|' . $request->ip();
            $settingService = app(SettingService::class);
            $maxAttempts = (int) $settingService->get('max_failed_login_attempts', 5);
            $banMinutes  = (int) $settingService->get('temporary_ban_duration_minutes', 15);

                return [

                    Limit::perMinutes($banMinutes, $maxAttempts)->by('long:' . $key)->response(function (Request $request, array $headers) {
                        $seconds = $headers['Retry-After'] ?? 0;

                        $time = $seconds >= 60 ? ceil($seconds / 60) . ' دقيقة' : $seconds . ' ثانية';

                            return response()->json([
                                'success' => false,
                                'message' => "تم حظر الدخول لتجاوز الحد المسموح بعدد المحاولات الممكنه يمكنك المحاولة بعد {$time}"
                            ], 429, $headers);
                        }),

                    Limit::perMinutes(2, 3)->by('short:' . $key)->response(function (Request $request, array $headers) {
                            $seconds = $headers['Retry-After'] ?? 0;
                            return response()->json([
                                'success' => false,
                                'message' => "تم حظر الدخول لتجاوز الحد المسموح بعدد المحاولات الممكنه يمكنك المحاولة بعد {$seconds} ثانية"
                            ], 429, $headers);
                        }),

                ];
        });
    }
}
