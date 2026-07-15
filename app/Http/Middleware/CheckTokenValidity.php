<?php

namespace App\Http\Middleware;

use App\Traits\ResponseApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class CheckTokenValidity
{
    use ResponseApi ;
    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('admin-api')->user();

        if ($admin && $admin->tokens_invalidated_at) {
            try {
                $payload = JWTAuth::parseToken()->getPayload();
                $issuedAt = $payload->get('iat');

                if ($issuedAt < $admin->tokens_invalidated_at->timestamp) {
                    return $this->errorApi('The session become expired after updated your permissions , you can login again', 401) ;
                }
            } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
                return $this->errorApi($e->getMessage(), 401);
            }
        }

        return $next($request);
    }
}
