<?php

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
        // $this->call(UsersTableSeeder::class);

        DB::table('users')->insert([
            'name' => 'Rolly Domingo',
            'email' => 'domingorolly11@gmail.com',
            'access_level' => 'admin',
            'password' => bcrypt('password'),
        ]);
    }
}
