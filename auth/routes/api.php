<?php

use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

/*
| Auth service: users, roles and permissions (Spatie) and JWT sign-in.
| Every URL starts with /api. Sign in with POST /api/auth/login, then send
| "Authorization: Bearer <token>" here and to the POS backend, which checks
| the token with the same JWT_SECRET. Permissions are "<screen>.<action>".
*/

// ---- Sign in ----
Route::prefix('auth')->group(function () {
    Route::post('register', [Api\AuthController::class, 'register'])->middleware('throttle:sign-in');
    Route::post('login', [Api\AuthController::class, 'login'])->middleware('throttle:sign-in');
    // A new JWT from a refresh token (works after the JWT has run out), and signing a refresh token out.
    Route::post('token/refresh', [Api\AuthController::class, 'refreshWithToken'])->middleware('throttle:token');
    Route::post('token/revoke', [Api\AuthController::class, 'revoke'])->middleware('throttle:token');

    Route::middleware('auth:api')->group(function () {
        Route::get('me', [Api\AuthController::class, 'me']);
        Route::post('refresh', [Api\AuthController::class, 'refresh']);
        Route::post('logout', [Api\AuthController::class, 'logout']);
    });
});

// ---- User Management ----
Route::middleware('auth:api')->group(function () {
    Route::get('users', [Api\UserController::class, 'index'])->middleware('permission:users.view');
    Route::get('users/{user}', [Api\UserController::class, 'show'])->middleware('permission:users.view');
    Route::post('users', [Api\UserController::class, 'store'])->middleware('permission:users.create');
    Route::put('users/{user}', [Api\UserController::class, 'update'])->middleware('permission:users.edit');
    Route::delete('users/{user}', [Api\UserController::class, 'destroy'])->middleware('permission:users.delete');
    Route::get('roles', [Api\RoleController::class, 'index'])->middleware('permission:users.view');
    Route::get('permissions', [Api\RoleController::class, 'permissions'])->middleware('permission:users.view');
    Route::post('roles', [Api\RoleController::class, 'store'])->middleware('permission:users.create');
    Route::put('roles/{name}', [Api\RoleController::class, 'update'])->middleware('permission:users.edit');
    Route::delete('roles/{name}', [Api\RoleController::class, 'destroy'])->middleware('permission:users.delete');
});

// ---- Service to service (POS backend) ----
Route::prefix('internal')->middleware('service.key')->group(function () {
    Route::get('branch-user-counts', [Api\InternalController::class, 'branchUserCounts']);
});
