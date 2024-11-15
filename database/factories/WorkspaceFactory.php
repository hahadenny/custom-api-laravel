<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkspaceFactory extends Factory
{

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_id'   => User::factory(),
            'company_id'   => function (array $attributes) {
                return User::find($attributes['user_id'])->company_id;
            },
            'name'      => 'Default',
            'is_active' => 1,
            'layout'    => '{"maxbox": {"id": "+12", "mode": "maximize", "size": 1, "children": []}, "dockbox": {"id": "+1", "mode": "vertical", "size": 400, "children": [{"id": "+3", "size": 260, "tabs": [{"id": "user-listing"}, {"id": "company-listing"}], "activeId": "company-listing"}]}, "floatbox": {"id": "+10", "mode": "float", "size": 1, "children": []}, "windowbox": {"id": "+11", "mode": "window", "size": 1, "children": []}}',
        ];
    }
}
