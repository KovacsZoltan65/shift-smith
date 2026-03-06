PROFI GIT BACKUP PROMPT v3 (Codex)

Feladat: Készíts “biztonságos backup mentést” a projektről úgy, hogy:

- ne a main/dev branch-et piszkáld, hanem automatikusan hozz létre (vagy használj) egy dátumozott backup branchet,
- commitolj csak akkor, ha van változás,
- futtass gyors sanity checkeket (opcionális, de ajánlott),
- pushold a backup branchet az origin remote-ra,
- adj részletes összefoglalót.

SZABÁLYOK

- A parancsokat TÉNYLEGESEN futtasd, ne csak felsorold.
- Ne force pusholj.
- Ne módosíts történelmet (no rebase/squash).
- Ne commitolj üresen.
- STOP, ha érzékeny fájl kerülne commitba.

LÉPÉSEK

1. Repo ellenőrzés

- Ha nincs Git repo:
  git init

2. Remote ellenőrzés (kötelező)

- Futtasd:
  git remote -v
- Ha nincs `origin`:
  ÁLLJ MEG és írd ki:
  "Nincs origin remote beállítva, push nem lehetséges."

3. Aktuális branch meghatározása

- Futtasd:
  git branch --show-current
- Ha üres:
  ÁLLJ MEG:
  "Detached HEAD állapot, nem biztonságos."

4. Working tree ellenőrzés

- Futtasd:
  git status --porcelain
- Ha nincs output:
  Írd ki:
  "Nincs commitolandó változás."
  és állj meg.

5. STOP-lista ellenőrzés (érzékeny / túl nagy fájlok)

- Listázd a változott fájlokat:
  git status --porcelain
- Ha található bármely:
    - .env, .env._, _.key, id_rsa, _.pfx, _.p12
    - node_modules/, vendor/
    - storage/logs/, storage/_.sql, _.dump, _.zip, _.tar, \*.gz
    - nagy reportok (integrity-\*.json), nagy binárisok
      Akkor:
    - Írd ki a találatokat
    - Javasolj gitignore-t vagy revertet
    - ÁLLJ MEG, és ne commitolj.

6. Változások áttekintése

- Futtasd:
  git status
  git diff --stat

7. Backup branch név képzése

- Hozz létre egy dátumozott branch nevet (helyi idő szerint):
  backup/YYYY-MM-DD
  Példa: backup/2026-03-05
- Ha már létezik a branch localban:
  használd.
- Ha nem létezik:
  hozd létre az aktuális branchedből.

Parancsok (logikával):

- Ellenőrizd létezik-e:
  git branch --list "backup/\*"
- Készítsd el / válts rá:
  git checkout -b backup/YYYY-MM-DD
  (ha már létezik: git checkout backup/YYYY-MM-DD)

8. Stage

- Futtasd:
  git add -A

9. Commit message (diff alapján, PR-ready)

- Készíts tömör összefoglalót a változások alapján (max 90 karakter):
  Formátum:
  [AUTO] Backup(YYYY-MM-DD): <összefoglaló>
- Ha nem tudsz jó összefoglalót:
  [AUTO] Backup(YYYY-MM-DD): project snapshot

10. Commit

- Futtasd:
  git commit -m "<GENERÁLT ÜZENET>"
- Ha nincs mit commitolni:
  Írd ki:
  "Nincs commitolandó változás (staging üres)."
  és állj meg.

11. Opcionális sanity check (gyors)

- Ha a projekt Laravel:
    - Futtasd (ha gyorsan lefut és nincs környezeti akadály):
      php -v
      php artisan --version
      php artisan test --testsuite=Feature --stop-on-failure
      Ha ez túl lassú vagy nem futtatható:
    - Hagyd ki, de jelezd, hogy kihagyva.

12. Push a backup branch-re (kötelező)

- Először állíts be upstream-et, ha nincs:
  git push -u origin HEAD
- Ez a backup branch-et feltolja origin-ra.

13. Végső ellenőrzés

- Futtasd:
  git log --oneline -1
  git status
  git branch -vv

KIMENET (a futás végén kötelező összefoglaló)

- Source branch (ahonnan indultál): <eredeti branch>
- Backup branch: backup/YYYY-MM-DD
- Commit: <hash> <subject>
- Diff stat: <N files changed, insertions(+), deletions(-)>
- Push: sikeres / sikertelen (hibaüzenettel)
- Remote: origin <url>
- Sanity check: sikeres / kihagyva / sikertelen

MEGJEGYZÉS

- A végén NE térj vissza automatikusan az eredeti branch-re (hogy látható legyen, hogy backup branchen vagy).
- Ne nyúlj a main/dev branchekhez.
