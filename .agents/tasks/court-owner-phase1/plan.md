# Implementation Plan — Court Owner (Dono de Quadra) ETAPA 1

Derived faithfully from the approved design at
`/home/andrefontoura/PhpstormProjects/sisbrasfute_laravel12_hostinger/.agents/tasks/court-owner-phase1/design.md`
(reviewed clean in `design-review.json`). Two codebases:

- BACKEND (Laravel 12): `/home/andrefontoura/PhpstormProjects/sisbrasfute_laravel12_hostinger`
- FRONTEND (Vue 3 Options API, Vite, Tailwind v4): `/home/andrefontoura/PhpstormProjects/sisbrasfute_tailwind`

> This file is the fallback single-file plan. The workflow was ALSO decomposed into FEAT
> artifacts under this same directory (`task.json`, `context.json`, `features/FEAT-00X.json`);
> the build loop was replaced with one coder step per FEAT plus a convergence loop. If the FEAT
> execution is used, this file remains the authoritative description of the same work.

## Build & verify commands (discovered during exploration)

- Backend tests: `php artisan test` (PHPUnit 11, SQLite `:memory:` via `phpunit.xml`; feature tests use `RefreshDatabase` and `Laravel\Sanctum\Sanctum::actingAs($user)`).
- Backend single suite: `php artisan test --filter Court`.
- Frontend build: `npm run build` (Vite). Lint: `npm run lint`.
- There is NO `composer`-level static analyzer or pint hook configured as a gate; use `php artisan test` as the backend gate and `npm run build` as the frontend gate.

## Key verified facts the implementer must honor

- Migrations: `AppServiceProvider::boot()` globs every dir under `database/migrations/*` and
  `loadMigrationsFrom`s them, so a NEW `database/migrations/Courts/` folder is auto-loaded (no config change).
- Route guard: the authenticated group is `Route::middleware('auth:api')`; `config/auth.php` maps
  `api` to the **sanctum** driver. Tests authenticate with `Laravel\Sanctum\Sanctum::actingAs($user)`, NEVER `Passport::actingAs`.
- Enums: new enums go in `app/Enums` (backed enums with `label()`), e.g. `app/Enums/MatchType.php`. Do NOT use the legacy `app/Enum`.
- `BaseRepository::updateById($data,$id)` is `updateOrCreate(['id'=>$id],$data)` — an UPSERT. The owner-scope
  fetch-and-guard MUST run before any update; never pass an unverified id into it.
- `UploadService::uploadFileToFolder($disk,$folder,$file)` returns a folder-prefixed path (`courts/<file>`); store verbatim.
  Delete with `deleteFileOnFolder('public','courts', basename($path))` — do NOT copy Team's full-path double-prefix bug.
- `Court::modalityInfo()` must be `belongsTo(Modality::class,'modality_id','id')` — do NOT copy Team's reversed args.
- Eloquent default `$snakeAttributes = true`, so camelCase relations serialize snake_cased
  (`pricingRules` → `pricing_rules`, `cityInfo` → `city_info`). Keep relation methods camelCase.
- i18n: add `court` domain keys to BOTH `lang/pt_BR/{error,messages}.php` and `lang/en/{error,messages}.php`.
- Admin gate: existing `isAdmin` middleware (checks `is_admin` column OR spatie `admin` role). FE gate: `authStore.isAdmin` + `requiresAdmin` meta.
- FE axios singleton `src/services/api.js`; uploads use `multipart/form-data`; error shape `err.response.data.errors` rendered by Swal.
- FE selects reused as-is: `ModalitiesSelect.vue` (emits an ARRAY even in single mode; needs a `labelName` prop added),
  `StateSelectComponent.vue`, `CitySelectComponent.vue` (city depends on `:state-id`).

---

## BACKEND

- [ ] 1. Create the four new backed enums in `app/Enums`.
      `CourtSurfaceType` (string: natural/sintetico/emborrachado/piso), `CourtStatus` (string: pending/approved/rejected),
      `CourtContactType` (string: whatsapp/phone/other), `ReservationDuration` (int: 60/90/120), each with a pt-BR `label()`.
      Files: `app/Enums/CourtSurfaceType.php`, `app/Enums/CourtStatus.php`, `app/Enums/CourtContactType.php`, `app/Enums/ReservationDuration.php`
      Verify: `php artisan test` still green (no behavior yet; confirms files parse/autoload).

- [ ] 2. Create the five migrations under a new `database/migrations/Courts/` folder, in this order
      (dependency: contacts/photos/pricing/schedule FK → courts): `courts`, `court_contacts`, `court_photos`,
      `court_pricing_rules`, `court_schedule_configs`. Match Team migration style (`$table->id()`, explicit
      `$table->foreign(...)->references('id')->on(...)`, `timestamps()`, `softDeletes()`). `courts.status` string(20)
      default `pending` indexed; index `court_pricing_rules (court_id, weekday)`; unique `court_schedule_configs (court_id, weekday)`.
      Files: `database/migrations/Courts/2026_09_20_000001_create_courts_table.php` and the four sibling tables.
      Verify: `php artisan migrate --env=testing` runs clean on a fresh sqlite; `php artisan test` green.

