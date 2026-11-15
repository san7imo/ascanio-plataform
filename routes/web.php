<?php

use App\Http\Controllers\Workspaces\GovernanceController;
use App\Http\Controllers\Workspaces\WorkspaceController;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::get('workspaces/create', [WorkspaceController::class, 'create'])->name('workspaces.create');
    Route::post('workspaces', [WorkspaceController::class, 'store'])->middleware('throttle:10,1')->name('workspaces.store');

    Route::prefix('workspaces/{workspace}')->whereUuid('workspace')->middleware(ResolveWorkspace::class)->group(function () {
        Route::get('/', [WorkspaceController::class, 'show'])->name('workspaces.show');
        Route::post('select', [WorkspaceController::class, 'select'])->name('workspaces.select');
        Route::patch('/', [WorkspaceController::class, 'update'])->middleware('throttle:30,1')->name('workspaces.update');
        Route::get('members', [WorkspaceController::class, 'members'])->name('workspaces.members');
        Route::get('roles', [GovernanceController::class, 'roles'])->name('workspaces.roles');
        Route::middleware('throttle:workspace-governance')->group(function () {
            Route::post('roles', [GovernanceController::class, 'storeRole'])->name('workspaces.roles.store');
            Route::patch('roles/{role}', [GovernanceController::class, 'updateRole'])->whereUuid('role')->name('workspaces.roles.update');
            Route::delete('roles/{role}', [GovernanceController::class, 'deleteRole'])->whereUuid('role')->name('workspaces.roles.destroy');
            Route::put('members/{membership}/roles', [GovernanceController::class, 'assignRoles'])->whereUuid('membership')->name('workspaces.members.roles');
            Route::patch('members/{membership}/status', [GovernanceController::class, 'changeStatus'])->whereUuid('membership')->name('workspaces.members.status');
            Route::delete('members/{membership}', [WorkspaceController::class, 'revoke'])->whereUuid('membership')->name('workspaces.members.revoke');
            Route::post('ownership/transfer', [GovernanceController::class, 'transfer'])->name('workspaces.ownership.transfer');
        });
    });
});

require __DIR__.'/settings.php';
