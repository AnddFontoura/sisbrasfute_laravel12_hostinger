# Design Review — Court Owner (Dono de Quadra) ETAPA 1 (revision 2)

Reviewer: design-review subagent (fresh read, no authoring context).
Scope reviewed: `/.agents/tasks/court-owner-phase1/design.md` against the real backend
(`sisbrasfute_laravel12_hostinger`, Laravel 12) and frontend (`sisbrasfute_tailwind`, Vue 3).

Every claim the design labels "verified" was independently re-opened in the source. This is
the second revision of the design (it already resolved 12 prior findings); the goal here was
to confirm those resolutions hold against the actual code and to look for anything still open.

## Verdict

APPROVED — 0 HIGH, 0 MEDIUM findings. (NITs listed below do not gate; they are improvements
the implementer can fold in, not blockers.)

---

## Findings

1. **NIT — Response-shape serialization keys are correct but the reason is undocumented.**
   The design's response shapes use snake_case relation keys (`city_info`, `state_info`,
   `modality_info`, `pricing_rules`, `schedule_configs`) while the Court model defines
   camelCase relation methods (`cityInfo`, `modalityInfo`, `pricingRules`, `scheduleConfigs`).
   This IS correct — Eloquent's `Model::$snakeAttributes` defaults to `true`, so a relation
   loaded as `with('pricingRules')` serializes under `pricing_rules`; `Team::cityInfo` already
   reaches the FE as `team.city_info.state_info` (confirmed in `TeamShow.vue`), and no model
   overrides `$snakeAttributes`. No code change needed.
   CONCRETE FIX (doc-only): add one line to the Response-shapes section — "relation keys are
   snake_cased by Eloquent's default `$snakeAttributes=true`, so `pricingRules` →
   `pricing_rules`" — so an implementer does not "fix" the model to force snake relation names.

2. **NIT — `court/show` is defined but no FE route is wired to call `GET court/show/{id}` for the
   owner's own detail, while `CourtShow.vue` route is `requiresAuth` only.** The design routes
   `/courts/show/:id` → `CourtShow.vue` and the backend `show` is owner-scoped (403 if not
   owner). That is internally consistent, but phase 3 (public browse) will need a *different*
   non-owner-scoped detail endpoint. The design notes phase 3 is a read query over
   `status=approved` with no new tables, which is fine, but does not explicitly say the
   owner-scoped `court/show` is NOT the public detail endpoint.
   CONCRETE FIX (doc-only): in "Future tables / Phase 3", add: "the public detail endpoint is
   new in phase 3; `court/show/{id}` stays owner-scoped and is not reused publicly."

3. **NIT — `ReservationDuration` enum is cast on `court_schedule_configs.reservation_duration`,
   but the schedule-config validation allows `isActive=false` weekdays whose window may be
   empty.** The validation rule "window must fit at least one slot (`end−start ≥ duration`)" is
   stated as unconditional. For an inactive weekday (`isActive=false`, court closed that day)
   the owner may legitimately leave `start_time`/`end_time`/`duration` blank or nonsensical.
   The design does not say whether the slot-fit rule is skipped when `isActive=false`.
   CONCRETE FIX: clarify the rule as "when `isActive` is true: `startTime`/`endTime`/
   `reservationDuration` required and `end−start ≥ duration`; when `isActive` is false: those
   fields are `nullable` and the slot-fit check is skipped."

4. **NIT — Pricing-rule coverage vs. slot generation is feasible but un-guarded.** The design
   correctly makes phase-2 slot pricing feasible (pricing rules keyed by `weekday` +
   `[start_time,end_time)`, indexed `(court_id, weekday)`). However phase 1 does not validate
   that pricing rules actually cover the schedule window, so a phase-2 slot could match no
   pricing rule and have a null price. This is acceptable for phase 1 (pricing rules are
   `nullable` on create) but should be an explicit, documented phase-2 concern rather than a
   silent gap.
   CONCRETE FIX (doc-only): under the pricing table "rich enough" note, add: "phase 2 must
   handle slots with no matching pricing rule (reject booking or require full coverage); phase 1
   does not enforce coverage because pricing rules are optional on create."

---

## Verified Assumptions (opened the real source; the design's claim holds)

- **Migration subfolder auto-load.** `AppServiceProvider::boot()` globs `database_path('migrations/*')`,
  filters to directories, and calls `loadMigrationsFrom($paths)`. Existing domain folders are
  PascalCase (`Teams`, `TeamUniforms`, `Modalities`, …). A new `database/migrations/Courts/`
  folder auto-loads with no config change. CONFIRMED.
- **Passport leftover is not the request guard.** `AppServiceProvider::boot()` still calls
  `Passport::personalAccessTokensExpireIn`/`loadKeysFrom`, but `config/auth.php` has
  `'api' => ['driver' => 'sanctum']` and `User` uses `Laravel\Sanctum\HasApiTokens`. `routes/api.php`
  even has `Route::middleware('auth:sanctum')` on `/user` alongside the `auth:api` group. The
  design's "use `Sanctum::actingAs`, never `Passport::actingAs`" is correct. CONFIRMED.
- **Player profile grants no spatie role.** `PlayerService::saveOrUpdate()` creates/updates the
  `Player` row and attaches modalities/positions; it never calls `assignRole()`. The design's
  "role grant is NEW behavior, mirror `AssignRoleOrPermission`, not player code" is correct. CONFIRMED.
