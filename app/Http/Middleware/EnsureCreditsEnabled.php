<?php

namespace App\Http\Middleware;

use App\Services\CreditService;
use App\Services\Crm\OwnerContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Credits switched off in Admin › Credits → the CRM's credits endpoints answer 404 for accounts with no
 * credits left (nothing about credits is reachable, not even by URL). An account that still holds
 * credits keeps its page, history and calculator; buying is refused separately (CreditService::canBuy).
 */
class EnsureCreditsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(OwnerContext::class);
        abort_unless($context->isAdmin() || CreditService::shownTo($context->owner()), 404, 'Credits aren\'t available at the moment.');

        return $next($request);
    }
}
