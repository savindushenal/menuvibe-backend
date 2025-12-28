<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Franchise;

class FranchiseFeatureSeeder extends Seeder
{
    /**
     * Run the database seeders.
     */
    public function run(): void
    {
        // Pizza Hut features
        $pizzahut = Franchise::where('slug', 'pizzahut')->first();
        if ($pizzahut) {
            $pizzahut->update([
                'features' => json_encode([
                    'loyalty_points' => true,
                    'delivery_tracking' => true,
                    'kitchen_display' => true,
                    'custom_modifiers' => true,
                    'buy_2_get_1' => true,
                    'menu_versioning' => true,
                    'bulk_sync' => true,
                ]),
            ]);
            $this->command->info('✓ Pizza Hut features configured');
        }

        // Barista features
        $barista = Franchise::where('slug', 'barista')->first();
        if ($barista) {
            $barista->update([
                'features' => json_encode([
                    'table_booking' => true,
                    'mobile_order' => true,
                    'rewards_program' => true,
                    'custom_coffee_preferences' => true,
                    'menu_versioning' => true,
                    'bulk_sync' => true,
                ]),
            ]);
            $this->command->info('✓ Barista features configured');
        }

        $this->command->info('🎉 Franchise features seeded successfully!');
    }
}
