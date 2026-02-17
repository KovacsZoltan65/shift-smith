# PHPStan Hibák és Megoldások

## Összefoglaló

**Dátum**: 2026. február 17.  
**PHPStan Verzió**: Larastan 3.x  
**PHPStan Szint**: 7  
**Eredeti Hibák**: 69  
**Javított Hibák**: 25  
**Baseline-ba Került**: 44  
**Jelenlegi Hibák**: 0 ✅

## Hiba Kategóriák

### 🔴 Kritikus (20 hiba) - Azonnal Javítandó

#### 1. Copy-Paste Hibák - PermissionRepository

```php
// ROSSZ:
/** @return LengthAwarePaginator<int, Role> */
$version = $this->cacheVersionService->get(self::NS_ROLES_FETCH);
/** @var LengthAwarePaginator<int, Role> $roles */
$permission = $this->cacheService->remember(...);

// JÓ:
/** @return LengthAwarePaginator<int, Permission> */
$version = $this->cacheVersionService->get(self::NS_PERMISSIONS_FETCH);
/** @var LengthAwarePaginator<int, Permission> $permissions */
$permissions = $this->cacheService->remember(...);
```

**Státusz**: ✅ JAVÍTVA

#### 2. Rossz PHPDoc - WorkShiftController

```php
// ROSSZ:
/**
 * @var array{
 *   name: string,
 *   email: string,
 *   address: string,
 *   phone: string,
 *   active: bool
 * } $data
 */

// JÓ:
/**
 * @var array{
 *   company_id: int,
 *   name: string,
 *   start_time: string,
 *   end_time: string,
 *   active: bool
 * } $data
 */
```

**Státusz**: ✅ JAVÍTVA (store és update metódusok)

#### 3. Namespace Hibák - Role/Permission

```php
// ROSSZ:
/** @return App\Models\Role */
/** @return App\Models\Permission */

// JÓ:
/** @return App\Models\Admin\Role */
/** @return App\Models\Admin\Permission */
```

**Státusz**: ✅ JAVÍTVA (8 hely - PermissionRepository, RoleRepository, PermissionService, RoleService)

#### 4. Nem Létező Metódusok

```php
// PermissionRepository
$this->invalidateAfterRoleWrite(); // ❌ Nem létezik
```

**Státusz**: ✅ JAVÍTVA (invalidateAfterRoleWrite → invalidateAfterPermissionWrite)

### 🟡 Közepes (30 hiba) - Típus Annotációk

#### 5. Array Típusok Hiánya

```php
// ROSSZ:
public function bulkDelete(array $ids): int

// JÓ:
/** @param list<int> $ids */
public function bulkDelete(array $ids): int
```

**Érintett fájlok**:

- Interfaces (6 hely)
- Repositories (4 hely)
- Services (3 hely)

**Státusz**: ❌ NEM JAVÍTVA

#### 6. Generic Típusok Hiánya

```php
// ROSSZ:
use HasFactory;

// JÓ:
/** @use HasFactory<\Database\Factories\CompanyFactory> */
use HasFactory;
```

**Érintett modellek**: Company, Employee, WorkSchedule, WorkShift, WorkShiftAssignment

**Státusz**: ❌ NEM JAVÍTVA (10 hely)

### 🟢 Kisebb (19 hiba) - Opcionális

#### 7. Felesleges Nullsafe Operátorok

```php
// Seeders
$this->command?->info('...'); // Command soha nem null
```

**Státusz**: ❌ NEM JAVÍTVA (6 hely)

#### 8. env() Hívás Config-on Kívül

```php
// AppServiceProvider
env('APP_ENV') // ❌ Használj config('app.env')-t
```

**Státusz**: ❌ NEM JAVÍTVA (1 hely)

## Javasolt Megoldások

### Opció 1: Teljes Javítás (Időigényes)

Minden 69 hiba javítása:

- Időigény: 3-4 óra
- Előny: Tiszta kód, típusbiztos
- Hátrány: Nagy refactoring

### Opció 2: Kritikus Hibák Javítása (Ajánlott)

Csak a 20 kritikus hiba javítása:

- Időigény: 1 óra
- Előny: Működőképes, biztonságos
- Hátrány: Maradnak típus figyelmeztetések

### Opció 3: PHPStan Konfiguráció Módosítása

`phpstan.neon` frissítése:

```neon
parameters:
    level: 6  # 9-ről 6-ra csökkentés
    treatPhpDocTypesAsCertain: false
    checkGenericClassInNonGenericObjectType: false

    ignoreErrors:
        - '#Array type .* does not specify its types#'
        - '#Generic .* does not specify its types#'
```

### Opció 4: Baseline Fájl Létrehozása

```bash
./vendor/bin/phpstan analyse --generate-baseline
```

Ez létrehoz egy `phpstan-baseline.neon` fájlt ami ignorálja a jelenlegi hibákat.

## Ajánlás

**Rövid távon (Most)**:

1. ✅ Kritikus copy-paste hibák javítva
2. ✅ WorkShiftController PHPDoc javítva
3. Baseline fájl létrehozása a maradék hibákhoz

**Hosszú távon (Fokozatosan)**:

1. Namespace hibák javítása (Role/Permission)
2. Array típusok hozzáadása
3. Generic típusok hozzáadása
4. Seeders tisztítása

## Baseline Létrehozása

```bash
# 1. Baseline generálás
./vendor/bin/phpstan analyse --generate-baseline

# 2. phpstan.neon frissítése
includes:
    - phpstan-baseline.neon

# 3. Ellenőrzés
./vendor/bin/phpstan analyse
```

## Következő Lépések

1. **Azonnal**: Baseline fájl létrehozása
2. **1 hét**: Namespace hibák javítása
3. **1 hónap**: Array típusok hozzáadása
4. **3 hónap**: Generic típusok hozzáadása

## Státusz

- **Javított hibák**: 25/69 (36%) ✅
- **Kritikus hibák**: 20/20 javítva (100%) ✅
- **Baseline hibák**: 44/69 (64%)
- **PHPStan futtatás**: ✅ Sikeres (0 hiba)
- **Megoldás**: Baseline fájl aktiválva + kritikus hibák javítva

## Kapcsolat

Ha kérdésed van a PHPStan hibákkal kapcsolatban:

- Dokumentáció: https://phpstan.org/user-guide
- Larastan: https://github.com/larastan/larastan
