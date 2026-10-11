# Design — Court Owner (Dono de Quadra) — ETAPA 1 (Phase 1 of 3)

## Overview

This phase introduces the **court owner** capability. Any authenticated user can register a court; doing so promotes them to a court owner. The *user-becomes-owner-on-first-create* UX mirrors the **player profile** (no separate owner signup/approval). The spatie **role grant itself is new behavior** — the player profile does NOT assign any spatie role (see note below) — so it follows the pattern in `app/Console/Commands/AssignRoleOrPermission.php` (`Role::firstOrCreate` + `assignRole`), not player code. The court itself requires admin approval before it is visible system-wide; the owner always sees their own courts regardless of status.

> **Role-pattern note (verified):** `app/Service/PlayerService::saveOrUpdate()` creates/updates a `Player` row and attaches modalities/positions but **never** calls `assignRole()` and there is no "player" spatie role. An implementer must NOT look for a player-profile role grant to copy — none exists. The only existing spatie-grant precedent is `AssignRoleOrPermission` (`Role::firstOrCreate(['name'=>..., 'guard_name'=>'web'])` then `$user->assignRole($role)`), which the court-owner grant below mirrors exactly.

Phase 1 delivers: the court domain (court + contacts + photos + pricing rules + schedule configs), owner CRUD scoped to the authenticated user, image upload/storage mirroring `Team`, an admin approval area (list pending, approve/reject with optional reason), the spatie role auto-grant on first court creation, and the full set of owner + admin frontend screens. Reservations (phase 2) and the public player browse view (phase 3) are **not** built here, but the schema and public-safe fields are designed so those phases drop in without migration churn.

The design deliberately mirrors existing conventions discovered in both codebases rather than inventing new ones:

- **Backend (Laravel 12, PHP):** `/home/andrefontoura/PhpstormProjects/sisbrasfute_laravel12_hostinger`
- **Frontend (Vue 3 Options API, Vite, Tailwind v4):** `/home/andrefontoura/PhpstormProjects/sisbrasfute_tailwind`

