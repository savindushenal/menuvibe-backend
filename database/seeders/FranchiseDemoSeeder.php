<?php

namespace Database\Seeders;

use App\Models\Franchise;
use App\Models\FranchiseUser;
use App\Models\User;
use App\Models\Location;
use Illuminate\Database\Seeder;

class FranchiseDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates demo franchises with design tokens for testing the premium template.
     */
    public function run(): void
    {
        // Create Barista Coffee franchise (demo)
        $barista = Franchise::updateOrCreate(
            ['slug' => 'barista'],
            [
                'name' => 'Barista Coffee',
                'description' => 'Premium coffee experience since 1993',
                'logo_url' => '/logos/barista.svg',
                'favicon_url' => '/favicons/barista.ico',
                'primary_color' => '#F26522',
                'secondary_color' => '#E53935',
                'accent_color' => '#F26522',
                'template_type' => 'premium',
                'design_tokens' => [
                    'colors' => [
                        'primary' => '#F26522',      // Barista orange
                        'secondary' => '#E53935',    // Red for cart/alerts
                        'background' => '#FFF8F0',   // Cream background
                        'dark' => '#1A1A1A',         // Dark text
                        'neutral' => '#F5F5F5',      // Neutral gray
                        'accent' => '#F26522',
                    ],
                    'fonts' => [
                        'heading' => 'Playfair Display',
                        'body' => 'Inter',
                    ],
                    'borderRadius' => 'lg',
                ],
                'support_email' => 'support@barista.lk',
                'support_phone' => '+94 11 234 5678',
                'website_url' => 'https://barista.lk',
                'is_active' => true,
            ]
        );

        // Create Hilton Hotel franchise (demo)
        $hilton = Franchise::updateOrCreate(
            ['slug' => 'hilton-colombo'],
            [
                'name' => 'Hilton Colombo',
                'description' => 'Luxury dining at Hilton Colombo',
                'logo_url' => '/logos/hilton.svg',
                'primary_color' => '#003B5C',
                'secondary_color' => '#C4A35A',
                'accent_color' => '#C4A35A',
                'template_type' => 'premium',
                'design_tokens' => [
                    'colors' => [
                        'primary' => '#003B5C',      // Hilton blue
                        'secondary' => '#C4A35A',    // Gold accent
                        'background' => '#FAFAFA',   // Light gray
                        'dark' => '#1C1C1C',         // Dark text
                        'neutral' => '#F0F0F0',      // Neutral
                        'accent' => '#C4A35A',
                    ],
                    'fonts' => [
                        'heading' => 'Cormorant Garamond',
                        'body' => 'Lato',
                    ],
                    'borderRadius' => 'md',
                ],
                'support_email' => 'dining@hilton.lk',
                'website_url' => 'https://hilton.com/colombo',
                'is_active' => true,
            ]
        );

        // Create a modern cafe franchise (demo)
        $greenLeaf = Franchise::updateOrCreate(
            ['slug' => 'greenleaf-cafe'],
            [
                'name' => 'GreenLeaf Café',
                'description' => 'Organic & sustainable café experience',
                'primary_color' => '#2D5A27',
                'secondary_color' => '#8BC34A',
                'accent_color' => '#FFC107',
                'template_type' => 'premium',
                'design_tokens' => [
                    'colors' => [
                        'primary' => '#2D5A27',      // Forest green
                        'secondary' => '#8BC34A',    // Light green
                        'background' => '#F5F9F4',   // Very light green
                        'dark' => '#1B3318',         // Dark green text
                        'neutral' => '#E8EFE7',      // Neutral green-gray
                        'accent' => '#FFC107',       // Yellow highlight
                    ],
                    'fonts' => [
                        'heading' => 'Montserrat',
                        'body' => 'Open Sans',
                    ],
                    'borderRadius' => 'xl',
                ],
                'support_email' => 'hello@greenleafcafe.com',
                'is_active' => true,
            ]
        );

        $this->command->info('Demo franchises created: Barista Coffee, Hilton Colombo, GreenLeaf Café');

        // Attach first admin user to franchises if exists
        $adminUser = User::first();
        if ($adminUser) {
            foreach ([$barista, $hilton, $greenLeaf] as $franchise) {
                FranchiseUser::updateOrCreate(
                    [
                        'franchise_id' => $franchise->id,
                        'user_id' => $adminUser->id,
                    ],
                    [
                        'role' => 'owner',
                        'permissions' => [
                            'locations' => ['view', 'create', 'edit', 'delete'],
                            'menus' => ['view', 'create', 'edit', 'delete'],
                            'orders' => ['view', 'manage'],
                            'analytics' => ['view'],
                            'settings' => ['view', 'edit'],
                            'team' => ['view', 'invite', 'manage'],
                        ],
                        'is_active' => true,
                        'accepted_at' => now(),
                    ]
                );
            }
            $this->command->info("Admin user {$adminUser->email} added as owner to all demo franchises");
        }
    }
}
