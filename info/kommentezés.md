# SHIFT-SMITH – BACKEND + FRONTEND COMMENTING AUDIT & IMPROVEMENT

You are working on the ShiftSmith project.

Your task is to audit and improve code comments in both backend and frontend code.

IMPORTANT LANGUAGE RULE:

All comments and documentation text must be written in Hungarian.

Exceptions:

- PHPDoc tags must remain in English (@param, @return, @var, @throws, etc.)
- Type declarations must remain unchanged
- Code identifiers must not be translated

Example:

/\*\*

- Visszaadja az aktuális tenant kontextusban látható dolgozókat.
-
- @param int $companyId
- @return LengthAwarePaginator<int, Employee>
  \*/

---

IMPORTANT:
The goal is NOT to spam comments everywhere.
The goal is to create clean, useful, maintainable comments and PHPDoc blocks that improve readability, IDE support, onboarding, and static analysis compatibility.

---

## CORE RULES

Follow these rules strictly:

1. Do NOT add obvious comments that merely restate the code.

Bad example:

// számláló növelése
$counter++;

2. Prefer meaningful comments only where they add real value:

- üzleti szabályok
- tenancy korlátozások
- biztonsági feltételezések
- nem nyilvánvaló architekturális döntések
- cache invalidáció logika
- komplex frontend állapotkezelés
- speciális validációs viselkedés
- teljesítményérzékeny kódrészletek
- ideiglenes kompatibilitási megjegyzések

3. Preserve project architecture:

Controller → Service → Repository → Model

4. Do not introduce behavioral changes unless necessary to fix PHPDoc / type mismatch issues.

5. All PHPDoc must be compatible with:

- PHP 8.4+
- Laravel 12
- PHPStan / Larastan
- strict typing

6. Do not write misleading documentation.

If a type is uncertain, inspect the actual code and document it accurately.

---

## TASK 1 — BACKEND COMMENTING AUDIT

Audit backend code and improve comments / PHPDoc in:

- Controllers
- Services
- Repositories
- Repository interfaces
- FormRequests
- Policies
- Models
- Data / DTO classes
- Traits
- Support classes
- custom cache services
- tenant-related services and middleware
- console commands if relevant

Focus especially on:

### A. Class-level PHPDoc

Add concise Hungarian class descriptions where useful.

Document:

- az osztály felelőssége
- melyik architektúra réteghez tartozik
- milyen logikát NEM tartalmazhat

### B. Method-level PHPDoc

Add PHPDoc only where it provides value.

Especially when methods:

- kollekciókat vagy paginátorokat adnak vissza
- komplex DTO-kat fogadnak
- domain logikát tartalmaznak
- tenant vagy company scope-ot kényszerítenek
- cache invalidációt végeznek
- strukturált API választ adnak

### C. Property PHPDoc

Add typed property docs only if needed for:

- PHPStan
- IDE inference
- generic collections
- Laravel mágikus viselkedések dokumentálása

### D. Array shape documentation

Use PHPStan-compatible array shapes where useful.

Examples:

array{data: array<int, mixed>, meta: array<string, mixed>}
array{id: int, name: string, active: bool}

### E. Collection generics

Examples:

Collection<int, Employee>
LengthAwarePaginator<int, TenantGroup>

### F. Relationship PHPDoc for Eloquent models

Examples:

BelongsTo<Company, Employee>
HasMany<WorkSchedule, Company>

---

## TASK 2 — FRONTEND COMMENTING AUDIT

Audit frontend code and improve comments in:

- Vue components
- composables
- service files
- utility/helpers
- state management logic
- datatable helpers
- modal/dialog state logic
- filter/sort/pagination flow
- tenant/company selection flow

Rules for frontend comments:

1. Kommentek legyenek rövidek és lényegre törők.
2. A "MIÉRT"-et magyarázzák, ne a "MIT".
3. Nagy komponenseknél használj szekció kommenteket.
4. Kerüld a zajos kommenteket template blokkokban.

Document:

- props szerződés
- kibocsátott események
- aszinkron state flow
- debounce / keresés / szűrés viselkedés
- kiválasztás szinkronizáció
- jogosultság alapú megjelenítés
- komplex watcher / computed logika

---

## TASK 3 — PHPDOC + PHPSTAN COMPATIBILITY

While updating comments, ensure PHPDoc improves static analysis.

Required checks:

1. Remove outdated or false PHPDoc
2. Fix incorrect @return annotations
3. Fix incorrect @param annotations
4. Fix nullable annotations
5. Fix generic collection annotations
6. Fix array shape annotations
7. Avoid vague annotations like:

@return mixed
@param mixed $data

unless unavoidable.

8. Prefer native PHP types first, PHPDoc second.
9. Do not duplicate native type declarations unnecessarily.
10. Ensure Laravel magic is documented in a PHPStan-friendly way.

---

## TASK 4 — LARAVEL-SPECIFIC DOCUMENTATION RULES

### Controllers

Document:

- endpoint csoport célja
- válasz struktúrája ha nem egyértelmű
- authorization és tenant scope jelentősége

### Services

Document:

- orchestration felelősség
- tranzakció határok
- cache invalidáció
- domain invariánsok

### Repositories

Document:

- scope szabályok
- szűrési szerződés
- visszatérési típusok
- query biztonsági feltételek

### FormRequests

Document:

- milyen adatokat validál
- authorization logika
- normalizációs lépések

### Policies

Document:

- mely permission string-et ellenőrzi
- HQ / landlord speciális logika

### Models

Document:

- relációk jelentése
- státusz mezők domain jelentése
- fontos cast-ok
- scope elvárások

### Tenant-related classes

Explain clearly:

- TenantGroup vs Company különbség
- tenant izoláció
- company scope
- landlord vs tenant context
- multi-DB readiness

---

## TASK 5 — FRONTEND DOCUMENTATION RULES

For Vue / JS files:

### Vue pages

Use section comments:

- szűrők
- táblázat state
- dialog state
- műveletek
- fetch lifecycle

### Components

Document:

- komponens célja
- fő props-ok
- események
- nem nyilvánvaló UI szabályok

### Service files

Document:

- endpoint szerződés
- payload struktúra
- hibakezelési elvárások

### Composables

Document:

- újrafelhasználható szerződés
- input / output
- side effectek
- watcher viselkedés

### Utility files

Document:

- edge case-ek
- formátum szabályok
- timezone feltételezések

---

## TASK 6 — DO NOT OVERCOMMENT

Avoid:

- minden sor kommentelése
- szintaxis magyarázat
- változónevek ismétlése prózában
- elavult TODO kommentek
- felesleges dekorációs banner kommentek
- kódban nem létező üzleti szabályok kitalálása

Bad example:

// felhasználók lekérése
$users = User::all();
