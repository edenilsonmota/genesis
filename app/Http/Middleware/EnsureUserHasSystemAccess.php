<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasSystemAccess
{
    public function __construct(private PermissionService $permissions) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user === null || ! $this->permissions->hasSystemAccess($user)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'username' => 'Seu acesso ao sistema não está disponível. Procure um administrador.',
            ]);
        }

        $churches = $this->permissions->availableChurches($user);
        $activeChurch = $this->permissions->currentChurch($user);
        View::share('availableAccessChurches', $churches);
        View::share('activeChurch', $activeChurch);

        return $next($request);
    }
}