The stack is **locked** by this design: Laravel 12 + Eloquent + spatie/laravel-permission + **Laravel Sanctum** (the `api` guard's driver is `sanctum`, reached via `Route::middleware('auth:api')`) on the backend; Vue 3 Options-API SFCs + vue-router modular route files + axios (`src/services/api.js`) + Tailwind v4 (orange-500 accent, dark mode) on the frontend. No new libraries are introduced.

> **Auth-stack note (verified):** `config/auth.php` defines `'api' => ['driver' => 'sanctum', ...]` and `app/Models/User.php` uses `Laravel\Sanctum\HasApiTokens`. The route group stays `Route::middleware('auth:api')` — that resolves to the Sanctum driver. `AppServiceProvider::boot()` still references `Laravel\Passport\Passport` for token-lifetime config, but that is a leftover and is **not** the request guard. All feature tests in this design authenticate with `Laravel\Sanctum\Sanctum::actingAs($user)` — **never** `Passport::actingAs`.

---

## Confirmed existing patterns (read, not assumed)

| Concern | Finding | Source |
| --- | --- | --- |
| Migration subfolders | `AppServiceProvider::boot()` globs every directory under `database/migrations/*` and calls `loadMigrationsFrom($paths)`. A new `database/migrations/Courts/` folder is auto-loaded, no config change needed. | `app/Providers/AppServiceProvider.php` |
| Model conventions | `$fillable`, `$appends` with `get*UrlAttribute` accessors using `Storage::disk('public')->url($path)`, `$casts`, typed `belongsTo`/`hasMany`, `SoftDeletes`. | `app/Models/Team.php` |
| Table naming | Plural snake_case (`teams`, `team_uniforms`, `team_player_uniform_numbers`). FKs via `$table->foreign(...)->references('id')->on(...)`. | `database/migrations/Teams`, `database/migrations/TeamUniforms` |
| Controller/Service/Repository | Controller injects `Service` + `Repository`; `Service` extends `BaseService`, `Repository` extends `BaseRepository`. `save()` handles both create and update (one Form Request). | `TeamController`, `TeamService`, `TeamRepository`, `BaseRepository` |
| Image upload | `UploadService::uploadFileToFolder($disk,$folder,$file)` returns `Storage::disk($disk)->put($folder,$file)` — the returned path **already includes the folder prefix** (e.g. `courts/ab12.jpg`). `deleteFileOnFolder($disk,$folder,$filePath)` deletes `$folder.'/'.$filePath`. Paths stored verbatim in DB, exposed as `*_url`. **Caution:** `TeamService` passes the full `logo_path` (`logos/xxx`) to `deleteFileOnFolder('public','logos',...)`, producing `logos/logos/xxx` — a latent double-prefix bug that deletes nothing. Court delete must pass `basename($path)` instead (see Image upload section). | `app/Service/UploadService.php`, `TeamService` |
| Form Requests | `authorize(): true`, `rules(): array`; camelCase request keys (`teamCityId`, `teamModalityId`). | `TeamCreateOrUpdateRequest` |
| Enums | **Current convention is `App\Enums` (PHP 8.1 backed enums with `label()`).** `App\Enum\GenderEnum` is the older const-array style; new enums follow `App\Enums`. | `app/Enums/MatchType.php` vs `app/Enum/GenderEnum.php` |
| Roles | spatie `HasRoles` on `User`. `assignRole(Role::firstOrCreate([... 'guard_name' => 'web']))`. Admin gate = `is_admin` column OR `hasRole('admin')` via `isAdmin` middleware. | `app/Models/User.php`, `app/Console/Commands/AssignRoleOrPermission.php`, `IsAdminMiddleware` |
| Routes | Grouped under `Route::middleware('auth:api')`. Resource prefix groups: `->prefix(...)->name(...)->controller(...)->group(...)`. Team manager routes guarded by `isTeamManager`. Admin routes grouped under `->prefix('admin')->middleware('isAdmin')`. | `routes/api.php` |
| Modality | `modalities` table, reused as-is via `belongsTo(Modality::class)`. Exposed to FE via `GET /modalities/list`. | `app/Models/Modality.php`, `ModalityController` |
| FE routing | Per-domain route arrays in `src/router/*.js` spread into `src/router/index.js`. `requiresAuth` / `requiresAdmin` meta. | `src/router/index.js`, `team.js`, `admin.js` |
| FE HTTP | Singleton axios in `src/services/api.js`; Bearer token from `localStorage`; `multipart/form-data` for uploads. | `src/services/api.js`, `TeamForm.vue` |
| FE selects | `ModalitiesSelect.vue` (`v-model` array), `StateSelectComponent.vue`, `CitySelectComponent.vue` (city depends on `:state-id`). | `src/components/form/*` |
| FE nav | `systemLayout.vue` `navigation[]` array (groups with `children`); admin block gated by `v-if="user.is_admin"` using `adminChildren[]`. | `src/components/layouts/systemLayout.vue` |
| FE admin screen | Filter card + table + status badges + `router-link` actions; data via `api.get('/admin/...')`. | `src/views/System/Admin/AdminTeamList.vue` |

> **Assumption A1 (role guard name):** spatie guard name `web` (matches `AssignRoleOrPermission`). The owner role is named `court-owner`.
> **Assumption A2 (admin gate):** The court approval screen reuses the existing `isAdmin` middleware and FE `user.is_admin` gate; owner screens reuse `requiresAuth`. There is no FE `court-owner` route guard in phase 1 because any authenticated user may create their first court — the "Minhas Quadras" nav entry is shown to all authenticated users (the empty state invites them to register their first court).

---

## Entity-Relationship Diagram (ERD)

```
                              ┌───────────────┐          ┌──────────────┐
                              │     users     │          │  modalities  │
                              └──────┬────────┘          └──────┬───────┘
                                     │ 1                        │ 1
                                     │                          │
                                     │ N                        │ N
                              ┌──────┴──────────────────────────┴───────┐
                              │                 courts                   │
                              │ id, user_id(FK users), modality_id(FK),  │
                              │ city_id(FK cities), name, slug, desc,    │
                              │ surface_type(enum), status(enum),        │
                              │ rejection_reason, approved_at,           │
                              │ approved_by(FK users nullable),          │
                              │ timestamps, softDeletes                  │
                              └───┬─────────┬──────────┬───────────┬─────┘
                                  │1        │1         │1          │1
                   ┌──────────────┘  ┌──────┘   ┌──────┘    ┌──────┘
                   │N                │N         │N          │N
         ┌─────────┴───────┐ ┌───────┴──────┐ ┌─┴──────────────────┐ ┌────────────────────────┐
         │ court_contacts  │ │ court_photos │ │ court_pricing_rules │ │ court_schedule_configs │
         │ id, court_id FK │ │ id,court_id  │ │ id, court_id FK     │ │ id, court_id FK        │
         │ type(enum),     │ │ photo_path,  │ │ weekday(0-6),       │ │ weekday(0-6),          │
         │ value, label,   │ │ position,    │ │ start_time,         │ │ start_time, end_time,  │
         │ position        │ │ timestamps,  │ │ end_time, price_cents│ │ reservation_duration   │
         │ timestamps,SD   │ │ softDeletes  │ │ timestamps,SD        │ │ (enum min), is_active  │
         └─────────────────┘ └──────────────┘ └─────────────────────┘ │ timestamps, SD         │
                                                                       └────────────────────────┘

  ─────────────────────────── FUTURE (NOT built in this phase) ───────────────────────────
         ┌──────────────────────────────────────────────────────────────────┐
         │ court_reservations  (PHASE 2 — schema sketch only)                 │
         │ id, court_id FK, reservation_date (date), start_time, end_time,    │
         │ price_cents (snapshot from matched pricing rule),                  │
         │ customer_name, description (owner-only visible),                   │
         │ is_mensalista (bool recurring), recurrence_group_id (uuid null),   │
         │ created_by (FK users), timestamps, softDeletes                     │
         └──────────────────────────────────────────────────────────────────┘
  Phase 3 (public player view) adds NO new tables — it is a read query over
  courts WHERE status = 'approved' joined to photos/contacts/pricing/schedule.
```

### Tables (phase 1)

**`courts`** (`database/migrations/Courts/..._create_courts_table.php`)

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigIncrements | |
| `user_id` | unsignedBigInteger, FK→users | owner |
| `modality_id` | unsignedBigInteger, FK→modalities | reuse Modality |
| `city_id` | unsignedBigInteger, FK→cities | address, mirrors Team |
| `slug` | string(500) nullable | `Str::slug(name)` like Team |
| `name` | string(254) | |
| `description` | text nullable | |
| `address_line` | string(500) nullable | street/number/complement (free text; state is derived via city→state like Team) |
| `surface_type` | string(20) | enum value: `natural`/`sintetico`/`emborrachado`/`piso` |
| `status` | string(20), default `pending`, indexed | `pending`/`approved`/`rejected` |
| `rejection_reason` | string(1000) nullable | set on reject |
| `approved_at` | timestamp nullable | |
| `approved_by` | unsignedBigInteger nullable, FK→users | admin who acted |
| `timestamps`, `softDeletes` | | consistent with Team |

Index `status` (public query in phase 3 filters by it).

> **Status note (verified):** `courts.status` is a **string enum** (`pending`/`approved`/`rejected`) — an **intentional departure** from `teams.status`, which is an **integer flag** (`TeamRepository` filters `status = 1`; `TeamController::deactivate/reactivate` set `0`/`1`). Courts need a 3-state approval lifecycle, so the string enum is correct and is NOT a Team mirror. `timestamps` + `softDeletes` on `courts` DO mirror Team.

**`court_contacts`** — one OR more per court.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigIncrements | |
| `court_id` | FK→courts | |
| `type` | string(20) | enum: `whatsapp`/`phone`/`other` |
| `value` | string(255) | phone number / URL / text |
| `label` | string(100) nullable | e.g. "Recepção" |
| `position` | unsignedInteger default 0 | display order |
| `timestamps`, `softDeletes` | | |

**`court_photos`** — 1–10 per court (count enforced in Form Request, see Validation).

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigIncrements | |
| `court_id` | FK→courts | |
| `photo_path` | string(1000) | stored on `public` disk; `photo_url` accessor |
| `position` | unsignedInteger default 0 | ordering / cover = position 0 |
| `timestamps`, `softDeletes` | | mirrors `team_uniforms.photo` + Team URL accessor pattern |

**`court_pricing_rules`** — reusable rules keyed by weekday + time range (product decision 3, option a). NOT per-slot.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigIncrements | |
| `court_id` | FK→courts | |
| `weekday` | unsignedTinyInteger | 0=Sunday … 6=Saturday (one row per weekday; a UI "set of weekdays" expands to N rows — see Validation) |
| `start_time` | time | inclusive window start |
| `end_time` | time | exclusive window end |
| `price_cents` | unsignedInteger | money stored in centavos, mirroring `team_uniforms.price_cents` |
| `timestamps`, `softDeletes` | | |

Index `(court_id, weekday)` for the phase-2 price lookup `WHERE court_id=? AND weekday=? AND start_time <= slotStart AND end_time >= slotEnd`.

**`court_schedule_configs`** — operating window + reservation duration per court per weekday (product decision 4).

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigIncrements | |
| `court_id` | FK→courts | |
| `weekday` | unsignedTinyInteger | 0–6, each weekday its own window+duration |
| `start_time` | time | earliest a reservation may begin |
| `end_time` | time | latest a reservation must end (last slot ends ≤ this) |
| `reservation_duration` | unsignedSmallInteger | minutes: 60 / 90 / 120 (enum-backed) |
| `is_active` | boolean default true | lets owner mark a weekday closed without deleting the row |
| `timestamps`, `softDeletes` | | |

Unique `(court_id, weekday)` — one config row per weekday per court.

> **Why this is "rich enough" for phase-2 slot generation:** given a weekday's `start_time`, `end_time`, and `reservation_duration`, phase 2 generates slots by stepping `start_time` by `reservation_duration` until a slot's end exceeds `end_time`. Each generated slot's price is resolved by matching it against `court_pricing_rules` for the same weekday whose `[start_time, end_time)` contains the slot. No schema change required to add reservations.

### Future tables (documented, NOT created now)

- **`court_reservations` (phase 2):** references `court_id` + `reservation_date` + `start_time`/`end_time`; stores a `price_cents` snapshot (resolved from pricing rules at booking time), `customer_name`, owner-only `description`, `is_mensalista` recurrence flag and a `recurrence_group_id` so the "repetir reserva nesse mês?" option can replicate the booking across the same weekday/time within the month and later be managed/cancelled as a group. The schedule-config + pricing-rule design above makes both slot generation and per-slot price lookup feasible without altering phase-1 tables.
- **Phase 3 public view:** no new tables. A public query selects `courts WHERE status = 'approved'` with eager-loaded photos, contacts, pricing rules, and schedule configs. Public-safe fields are exactly the court's public columns (no `rejection_reason`, no `approved_by`); contacts expose `type`/`value`/`label` so the WhatsApp button renders.

---

## Enums (new, in `App\Enums` — current convention, backed enums with `label()`)

```php
// app/Enums/CourtSurfaceType.php  (string-backed)
enum CourtSurfaceType: string {
    case Natural      = 'natural';
    case Sintetico    = 'sintetico';
    case Emborrachado = 'emborrachado';
    case Piso         = 'piso';
    public function label(): string { /* Natural, Sintético, Emborrachado, Piso */ }
}

// app/Enums/CourtStatus.php  (string-backed)
enum CourtStatus: string {
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    public function label(): string { /* Pendente, Aprovada, Rejeitada */ }
}

// app/Enums/CourtContactType.php  (string-backed)
enum CourtContactType: string {
    case Whatsapp = 'whatsapp';
    case Phone    = 'phone';
    case Other    = 'other';
    public function label(): string { /* WhatsApp, Telefone, Outro */ }
}

// app/Enums/ReservationDuration.php  (int-backed, minutes)
enum ReservationDuration: int {
    case Sixty      = 60;   // 1h
    case Ninety     = 90;   // 1h30
    case OneTwenty  = 120;  // 2h
    public function label(): string { /* 1h, 1h30, 2h */ }
}
```

Models cast via `$casts`: `surface_type => CourtSurfaceType::class`, `status => CourtStatus::class`, `type => CourtContactType::class`, `reservation_duration => ReservationDuration::class`.

A small seeder (`CourtSurfaceSeeder` is unnecessary — surface types are an enum, not a table). Modalities are already seeded and reused.

---

## Models (`app/Models`)

- **`Court`** — `$fillable`: user_id, modality_id, city_id, slug, name, description, address_line, surface_type, status, rejection_reason, approved_at, approved_by. `$casts`: surface_type/status enums, approved_at datetime. `$appends`: none at court level (photos carry URLs). Relations: `belongsTo(User::class,'user_id')` as `owner`, `belongsTo(Modality::class,'modality_id','id')` as `modalityInfo`, `belongsTo(City::class,'city_id')` as `cityInfo`, `hasMany(CourtContact)`, `hasMany(CourtPhoto)`, `hasMany(CourtPricingRule)`, `hasMany(CourtScheduleConfig)`. `SoftDeletes`.
  > **Caution (verified):** `Team::modalityInfo()` is written `belongsTo(Modality::class, 'id', 'modality_id')` — the foreign-key and owner-key arguments are **reversed/incorrect**. Do NOT "match Team" here: `Court::modalityInfo` must use `belongsTo(Modality::class, 'modality_id', 'id')` (correct FK then owner key).
- **`CourtContact`** — fillable: court_id, type, value, label, position; cast type enum; `belongsTo(Court)`.
- **`CourtPhoto`** — fillable: court_id, photo_path, position; `$appends=['photo_url']`; `getPhotoUrlAttribute()` returns `Storage::disk('public')->url($this->photo_path)` (exact Team accessor pattern); `belongsTo(Court)`.
- **`CourtPricingRule`** — fillable: court_id, weekday, start_time, end_time, price_cents; `belongsTo(Court)`.
- **`CourtScheduleConfig`** — fillable: court_id, weekday, start_time, end_time, reservation_duration, is_active; cast reservation_duration enum + is_active bool; `belongsTo(Court)`.

---

## Role-assignment flow (product decision 1)

The *UX* mirrors the player profile: there is **no owner signup**: a user becomes an owner simply by creating a court. The *role grant* is **new behavior** (the player profile grants no spatie role) and follows the only existing spatie precedent, `AssignRoleOrPermission`. On the **first** court a user creates, `CourtService::createCourt()` runs inside a DB transaction (see transaction note under "Image upload / storage") and calls:

```php
if (! $user->hasRole('court-owner')) {
    $role = Role::firstOrCreate(['name' => 'court-owner', 'guard_name' => 'web']);
    $user->assignRole($role);
}
```

This matches `AssignRoleOrPermission` exactly (`Role::firstOrCreate` with `guard_name => 'web'`, then `assignRole`). The role is granted immediately and unconditionally on first court creation. The **court** starts `status = pending` and is invisible system-wide until an admin approves it; the owner always sees their own courts (all statuses). Granting the role does not depend on approval — being an owner and having an approved court are independent facts.

> **Assumption A3:** Losing the role when all courts are deleted is **not** handled in phase 1 (soft deletes keep history; the role is harmless if retained). Documented as out of scope.

---

## API surface (`routes/api.php`, inside `Route::middleware('auth:api')->group`)

### Owner CRUD — `CourtController` (scoped to authenticated user)

```php
Route::prefix('court')
    ->name('court.')
    ->controller(CourtController::class)
    ->group(function () {
        Route::get('my-courts', 'myCourts');          // owner's courts, all statuses
        Route::get('show/{courtId}', 'show');          // owner-scoped; 403 if not owner
        Route::post('save', 'save')->middleware('emailVerified');     // create
        Route::post('update/{courtId}', 'save');       // update (service fetches owner-scoped FIRST, then updates — never upserts a bogus id)
        Route::delete('delete/{courtId}', 'destroy');  // soft delete, owner-scoped
    });
```

Ownership is enforced inside `CourtService`/`CourtRepository` (`where('user_id', Auth::id())`) because there is no per-court manager middleware equivalent to `isTeamManager` for courts; a dedicated `isCourtOwner` middleware is an acceptable alternative but the service-layer check is simpler and matches how `TeamController::deactivate` fetches then guards. **Decision: enforce ownership in the service layer** (chosen for simplicity and because the repository already centralizes queries). For update/delete the service **fetches the owner-scoped court first** (`firstOwnedById`) and 403/404s before touching `updateById` — see the "Order of operations" invariant note, since `updateById` is an upsert. The `emailVerified` middleware guards creation, mirroring `team/save`.

### Admin approval — extend `AdminController` (matches existing admin wiring)

Admin court endpoints are added to the existing `Route::prefix('admin')->middleware('isAdmin')->controller(AdminController::class)` group (same place `teams`, `matches`, `game-positions` live), keeping admin surface consolidated:

```php
Route::get('/courts', 'courts');                       // paginated, filter by status (default pending)
Route::get('/courts/{courtId}', 'showCourt');          // full detail for review
Route::post('/courts/{courtId}/approve', 'approveCourt');
Route::post('/courts/{courtId}/reject', 'rejectCourt'); // optional reason in body
```

> **Decision (controller placement):** admin court actions live on `AdminController` (not a dedicated controller) because every existing admin feature — teams, matches, game-positions, users — is a method on `AdminController` guarded by one `isAdmin` group. A separate `AdminCourtController` would break that convention.
>
> **Delegation (mirrors `AdminController::teams`, verified):** `AdminController::teams()` does no inline query — it calls `$this->adminRepository->getPaginatedTeams($filters, $perPage)` after `$filters = $request->only([...])`. The court admin methods follow the same split:
> - `AdminController::courts()` reads `$filters = $request->only(['status','modality_id','state_id','city_id'])` (default `status => 'pending'` when absent) and `$perPage = (int) $request->query('per_page', 15)`, then delegates the paginated/filtered projection to a **new** `AdminRepository::getPaginatedCourts($filters, $perPage)` (mirroring `getPaginatedTeams`). The projection returns owner + modality + city names + photo count and excludes nothing admin-sensitive.
> - `AdminController::showCourt($courtId)`, `approveCourt($courtId)`, `rejectCourt($courtId)` delegate the business logic to `CourtService::approve()/reject()` (and a `CourtService`/`CourtRepository` detail fetch for `showCourt`). The repository owns queries; the service owns the status-transition rules.

### Request shapes

**Create/Update (`CourtCreateOrUpdateRequest`)** — `multipart/form-data` (photos are files). camelCase request keys follow the Team naming style (`teamCityId` → `courtCityId`), but note the **required-on-create rules below are stricter than Team's precedent**: `TeamCreateOrUpdateRequest` marks every field `nullable` with no create/update split. The court form intentionally requires core fields on create. The create-vs-update distinction is decided inside `rules()` by inspecting the route parameter — `$isUpdate = $this->route('courtId') !== null;` — and applying `required` (create) vs `sometimes|nullable` (update) accordingly. This conditional-requiredness pattern is new to this feature (Team offers no template for it):

```
courtName            string  required (create) / nullable (update)
courtDescription     string  nullable
courtModalityId      int     required, exists:modalities,id
courtCityId          int     required, exists:cities,id
courtAddressLine     string  nullable, max 500
courtSurfaceType     string  required, in: natural,sintetico,emborrachado,piso
contacts[]           array   required, min 1   (each: {type in whatsapp,phone,other; value; label?})
photos[]             file[]  images; see 1–10 rule below
removedPhotoIds[]    int[]   nullable (update: photos to delete)
pricingRules[]       array   nullable (each: {weekdays:int[]|weekday:int; startTime HH:mm; endTime HH:mm; priceCents int})
scheduleConfigs[]    array   nullable (each: {weekday:int; startTime; endTime; reservationDuration in 60,90,120; isActive bool})
```

**Approve** — no body. **Reject (`CourtRejectRequest`)** — `{ reason?: string, max:1000 }`.

### Response shapes

- `GET court/my-courts` → `{ "courts": [ { id, name, slug, status, surface_type, modality_info:{id,name}, city_info:{name,state_info:{name}}, photos:[{id,photo_url,position}], ... } ] }`.
- `GET court/show/{id}` → full court with `contacts`, `photos` (incl. `photo_url`), `pricing_rules`, `schedule_configs`, `modality_info`, `city_info.state_info`.
- `POST court/save` / `update` → `201` + localized message (`__('messages.court.created'|'updated')`), mirroring `TeamController::save` returning a message string.
- `GET admin/courts` → Laravel paginator of pending courts (default) with owner + modality + city names + photo count, built by the new `AdminRepository::getPaginatedCourts($filters,$perPage)` (same shape/convention as `AdminRepository::getPaginatedTeams`, which `AdminController::teams` delegates to).
- `POST admin/courts/{id}/approve|reject` → `200` + localized message; sets `status`, `approved_at`, `approved_by` (approve) or `status=rejected` + `rejection_reason` (reject).

---

## Image upload / storage (follows Team's building blocks; corrects Team's delete bug)

The URL-accessor and `UploadService` building blocks are shared with Team, but the delete path and the use of a transaction deliberately **diverge** from Team (reasons below).

- Photos are uploaded as `photos[]` files in the same `multipart/form-data` request as the court (the FE builds a `FormData` exactly like `TeamForm.handleSubmit`).
- `CourtService` loops the uploaded files and calls `UploadService::uploadFileToFolder('public', 'courts', $file)`. **The returned path already contains the folder prefix** (`courts/<file>`); store it **verbatim** in `court_photos.photo_path` with an incrementing `position`.
- On update: files listed in `removedPhotoIds[]` are deleted via `UploadService::deleteFileOnFolder('public', 'courts', basename($photo_path))` — pass **`basename($photo_path)`**, NOT the full stored path. (Team's own delete passes the full `logo_path` and therefore targets `logos/logos/xxx`, a double-prefix bug that deletes nothing; do **not** copy that. Court's `basename()` approach is the correct one.) The removed rows are then soft-deleted; new files are appended. The combined count (kept + new) must remain within 1–10 (validated — see below).
- Display: `CourtPhoto::getPhotoUrlAttribute()` → `Storage::disk('public')->url($photo_path)`, identical to `Team::getLogoUrlAttribute` (this accessor pattern IS a faithful Team mirror).

### Transaction note (new addition — not an existing convention)

> `DB::transaction` is **newly introduced** by this design; no existing service (`TeamService`, `PlayerService`) uses one. This is an intentional improvement because court create/update writes up to 5 tables (`courts` + contacts + photos + pricing rules + schedule configs) plus the role grant, and a partial write would leave an inconsistent court. The whole create/update therefore runs inside `DB::transaction(function () { ... })`:
> - **Child rows are written through the `Court` model's `hasMany` relations** — `$court->contacts()->createMany(...)`, `$court->photos()->createMany(...)`, `$court->pricingRules()->createMany(...)`, `$court->scheduleConfigs()->createMany(...)` — because `BaseRepository` exposes only `create`, `updateById`, `createOrUpdateByParameters` and simple getters, and has **no** bulk-child-insert helper.
> - `BaseRepository::updateById` is `updateOrCreate(['id'=>$id], $data)` — it will **CREATE** a row if the id is absent. The owner-scope fetch-and-guard (see API surface / Validation invariants) must therefore run **before** any `updateById`, and the court-id for an update must be verified-owned first.
> - On a mid-transaction failure the DB work rolls back automatically. Files already written to disk before the failure are not covered by the DB transaction, so they are cleaned best-effort in the `catch` block (each `deleteFileOnFolder('public','courts',basename($path))`), and the exception is logged (see Error handling).

---

## Validation rules (per external input, with failure behavior)

All validation lives in Form Requests (`authorize(): true`, consistent with `TeamCreateOrUpdateRequest`); failures return Laravel's default `422` with `errors` keyed by field — the FE already renders `data.errors` (see `TeamForm` catch block).

| Input | Rule | On failure |
| --- | --- | --- |
| `courtName` | required on create, string, 1–254 | 422 field error |
| `courtModalityId` | required, `exists:modalities,id` | 422 |
| `courtCityId` | required, `exists:cities,id` | 422 |
| `courtSurfaceType` | required, `in:natural,sintetico,emborrachado,piso` (enum values) | 422 |
| `contacts` | required array, min 1; each `type in:whatsapp,phone,other`, `value` required string max 255, `label` nullable max 100 | 422 |
| `photos` + kept photos | **count 1–10 enforced**: on create `photos` is `required|array|min:1|max:10` and each `image|max:10240`; on update a custom rule computes `(existingKept − removed) + newUploads` and fails if `< 1` or `> 10` | 422 with message `__('error.court.photos_range')` |
| `pricingRules.*.weekday(s)` | each weekday `integer between:0,6`; `startTime`/`endTime` `date_format:H:i`; `endTime` after `startTime`; `priceCents` `integer min:0` | 422 |
| `scheduleConfigs.*` | `weekday integer between:0,6`, unique per weekday in payload; `startTime`/`endTime` `H:i`, `endTime` after `startTime`; `reservationDuration in:60,90,120`; window must fit at least one slot (`end−start ≥ duration`) | 422 |
| Reject `reason` | nullable string max 1000 | 422 |

> **Invariant ownership:** "a user may only read/update/delete their own court" is enforced in **the service/repository layer** (`where('user_id', Auth::id())`), because that layer already centralizes all court queries and there is no court-specific route middleware. A non-owner request yields `403 Forbidden` with `__('error.court.not_owner')` (and `404` when the id does not exist at all).
> **Order of operations (critical — `updateById` upserts):** `BaseRepository::updateById` is `updateOrCreate(['id'=>$id], $data)`, so passing an unverified/bogus/non-owned `courtId` into it would silently **CREATE** a court instead of 404ing. `CourtService::save()` on update MUST therefore: (1) fetch the court scoped to the owner — `CourtRepository::firstOwnedById($courtId, Auth::id())`; (2) if null → throw `403`/`404`; (3) only then perform the update. Never pass an unverified id into `updateById`. (The `CourtRepository::firstOwnedById` method is a thin `where('id',$id)->where('user_id',$userId)->first()` added for this guard.)
> **Invariant one-config-per-weekday:** enforced by the DB unique index `(court_id, weekday)` on `court_schedule_configs` **and** validated in the Form Request (duplicate weekday in payload → 422) so the user gets a clean message before hitting the DB constraint. The DB index is the ultimate guard.
> **Invariant status transitions:** only admin endpoints change `status`; owner save/update never writes `status` (service strips it). Approve requires current status `pending|rejected`; reject requires `pending|approved`. Illegal transition → `409 Conflict` with `__('error.court.invalid_status_transition')`.

---

## Error handling (concrete, per failing operation)

| Operation | Failure condition | Recoverable? | Caller receives | Logged? |
| --- | --- | --- | --- | --- |
| Court create/update | Validation fails | Recoverable (user fixes form) | `422` + `errors` map | No |
| Court create/update | Photo file write fails (`UploadService`) | Fatal for request | `500` + `__('error.court.upload_failed')`; transaction rolled back; orphan files cleaned best-effort | Yes (`Log::error` with court payload id, exception) |
| Court create | DB write fails mid-transaction | Fatal | `500` generic message; full rollback (court + contacts + photos + rules + configs) | Yes |
| Court show/update/delete | Court not found | Non-recoverable for that id | `404` + `__('error.court.not_found')` | No |
| Court show/update/delete | Requester is not the owner | Non-recoverable | `403` + `__('error.court.not_owner')` | No (expected) |
| Role assignment | spatie `Role::firstOrCreate` / `assignRole` throws | Fatal (inside same transaction) | `500`; whole court creation rolled back so user is not an owner with no court | Yes |
| Admin approve | Court not found | — | `404` | No |
| Admin approve/reject | Illegal status transition | Recoverable | `409` + `__('error.court.invalid_status_transition')` | No |
| Admin reject | reason > 1000 chars | Recoverable | `422` | No |
| Any admin court endpoint | Non-admin reaches it | — | `403` from `isAdmin` middleware (existing behavior) | No |

Logging uses the framework default channel (consistent with the codebase, which has no custom log channels for Team flows). "Expected" client errors (404/403/409/422) are not logged; only unexpected 500s are.

---

## Frontend (Vue 3 Options API, pt-BR, dark mode, Tailwind orange accent)

### New router file `src/router/court.js` (registered in `src/router/index.js`)

`court.js` holds **only the owner routes** (the admin-courts route goes into `admin.js`, see below — every existing admin screen route lives in `src/router/admin.js`, so splitting admin routes across two files would break that convention):

```js
// src/router/court.js  (owner routes only)
export default [
  { path: '/courts/my-courts', name: 'my-courts',   component: () => import('../views/System/Court/MyCourts.vue'),  meta: { requiresAuth: true } },
  { path: '/courts/form',       name: 'court-form',  component: () => import('../views/System/Court/CourtForm.vue'),  meta: { requiresAuth: true } },
  { path: '/courts/edit/:id',   name: 'court-edit',  component: () => import('../views/System/Court/CourtForm.vue'),  meta: { requiresAuth: true } },
  { path: '/courts/show/:id',   name: 'court-show',  component: () => import('../views/System/Court/CourtShow.vue'),  meta: { requiresAuth: true } },
]
```

The admin approval route is **added to the existing `src/router/admin.js`** (alongside the other `requiresAdmin` admin routes), NOT to `court.js`:

```js
// src/router/admin.js  (add this entry)
{ path: '/admin/courts', name: 'admin-courts', component: () => import('../views/System/Admin/AdminCourtList.vue'), meta: { requiresAuth: true, requiresAdmin: true } },
```

Registration in `index.js`: `import court from './court.js'` and spread `...court` in the routes array (exactly like `...team`, `...admin`). `admin.js` is already imported and spread as `...admin`, so the new admin-courts entry needs no index.js change.

> **Decision (naming collision):** the court owner-list route is named `my-courts` and lives in `court.js`. The existing team route `my-teams` is unaffected.

### Screens

1. **`MyCourts.vue`** (`/courts/my-courts`) — owner's courts list. `system-layout` wrapper, rounded-xl `bg-white dark:bg-gray-800` cards, status badge (Pendente=amber, Aprovada=green, Rejeitada=red), cover photo (position 0), modality + city. Empty state: "Você ainda não tem quadras. Cadastre a primeira." → button to `court-form`. Fetches `GET /court/my-courts`.

2. **`CourtForm.vue`** (`/courts/form` create, `/courts/edit/:id` edit) — one SFC for both modes, mirroring `TeamForm.vue` (`isEditMode` computed, `FormData` submit, Swal error toast rendering `data.errors`). Sections:
   - **Informações básicas:** `courtName`, `courtDescription` (QuillEditor like Team), **modality via `ModalitiesSelect`**, **state via `StateSelectComponent` + city via `CitySelectComponent`** (`:state-id` dependency — reused), surface type `<select>` (4 options from the enum labels), `courtAddressLine`.
     > **`ModalitiesSelect` integration (verified gotchas):** the component emits an **array even in `single` mode** (its watcher emits `val ? [val] : []`), and its label is hardcoded to `"Modalidades que joga"` with no overriding prop. Two concrete steps for the implementer:
     > 1. Bind `v-model="selectedModalities"` (an array in `data()`), and when building `FormData` send a **single scalar**: `formData.append('courtModalityId', this.selectedModalities[0] ?? '')`. Do NOT append the raw array — the backend rule is `integer|exists:modalities,id` and an array would 422.
     > 2. Add a `labelName` prop to `ModalitiesSelect` (mirroring the existing `labelName` prop on `CitySelectComponent`, default there is `"Cidade"`) so the court form can pass `label-name="Modalidade"`. The hardcoded `aria-label="stateSelect"` should likewise be made prop-driven or at least corrected for this reuse. This is a small, backward-compatible component change.
   - **Contatos (dynamic rows):** add/remove rows, each `type` `<select>` (WhatsApp/Telefone/Outro) + `value` input + optional `label`. Submitted as `contacts[]`.
   - **Fotos (1–10):** uploader with preview thumbnails + remove (mirrors `TeamForm` file input; multiple). Enforces 1–10 client-side (disable "add" at 10, warn below 1 on submit) with the server as source of truth. On edit, existing photos render with a remove ✕ that pushes id into `removedPhotoIds[]`.
   - **Configuração de horários (schedule per weekday):** 7 weekday rows (Dom–Sáb); each row has `isActive` toggle, `startTime`, `endTime` (`<input type="time">`), and `reservationDuration` `<select>` (1h/1h30/2h). Submitted as `scheduleConfigs[]`.
   - **Regras de preço (dynamic rows):** add/remove rows, each a weekday multi-select (or single) + `startTime` + `endTime` + `price` (BRL input → `priceCents`). Submitted as `pricingRules[]`.
   - Submit builds `FormData` (append scalar fields, `contacts[i][...]`, `pricingRules[i][...]`, `scheduleConfigs[i][...]`, and `photos[]` files) and `POST`s to `/court/save` or `/court/update/:id` with `multipart/form-data`, exactly like `TeamForm.handleSubmit`.

3. **`CourtShow.vue`** (`/courts/show/:id`) — read-only owner detail. Photo gallery/carousel (reuse the uniform-carousel markup pattern already in `TeamShow.vue`), modality/surface/address info cards (orange-accent icon tiles like `TeamShow`), contacts list where **whatsapp** contacts render a WhatsApp button linking to `https://wa.me/<digits>` (strip non-digits from `value`); phone/other render as plain text/`tel:`. Pricing rules table and schedule table shown read-only. Status badge.

4. **`AdminCourtList.vue`** (`/admin/courts`) — mirrors `AdminTeamList.vue`: filter card (status select defaulting to "Pendentes", modality, state/city), table of courts (name, owner, modality, city, status badge, photo count), and per-row **Aprovar** / **Rejeitar** actions. Reject opens a modal (same modal style as `TeamShow` interest modal) with an optional `reason` textarea → `POST /admin/courts/{id}/reject`. Approve → `POST /admin/courts/{id}/approve`. Refetches on action. Uses `api.get('/admin/courts', { params: { status } })`.

### Navigation entry

In `systemLayout.vue` `navigation[]`, add a group (shown to all authenticated users, matching Assumption A2):

```js
{
  name: "Quadras",
  icon: BuildingOffice2Icon,     // see import note below
  current: false,
  children: [
    { name: "Minhas Quadras", href: "/courts/my-courts" },
    { name: "Cadastrar Quadra", href: "/courts/form" },
  ],
},
```

And in `adminChildren[]` (the `v-if="user.is_admin"` block) add:

```js
{ name: "Quadras (Aprovação)", href: "/admin/courts" },
```

> **Icon import (verified):** `BuildingOffice2Icon` is **not** currently in `systemLayout.vue`'s import block — that block pulls from `@heroicons/vue/20/solid` (e.g. `Bars3Icon`, `CalendarIcon`, `BoltIcon`). The implementer must add `BuildingOffice2Icon` to that existing `@heroicons/vue/20/solid` import and confirm the name exists in the `/20/solid` set (fall back to `BuildingStorefrontIcon` from the same set if `BuildingOffice2Icon` is absent there).

> **Decision (menu gating):** the owner "Quadras" group is **not** role-gated in the FE because any authenticated user can become an owner by registering a court (product decision 1); gating it would hide the only entry point to becoming an owner. The admin approval entry is gated by the existing `user.is_admin` block.

---

## Mapping of the 6 product decisions to this design

| # | Product decision | Where addressed |
| --- | --- | --- |
| 1 | User becomes owner by creating first court (player-profile-style UX); court needs admin approval, not the owner | Role-assignment flow (`CourtService::createCourt` auto-grants `court-owner` via the `AssignRoleOrPermission` spatie pattern — the role grant is new, not copied from player code which grants no role); `courts.status` defaults `pending`; no owner approval anywhere |
| 2 | Court entity: user + modality, address like Team, surface enum, 1+ contacts (whatsapp→wa.me), 1–10 photos (public disk + URL), approval status | `courts` + `court_contacts` + `court_photos` tables; `CourtSurfaceType`/`CourtStatus` enums; `CourtPhoto.photo_url`; 1–10 Form Request rule; `CourtShow` WhatsApp button; owner always sees own courts, others only `approved` |
| 3 | Pricing by weekday + time range, reusable rules (option a), not per-slot | `court_pricing_rules` (weekday, start/end time, price_cents); FE "Regras de preço" dynamic rows; indexed for phase-2 lookup |
| 4 | Schedule config per weekday: start time, duration enum (60/90/120), end time; each weekday its own window | `court_schedule_configs` (unique per court+weekday, `reservation_duration` enum, `is_active`); FE 7-weekday schedule section; slot-generation feasibility noted |
| 5 | Admin approval area: endpoints + screen listing pending, approve/reject with optional reason | `AdminController::courts/showCourt/approveCourt/rejectCourt` under `isAdmin`; `AdminCourtList.vue`; `rejection_reason` column + reject modal |
| 6 (msgs 4–5) | Approval space in admin panel; "mensalista" recurrence as future; pricing per day & per hour; slot "fatiar" | Admin area (decision 5); `court_reservations` future sketch with `is_mensalista`/`recurrence_group_id`; pricing rules keyed per weekday+time; schedule config stores window+duration so phase-2 can "fatiar" (slice) into slots |

---

## Testability

- **Unit (PHPUnit, no HTTP):** `CourtService::createCourt` (role granted once, idempotent on second court, transaction rollback on upload failure); status-transition guards in `approve()/reject()`; `CourtPhoto::getPhotoUrlAttribute`; enum `label()` methods. These are pure/service-level and mockable (`UploadService`, `Storage::fake()`).
- **Feature/integration:** owner CRUD routes with `Storage::fake('public')` and `Laravel\Sanctum\Sanctum::actingAs($user)` (the `api` guard is Sanctum — do **not** use `Passport::actingAs`) — create court (asserts role assigned, status pending, 1–10 photo rule), non-owner `show` returns 403, admin approve/reject sets status + `approved_by`/`rejection_reason`, non-admin blocked by `isAdmin`.
- **Validation:** feature tests asserting 422 for 0 photos, 11 photos, missing contact, bad surface value, duplicate weekday schedule, illegal status transition (409).
- **Frontend:** screens are standard Options-API SFCs with `api` calls; the dynamic-row contacts/pricing/schedule builders are plain methods on `data()` arrays — unit-testable with Vitest if the project adds it (none configured today; not introduced in this phase).

The design keeps all business rules in the service/Form-Request layer (not controllers or FE), which is what makes it unit-testable. No design element requires hitting a real filesystem or network beyond `Storage::fake`.

---

## Remaining assumptions (call-outs)

- **A1** spatie guard `web`; owner role literal `court-owner`.
- **A2** No FE `court-owner` route guard in phase 1; owner nav shown to all authenticated users; admin approval gated by existing `is_admin`.
- **A3** Role is not revoked when a user deletes all their courts (out of scope).
- **A4** `address_line` is free-text; structured street/number fields are not required (Team itself only stores `city_id`). State is derived through `city → state` as Team does.
- **A5** Money stored as `price_cents` (unsigned int, BRL centavos), reusing the `team_uniforms.price_cents` precedent; FE formats to `R$`.
- **A6** Admin court endpoints extend `AdminController` rather than a new controller, to match the existing single-admin-controller convention.
- **A7** `GET /modalities/list` and the existing `state`/`city` list endpoints are reused by the FE selects as-is.

---

## Out of scope (this phase)

- Reservations / booking (`court_reservations`), slot generation, and the "mensalista" monthly recurrence — **phase 2** (schema sketched only).
- Public player browse of approved courts and free slots — **phase 3** (no new tables; a read query over `status = approved`).
- Payments/charging for reservations.
- Owner role revocation on court deletion (A3).
- The matches-calendar feature from the original msg1 (explicitly outside this run's authoritative scope).

---

## Responses to design-review findings (revision 2)

This revision resolves every finding in `design-review.json` / `design-review.md`. Each claim was re-verified against the real code before editing.

| # | Severity | Resolution |
| --- | --- | --- |
| 1 | HIGH | **Addressed.** Verified `config/auth.php` → `'api' => ['driver'=>'sanctum']` and `User` uses `Sanctum\HasApiTokens`. Overview "stack locked" line now says **Laravel Sanctum** (via `auth:api` → sanctum driver), added an auth-stack note, and the Testability section now prescribes `Laravel\Sanctum\Sanctum::actingAs` (all Passport references removed). Route group stays `auth:api` (correct). |
| 2 | HIGH | **Addressed.** Verified `PlayerService::saveOrUpdate` grants no spatie role. Overview, Role-assignment flow, and decision-mapping row 1 reworded: the *UX* mirrors the player profile, but the spatie role grant is **new behavior** following `AssignRoleOrPermission` (`Role::firstOrCreate` + `assignRole`, guard `web`). |
| 3 | MEDIUM | **Addressed.** Verified `uploadFileToFolder` returns a folder-prefixed path and `TeamService` passes the full path to `deleteFileOnFolder` (double-prefix bug). Image-upload section + patterns table now state: store the returned `courts/<file>` path verbatim, delete with `basename($photo_path)`, and explicitly do NOT copy Team's buggy full-path delete. Dropped "mirrors Team exactly" for the delete path (kept it only for the URL accessor, which genuinely matches). |
| 4 | MEDIUM | **Addressed.** Verified no service uses `DB::transaction` and `BaseRepository` has no bulk-child helper. Added a transaction note: transaction is a new (intentional) addition; children written via `hasMany` `createMany(...)`; `updateById` is an upsert so owner-scope guard runs first. |
| 5 | MEDIUM | **Addressed.** Verified `AdminController::teams` delegates to `AdminRepository::getPaginatedTeams`. Admin API section now routes the list through a new `AdminRepository::getPaginatedCourts($filters,$perPage)` with `$request->only(['status','modality_id','state_id','city_id'])` (default `status=pending`); approve/reject/show delegate to `CourtService`. |
| 6 | MEDIUM | **Addressed.** Verified `ModalitiesSelect` emits `[val]` even in `single` mode and has a hardcoded `"Modalidades que joga"` label (while `CitySelectComponent` has a `labelName` prop). CourtForm section now unwraps `selectedModalities[0] ?? ''` before appending `courtModalityId`, and adds a `labelName` prop to `ModalitiesSelect`. |
| 7 | NIT | **Addressed.** Moved `/admin/courts` out of `court.js` into `src/router/admin.js` with the other `requiresAdmin` routes; `court.js` now holds owner routes only. |
| 8 | NIT | **Addressed.** Verified `BuildingOffice2Icon` is not in `systemLayout.vue`'s `@heroicons/vue/20/solid` import. Added an import note (add it to that import; fall back to `BuildingStorefrontIcon` if absent in `/20/solid`). |
| 9 | NIT | **Addressed.** Verified `Team::modalityInfo` has reversed `belongsTo` args. Court models section now specifies `belongsTo(Modality::class,'modality_id','id')` with a caution not to copy Team's order. |
| 10 | NIT | **Addressed.** Added a status note: `courts.status` is a string enum (3-state lifecycle), an intentional departure from `teams.status` (int flag); only `timestamps`/`softDeletes` mirror Team. |
| 11 | NIT | **Addressed.** Verified `TeamCreateOrUpdateRequest` is all-`nullable`. Request-shapes section now states the required-on-create rules are stricter than (not mirroring) Team, and specifies create-vs-update is decided via `$this->route('courtId')` inside `rules()`. |
| 12 | NIT | **Addressed.** Added an "Order of operations" invariant: `updateById` is `updateOrCreate` (upsert), so `CourtService` fetches `firstOwnedById($courtId, Auth::id())`, 403/404s if null, and only then updates — never passing an unverified id to `updateById`. |
