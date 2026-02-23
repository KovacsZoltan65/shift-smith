Feladat: Készíts Git mentést a jelenlegi projektről.

Lépések:

1. Ellenőrizd, hogy a projekt Git repository-e inicializálva van-e.
    - Ha nem, futtasd: git init

2. Ellenőrizd a módosított fájlokat:
    - git status

3. Add hozzá az összes változást a staging area-hoz:
    - git add .

4. Készíts commitot beszédes üzenettel:
    - Formátum: "[AUTO] Mentés: <rövid leírás a változásokról>"
    - Ha nem ismert a változás, használd:
      "[AUTO] Project backup commit"

5. Ha van beállított remote repository:
    - push a jelenlegi branch-re:
      git push origin HEAD

6. Ha nincs remote:
    - jelezd, hogy remote szükséges a push-hoz.

Kimenet:

- Rövid összefoglaló a commitról
- Commit hash
- Mely branch-re történt a mentés
