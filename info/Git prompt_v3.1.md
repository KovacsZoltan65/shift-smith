PROFI GIT BACKUP PROMPT v3.1 (Codex)

Feladat: Készíts “biztonságos backup mentést” a projektről úgy, hogy:

- automatikusan létrehozz (vagy használsz) egy dátumozott backup branchet,
- a backup branch neve tartalmazza az eredeti branch nevét is,
- commitolj csak akkor, ha van változás,
- pushold a backup branchet az origin remote-ra,
- a folyamat végén automatikusan válts vissza az eredeti branch-re,
- adj részletes összefoglalót.

SZABÁLYOK

- A parancsokat TÉNYLEGESEN futtasd, ne csak felsorold.
- Ne force pusholj.
- Ne módosíts history-t (no rebase/squash).
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

3. Eredeti branch mentése

- Futtasd:
  git branch --show-current
- Ha üres:
  ÁLLJ MEG:
  "Detached HEAD állapot, nem biztonságos."
- Mentsd el változóba: SOURCE_BRANCH

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
- STOP, ha található bármely:
    - .env, .env._, _.key, id_rsa, _.pfx, _.p12
    - node_modules/, vendor/
    - storage/logs/, storage/_.sql, _.dump, _.zip, _.tar, \*.gz
    - integrity-\*.json vagy nagy bináris fájlok
      Ilyenkor:
    - Írd ki a találatokat
    - Javasolj gitignore-t vagy revertet
    - ÁLLJ MEG, ne commitolj.

6. Változások áttekintése

- Futtasd:
  git status
  git diff --stat

7. Backup branch név képzése

- Képezd a backup branch nevet helyi idő szerint:
  backup/YYYY-MM-DD-from-SOURCE_BRANCH
  Példa: backup/2026-03-05-from-Hierarchia

- Ha a branch már létezik localban:
  használd.
- Ha nem létezik:
  hozd létre az aktuális branchedből.

Parancslogika:

- Ellenőrzés:
  git rev-parse --verify "refs/heads/<BACKUP_BRANCH>"
- Ha létezik:
  git checkout <BACKUP_BRANCH>
- Ha nem létezik:
  git checkout -b <BACKUP_BRANCH>

8. Stage

- Futtasd:
  git add -A

9. Commit message (diff alapján, PR-ready)

- Generálj tömör üzenetet (max 90 karakter):
  [AUTO] Backup(YYYY-MM-DD): <összefoglaló>
- Ha nem tudsz jó összefoglalót:
  [AUTO] Backup(YYYY-MM-DD): project snapshot

10. Commit

- Futtasd:
  git commit -m "<GENERÁLT ÜZENET>"
- Ha nincs mit commitolni:
  Írd ki:
  "Nincs commitolandó változás (staging üres)."
  majd:
  git checkout SOURCE_BRANCH
  és állj meg.

11. Push a backup branch-re (kötelező)

- Futtasd:
  git push -u origin HEAD
  (Ez feltolja a backup branchet, és beállítja az upstream-et.)

12. Végső ellenőrzés

- Futtasd:
  git log --oneline -1
  git status
  git branch -vv

13. Visszaváltás az eredeti branch-re (kötelező)

- Futtasd:
  git checkout SOURCE_BRANCH

KIMENET (a futás végén kötelező összefoglaló)

- Source branch: <SOURCE_BRANCH>
- Backup branch: <BACKUP_BRANCH>
- Commit: <hash> <subject>
- Diff stat: <N files changed, insertions(+), deletions(-)>
- Push: sikeres / sikertelen (hibaüzenettel)
- Remote: origin <url>
- Current branch (end): <SOURCE_BRANCH>

MEGJEGYZÉS

- Ne nyúlj a main/dev branchekhez.
- Ne fusson force push.
- Ne távolíts el fájlokat automatikusan; STOP-listánál inkább állj meg.
