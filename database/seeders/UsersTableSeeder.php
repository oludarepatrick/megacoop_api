<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\AdminLogin;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $admins = [
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'super@megacoop.com',
                'password' => Hash::make('password'),
                'phone' => '07038635986',
                'role_id' => 1,
            ],
            [
                'first_name' => 'Support',
                'last_name' => 'Admin',
                'email' => 'admin@megacoop.com',
                'password' => Hash::make('password'),
                'phone' => '07038655985',
                
                'role_id' => 2,
            ],
        ];

        foreach ($admins as $user) {
            AdminLogin::updateOrCreate(
                ['email' => $user['email']], // search by email
                $user                          // update or create with this data
            );
        }
    }
}
