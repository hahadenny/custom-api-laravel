<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
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
            // 'workspace_id' => Workspace::factory(),
            'name'         => ucwords($this->faker->word()).' Project',
            'sort_order'   => $this->faker->randomNumber(2),
            'created_by'   => User::factory(),
        ];
    }
}
