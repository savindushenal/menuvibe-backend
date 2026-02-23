<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixDuplicateVersions extends Command
{
    protected $signature = 'menu:fix-duplicate-versions';
    protected $description = 'Fix duplicate menu version entries and sync version numbers';

    public function handle()
    {
        $this->info('Checking for duplicate menu versions...');
        
        // Step 1: Find duplicates
        $duplicates = DB::select("
            SELECT 
                master_menu_id, 
                version_number, 
                COUNT(*) as count
            FROM master_menu_versions
            GROUP BY master_menu_id, version_number
            HAVING COUNT(*) > 1
        ");
        
        if (empty($duplicates)) {
            $this->info('✓ No duplicates found!');
        } else {
            $this->warn('Found ' . count($duplicates) . ' duplicate version(s)');
            foreach ($duplicates as $dup) {
                $this->line("  Menu {$dup->master_menu_id}, Version {$dup->version_number}: {$dup->count} copies");
            }
            
            // Step 2: Delete duplicates (keep oldest)
            $this->info('Deleting duplicate versions...');
            $deleted = DB::delete("
                DELETE v1 FROM master_menu_versions v1
                INNER JOIN master_menu_versions v2 
                WHERE 
                    v1.master_menu_id = v2.master_menu_id
                    AND v1.version_number = v2.version_number
                    AND v1.id > v2.id
            ");
            $this->info("✓ Deleted {$deleted} duplicate records");
        }
        
        // Step 3: Update current_version counters
        $this->info('Synchronizing version counters...');
        DB::update("
            UPDATE master_menus m
            SET current_version = (
                SELECT COALESCE(MAX(version_number), 0)
                FROM master_menu_versions v
                WHERE v.master_menu_id = m.id
            )
        ");
        
        // Step 4: Verify
        $inconsistent = DB::select("
            SELECT 
                m.id,
                m.name,
                m.current_version,
                (SELECT MAX(version_number) FROM master_menu_versions v WHERE v.master_menu_id = m.id) as actual_max_version
            FROM master_menus m
            WHERE m.current_version != (SELECT COALESCE(MAX(version_number), 0) FROM master_menu_versions v WHERE v.master_menu_id = m.id)
        ");
        
        if (empty($inconsistent)) {
            $this->info('✓ All version counters synchronized!');
        } else {
            $this->error('Still found ' . count($inconsistent) . ' inconsistent menu(s)');
        }
        
        $this->info('Done!');
        return 0;
    }
}
