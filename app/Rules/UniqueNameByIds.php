<?php

namespace App\Rules;

use App\Traits\Rules\ValueFilterTrait;
use Closure;
use Illuminate\Contracts\Validation\Rule;

class UniqueNameByIds implements Rule
{
    use ValueFilterTrait;

    protected string $modelClass;

    /**
     * @see \Illuminate\Validation\Rules\DatabaseRule::$using
     */
    protected array $using = [];

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
    }

    public static function make(string $modelClass): static
    {
        return new static($modelClass);
    }

    /**
     * @see \Illuminate\Validation\Rules\DatabaseRule::using()
     */
    public function where(Closure $callback): static
    {
        $this->using[] = $callback;
        return $this;
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

        $isNameAmongIdsUnique = $model->newQuery()
            ->select(['name'])
            ->whereKey($ids)
            ->groupBy(['name'])
            ->havingRaw('COUNT(name) > 1')
            ->doesntExist();

        if (! $isNameAmongIdsUnique) {
            return false;
        }

        $query = $model->newQuery()
            ->whereIn('name', $model->newQuery()->select(['name'])->whereKey($ids))
            ->whereKeyNot($ids);

        foreach ($this->using as $callback) {
            $query->where(function ($query) use ($callback) {
                $callback($query);
            });
        }

        return $query->doesntExist();
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
