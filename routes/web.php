<?php

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
        Route::delete('members/{membership}', [WorkspaceController::class, 'revoke'])->whereUuid('membership')
            ->middleware('throttle:10,1')->name('workspaces.members.revoke');
    });
});

require __DIR__.'/settings.php';
