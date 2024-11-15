<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class NotInArray implements DataAwareRule, Rule
{
    protected string $anotherField;

    protected array $data;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(string $anotherField)
    {
        $this->anotherField = $anotherField;
    }

    public function setData($data)
    {
        $this->data = $data;
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
        return Validator::make($this->data, [$attribute => 'in_array:'.$this->anotherField])->fails();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        $anotherField = trim(preg_replace('/[.*]+/', ' ', $this->anotherField));
        $anotherField = Validator::make([], [])->getDisplayableAttribute($anotherField);

        return trans('messages.validation.not_in_array', ['other' => $anotherField]);
    }
}
