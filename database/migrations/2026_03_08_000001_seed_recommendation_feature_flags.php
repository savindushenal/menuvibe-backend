<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\PlatformSetting;

return new class extends Migration
{
    public function up(): void
    {
        // Seed recommendation + mascot feature flags into platform_settings.
        // These are system-level toggles controllable by the admin team only.
        $settings = [
            [
                'key'         => 'recommendation_guide_enabled',
                'value'       => 'true',
                'type'        => 'boolean',
                'group'       => 'features',
                'description' => 'Enable the "Not sure what to get?" recommendation guide button on customer menus.',
                'is_public'   => true,
            ],
            [
                'key'         => 'mascot_assistant_enabled',
                'value'       => 'false',
                'type'        => 'boolean',
                'group'       => 'features',
                'description' => 'Enable the mascot/character layer on top of the recommendation guide. Requires recommendation_guide_enabled to be true.',
                'is_public'   => true,
            ],
        ];

        foreach ($settings as $data) {
            \DB::table('platform_settings')->updateOrInsert(
                ['key' => $data['key']],
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    public function down(): void
    {
        \DB::table('platform_settings')
            ->whereIn('key', ['recommendation_guide_enabled', 'mascot_assistant_enabled'])
            ->delete();
    }
};
