<?php

namespace App\Http\Middleware;

use App\Services\ResidentLookup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegisteredPhone
{
    public function __construct(private readonly ResidentLookup $lookup) {}

    public function handle(Request $request, Closure $next): Response
    {
        $phone = $request->session()->get('portal_verified_phone');

        if ($phone === null || ! $this->lookup->resolve($phone)->found()) {
            $request->session()->put('portal_intended', $request->url());

            return redirect()->route('portal.verify');
        }

        return $next($request);
    }
}
