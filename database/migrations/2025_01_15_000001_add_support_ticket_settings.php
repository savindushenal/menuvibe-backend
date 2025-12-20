<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add support ticket settings
        $settings = [
            [
                'key' => 'support.max_tickets_per_staff',
                'value' => '5',
                'type' => 'integer',
                'group' => 'support',
                'description' => 'Maximum number of open tickets a support staff member can be assigned at once',
                'is_public' => false,
            ],
            [
                'key' => 'support.auto_assign_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'support',
                'description' => 'Enable automatic ticket assignment to available support staff',
                'is_public' => false,
            ],
            [
                'key' => 'support.auto_reassign_on_close',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'support',
                'description' => 'Automatically assign unassigned tickets when staff closes a ticket and has capacity',
                'is_public' => false,
            ],
            [
                'key' => 'support.prioritize_online_staff',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'support',
                'description' => 'Prioritize online support staff when auto-assigning tickets',
                'is_public' => false,
            ],
        ];

        foreach ($settings as $setting) {
            // Only insert if key doesn't exist
            $exists = DB::table('platform_settings')->where('key', $setting['key'])->exists();
            if (!$exists) {
                DB::table('platform_settings')->insert(array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('platform_settings')
            ->where('group', 'support')
            ->delete();
    }
};
