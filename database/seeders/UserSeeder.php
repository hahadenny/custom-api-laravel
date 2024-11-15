<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // create super admin
        \App\Models\User::factory()
            ->unverified()
            ->superAdmin()
            // ->hasWorkspaces()
            ->create([
                 'first_name'     => 'Super Admin First',
                 'last_name'      => 'Super Admin Last',
                 'email'          => 'superadmin@disguise.one',
                 'remember_token' => null,
            ])
            // avoid the password mutator on User
            ->setRawAttributes([
                'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // "password"
            ])
            ->save();

        // create main (on-prem) company admin
        \App\Models\User::factory()
                        ->unverified()
                        ->admin()
                        ->for(Company::factory()->onPrem())
                        ->create([
                             'first_name'     => 'Main User First',
                             'last_name'      => 'Main User Last',
                             'email'          => 'maincompanyadmin@disguise.one',
                             'remember_token' => null,
                         ])
                        // avoid the password mutator on User
                        ->setRawAttributes([
                           'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // "password"
                       ])
                        ->save();

        // comment out since seeding is used for on-prem setup for now
        /*// create local company admin
        \App\Models\User::factory()
            ->unverified()
            ->admin()
            // ->hasWorkspaces()
            ->for(Company::factory()->local())
            ->create([
                 'first_name'     => 'Local First',
                 'last_name'      => 'Local Last',
                 'email'          => 'localadmin@disguise.one',
                 'remember_token' => null,
             ])
            // avoid the password mutator on User
            ->setRawAttributes([
                'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // "password"
            ])
            ->save();

        // create dev company admin
        \App\Models\User::factory()
                        ->unverified()
                        ->admin()
                        // ->hasWorkspaces()
                        ->for(Company::factory()->dev())
                        ->create([
                            'first_name'     => 'Dev First',
                            'last_name'      => 'Dev Last',
                            'email'          => 'devadmin@disguise.one',
                            'remember_token' => null,
                        ])
                        // avoid the password mutator on User
                        ->setRawAttributes([
                            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // "password"
                        ])
                        ->save();

        // create staging company admin
        \App\Models\User::factory()
                        ->unverified()
                        ->admin()
                        // ->hasWorkspaces()
                        ->for(Company::factory()->staging())
                        ->create([
                            'first_name'     => 'Staging First',
                            'last_name'      => 'Staging Last',
                            'email'          => 'stagingadmin@disguise.one',
                            'remember_token' => null,
                        ])
                        // avoid the password mutator on User
                        ->setRawAttributes([
                            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // "password"
                        ])
                        ->save();*/
    }
}
