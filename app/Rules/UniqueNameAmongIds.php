<?php

namespace App\Rules;

use App\Traits\Rules\ValueFilterTrait;
use Illuminate\Contracts\Validation\Rule;

class UniqueNameAmongIds implements Rule
{
    use ValueFilterTrait;

    protected string $modelClass;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
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
        if (! $this->validateIdOrIds($value)) {
            return false;
        }

        $ids = array_values(array_unique($this->filterIds($value)));

        if (empty($ids)) {
            return true;
        }

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $this->modelClass;

        return $model->newQuery()
            ->select(['name'])
            ->whereKey($ids)
            ->groupBy(['name'])
            ->havingRaw('COUNT(name) > 1')
            ->doesntExist();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return trans('messages.validation.unique_name');
    }
}
