# PHPStan Hibák Javítási Terve

## Kritikus Hibák (Azonnal javítandó)

### 1. PermissionRepository - Copy-Paste Hibák

- ✅ Line 50-51: Role helyett Permission típus
- ✅ Line 104: NS_ROLES_FETCH helyett NS_PERMISSIONS_FETCH
- ✅ Line 109: $roles helyett $permissions változó
- [ ] Line 124-125, 138-139: App\Models\Permission helyett App\Models\Admin\Permission
- [ ] Line 170, 198: invalidateAfterRoleWrite() nem létezik
- [ ] Line 189: $role helyett $permission

### 2. WorkShiftController - Rossz PHPDoc

- [ ] Line 114, 152: Rossz array típus (company_id, name, start_time, end_time helyett email, address, phone)

### 3. WorkScheduleController - Hiányzó Típusok

- [ ] Line 71, 87: $data típus specifikálása

### 4. RoleRepository - Típus Hibák

- [ ] Line 130, 143: App\Models\Role helyett App\Models\Admin\Role
- [ ] Line 162-163, 195-196: permission_ids offset kezelése

### 5. WorkShiftRepository

- [ ] Line 51: Rossz return type (WorkShift helyett Company)
- [ ] Line 263: 'companies' relation nem létezik Company modellben

## Közepes Súlyosságú Hibák

### 6. Generic Típusok Hiánya

- [ ] Company, Employee, WorkSchedule, WorkShift, WorkShiftAssignment: HasFactory<TFactory>
- [ ] WorkShiftAssignment: BelongsTo<TRelatedModel, TDeclaringModel>

### 7. Array Típusok Hiánya

- [ ] Interfaces: $ids, $data paraméterek típusai
- [ ] Services: $ids paraméterek típusai

### 8. AppServiceProvider

- [ ] Line 92: env() hívás config könyvtáron kívül

### 9. Seeders

- [ ] Nullsafe operátor felesleges használata

## Javítási Sorrend

1. PermissionRepository copy-paste hibák
2. WorkShiftController PHPDoc
3. WorkScheduleController típusok
4. RoleRepository típusok
5. Interfaces array típusok
6. Generic típusok (opcionális, nem kritikus)
7. Seeders nullsafe (opcionális)
