<?php

namespace App\Http\Middleware;

use App\Support\ApiEnvelope;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Wraps every JSON reply of the enveloped API paths as {success, message, data} — see ApiEnvelope. */
class ApiEnvelopeResponse
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof JsonResponse && ApiEnvelope::applies($request)) {
            return ApiEnvelope::wrap($response, $request);
        }

        return $response;
    }
}
