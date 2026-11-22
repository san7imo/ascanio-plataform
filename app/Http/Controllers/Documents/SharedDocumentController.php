<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Modules\Documents\Actions\DownloadDocumentVersion;
use App\Modules\Documents\Queries\SharedDocumentQueries;
use App\Modules\IdentityAccess\Actions\RecordAudit;
use App\Modules\IdentityAccess\Models\Tenant;
use App\Modules\IdentityAccess\Queries\DocumentGrantAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SharedDocumentController extends Controller
{
    public function index(Request $request, SharedDocumentQueries $queries)
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);

        return Inertia::render('documents/SharedIndex', ['documents' => $queries->listing($request->user())]);
    }

    public function show(Request $request, string $grant, SharedDocumentQueries $queries, DocumentGrantAccess $access, RecordAudit $audit)
    {
        $record = $access->find($request->user(), $grant);
        $audit->execute($request->user(), Tenant::query()->findOrFail($record->tenant_id), 'resource_grant.viewed', 'resource_grant', $record->id, ['document_version_id' => $record->document_version_id, 'projection' => $record->projection]);

        return Inertia::render('documents/SharedShow', ['document' => $queries->projection($request->user(), $grant)]);
    }

    public function download(Request $request, string $grant, DownloadDocumentVersion $action)
    {
        return $action->shared($request->user(), $grant);
    }
}
