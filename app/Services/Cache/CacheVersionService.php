<?php

namespace App\Services\Cache;

use App\Models\TenantGroup;
use Illuminate\Support\Facades\Cache;

/**
 * Cache verzió kezelő szolgáltatás
 * 
 * Namespace-alapú verziókezelést biztosít a cache invalidáláshoz.
 * Minden namespace-hez egy verzió számot tárol, amit növelni lehet.
 * Cache kulcsok tartalmazzák a verzió számot, így verzió növeléssel
 * az összes kapcsolódó cache bejegyzés érvénytelenné válik.
 */
final class CacheVersionService
{
    private const PREFIX = 'v:'; // v:<namespace>
    private const TENANT_CTX_KEY = 'tenantId';

    /**
     * Verzió szám lekérése egy namespace-hez
     * 
     * @param string $namespace Cache namespace azonosító (pl. 'users.fetch', 'companies.list')
     * @param int $default Alapértelmezett verzió, ha még nincs beállítva
     * @return int Aktuális verzió szám
     */
    public function get(string $namespace, int $default = 1): int
    {
        $qualifiedNamespace = $this->qualifyNamespace($namespace);
        $key = self::PREFIX.$qualifiedNamespace;

        return (int) Cache::get($key, $default);
    }

    /**
     * Verzió szám növelése (cache invalidálás)
     * 
     * Növeli a namespace verzió számát, ezzel érvényteleníti az összes
     * kapcsolódó cache bejegyzést. Ha a verzió még nem létezik, 1-re állítja.
     * 
     * @param string $namespace Cache namespace azonosító
     * @return int Az új verzió szám
     */
    public function bump(string $namespace): int
    {
        $qualifiedNamespace = $this->qualifyNamespace($namespace);
        $key = self::PREFIX.$qualifiedNamespace;

        if (! Cache::has($key)) {
            Cache::forever($key, 1);
        }

        return (int) Cache::increment($key);
    }

    private function tenantId(): ?int
    {
        $tenantId = app()->bound('context') ? app('context')->get(self::TENANT_CTX_KEY) : null;
        if (! is_numeric($tenantId)) {
            $tenantId = TenantGroup::current()?->id;
        }

        if (! is_numeric($tenantId)) {
            return null;
        }

        $id = (int) $tenantId;

        return $id > 0 ? $id : null;
    }

    private function qualifyNamespace(string $namespace): string
    {
        $tenantId = $this->tenantId();

        if ($tenantId === null) {
            return "landlord:{$namespace}";
        }

        return "tenant:{$tenantId}:{$namespace}";
    }
}
