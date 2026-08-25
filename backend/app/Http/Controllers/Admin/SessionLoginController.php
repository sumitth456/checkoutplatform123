<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\AdminUserService;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SessionLoginController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $remember)) {
            throw ValidationException::withMessages([
                'email' => [__('filament-panels::auth/pages/login.messages.failed')],
            ]);
        }

        $user = Auth::user();

        if ($user instanceof FilamentUser && ! $user->canAccessPanel(Filament::getPanel('admin'))) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => ['You do not have access to the admin panel.'],
            ]);
        }

        $request->session()->regenerate();

        if ($user instanceof \App\Models\User) {
            app(AdminUserService::class)->recordLogin($user, $request->ip());
        }

        return response()->json([
            'redirect' => '/admin',
        ]);
    }
}
