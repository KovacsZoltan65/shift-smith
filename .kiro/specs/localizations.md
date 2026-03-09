Lokalizációs szabály:

- A backend és a frontend közös fordítási forrást használ.
- Kizárólag a `lang/en.json` és `lang/hu.json` fájlok használhatók.
- A fordítási kulcsok lapos, pontozott formátumúak:
    - `employees.title`
    - `common.save`
    - `work_shifts.fields.name`
- Backend oldalon a fordítás hívása:
    - `__('employees.title')`
- Frontend oldalon a fordítás hívása:
    - `$t('employees.title')` vagy `trans('employees.title')`
- Új `.php` nyelvi fájlok nem hozhatók létre.
- A frontend nem tarthat külön locale fájlokat.
- A placeholder szintaxis Laravel-kompatibilis:
    - `:name`, `:count`
