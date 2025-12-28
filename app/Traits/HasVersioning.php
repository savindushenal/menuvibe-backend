<?php

namespace App\Traits;

use App\Models\MenuVersion;

trait HasVersioning
{
    /**
     * Create a new version of this menu
     */
    public function createVersion(string $description = null): MenuVersion
    {
        return MenuVersion::create([
            'menu_id' => $this->id,
            'version_number' => $this->versions()->count() + 1,
            'description' => $description ?? "Version created at " . now()->toDateTimeString(),
            'data' => $this->toArray(),
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Restore to a specific version
     */
    public function restoreVersion(int $versionNumber): bool
    {
        $version = $this->versions()
            ->where('version_number', $versionNumber)
            ->firstOrFail();

        $this->update($version->data);
        
        return true;
    }

    /**
     * Get latest version
     */
    public function latestVersion()
    {
        return $this->versions()->latest('version_number')->first();
    }

    /**
     * Version history relationship
     */
    public function versions()
    {
        return $this->hasMany(MenuVersion::class);
    }
}
