<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'user_name' => 'test_user',
            'email' => 'test@example.com',
        ]);

        $this->call(BookSeeder::class);
    }
}

// call seeder: php artisan db:seed --class=BookSeeder