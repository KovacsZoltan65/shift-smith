## Minta

    Companies

## Migrációk

### companies (cégek):

#### oszlopok

| column     | type      | comment                                |
| ---------- | --------- | -------------------------------------- |
| id         | bigint    | Rekord azonosító                       |
| name       | string    | Cég neve                               |
| name_lc    | string    | Cég neve kisbetűsen (generated/stored) |
| email      | string    | Cég email címe                         |
| address    | string    | Cég címe                               |
| phone      | string    | Cég telefonszáma                       |
| active     | bool      | Aktív                                  |
| created_at | timestamp | rekord elkészülte                      |
| updated_at | timestamp | rekord frissítése                      |
| deleted_at | timestamp | rekord törlése                         |

#### indexek

| column  | index                 |
| ------- | --------------------- |
| name    | companies_name_idx    |
| name_lc | companies_name_lc_idx |
| email   | companies_email_idx   |
| active  | companies_active_idx  |

### employees (dolgozók):

#### oszlopok

| column     | type      | comment              |
| ---------- | --------- | -------------------- |
| id         | bigint    | Rekord azonosító     |
| first_name | string    | Dolgozó kereszt neve |
| last_name  | string    | Dolgozó vezeték neve |
| email      | string    | Dolgozó email címe   |
| address    | string    | Dolgozó címe         |
| position   | string    | Dolgozó beosztása    |
| phone      | string    | Dolgozó telefonszáma |
| hired_at   | date      | Belépés dátuma       |
| active     | bool      | Aktív                |
| created_at | timestamp | rekord elkészülte    |
| updated_at | timestamp | rekord frissítése    |
| deleted_at | timestamp | rekord törlése       |

#### indexek

| column     | index                    |
| ---------- | ------------------------ |
| first_name | employees_first_name_idx |
| last_name  | employees_last_name_idx  |
| email      | employees_email_idx      |

### companies_employees_assignments (cég - dolgozó kapcsolat):

#### oszlopok

| column        | type      | comment                         |
| ------------- | --------- | ------------------------------- |
| - id          | bigint    | Rekord azonosító                |
| - company_id  | bigint    | kapcsolat a companies táblával  |
| - employee_id | bigint    | kapcsolat az employees táblával |
| - active      | bool      | Aktív                           |
| - created_at  | timestamp | rekord elkészülte               |
| - updated_at  | timestamp | rekord frissítése               |
| - deleted_at  | timestamp | rekord törlése                  |

#### indexek

| column      | index                        |
| ----------- | ---------------------------- |
| company_id  | comp_emp_ass_company_id_idx  |
| employee_id | comp_emp_ass_employee_id_idx |
| active      | comp_emp_ass_active_idx      |

#### kapcsolatok

employees.company_id | companies.id | FK

### work_shifts (műszak):

#### oszlopok

| column     | type      | comment              |
| ---------- | --------- | -------------------- |
| id         | bigint    | Rekord azonosító     |
| name       | string    | Műszak megnevezése   |
| start_time | time      | Műszak kezdeti ideje |
| end_time   | time      | Műszak végének ideje |
| work_time  | int       | Műszak hossza        |
| active     | bool      | Aktív                |
| created_at | timestamp | rekord elkészülte    |
| updated_at | timestamp | rekord frissítése    |
| deleted_at | timestamp | rekord törlése       |

#### indexek

| column     | index             |
| ---------- | ----------------- |
| name       | ws_name_idx       |
| start_time | ws_start_time_idx |
| end_time   | ws_end_time_idx   |
| active     | ws_active_idx     |

## work_shift_assignments (Cég - Műszak - Dolgozó kapcsolatok):

#### oszlopok

