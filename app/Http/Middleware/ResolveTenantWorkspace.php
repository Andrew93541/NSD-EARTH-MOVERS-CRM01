<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $company = $user->currentCompany();

            if (! $company && ! $request->routeIs('workspace.*', 'logout')) {
                return redirect()->route('workspace.select');
            }

            if ($company) {
                session(['current_company_id' => $company->id]);
                view()->share('currentCompany', $company);
                view()->share('currentUser', $user);
                view()->share('userCompanies', $user->companies);
                view()->share('currentRole', $user->currentRole());
            }
        }

        return $next($request);
    }
}
