# CSRF Védelem Dokumentáció

## Áttekintés

Az alkalmazás teljes CSRF (Cross-Site Request Forgery) védelemmel rendelkezik minden POST, PUT, PATCH és DELETE kéréshez.

## Implementáció

### 1. Backend (Laravel)

#### Middleware

- **Fájl**: `app/Http/Middleware/VerifyCsrfToken.php`
- **Konfiguráció**: `bootstrap/app.php`
- Automatikusan ellenőrzi a CSRF tokent minden nem-GET kérésnél

#### Kizárások

Ha szükséges webhook vagy API endpoint kizárása:

```php
// app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    'webhooks/*',
    'api/*',
];
```

### 2. Frontend

#### Meta Tag

A CSRF token minden oldalon elérhető:

```html
<!-- resources/views/app.blade.php -->
<meta name="csrf-token" content="{{ csrf_token() }}" />
```

#### Axios (Automatikus)

Az Axios automatikusan küldi a CSRF tokent:

```javascript
// resources/js/bootstrap.js
window.axios.defaults.headers.common["X-CSRF-TOKEN"] = token.content;
```

#### Fetch API (csrfFetch)

Egyedi fetch wrapper CSRF tokennel:

```javascript
// resources/js/lib/csrfFetch.js
import { csrfFetch } from "@/lib/csrfFetch";

await csrfFetch("/api/endpoint", {
    method: "POST",
    body: JSON.stringify(data),
});
```

#### Inertia.js

Az Inertia automatikusan kezeli a CSRF tokent minden kérésnél.

## Használat

### Axios példa

```javascript
// Automatikusan tartalmazza a CSRF tokent
axios.post("/users", userData);
axios.put("/users/1", userData);
axios.delete("/users/1");
```

### Fetch API példa

```javascript
// csrfFetch használata
import { csrfFetch } from "@/lib/csrfFetch";

await csrfFetch("/companies", {
    method: "POST",
    headers: {
        "Content-Type": "application/json",
    },
    body: JSON.stringify(companyData),
});
```

### Inertia példa

```javascript
// Inertia automatikusan kezeli
import { router } from "@inertiajs/vue3";

router.post("/users", userData);
router.put("/users/1", userData);
router.delete("/users/1");
```

## Hibakeresés

### 419 Token Mismatch Error

Ha 419 hibát kapsz:

1. **Ellenőrizd a meta tag-et**:

    ```javascript
    console.log(document.querySelector('meta[name="csrf-token"]')?.content);
    ```

2. **Ellenőrizd az Axios header-t**:

    ```javascript
    console.log(window.axios.defaults.headers.common["X-CSRF-TOKEN"]);
    ```

3. **Ellenőrizd a cookie-t**:

    ```javascript
    console.log(document.cookie);
    ```

4. **Session lejárt**: Frissítsd az oldalt

### Token Refresh

A CSRF token automatikusan frissül minden oldalbetöltésnél. Ha SPA-ban hosszú ideig nem töltődik újra az oldal, lehet szükség manuális frissítésre:

```javascript
// Token frissítése
const token = document.querySelector('meta[name="csrf-token"]')?.content;
if (token) {
    window.axios.defaults.headers.common["X-CSRF-TOKEN"] = token;
}
```

## Biztonsági Megjegyzések

1. **Soha ne tárold a CSRF tokent localStorage-ban** - csak meta tag vagy cookie
2. **HTTPS használata production-ben** - kötelező a biztonságos működéshez
3. **SameSite cookie beállítás** - már konfigurálva `config/session.php`-ban
4. **Token rotáció** - automatikus minden session regeneráláskor

## Tesztelés

### Unit teszt

```javascript
// resources/js/__tests__/csrf.test.js
import { csrfFetch, csrfToken } from "@/lib/csrfFetch";

test("csrfToken returns token from meta tag", () => {
    document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
    expect(csrfToken()).toBe("test-token");
});
```

### E2E teszt

```javascript
// Cypress példa
cy.visit("/login");
cy.get('meta[name="csrf-token"]').should("exist");
```

## További Információk

- [Laravel CSRF Dokumentáció](https://laravel.com/docs/csrf)
- [OWASP CSRF Prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
