<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\Project::factory()->create();
    }
}
