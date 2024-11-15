<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The content of the file under validation must be of type json.
 * The mimetypes rule works not always. This rule can be used instead.
 */
class FileContentJson implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if ($value instanceof UploadedFile && ! $value->isValid()) {
            return false;
        }

        if (! ($value instanceof File)) {
            return false;
        }

        json_decode($value->getContent());

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return trans('validation.mimetypes', ['values' => 'json']);
    }
}
