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
                'template_type' => 'isso',
                'is_active' => true,
            ]);
        } else {
            echo "Updating Isso franchise...\n";
            $isso->update([
                'template_type' => 'isso',
            ]);
        }

        // Set design tokens for Isso brand
        $isso->design_tokens = [
            'template' => 'isso/demo', // Template identifier for frontend (demo variant)
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

            // Mains - with customizations
            [
                'category' => 'Mains',
                'name' => 'Hot Butter',
                'description' => 'Signature prawns cooked in rich butter sauce with aromatic spices.',
                'price' => 2850,
                'image_url' => 'https://app.menuvire.com/isso/Hot%20Butter.jpg',
                'is_featured' => true,
                'sort_order' => 1,
                'variations' => [
                    [
                        'id' => 'base_section',
                        'name' => 'Select Base (required)',
                        'type' => 'section',
                        'required' => true,
                        'min_selections' => 1,
                        'max_selections' => 1,
                        'options' => [
                            ['id' => 'prawns', 'name' => 'Prawns', 'price_modifier' => 0],
                            ['id' => 'cuttlefish', 'name' => 'Cuttlefish', 'price_modifier' => -200],
                            ['id' => 'mixed', 'name' => 'Mixed Seafood', 'price_modifier' => 100],
                        ]
                    ],
                    [
                        'id' => 'sides_section',
                        'name' => 'Choose Sides (required)',
                        'type' => 'section',
                        'required' => true,
                        'min_selections' => 1,
                        'max_selections' => 1,
                        'options' => [
                            ['id' => 'egg_fried_rice', 'name' => 'Egg Fried Rice', 'price_modifier' => 350],
                            ['id' => 'basmati_rice', 'name' => 'Basmati Rice', 'price_modifier' => 300],
                            ['id' => 'saffron_rice', 'name' => 'Saffron Rice', 'price_modifier' => 400],
                        ]
                    ],
                    [
                        'id' => 'extras_section',
                        'name' => 'Add Extras (optional)',
                        'type' => 'section',
                        'required' => false,
                        'min_selections' => 0,
                        'max_selections' => 2,
                        'options' => [
                            ['id' => 'extra_sauce', 'name' => 'Extra Butter Sauce', 'price_modifier' => 200],
                            ['id' => 'garlic_bread', 'name' => 'Garlic Bread', 'price_modifier' => 450],
                        ]
                    ]
                ]
            ],
            [
                'category' => 'Mains',
                'name' => 'Garlic Butter Prawns',
                'description' => 'Fresh prawns sautéed in fragrant garlic butter with herbs.',
                'price' => 2750,
                'image_url' => 'https://app.menuvire.com/isso/Garlic%20Butter.jpg',
                'is_featured' => false,
                'sort_order' => 2,
                'variations' => [
                    [
                        'id' => 'spice_level_section',
                        'name' => 'Spice Level (required)',
                        'type' => 'section',
                        'required' => true,
                        'min_selections' => 1,
                        'max_selections' => 1,
                        'options' => [
                            ['id' => 'mild', 'name' => 'Mild', 'price_modifier' => 0],
                            ['id' => 'medium', 'name' => 'Medium', 'price_modifier' => 0],
                            ['id' => 'spicy', 'name' => 'Spicy', 'price_modifier' => 0],
                        ]
                    ]
                ]
            ],
            [
                'category' => 'Mains',
                'name' => 'Black Pepper Crusted Yellow Fin Tuna Tataki',
                'description' => 'Seared yellow fin tuna with bold black pepper crust, served rare.',
                'price' => 3250,
                'image_url' => 'https://app.menuvire.com/isso/Black%20Pepper%20Crusted%20Yellow%20Fin%20Tuna%20Tataki.jpg',
                'is_featured' => true,
                'sort_order' => 2,
                'variations' => [
                    [
                        'id' => 'doneness_section',
                        'name' => 'Doneness (required)',
                        'type' => 'section',
                        'required' => true,
                        'min_selections' => 1,
                        'max_selections' => 1,
                        'options' => [
                            ['id' => 'rare', 'name' => 'Rare', 'price_modifier' => 0],
                            ['id' => 'medium_rare', 'name' => 'Medium Rare', 'price_modifier' => 100],
                            ['id' => 'medium', 'name' => 'Medium', 'price_modifier' => 150],
                        ]
                    ],
                    [
                        'id' => 'sauce_section',
                        'name' => 'Choose Sauce (optional)',
                        'type' => 'section',
                        'required' => false,
                        'min_selections' => 0,
                        'max_selections' => 2,
                        'options' => [
                            ['id' => 'ponzu_sauce', 'name' => 'Ponzu Sauce', 'price_modifier' => 100],
                            ['id' => 'ginger_sauce', 'name' => 'Ginger Sauce', 'price_modifier' => 100],
                            ['id' => 'soy_reduction', 'name' => 'Soy Reduction', 'price_modifier' => 75],
                        ]
                    ]
                ]
            ],
            [
                'category' => 'Mains',
                'name' => 'Grilled Fish Steak',
                'description' => 'Premium grilled fish steak with fresh herbs and lemon butter.',
                'price' => 2950,
                'image_url' => 'https://app.menuvire.com/isso/Fish%20Steak.jpg',
                'is_featured' => false,
                'sort_order' => 3,
                // No variations - plain item
            ],

            // Salads - no customizations
            [
                'category' => 'Salads',
                'name' => 'Prawn & Mango Salad',
                'description' => 'Fresh green salad with succulent prawns and sweet mango pieces.',
                'price' => 1950,
                'image_url' => 'https://app.menuvire.com/isso/Prawn%20Mango%20Salad.jpg',
                'is_featured' => false,
                'sort_order' => 1,
            ],
            [
                'category' => 'Salads',
                'name' => 'Tuna & Avocado Salad',
                'description' => 'Tender tuna chunks with creamy avocado in a light dressing.',
                'price' => 2150,
                'image_url' => 'https://app.menuvire.com/isso/Tuna%20Avocado%20Salad.jpg',
                'is_featured' => false,
                'sort_order' => 2,
            ],

            // Special Combos - with simple customization
            [
                'category' => 'Special Combos',
                'name' => 'Seafood Combo Special',
                'description' => 'Limited time offer! A selection of our best seafood dishes at a special price.',
                'price' => 3499,
                'image_url' => 'https://app.menuvire.com/isso/Seafood%20Combo.jpg',
                'is_featured' => true,
                'is_available' => true,
                'sort_order' => 1,
                'variations' => [
                    [
                        'id' => 'portion_section',
                        'name' => 'Choose Portion (required)',
                        'type' => 'section',
                        'required' => true,
                        'min_selections' => 1,
                        'max_selections' => 1,
                        'options' => [
                            ['id' => 'regular', 'name' => 'Regular (2 people)', 'price_modifier' => 0],
                            ['id' => 'large', 'name' => 'Large (4 people)', 'price_modifier' => 1500],
                        ]
                    ]
                ]
            ],
            [
                'category' => 'Special Combos',
                'name' => 'Family Seafood Platter',
                'description' => 'Assorted seafood cooked various ways, perfect for sharing.',
                'price' => 5999,
                'image_url' => 'https://app.menuvire.com/isso/Family%20Platter.jpg',
                'is_featured' => true,
                'is_available' => true,
                'sort_order' => 2,
                // No variations - fixed offering
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
                    'variations' => $itemData['variations'] ?? [],
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
