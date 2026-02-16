# ShiftSmith – Migrációs Spec (Javított)

## companies (cégek)

### oszlopok

| column     | type      | comment                           |
| ---------- | --------- | --------------------------------- |
| id         | bigint    | Rekord azonosító                  |
| name       | string    | Cég neve                          |
| name_lc    | string    | Cég neve kis betűkkel (generated) |
| email      | string    | Cég email címe                    |
| address    | string    | Cég címe                          |
| phone      | string    | Cég telefonszáma                  |
| active     | bool      | Aktív                             |
| created_at | timestamp | rekord elkészülte                 |
| updated_at | timestamp | rekord frissítése                 |
| deleted_at | timestamp | rekord törlése                    |

### indexek

| column  | index                 |
| ------- | --------------------- |
| name    | companies_name_idx    |
| name_lc | companies_name_lc_idx |
| email   | companies_email_idx   |
| active  | companies_active_idx  |

---

## employees (dolgozók)

### oszlopok

| column     | type      | comment                        |
| ---------- | --------- | ------------------------------ |
| id         | bigint    | Rekord azonosító               |
| company_id | bigint    | kapcsolat a companies táblával |
| first_name | string    | Dolgozó kereszt neve           |
| last_name  | string    | Dolgozó vezeték neve           |
| email      | string    | Dolgozó email címe             |
| address    | string    | Dolgozó címe                   |
| position   | string    | Dolgozó beosztása              |
| phone      | string    | Dolgozó telefonszáma           |
| hired_at   | date      | Belépés dátuma                 |
| active     | bool      | Aktív                          |
| created_at | timestamp | rekord elkészülte              |
| updated_at | timestamp | rekord frissítése              |
| deleted_at | timestamp | rekord törlése                 |

### indexek

| column             | index                        |
| ------------------ | ---------------------------- |
| company_id         | employees_company_id_idx     |
| first_name         | employees_first_name_idx     |
| last_name          | employees_last_name_idx      |
| email              | employees_email_idx          |
| company_id, active | employees_company_active_idx |

---

## work_shifts (műszak)

### oszlopok

| column            | type      | comment                        |
| ----------------- | --------- | ------------------------------ |
| id                | bigint    | Rekord azonosító               |
| company_id        | bigint    | kapcsolat a companies táblával |
| name              | string    | Műszak megnevezése             |
| start_time        | time      | Műszak kezdeti ideje           |
| end_time          | time      | Műszak végének ideje           |
| work_time_minutes | int       | Műszak hossza percben          |
| active            | bool      | Aktív                          |
| created_at        | timestamp | rekord elkészülte              |
| updated_at        | timestamp | rekord frissítése              |
| deleted_at        | timestamp | rekord törlése                 |

### indexek

| column             | index                 |
| ------------------ | --------------------- |
| company_id         | ws_company_id_idx     |
| name               | ws_name_idx           |
| start_time         | ws_start_time_idx     |
| end_time           | ws_end_time_idx       |
| company_id, active | ws_company_active_idx |
| company_id, name   | ws_company_name_idx   |

---

## break_times (szünet)

### oszlopok

| column     | type      | comment                        |
| ---------- | --------- | ------------------------------ |
| id         | bigint    | Rekord azonosító               |
| company_id | bigint    | kapcsolat a companies táblával |
| name       | string    | Szünet megnevezése             |
| start_time | time      | Szünet kezdeti ideje           |
| end_time   | time      | Szünet végének ideje           |
| active     | bool      | Aktív                          |
| created_at | timestamp | rekord elkészülte              |
| updated_at | timestamp | rekord frissítése              |
| deleted_at | timestamp | rekord törlése                 |

### indexek

| column               | index                 |
| -------------------- | --------------------- |
| company_id           | bt_company_id_idx     |
| name                 | bt_name_idx           |
| start_time           | bt_start_idx          |
| end_time             | bt_end_idx            |
| start_time, end_time | bt_start_end_idx      |
| company_id, active   | bt_company_active_idx |

---

## work_shift_break_time_assignments

### oszlopok

| column        | type      | comment                          |
| ------------- | --------- | -------------------------------- |
| id            | bigint    | Rekord azonosító                 |
| work_shift_id | bigint    | kapcsolat a work_shifts táblával |
| break_time_id | bigint    | kapcsolat a break_times táblával |
| active        | bool      | Aktív                            |
| created_at    | timestamp | rekord elkészülte                |
| updated_at    | timestamp | rekord frissítése                |
| deleted_at    | timestamp | rekord törlése                   |

### indexek

| column                       | index                    |
| ---------------------------- | ------------------------ |
| work_shift_id                | ws_bt_ws_id_idx          |
| break_time_id                | ws_bt_bt_id_idx          |
| active                       | ws_bt_active_idx         |
| work_shift_id, break_time_id | ws_bt_ws_id_bt_id_unique |

---

## work_shift_assignments

### oszlopok

| column        | type      | comment                          |
| ------------- | --------- | -------------------------------- |
| id            | bigint    | Rekord azonosító                 |
| company_id    | bigint    | kapcsolat a companies táblával   |
| employee_id   | bigint    | kapcsolat az employees táblával  |
| work_shift_id | bigint    | kapcsolat a work_shifts táblával |
| active        | bool      | Aktív                            |
| created_at    | timestamp | rekord elkészülte                |
| updated_at    | timestamp | rekord frissítése                |
| deleted_at    | timestamp | rekord törlése                   |

### indexek

| column                                 | index                             |
| -------------------------------------- | --------------------------------- |
| company_id                             | ws_ass_company_id_idx             |
| employee_id                            | ws_ass_employee_id_idx            |
| work_shift_id                          | ws_ass_work_shift_id_idx          |
| company_id, employee_id                | ws_ass_company_employee_idx       |
| company_id, employee_id, work_shift_id | ws_ass_company_employee_ws_idx    |
| active                                 | ws_ass_active_idx                 |
| company_id, employee_id, work_shift_id | ws_ass_company_employee_ws_unique |
