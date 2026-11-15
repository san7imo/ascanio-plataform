<?php

namespace App\Modules\Parties\Data;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PartyInput
{
    public const FIELDS = ['kind', 'display_name', 'legal_name', 'email', 'phone', 'notes', 'source_note', 'business_type_codes', 'identifiers', 'version'];

    public static function rules(bool $updating): array
    {
        $rules = [
            'kind' => ['required', Rule::in(['person', 'organization'])],
            'display_name' => ['required', 'string', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'string', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'source_note' => ['nullable', 'string', 'max:500'],
            'business_type_codes' => ['present', 'array', 'max:20'],
            'business_type_codes.*' => ['required', 'string', 'distinct', Rule::exists('business_types', 'code')],
            'identifiers' => ['present', 'array', 'max:20'],
            'identifiers.*' => ['array:scheme,value,issuer'],
            'identifiers.*.scheme' => ['required', Rule::in(['ipi', 'isni', 'other'])],
            'identifiers.*.value' => ['required', 'string', 'max:120'],
            'identifiers.*.issuer' => ['nullable', 'string', 'max:160'],
            'version' => $updating ? ['required', 'integer', 'min:1'] : ['prohibited'],
        ];
        foreach (['tenant_id', 'party_id', 'created_by', 'user_id', 'owner_id', 'verification_status', 'source', 'verified_at'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    public static function validate(array $values, bool $updating): array
    {
        $values = Validator::make($values, self::rules($updating))->validate();
        $values = array_intersect_key($values, array_flip(self::FIELDS));
        foreach (['display_name', 'legal_name', 'email', 'phone', 'notes', 'source_note'] as $field) {
            $values[$field] = isset($values[$field]) ? trim($values[$field]) : null;
            if ($field !== 'display_name' && $values[$field] === '') {
                $values[$field] = null;
            }
        }
        if ($values['display_name'] === '') {
            throw ValidationException::withMessages(['display_name' => 'Escribe un nombre para la ficha.']);
        }
        $seen = [];
        foreach ($values['identifiers'] as $index => &$identifier) {
            $identifier['value'] = trim($identifier['value']);
            $identifier['issuer'] = isset($identifier['issuer']) ? trim($identifier['issuer']) : null;
            if ($identifier['value'] === '' || isset($seen[$identifier['scheme'].':'.$identifier['value']])) {
                throw ValidationException::withMessages(['identifiers.'.$index.'.value' => 'Usa un identificador no vacío y sin repetir en esta ficha.']);
            }
            $seen[$identifier['scheme'].':'.$identifier['value']] = true;
        }
        unset($identifier);
        usort($values['identifiers'], static fn ($a, $b) => [$a['scheme'], $a['value']] <=> [$b['scheme'], $b['value']]);
        sort($values['business_type_codes']);

        return $values;
    }
}
