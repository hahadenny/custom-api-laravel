<?php

namespace App\Api\V1\Requests;

use App\Traits\Requests\DingoFormRequestAdapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use JsonMachine\Items;

class ImportTemplateRequest extends FormRequest
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
                'prohibits:file_path',
                'required_without:file_path',
                'file',
            ],
            'file_path' => 'prohibits:file|required_without:file',
            'ids' => 'array',
            'ids.*' => 'integer',
            'group_ids' => 'array',
            'group_ids.*' => 'integer',
        ];
    }

    public function validateFileImportType(string $importType): void
    {
        $this->validate(['file' => [function ($attribute, $value, $fail) use ($importType) {
            $this->validateImportTypeRule($value, $importType, $fail);
        }]]);
    }

    public function validateDataImportType(string $importType): void
    {
        $this->validate(['file_path' => [function ($attribute, $value, $fail) use ($importType) {
            if (!Storage::disk('local')->exists($value)) {
                $fail(trans('messages.validation.import_file', ['import_type' => $importType]));
            }
        }]]);
    }

    protected function validateImportTypeRule(UploadedFile $value, string $importType, callable $fail): void
    {
        $types = Items::fromFile($value->getPathname(), ['pointer' => '/_type']);
        $success = false;
        foreach ($types as $name => $data) {
            if ($name === '_type' && $data === $importType) {
                $success = true;
            }
        }
        if (!$success) {
            $fail(trans('messages.validation.import_type', ['import_type' => $importType]));
        }
    }
}
