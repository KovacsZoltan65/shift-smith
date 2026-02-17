# ShiftSmith - Dokumentáció

## Áttekintés

Ez a könyvtár tartalmazza a ShiftSmith alkalmazás teljes dokumentációját, beleértve a biztonsági útmutatókat, deployment folyamatokat és best practice-eket.

## Dokumentumok

### 🔒 Biztonsági Dokumentáció

#### [SECURITY_AUDIT_SUMMARY.md](./SECURITY_AUDIT_SUMMARY.md)

Teljes biztonsági audit összefoglalója, beleértve:

- Végrehajtott javítások listája
- Biztonsági pontszám (9.5/10)
- Védelem rétegek részletezése
- Tesztelési javaslatok
- Karbantartási feladatok

#### [CSRF_PROTECTION.md](./CSRF_PROTECTION.md)

CSRF védelem részletes dokumentációja:

- Backend implementáció
- Frontend használat (Axios, Fetch, Inertia)
- Hibakeresési útmutató
- Példakódok

### 🚀 Deployment Dokumentáció

#### [PRODUCTION_DEPLOYMENT.md](./PRODUCTION_DEPLOYMENT.md)

Production környezet telepítési útmutatója:

- Environment konfiguráció
- HTTPS beállítás
- Security checklist
- Deployment lépések
- Performance optimalizálás
- Rollback terv
- Monitoring és logging

### 📋 Konfigurációs Fájlok

#### [../.env.production.example](../.env.production.example)

Production környezet példa konfiguráció:

- Biztonságos alapértelmezett értékek
- Session security beállítások
- Cache és Redis konfiguráció
- Mail beállítások

## Gyors Kezdés

### Development Környezet

```bash
# 1. Függőségek telepítése
composer install
npm install

# 2. Environment beállítása
cp .env.example .env
php artisan key:generate

# 3. Database setup
php artisan migrate
php artisan db:seed

# 4. Frontend build
npm run dev

# 5. Szerver indítása
php artisan serve
```

### Production Deployment

```bash
# 1. Kód frissítés
git pull origin main

# 2. Függőségek
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 3. Laravel optimalizálás
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Migrációk
php artisan migrate --force
```

Részletes lépésekért lásd: [PRODUCTION_DEPLOYMENT.md](./PRODUCTION_DEPLOYMENT.md)

## Biztonsági Funkciók

### ✅ Implementált Védelmek

1. **Autentikáció**
    - Laravel Sanctum
    - Email verification
    - Rate limiting (5 kísérlet/perc)

2. **Autorizáció**
    - Spatie Permission (RBAC)
    - Policy-based authorization
    - Request-level checks

3. **Input Validáció**
    - FormRequest osztályok
    - Erős jelszó követelmények
    - Type casting

4. **CSRF Védelem**
    - Token minden írási műveletnél
    - Automatikus Axios integráció
    - Inertia beépített védelem

5. **Rate Limiting**
    - Login: 5 kérés/perc
    - Olvasás: 60 kérés/perc
    - Írás: 20-30 kérés/perc
    - Bulk: 10 kérés/perc

6. **Session Security**
    - HTTP Only cookies
    - SameSite: lax
    - Encryption (production)
    - Secure flag (production)

7. **HTTP Security Headers**
    - X-Frame-Options
    - X-Content-Type-Options
    - X-XSS-Protection
    - Referrer-Policy
    - HSTS (production)

## Karbantartás

### Napi Feladatok

- Log fájlok ellenőrzése
- Failed login attempts monitoring

### Heti Feladatok

- Dependency frissítések
- Security advisory-k áttekintése

### Havi Feladatok

- Teljes biztonsági audit
- Backup tesztelés
- SSL certificate ellenőrzés

### Negyedévente

- Penetration testing
- Security policy felülvizsgálat
- Disaster recovery drill

## Támogatás

### Dokumentáció

- [Laravel Dokumentáció](https://laravel.com/docs)
- [Inertia.js Dokumentáció](https://inertiajs.com)
- [Spatie Permission](https://spatie.be/docs/laravel-permission)

### Biztonsági Incidensek

Ha biztonsági problémát találsz, kérjük jelentsd:

- Email: security@yourdomain.com
- Ne hozz létre nyilvános issue-t!

## Changelog

### 2026-02-17 - Biztonsági Audit

- ✅ Hardcoded jelszó eltávolítva
- ✅ Autorizációs ellenőrzések javítva
- ✅ Jelszó validáció implementálva
- ✅ CSRF védelem explicit beállítva
- ✅ Rate limiting hozzáadva
- ✅ Security headers implementálva
- ✅ Activity log érzékeny adatok kizárva
- ✅ Production deployment dokumentáció

**Biztonsági Pontszám**: 5/10 → 9.5/10 ⬆️

## Licenc

[MIT License](../LICENSE)

## Kapcsolat

- **Projekt**: ShiftSmith
- **Verzió**: 1.0.0
- **Laravel**: 12.x
- **PHP**: 8.2+
