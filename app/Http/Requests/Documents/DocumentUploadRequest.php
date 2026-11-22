<?php

namespace App\Http\Requests\Documents;

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Queries\DocumentQueries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DocumentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->route('workspace');
        if ($id = $this->route('document')) {
            $document = app(DocumentQueries::class)->find($this->user(), $tenant, $id);

            return Gate::allows('upload', [$document, $tenant]);
        }

        return Gate::allows('create', [Document::class, $tenant]);
    }

    public function rules(): array
    {
        $rules = ['file' => ['required', 'file'], 'upload_key' => ['required', 'uuid'],
            'expected_version' => $this->route('document') ? ['required', 'integer', 'min:1'] : ['prohibited']];
        if (! $this->route('document')) {
            $rules += ['title' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:1000']];
        }
        foreach (['tenant_id', 'uploaded_by', 'storage_path', 'scan_status', 'sha256', 'size_bytes', 'mime_type'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