- [ ] 3. Create the five Eloquent models with the exact relations/casts/appends from the design.
      `Court` (SoftDeletes, casts surface_type/status enums + approved_at datetime; relations owner/modalityInfo
      [`belongsTo(Modality::class,'modality_id','id')`]/cityInfo/contacts/photos/pricingRules/scheduleConfigs),
      `CourtContact`, `CourtPhoto` (`$appends=['photo_url']`, `getPhotoUrlAttribute` via `Storage::disk('public')->url`),
      `CourtPricingRule`, `CourtScheduleConfig` (casts reservation_duration enum + is_active bool).
      Files: `app/Models/Court.php`, `app/Models/CourtContact.php`, `app/Models/CourtPhoto.php`, `app/Models/CourtPricingRule.php`, `app/Models/CourtScheduleConfig.php`
      Verify: `php artisan test` green; a quick model unit test asserting `photo_url` accessor and enum casts passes.

- [ ] 4. Create `CourtRepository` (extends `BaseRepository`, model `Court`) with `firstOwnedById($id,$userId)`
      (`where('id',$id)->where('user_id',$userId)->first()`), `myCourts($userId)` (owner's courts, all statuses, eager
      photos/modalityInfo/cityInfo.stateInfo), and `getOwnedDetail($id,$userId)` (full eager load). Add
      `AdminRepository::getPaginatedCourts(array $filters,int $perPage)` mirroring `getPaginatedTeams` (filters
      status[default pending]/modality_id/state_id/city_id; project owner+modality+city names + photo count).
      Files: `app/Repository/CourtRepository.php`, `app/Repository/AdminRepository.php`
      Verify: `php artisan test` green.

- [ ] 5. Create `CourtCreateOrUpdateRequest` and `CourtRejectRequest` Form Requests (`authorize(): true`).
      Create-vs-update decided via `$this->route('courtId')`. Rules exactly per design (required-on-create core fields,
      `contacts` min 1, photo count 1–10 with the update-aware kept/removed/new computation, pricing/schedule rules,
      schedule slot-fit only when `isActive=true`, duplicate-weekday guard, reject reason max 1000).
      Files: `app/Http/Requests/CourtCreateOrUpdateRequest.php`, `app/Http/Requests/CourtRejectRequest.php`
      Verify: feature test asserting 422 for 0 photos / 11 photos / missing contact / bad surface / duplicate weekday.

- [ ] 6. Create `CourtService` (extends `BaseService`, injects `CourtRepository` + `UploadService`) with
      `createCourt`, `updateCourt`, `getMyCourts`, `getOwnedDetail`, `delete`, `approve`, `reject`. Wrap create/update in
      `DB::transaction`; write children via `hasMany` `createMany`; grant `court-owner` role on first create via
      `Role::firstOrCreate(['name'=>'court-owner','guard_name'=>'web'])` + `assignRole`; owner-scope guard before update
      (403/404); strip `status` from owner writes; status-transition guards (approve: pending|rejected; reject:
      pending|approved; else 409); best-effort file cleanup + `Log::error` on failure (delete via `basename`).
      Files: `app/Service/CourtService.php`
      Verify: unit tests (role granted once/idempotent, rollback on upload failure, transition guards) + `php artisan test` green.

- [ ] 7. Create `CourtController` (owner CRUD: `myCourts`, `show`, `save`, `destroy`) and add the admin methods
      `courts`, `showCourt`, `approveCourt`, `rejectCourt` to the EXISTING `AdminController` (delegating to repo/service).
      Add i18n keys (`court.*`) to `lang/pt_BR/{error,messages}.php` and `lang/en/{error,messages}.php`.
      Files: `app/Http/Controllers/CourtController.php`, `app/Http/Controllers/AdminController.php`,
      `lang/pt_BR/error.php`, `lang/pt_BR/messages.php`, `lang/en/error.php`, `lang/en/messages.php`
      Verify: feature tests for create (role assigned, status pending), non-owner show → 403, admin approve/reject sets fields.

- [ ] 8. Register routes in `routes/api.php`: a `court` prefix group (my-courts/show/save/update/delete, `save` guarded
      by `emailVerified`) inside the `auth:api` group, and the four admin court routes inside the EXISTING
      `admin`+`isAdmin`+`AdminController` group (next to teams/matches/game-positions).
      Files: `routes/api.php`
      Verify: `php artisan route:list --path=court` and `--path=admin/courts` show the routes; feature tests pass; `php artisan test` green.

---

## FRONTEND

- [ ] 9. Add a `labelName` prop (default "Modalidades que joga") to `ModalitiesSelect.vue` and bind the label/aria to it
      (backward compatible — existing callers keep the default).
      Files: `/home/andrefontoura/PhpstormProjects/sisbrasfute_tailwind/src/components/form/ModalitiesSelect.vue`
      Verify: `npm run build` succeeds; existing team form still renders modalities.

