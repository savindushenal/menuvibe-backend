<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Franchise;
use App\Models\MenuTemplate;
use App\Models\MenuTemplateCategory;
use App\Models\MenuTemplateItem;
use Illuminate\Support\Str;

class IssoDemoSeeder extends Seeder
{
    public function run()
    {
        // Find or create Isso franchise
        $isso = Franchise::where('slug', 'isso')->first();
        
        if (!$isso) {
            echo "Creating Isso franchise...\n";
            $isso = Franchise::create([
                'name' => 'Isso',
                'slug' => 'isso',
                'template_type' => 'premium',
                'is_active' => true,
            ]);
        } else {
            echo "Updating Isso franchise...\n";
            $isso->update([
                'template_type' => 'premium',
            ]);
        }

        // Set design tokens for Isso brand
        $isso->design_tokens = [
            'template' => 'isso-seafood', // Template identifier for frontend
            'brand' => [
                'name' => 'ISSO',
                'tagline' => 'Experience ISSO, a home-grown Sri Lankan seafood restaurant chain offering export-quality prawns and more.',
                'logo' => 'https://app.menuvire.com/isso-logo.png',
                'greeting' => 'Good Afternoon! 🦐',
            ],
            'colors' => [
                'primary' => '#FF6B35',      // Orange/Coral for seafood theme
                'secondary' => '#004E89',     // Deep blue for ocean theme
                'accent' => '#F77F00',        // Warm orange accent
                'background' => '#FFFFFF',
                'text' => '#2C3E50',
                'textLight' => '#718096',
            ],
            'contact' => [
                'address' => '53 Ananda Kumaraswami Mawatha, Colombo 03',
                'phone' => '+94 11 234 5678',
                'hours' => '8 AM – 11 PM Daily',
            ],
        ];
        $isso->save();

        echo "✅ Isso franchise configured with design tokens\n";

        // Get admin user for template creation
        $adminUser = \App\Models\User::first();
        if (!$adminUser) {
            echo "⚠️  No users found. Please create a user first.\n";
            return;
        }

        // Create or update menu template
        $template = MenuTemplate::where('franchise_id', $isso->id)
            ->where('name', 'Isso Main Menu')
            ->first();

        if (!$template) {
            echo "Creating Isso Main Menu template...\n";
            $template = MenuTemplate::create([
                'user_id' => $adminUser->id,
                'franchise_id' => $isso->id,
                'name' => 'Isso Main Menu',
                'description' => 'Premium seafood menu featuring export-quality prawns and fresh seafood',
                'is_active' => true,
                'currency' => 'LKR',
                'slug' => 'isso-main-menu',
                'settings' => [
                    'template_type' => 'isso', // Use dedicated isso-seafood template
                    'show_prices' => true,
                    'show_images' => true,
                    'currency_symbol' => 'LKR',
                ],
            ]);
        }

        echo "✅ Menu template created (ID: {$template->id})\n";

        // Create categories
        $categories = [
            [
                'name' => 'Appetizers',
                'description' => 'Start your meal with our signature seafood appetizers',
                'icon' => '🦐',
                'sort_order' => 1,
            ],
            [
                'name' => 'Mains',
                'description' => 'Premium seafood main courses',
                'icon' => '🍽️',
                'sort_order' => 2,
            ],
            [
                'name' => 'Salads',
                'description' => 'Fresh and healthy seafood salads',
                'icon' => '🥗',
                'sort_order' => 3,
            ],
            [
                'name' => 'Special Combos',
                'description' => 'Value combos and special offers',
                'icon' => '⭐',
                'sort_order' => 4,
            ],
        ];

        $categoryMap = [];
        foreach ($categories as $catData) {
            $category = MenuTemplateCategory::updateOrCreate(
                [
                    'template_id' => $template->id,
                    'slug' => Str::slug($catData['name']),
                ],
                [
                    'name' => $catData['name'],
                    'description' => $catData['description'],
                    'icon' => $catData['icon'],
                    'sort_order' => $catData['sort_order'],
                    'is_active' => true,
                ]
            );
            $categoryMap[$catData['name']] = $category->id;
            echo "  ✓ Category: {$catData['name']}\n";
        }

        // Create menu items
        $items = [
            // Appetizers
            [
                'category' => 'Appetizers',
                'name' => 'Batter Fried Prawns 4pcs',
                'description' => 'Crispy golden prawns in a light, fluffy batter served with tangy dipping sauce.',
                'price' => 1850,
                'image_url' => 'https://app.menuvire.com/isso/Batter%20Fried%20Prawns%204pcs.jpg',
                'is_featured' => true,
                'sort_order' => 1,
            ],
            [
                'category' => 'Appetizers',
                'name' => 'Coconut Crumbed Prawns 4 pcs',
                'description' => 'Succulent prawns coated in crispy coconut flakes, perfectly golden fried.',
                'price' => 1950,
                'image_url' => 'https://app.menuvire.com/isso/Coconut%20Crumbed%20Prawns%204%20pcs.jpg',
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'category' => 'Appetizers',
                'name' => 'Isso Wade 4pcs',
                'description' => 'Traditional Sri Lankan prawn fritters with aromatic spices and herbs.',
                'price' => 1650,
                'image_url' => 'https://app.menuvire.com/isso/Isso%20wade%204pcs.jpg',
                'is_featured' => false,
                'sort_order' => 3,
            ],
            [
                'category' => 'Appetizers',
                'name' => 'Prawn Soufflé Toast',
                'description' => 'Crispy toast topped with light, airy prawn soufflé and garnished with herbs.',
                'price' => 1450,
                'image_url' => 'https://app.menuvire.com/isso/Prawn%20Souffl%C3%A9%20Toast.jpg',
                'is_featured' => false,
                'sort_order' => 4,
            ],
            [
                'category' => 'Appetizers',
                'name' => 'Sri Lankan Tuna Cutlets 4pcs',
                'description' => 'Spiced tuna cutlets with Sri Lankan flavors, crispy on the outside, tender inside.',
                'price' => 1350,
                'image_url' => 'https://app.menuvire.com/isso/Sri%20Lankan%20Tuna%20Cutlets%204pcs.jpg',
                'is_featured' => false,
                'sort_order' => 5,
            ],

            // Mains
            [
                'category' => 'Mains',
                'name' => 'Hot Butter',
                'description' => 'Signature prawns cooked in rich butter sauce with aromatic spices.',
                'price' => 2850,
                'image_url' => 'https://app.menuvire.com/isso/Hot%20Butter.jpg',
                'is_featured' => true,
                'sort_order' => 1,
            ],
            [
                'category' => 'Mains',
                'name' => 'Black Pepper Crusted Yellow Fin Tuna Tataki',
                'description' => 'Seared yellow fin tuna with bold black pepper crust, served rare.',
                'price' => 3250,
                'image_url' => 'https://app.menuvire.com/isso/Black%20Pepper%20Crusted%20Yellow%20Fin%20Tuna%20Tataki.jpg',
                'is_featured' => true,
                'sort_order' => 2,
            ],

            // Special Combos
            [
                'category' => 'Special Combos',
                'name' => 'Seafood Combo Special',
                'description' => 'Limited time offer! A selection of our best seafood dishes at a special price.',
                'price' => 3499,
                'image_url' => 'https://app.menuvire.com/isso/Hot%20Butter.jpg',
                'is_featured' => true,
                'is_available' => true,
                'sort_order' => 1,
            ],
        ];

        foreach ($items as $itemData) {
            $categoryId = $categoryMap[$itemData['category']];
            
            MenuTemplateItem::updateOrCreate(
                [
                    'template_id' => $template->id,
                    'slug' => Str::slug($itemData['name']),
                ],
                [
                    'category_id' => $categoryId,
                    'name' => $itemData['name'],
                    'description' => $itemData['description'],
                    'price' => $itemData['price'],
                    'currency' => 'LKR',
                    'image_url' => $itemData['image_url'],
                    'is_available' => $itemData['is_available'] ?? true,
                    'is_featured' => $itemData['is_featured'],
                    'sort_order' => $itemData['sort_order'],
                ]
            );
            echo "  ✓ Item: {$itemData['name']} (LKR {$itemData['price']})\n";
        }

        echo "\n✅ Isso demo menu seeded successfully!\n";
        echo "Franchise ID: {$isso->id}\n";
        echo "Template ID: {$template->id}\n";
        echo "Total Categories: " . count($categories) . "\n";
        echo "Total Items: " . count($items) . "\n";
    }
}
