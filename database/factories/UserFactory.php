<?php

namespace Database\Factories;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'first_name' => $this->faker->name(),
            'last_name' => $this->faker->name(),
            'company_id' => null,
            'user_group_id' => null,
            'role' => UserRole::User,
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function unverified()
    {
        return $this->state(function (array $attributes) {
            return [
                'email_verified_at' => null,
            ];
        });
    }

    /**
     * Give the model a specified role
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function role(UserRole $userRole)
    {
        return $this->state(function (array $attributes) use ($userRole) {
            return [
                'role' => $userRole,
            ];
        });
    }

    /**
     * Give the model the SuperAdmin role
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function superAdmin()
    {
        return $this->role(UserRole::SuperAdmin);
    }

    /**
     * Give the model the Admin role
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function admin()
    {
        return $this->role(UserRole::Admin);
    }
}
