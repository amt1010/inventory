<?php

namespace Database\Seeders;

use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Staff::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Platform Admin', 'password' => Hash::make('password')]
        );

        $admin->assignRole('admin');
    }
}