- **spatie grant precedent.** `AssignRoleOrPermission` does
  `Role::firstOrCreate(['name'=>…, 'guard_name'=>'web'])` then `$user->assignRole($role)`. The
  design's `court-owner`/guard `web` grant mirrors it exactly. CONFIRMED.
- **UploadService folder-prefix behavior.** `uploadFileToFolder` returns `Storage::disk($disk)->put($folderPath,$file)`
  (path already prefixed with the folder). `deleteFileOnFolder` deletes `$folderPath.'/'.$filePath`.
  CONFIRMED — the design's "store verbatim, delete with `basename($path)`" is the right call.
- **Team's delete double-prefix bug is real.** `TeamService::updateTeam` passes the full
  `$team->logo_path` (e.g. `logos/xxx`) into `deleteFileOnFolder('public','logos',…)`, producing
  `logos/logos/xxx`. The design is right NOT to copy this. CONFIRMED.
- **Team's `modalityInfo` arg order is reversed.** `Team::modalityInfo()` is
  `belongsTo(Modality::class,'id','modality_id')` (wrong). The design's
  `Court::modalityInfo = belongsTo(Modality::class,'modality_id','id')` is correct. CONFIRMED.
- **`BaseRepository::updateById` is an upsert.** It is `updateOrCreate(['id'=>$id],$data)` — it
  WILL create on a missing id. The design's fetch-owner-first-then-update invariant is justified.
  CONFIRMED.
- **One Form Request handles create+update.** `TeamController::save(TeamCreateOrUpdateRequest, $teamId=null)`
  serves both `team/save` and `team/update/{teamId}`. CONFIRMED.
- **`TeamCreateOrUpdateRequest` is all-`nullable`, no create/update split.** CONFIRMED — the
  design's stricter create rules are correctly flagged as new, not a Team mirror.
- **Enum convention.** `app/Enums/` holds backed enums with `label()` (`MatchType`, `PaymentMethod`,
  …); `app/Enum/GenderEnum.php` is the older const style. New `App\Enums` placement is correct. CONFIRMED.
- **Admin wiring.** `routes/api.php` has `Route::prefix('admin')->middleware('isAdmin')->controller(AdminController::class)`
  with `teams`, `matches`, `game-positions`, `users` as methods. `AdminController::teams` delegates
  to `AdminRepository::getPaginatedTeams($filters,$perPage)` with `$request->only([...])`. The
  design's `getPaginatedCourts` mirror and controller-placement decision match. CONFIRMED.
- **`isAdmin` gate.** `IsAdminMiddleware` allows `is_admin` column OR `hasRole('admin')`; `User`
  exposes `is_admin` accessor as `$appends`. `requiresAdmin` FE guard uses `authStore.isAdmin`. CONFIRMED.
- **Middleware aliases.** `bootstrap/app.php` registers `emailVerified`, `isAdmin`, `isTeamManager`,
  `isTeamMember`, `isTeamAdmin`. The design's `emailVerified` on `court/save` and `isAdmin` on admin
  routes are valid. CONFIRMED.
- **`team/save` uses `emailVerified`.** CONFIRMED in `routes/api.php` — design mirrors it.
- **`price_cents` precedent.** `team_uniforms` migration has `unsignedInteger('price_cents')->nullable()`.
  CONFIRMED (design's required unsigned int for pricing rules is a reasonable tightening).
- **City/State relation.** `City::stateInfo = belongsTo(State::class,'state_id','id')`; serializes as
  `state_info`. Admin `state_id` filter via `whereHas('cityInfo')` is feasible for courts. CONFIRMED.
- **`ModalitiesSelect` quirks.** Emits `[val]` even in `single` mode; label hardcoded
  `"Modalidades que joga"`; `aria-label="stateSelect"`; NO `labelName` prop. The design's unwrap
  `selectedModalities[0] ?? ''` and the proposed `labelName` prop addition are correct and needed. CONFIRMED.
- **`CitySelectComponent`** has a `labelName` prop (default "Cidade") and a `:state-id` dependency
  with city-clear-on-state-change. CONFIRMED — the reuse pattern described is accurate.
- **`TeamForm.vue`** builds `FormData`, posts `multipart/form-data`, and renders `data.errors`
  through Swal. CONFIRMED — the Court form's described submit mirrors it faithfully.
- **`TeamShow.vue`** has a uniforms carousel and an interest modal. CONFIRMED — the "reuse carousel
  markup" and "same modal style" claims for `CourtShow`/`AdminCourtList` are grounded.
- **Router modular files.** `src/router/index.js` imports+spreads per-domain arrays
  (`...team`, `...admin`, …); `admin.js` entries use `requiresAuth+requiresAdmin`; `team.js` owner
  list is named `my-teams` (so `my-courts` does not collide). CONFIRMED.
- **`systemLayout.vue`** imports heroicons from `@heroicons/vue/20/solid` and `BuildingOffice2Icon`
  is NOT in that import; `navigation[]` and `adminChildren[]` arrays exist with the described shape.
  CONFIRMED — the icon-import caveat is accurate.
- **Modality reuse.** `Modality` model maps table `modalities`; `/modalities/list` returns
  `data.modalities` (used by `ModalitiesSelect`). CONFIRMED.

## Unverified / Wrong Assumptions

None. Every claim the design asserted as "verified" was confirmed in the real source. No claim
was found to be wrong, and no claim remained unverifiable within the two codebases.
