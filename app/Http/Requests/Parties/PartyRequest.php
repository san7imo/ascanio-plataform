<?php

namespace App\Http\Requests\Parties;

use App\Modules\Parties\Data\PartyInput;
use App\Modules\Parties\Models\TenantParty;
use App\Modules\Parties\Queries\PartyQueries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->route('workspace');
        if ($this->isMethod('post')) {
            return Gate::allows('create', [TenantParty::class, $tenant]);
        }
        $party = app(PartyQueries::class)->find($this->user(), $tenant, $this->route('party'));

        return Gate::allows('update', [$party, $tenant]);
    }

    public function rules(): array
    {
        return PartyInput::rules(! $this->isMethod('post'));
    }

    public function messages(): array
    {
        return [
            'display_name.required' => 'Escribe un nombre para la ficha.',
            'business_type_codes.*.exists' => 'Selecciona una actividad disponible.',
            'identifiers.*.scheme.in' => 'Selecciona IPI/CAE, ISNI u otro identificador.',
            'identifiers.*.value.required' => 'Escribe el identificador como texto.',
            'version.required' => 'Recarga la ficha antes de guardar.',
            '*.prohibited' => 'Este campo lo determina el servidor y no se puede asignar desde el formulario.',
        ];
    }
}
