<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'company_id'   => Company::factory(),
            'name'      => $this->faker->word(),
            '_lft' => 0,
            '_rft' => 0,
        ];
    }
}
