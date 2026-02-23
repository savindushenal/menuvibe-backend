-- Fix duplicate menu versions in production database

-- Step 1: Check for duplicates
SELECT 
    master_menu_id, 
    version_number, 
    COUNT(*) as count
FROM master_menu_versions
GROUP BY master_menu_id, version_number
HAVING COUNT(*) > 1;

-- Step 2: Delete duplicate versions (keep the oldest one)
DELETE v1 FROM master_menu_versions v1
INNER JOIN master_menu_versions v2 
WHERE 
    v1.master_menu_id = v2.master_menu_id
    AND v1.version_number = v2.version_number
    AND v1.id > v2.id;

-- Step 3: Update current_version to match the actual max version
UPDATE master_menus m
SET current_version = (
    SELECT COALESCE(MAX(version_number), 0)
    FROM master_menu_versions v
    WHERE v.master_menu_id = m.id
);

-- Step 4: Verify the fix
SELECT 
    m.id,
    m.name,
    m.current_version,
    (SELECT MAX(version_number) FROM master_menu_versions v WHERE v.master_menu_id = m.id) as actual_max_version
FROM master_menus m
WHERE m.current_version != (SELECT COALESCE(MAX(version_number), 0) FROM master_menu_versions v WHERE v.master_menu_id = m.id);
