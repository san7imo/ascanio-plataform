<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\DocumentUploadRequest;
use App\Modules\Documents\Actions\DownloadDocumentVersion;
use App\Modules\Documents\Actions\RetryDocumentScan;
use App\Modules\Documents\Actions\UploadDocumentVersion;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Queries\DocumentQueries;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Queries\WorkspaceQueries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request, Tenant $workspace, DocumentQueries $queries, WorkspaceQueries $workspaces): Response
    {
        Gate::authorize('viewAny', [Document::class, $workspace]);
        $request->validate(['search' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1']]);

        return Inertia::render('documents/Index', [
            'workspace' => $workspaces->projection($workspace),
            'documents' => $queries->listing($request->user(), $workspace, $request->string('search')->toString()),
            'search' => $request->string('search')->toString(), 'uploadsEnabled' => (bool) config('documents.uploads_enabled'),
            'canUpload' => Gate::allows('create', [Document::class, $workspace]),
        ]);
    }

    public function show(Request $request, Tenant $workspace, string $document, DocumentQueries $queries, WorkspaceQueries $workspaces): Response
    {
        $record = $queries->find($request->user(), $workspace, $document);
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);

        return Inertia::render('documents/Show', [
            'workspace' => $workspaces->projection($workspace),
            'document' => ['id' => $record->id, 'title' => $record->title, 'description' => $record->description, 'version_count' => (int) $record->version_count],
            'versions' => $queries->versions($request->user(), $workspace, $record),
            'uploadsEnabled' => (bool) config('documents.uploads_enabled'), 'canUpload' => Gate::allows('upload', [$record, $workspace]),
        ]);
    }

    public function store(DocumentUploadRequest $request, Tenant $workspace, UploadDocumentVersion $action): RedirectResponse
    {
        $version = $action->execute($request->user(), $workspace, null, $request->safe()->except('file'), $request->file('file'));

        return to_route('documents.show', [$workspace, $version->document_id]);
    }

    public function upload(DocumentUploadRequest $request, Tenant $workspace, string $document, UploadDocumentVersion $action): RedirectResponse
    {
        $version = $action->execute($request->user(), $workspace, $document, $request->safe()->except('file'), $request->file('file'));

        return to_route('documents.show', [$workspace, $version->document_id]);
    }

    public function retry(Request $request, Tenant $workspace, string $document, string $version, RetryDocumentScan $action): RedirectResponse
    {
        $action->execute($request->user(), $workspace, $document, $version);

        return to_route('documents.show', [$workspace, $document]);
    }

    public function download(Request $request, Tenant $workspace, string $document, string $version, DownloadDocumentVersion $action): StreamedResponse
    {
        return $action->execute($request->user(), $workspace, $document, $version);
    }
}
