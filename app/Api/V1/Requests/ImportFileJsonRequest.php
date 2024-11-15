<?php

namespace App\Api\V1\Requests;

use App\Rules\FileContentJson;
use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;

class ImportFileJsonRequest extends FormRequest
{
    use DingoFormRequestAdapter;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'file' => [
                'prohibits:data',
                'required_without:data',
                'file',
                new FileContentJson(),
            ],
            'data' => 'prohibits:file|required_without:file|json',
            'ids' => 'array',
            'ids.*' => 'integer',
            'group_ids' => 'array',
            'group_ids.*' => 'integer',
        ];
    }

    public function validateFileImportType(string $importType): void
    {
        $this->validate(['file' => [function ($attribute, $value, $fail) use ($importType) {
            $this->validateImportTypeRule(json_decode($value->getContent(), true), $importType, $fail);
        }]]);
    }

    public function validateDataImportType(string $importType): void
    {
        $this->validate(['data' => [function ($attribute, $value, $fail) use ($importType) {
            $this->validateImportTypeRule(json_decode($value, true), $importType, $fail);
        }]]);
    }

    protected function validateImportTypeRule($data, string $importType, callable $fail): void
    {
        if (! isset($data['_type']) || $data['_type'] !== $importType) {
            $fail(trans('messages.validation.import_type', ['import_type' => $importType]));
        }
    }
}
