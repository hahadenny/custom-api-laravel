<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            UserSeeder::class,
            PermissionSeeder::class,
        ]);

        $admins = User::where('role', '=', 'admin')->get()->unique();
        $company = $admins->first()->company;
        // enable disguise templates off the bat
        $company->companyIntegrations()->create(['type' => 'disguise']);

        foreach($admins as $admin){
            $company = $admin->company;
            $project = \App\Models\Project::factory()
                                          ->for($admin, 'createdBy')
                                          ->for($company)
                                          ->create(['name' => 'Default Project']);

        }

    }
}