- [ ] 10. Create the owner router file and register it; add the admin-courts route to admin.js.
      `src/router/court.js` exports the 4 owner routes (my-courts, court-form, court-edit, court-show, `requiresAuth`).
      Import+spread `...court` in `src/router/index.js`. Add `/admin/courts` (`requiresAdmin`) to `src/router/admin.js`.
      Files: `src/router/court.js` (new), `src/router/index.js`, `src/router/admin.js`
      Verify: `npm run build` succeeds; navigating to `/courts/my-courts` resolves the component.

- [ ] 11. Add the "Quadras" nav group (Minhas Quadras + Cadastrar Quadra) to `systemLayout.vue` `navigation[]` using
      `BuildingOffice2Icon` (confirmed present in `@heroicons/vue/20/solid`; add to the import + `components`), and add
      "Quadras (Aprovação)" to `adminChildren[]`.
      Files: `/home/andrefontoura/PhpstormProjects/sisbrasfute_tailwind/src/components/layouts/systemLayout.vue`
      Verify: `npm run build` succeeds; sidebar shows the new entries.

- [ ] 12. Create `MyCourts.vue` (`/courts/my-courts`) — owner list, status badges, cover photo, empty state linking to
      court-form. Fetches `GET /court/my-courts`.
      Files: `/home/andrefontoura/PhpstormProjects/sisbrasfute_tailwind/src/views/System/Court/MyCourts.vue`
      Verify: `npm run build` succeeds; list renders against the API shape `{ courts: [...] }`.

- [ ] 13. Create `CourtForm.vue` (`/courts/form` + `/courts/edit/:id`) mirroring `TeamForm.vue`: basic info
      (name/description QuillEditor/ModalitiesSelect with `label-name="Modalidade"` unwrapping `selectedModalities[0]`,
      State+City selects, surface select, address), dynamic contacts rows, 1–10 photos uploader (with `removedPhotoIds[]`
      on edit), 7-weekday schedule section, dynamic pricing rows; builds `FormData` and POSTs to `/court/save` or
      `/court/update/:id` with `multipart/form-data`.
      Files: `/home/andrefontoura/PhpstormProjects/sisbrasfute_tailwind/src/views/System/Court/CourtForm.vue`
      Verify: `npm run build` succeeds; create + edit submit builds the expected FormData keys.

- [ ] 14. Create `CourtShow.vue` (`/courts/show/:id`) — read-only owner detail: photo gallery (reuse TeamShow carousel
      markup), info cards, contacts list with WhatsApp button `https://wa.me/<digits>` (strip non-digits), pricing + schedule
      tables, status badge. Fetches `GET /court/show/:id`.
      Files: `/home/andrefontoura/PhpstormProjects/sisbrasfute_tailwind/src/views/System/Court/CourtShow.vue`
      Verify: `npm run build` succeeds.

- [ ] 15. Create `AdminCourtList.vue` (`/admin/courts`) mirroring `AdminTeamList.vue`: status filter (default Pendentes) +
      modality/state/city, table, Aprovar/Rejeitar actions (reject modal with optional reason). Uses
      `api.get('/admin/courts',{params})`, `POST /admin/courts/{id}/approve|reject`, refetches on action.
      Files: `/home/andrefontoura/PhpstormProjects/sisbrasfute_tailwind/src/views/System/Admin/AdminCourtList.vue`
      Verify: `npm run build` succeeds; actions hit the admin endpoints.

---

## Design-decision → 6 product-decision mapping (honor these)

1. User becomes owner by creating first court (player-profile-style UX); the spatie `court-owner` role grant is NEW
   behavior following `AssignRoleOrPermission` (player code grants no role). Court needs ADMIN approval, not the owner;
   `courts.status` defaults `pending`.
2. Court entity = user + modality + city (address like Team) + surface enum + 1..N contacts (whatsapp → wa.me button) +
   1–10 photos (public disk, `photo_url`) + approval status. Owner always sees own courts; others see only `approved` (phase 3).
3. Pricing by weekday + time range, reusable rules (NOT per-slot) → `court_pricing_rules`.
4. Schedule config per weekday: start/end + duration enum (60/90/120); each weekday its own window → `court_schedule_configs` (unique per court+weekday).
5. Admin approval area: endpoints on `AdminController` + `AdminCourtList.vue`; approve/reject with optional reason; `rejection_reason` column.
6. (msgs 4–5) Admin approval space; "mensalista" recurrence = PHASE 2; pricing per day & per hour (decision 3); slot "fatiar" feasible from schedule config + pricing (phase 2).

## Phase-2 / phase-3 schema anticipations (do NOT build, but do NOT block)

- Phase 2 `court_reservations` (sketch): court_id, reservation_date, start_time, end_time, price_cents (snapshot),
  customer_name, owner-only description, is_mensalista, recurrence_group_id (uuid), created_by. Phase-1 schedule+pricing
  tables are intentionally rich enough to generate slots and resolve per-slot prices with no migration churn.
  Phase 2 must handle slots that match no pricing rule (phase 1 does not enforce pricing coverage).
- Phase 3 (public player browse): NO new tables — a read query over `courts WHERE status='approved'` with eager
  photos/contacts/pricing/schedule. `court/show/{id}` stays owner-scoped (403 for non-owner); the public detail endpoint
  is NEW in phase 3 and does not reuse `court/show`.
