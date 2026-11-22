<?php

use App\Http\Controllers\Documents\DocumentController;
use App\Http\Controllers\Documents\DocumentSharingController;
use App\Http\Controllers\Documents\SharedDocumentController;
use App\Http\Controllers\Parties\TenantPartyController;
use App\Http\Controllers\ResourceInvitationClaimController;
use App\Http\Controllers\TeamInvitationClaimController;
use App\Http\Controllers\Workspaces\GovernanceController;
use App\Http\Controllers\Workspaces\TeamInvitationController;
use App\Http\Controllers\Workspaces\WorkspaceController;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::get('team-invitations/review', [TeamInvitationClaimController::class, 'review'])->middleware(['auth', 'verified'])->name('team-invitations.review');
Route::post('team-invitations/accept', [TeamInvitationClaimController::class, 'accept'])->middleware(['auth', 'verified', 'throttle:10,1'])->name('team-invitations.accept');
Route::get('team-invitations/{invitation}', [TeamInvitationClaimController::class, 'landing'])->whereUuid('invitation')->name('team-invitations.landing');
Route::post('team-invitations/{invitation}/claim', [TeamInvitationClaimController::class, 'claim'])->whereUuid('invitation')->middleware('throttle:10,1')->name('team-invitations.claim');

Route::get('resource-invitations/{invitation}', [ResourceInvitationClaimController::class, 'landing'])->whereUuid('invitation')->middleware('cache.headers:private;no_store')->name('resource-invitations.landing');
Route::post('resource-invitations/{invitation}/claim', [ResourceInvitationClaimController::class, 'claim'])->whereUuid('invitation')->middleware(['throttle:10,1', 'cache.headers:private;no_store'])->name('resource-invitations.claim');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('cache.headers:private;no_store')->group(function () {
        Route::get('resource-invitations/review/current', [ResourceInvitationClaimController::class, 'review'])->name('resource-invitations.review');
        Route::post('resource-invitations/accept/current', [ResourceInvitationClaimController::class, 'accept'])->middleware('throttle:10,1')->name('resource-invitations.accept');
        Route::get('shared-documents', [SharedDocumentController::class, 'index'])->name('shared-documents.index');
        Route::get('shared-documents/{grant}', [SharedDocumentController::class, 'show'])->whereUuid('grant')->name('shared-documents.show');
        Route::get('shared-documents/{grant}/download', [SharedDocumentController::class, 'download'])->whereUuid('grant')->middleware('throttle:30,1')->name('shared-documents.download');
    });
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::get('workspaces/create', [WorkspaceController::class, 'create'])->name('workspaces.create');
    Route::post('workspaces', [WorkspaceController::class, 'store'])->middleware('throttle:10,1')->name('workspaces.store');

    Route::prefix('workspaces/{workspace}')->whereUuid('workspace')->middleware(ResolveWorkspace::class)->group(function () {
        Route::get('/', [WorkspaceController::class, 'show'])->name('workspaces.show');
        Route::post('select', [WorkspaceController::class, 'select'])->name('workspaces.select');
        Route::patch('/', [WorkspaceController::class, 'update'])->middleware('throttle:30,1')->name('workspaces.update');
        Route::middleware('cache.headers:private;no_store')->group(function () {
            Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
            Route::post('documents', [DocumentController::class, 'store'])->middleware('throttle:10,1')->name('documents.store');
            Route::get('documents/{document}/sharing', [DocumentSharingController::class, 'index'])->whereUuid('document')->name('documents.sharing.index');
            Route::post('documents/{document}/versions/{version}/invitations', [DocumentSharingController::class, 'store'])->whereUuid('document')->whereUuid('version')->middleware('throttle:workspace-governance')->name('documents.sharing.store');
            Route::delete('documents/{document}/sharing/{invitation}', [DocumentSharingController::class, 'revoke'])->whereUuid('document')->whereUuid('invitation')->middleware('throttle:workspace-governance')->name('documents.sharing.revoke');
            Route::post('documents/{document}/sharing/{invitation}/resend', [DocumentSharingController::class, 'resend'])->whereUuid('document')->whereUuid('invitation')->middleware('throttle:workspace-governance')->name('documents.sharing.resend');
            Route::get('documents/{document}', [DocumentController::class, 'show'])->whereUuid('document')->name('documents.show');
            Route::post('documents/{document}/versions', [DocumentController::class, 'upload'])->whereUuid('document')->middleware('throttle:10,1')->name('documents.versions.store');
            Route::get('documents/{document}/versions/{version}/download', [DocumentController::class, 'download'])->whereUuid('document')->whereUuid('version')->middleware('throttle:30,1')->name('documents.download');
            Route::post('documents/{document}/versions/{version}/retry', [DocumentController::class, 'retry'])->whereUuid('document')->whereUuid('version')->middleware('throttle:10,1')->name('documents.retry');
        });
        Route::get('parties', [TenantPartyController::class, 'index'])->name('parties.index');
        Route::get('parties/create', [TenantPartyController::class, 'create'])->name('parties.create');
        Route::post('parties', [TenantPartyController::class, 'store'])->middleware('throttle:30,1')->name('parties.store');
        Route::get('parties/{party}', [TenantPartyController::class, 'show'])->whereUuid('party')->name('parties.show');
        Route::get('parties/{party}/edit', [TenantPartyController::class, 'edit'])->whereUuid('party')->name('parties.edit');
        Route::patch('parties/{party}', [TenantPartyController::class, 'update'])->whereUuid('party')->middleware('throttle:30,1')->name('parties.update');
        Route::get('members', [WorkspaceController::class, 'members'])->name('workspaces.members');
        Route::get('roles', [GovernanceController::class, 'roles'])->name('workspaces.roles');
        Route::get('invitations', [TeamInvitationController::class, 'index'])->name('workspaces.invitations');
        Route::middleware('throttle:workspace-governance')->group(function () {
            Route::post('invitations', [TeamInvitationController::class, 'store'])->name('workspaces.invitations.store');
            Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'revoke'])->whereUuid('invitation')->name('workspaces.invitations.revoke');
            Route::post('invitations/{invitation}/resend', [TeamInvitationController::class, 'resend'])->whereUuid('invitation')->name('workspaces.invitations.resend');
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
