<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Check if an index exists on a table (MySQL compatible)
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($result) > 0;
    }

    /**
     * Safely add an index if it doesn't exist
     */
    private function addIndexIfNotExists(string $table, $columns, string $indexName): void
    {
        if (!$this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $t) use ($columns, $indexName) {
                $t->index($columns, $indexName);
            });
        }
    }

    /**
     * Run the migrations.
     * 
     * Performance indexes for optimizing frequently queried columns
     * and composite conditions used throughout the application.
     */
    public function up(): void
    {
        // Users table indexes
        $this->addIndexIfNotExists('users', 'role', 'users_role_index');
        $this->addIndexIfNotExists('users', 'is_active', 'users_is_active_index');
        $this->addIndexIfNotExists('users', ['role', 'is_active'], 'users_role_is_active_index');
        $this->addIndexIfNotExists('users', ['role', 'is_online', 'is_active'], 'users_role_is_online_is_active_index');
        $this->addIndexIfNotExists('users', 'created_at', 'users_created_at_index');

        // Support tickets table indexes
        $this->addIndexIfNotExists('support_tickets', 'category', 'support_tickets_category_index');
        $this->addIndexIfNotExists('support_tickets', 'user_id', 'support_tickets_user_id_index');
        $this->addIndexIfNotExists('support_tickets', ['status', 'priority'], 'support_tickets_status_priority_index');
        $this->addIndexIfNotExists('support_tickets', ['status', 'assigned_to'], 'support_tickets_status_assigned_to_index');
        $this->addIndexIfNotExists('support_tickets', ['status', 'created_at'], 'support_tickets_status_created_at_index');

        // Franchise accounts table indexes
        $this->addIndexIfNotExists('franchise_accounts', ['franchise_id', 'user_id'], 'franchise_accounts_franchise_user_index');
        $this->addIndexIfNotExists('franchise_accounts', ['franchise_id', 'location_id'], 'franchise_accounts_franchise_location_index');
        $this->addIndexIfNotExists('franchise_accounts', ['franchise_id', 'is_active'], 'franchise_accounts_franchise_is_active_index');

        // User subscriptions table indexes
        $this->addIndexIfNotExists('user_subscriptions', ['is_active', 'status'], 'user_subscriptions_is_active_status_index');
        $this->addIndexIfNotExists('user_subscriptions', ['is_active', 'status', 'ends_at'], 'user_subscriptions_active_status_ends_index');
        $this->addIndexIfNotExists('user_subscriptions', 'subscription_plan_id', 'user_subscriptions_plan_id_index');

        // Locations table indexes
        $this->addIndexIfNotExists('locations', 'franchise_id', 'locations_franchise_id_index');
        $this->addIndexIfNotExists('locations', ['franchise_id', 'is_active'], 'locations_franchise_is_active_index');
        $this->addIndexIfNotExists('locations', 'user_id', 'locations_user_id_index');

        // Menus table indexes
        $this->addIndexIfNotExists('menus', 'location_id', 'menus_location_id_index');
        $this->addIndexIfNotExists('menus', ['location_id', 'is_active'], 'menus_location_is_active_index');

        // Menu items table indexes
        $this->addIndexIfNotExists('menu_items', 'menu_id', 'menu_items_menu_id_index');
        $this->addIndexIfNotExists('menu_items', 'menu_category_id', 'menu_items_category_id_index');
        $this->addIndexIfNotExists('menu_items', 'is_available', 'menu_items_is_available_index');

        // Notifications table indexes (if exists)
        if (Schema::hasTable('notifications')) {
            $this->addIndexIfNotExists('notifications', 'user_id', 'notifications_user_id_index');
            $this->addIndexIfNotExists('notifications', ['user_id', 'is_read'], 'notifications_user_is_read_index');
            $this->addIndexIfNotExists('notifications', ['user_id', 'created_at'], 'notifications_user_created_index');
        }

        // Admin activity logs table indexes (if exists)
        if (Schema::hasTable('admin_activity_logs')) {
            $this->addIndexIfNotExists('admin_activity_logs', 'user_id', 'admin_activity_logs_user_id_index');
            $this->addIndexIfNotExists('admin_activity_logs', 'action', 'admin_activity_logs_action_index');
            $this->addIndexIfNotExists('admin_activity_logs', 'created_at', 'admin_activity_logs_created_at_index');
        }
    }

    /**
     * Safely drop an index if it exists
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            Schema::table($table, function (Blueprint $t) use ($indexName) {
                $t->dropIndex($indexName);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('users', 'users_role_index');
        $this->dropIndexIfExists('users', 'users_is_active_index');
        $this->dropIndexIfExists('users', 'users_role_is_active_index');
        $this->dropIndexIfExists('users', 'users_role_is_online_is_active_index');
        $this->dropIndexIfExists('users', 'users_created_at_index');

        $this->dropIndexIfExists('support_tickets', 'support_tickets_category_index');
        $this->dropIndexIfExists('support_tickets', 'support_tickets_user_id_index');
        $this->dropIndexIfExists('support_tickets', 'support_tickets_status_priority_index');
        $this->dropIndexIfExists('support_tickets', 'support_tickets_status_assigned_to_index');
        $this->dropIndexIfExists('support_tickets', 'support_tickets_status_created_at_index');

        $this->dropIndexIfExists('franchise_accounts', 'franchise_accounts_franchise_user_index');
        $this->dropIndexIfExists('franchise_accounts', 'franchise_accounts_franchise_location_index');
        $this->dropIndexIfExists('franchise_accounts', 'franchise_accounts_franchise_is_active_index');

        $this->dropIndexIfExists('user_subscriptions', 'user_subscriptions_is_active_status_index');
        $this->dropIndexIfExists('user_subscriptions', 'user_subscriptions_active_status_ends_index');
        $this->dropIndexIfExists('user_subscriptions', 'user_subscriptions_plan_id_index');

        $this->dropIndexIfExists('locations', 'locations_franchise_id_index');
        $this->dropIndexIfExists('locations', 'locations_franchise_is_active_index');
        $this->dropIndexIfExists('locations', 'locations_user_id_index');

        $this->dropIndexIfExists('menus', 'menus_location_id_index');
        $this->dropIndexIfExists('menus', 'menus_location_is_active_index');

        $this->dropIndexIfExists('menu_items', 'menu_items_menu_id_index');
        $this->dropIndexIfExists('menu_items', 'menu_items_category_id_index');
        $this->dropIndexIfExists('menu_items', 'menu_items_is_available_index');

        if (Schema::hasTable('notifications')) {
            $this->dropIndexIfExists('notifications', 'notifications_user_id_index');
            $this->dropIndexIfExists('notifications', 'notifications_user_is_read_index');
            $this->dropIndexIfExists('notifications', 'notifications_user_created_index');
        }

        if (Schema::hasTable('admin_activity_logs')) {
            $this->dropIndexIfExists('admin_activity_logs', 'admin_activity_logs_user_id_index');
            $this->dropIndexIfExists('admin_activity_logs', 'admin_activity_logs_action_index');
            $this->dropIndexIfExists('admin_activity_logs', 'admin_activity_logs_created_at_index');
        }
    }
};
