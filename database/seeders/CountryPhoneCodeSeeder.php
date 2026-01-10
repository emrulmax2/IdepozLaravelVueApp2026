<?php

namespace Database\Seeders;

use App\Models\CountryPhoneCode;
use Illuminate\Database\Seeder;

class CountryPhoneCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $codes = [
            [
                'name' => 'Bangladesh',
                'iso_code' => 'BD',
                'dial_code' => '+880',
                'min_nsn_length' => 10,
                'max_nsn_length' => 10,
                'example_format' => '17 1234 5678',
                'is_default' => true,
            ],
            [
                'name' => 'India',
                'iso_code' => 'IN',
                'dial_code' => '+91',
                'min_nsn_length' => 10,
                'max_nsn_length' => 10,
                'example_format' => '98765 43210',
                'is_default' => false,
            ],
            [
                'name' => 'Pakistan',
                'iso_code' => 'PK',
                'dial_code' => '+92',
                'min_nsn_length' => 10,
                'max_nsn_length' => 10,
                'example_format' => '301 2345678',
                'is_default' => false,
            ],
            [
                'name' => 'Nepal',
                'iso_code' => 'NP',
                'dial_code' => '+977',
                'min_nsn_length' => 8,
                'max_nsn_length' => 10,
                'example_format' => '981 2345678',
                'is_default' => false,
            ],
            [
                'name' => 'Sri Lanka',
                'iso_code' => 'LK',
                'dial_code' => '+94',
                'min_nsn_length' => 9,
                'max_nsn_length' => 9,
                'example_format' => '71 234 5678',
                'is_default' => false,
            ],
            [
                'name' => 'United States',
                'iso_code' => 'US',
                'dial_code' => '+1',
                'min_nsn_length' => 10,
                'max_nsn_length' => 10,
                'example_format' => '415 555 2671',
                'is_default' => false,
            ],
            [
                'name' => 'United Kingdom',
                'iso_code' => 'GB',
                'dial_code' => '+44',
                'min_nsn_length' => 10,
                'max_nsn_length' => 10,
                'example_format' => '20 7946 0958',
                'is_default' => false,
            ],
            [
                'name' => 'Canada',
                'iso_code' => 'CA',
                'dial_code' => '+1',
                'min_nsn_length' => 10,
                'max_nsn_length' => 10,
                'example_format' => '604 555 0198',
                'is_default' => false,
            ],
            [
                'name' => 'Singapore',
                'iso_code' => 'SG',
                'dial_code' => '+65',
                'min_nsn_length' => 8,
                'max_nsn_length' => 8,
                'example_format' => '8123 4567',
                'is_default' => false,
            ],
            [
                'name' => 'Philippines',
                'iso_code' => 'PH',
                'dial_code' => '+63',
                'min_nsn_length' => 10,
                'max_nsn_length' => 10,
                'example_format' => '917 123 4567',
                'is_default' => false,
            ],
        ];

        foreach ($codes as $code) {
            CountryPhoneCode::updateOrCreate(
                ['iso_code' => $code['iso_code']],
                [
                    'name' => $code['name'],
                    'dial_code' => $code['dial_code'],
                    'min_nsn_length' => $code['min_nsn_length'],
                    'max_nsn_length' => $code['max_nsn_length'],
                    'example_format' => $code['example_format'],
                    'is_active' => true,
                    'is_default' => $code['is_default'],
                ]
            );
        }
    }
}
