<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $isSuperAdmin = $user instanceof User && $user->hasSuperAdminRole();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => fn () => $user,
                'roles' => fn () => $user?->getRoleNames() ?? [],
                'permissions' => fn () => $user?->getAllPermissions()->pluck('name') ?? [],
                'isSuperAdmin' => fn () => $isSuperAdmin,
            ],
            'tenant' => tenant() ? tenant()->only('id', 'name', 'slug', 'logo_url') : null,
            'tenantContext' => fn () => $this->sharedTenantContext($request, $isSuperAdmin),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{
     *     canSelect: bool,
     *     activeTenantId: string|null,
     *     activeTenant: array{id: string, name: string, slug: string}|null
     * }
     */
    protected function sharedTenantContext(Request $request, bool $isSuperAdmin): array
    {
        if (! $isSuperAdmin) {
            return [
                'canSelect' => false,
                'activeTenantId' => null,
                'activeTenant' => null,
            ];
        }

        $tenantId = $request->session()->get($this->tenantContextSessionKey());

        if (! is_string($tenantId) || $tenantId === '') {
            return [
                'canSelect' => true,
                'activeTenantId' => null,
                'activeTenant' => null,
            ];
        }

        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant instanceof Tenant) {
            $request->session()->forget($this->tenantContextSessionKey());

            return [
                'canSelect' => true,
                'activeTenantId' => null,
                'activeTenant' => null,
            ];
        }

        return [
            'canSelect' => true,
            'activeTenantId' => $tenant->id,
            'activeTenant' => $tenant->only('id', 'name', 'slug'),
        ];
    }

    protected function tenantContextSessionKey(): string
    {
        $sessionKey = config('tenancy.tenant_context.session_key');

        return is_string($sessionKey) && $sessionKey !== ''
            ? $sessionKey
            : 'active_tenant_id';
    }
}