| column        | type      | comment                          |
| ------------- | --------- | -------------------------------- |
| id            | bigint    | Rekord azonosító                 |
| company_id    | bigint    | kapcsolat a companies táblával   |
| employee_id   | bigint    | kapcsolat az employees táblával  |
| work_shift_id | bigint    | kapcsolat a work_shifts táblával |
| created_at    | timestamp | rekord elkészülte                |
| updated_at    | timestamp | rekord frissítése                |
| deleted_at    | timestamp | rekord törlése                   |

#### indexek

| column                               | index                           |
| ------------------------------------ | ------------------------------- |
| company_id                           | ws_ass_comp_id_idx              |
| employee_id,work_shift_id            | ws_ass_emp_id_idx               |
| work_shift_id                        | ws_ass_ws_id_idx                |
| company_id,employee_id,work_shift_id | ws_ass_comp_id_emp_id_ws_id_idx |
| active                               | ws_ass_active_idx               |

### break_times (szünet):

#### oszlopok

| column     | type      | comment              |
| ---------- | --------- | -------------------- |
| id         | bigint    | Rekord azonosító     |
| name       | string    | Szünet megnevezése   |
| start_time | time      | Műszak kezdeti ideje |
| end_time   | time      | Műszak végének ideje |
| active     | bool      | Aktív                |
| created_at | timestamp | rekord elkészülte    |
| updated_at | timestamp | rekord frissítése    |
| deleted_at | timestamp | rekord törlése       |

#### indexek

| column               | index            |
| -------------------- | ---------------- |
| name                 | bt_name_idx      |
| start_time           | bt_start_idx     |
| end_time             | bt_end_idx       |
| start_time, end_time | bt_start_end_idx |
| active               | bt_active_idx    |

### work_shift_break_time_assignments (műszak - szünet kapcsolatok):

#### oszlopok

| column        | type      | comment           |
| ------------- | --------- | ----------------- |
| id            | bigint    | Rekord azonosító  |
| work_shift_id | bigint    | műszak azonosító  |
| break_time_id | bigint    | szünet azonosító  |
| active        | bool      | Aktív             |
| created_at    | timestamp | rekord elkészülte |
| updated_at    | timestamp | rekord frissítése |
| deleted_at    | timestamp | rekord törlése    |

#### indexek

| column        | index                |
| ------------- | -------------------- |
| work_shift_id | ws_bt_ws_id_idx      |
| break_time_id | ws_bt_bt_idx         |
| active        | ws_bt_ass_active_idx |

# Fájlok

    1) Migráció
        - create_work_shifts_table.php
        - create_break_times_table.php
        - create_work_shift_break_time_assignments_table.php
    2) Model
        - WorkShift.php
        - BreakTime.php
        - WorkShift_BreakTime_Assignment.php
    3) Controller
        - WorkShiftController.php
        - BreakTimeController.php
        - WorkShift_BreakTime_AssignmentController.php
    4) Service
        - WorkShiftService.php
        - BreakTimeService.php
        - WorkShift_BreakTime_AssignmentService.php
    5) Repository
        - WorkShiftRepository.php
        - BreakTimeRepository.php
        - WorkShift_BreakTime_AssignmentRepository.php
    6) Policy
        - WorkShiftPolicy.php
        - BreakTimePolicy.php
        - WorkShift_BreakTime_AssignmentPolicy.php
    7) Request
        - WorkShift:
          - WorkShift/BulkDeleteREquest.php
          - WorkShift/DeleteRequest.php
          - WorkShift/IndexRequest.php
          - WorkShift/StoreRequest.php
          - WorkShift/UpdateRequest.php
        - BreakTime:
          - BreakTime/BulkDeleteREquest.php
          - BreakTime/DeleteRequest.php
          - BreakTime/IndexRequest.php
          - BreakTime/StoreRequest.php
          - BreakTime/UpdateRequest.php
        - WorkShift_BreakTime_Assignment:
          - BreakTime/BulkDeleteREquest.php
          - BreakTime/DeleteRequest.php
          - BreakTime/IndexRequest.php
          - BreakTime/StoreRequest.php
          - BreakTime/UpdateRequest.php
