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

        // Hilton Colombo features
        $hilton = Franchise::where('slug', 'hilton-colombo')->first();
        if ($hilton) {
            $hilton->update([
                'features' => json_encode([
                    'table_booking' => true,
                    'room_service' => true,
                    'event_catering' => true,
                    'menu_versioning' => true,
                    'qr_code_menus' => true,
                ]),
            ]);
            $this->command->info('✓ Hilton Colombo features configured');
        }

        // GreenLeaf Café features
        $greenleaf = Franchise::where('slug', 'greenleaf-cafe')->first();
        if ($greenleaf) {
            $greenleaf->update([
                'features' => json_encode([
                    'online_ordering' => true,
                    'qr_code_menus' => true,
                    'mobile_order' => true,
                ]),
            ]);
            $this->command->info('✓ GreenLeaf Café features configured');
        }

        $this->command->info('🎉 Franchise features seeded successfully!');
    }
}
