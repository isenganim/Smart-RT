# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project state

Smart RT is a PWA for administering a single Indonesian neighborhood unit (RT): resident/household data, nightly watch (ronda) scheduling and check-in, daily cash collection (kas) via QR + daily PIN, fines, announcements, resident reports, letters, inventory, and simple voting.

The Laravel application is scaffolded in this repository. Development runs through DDEV, so use `ddev artisan`, `ddev composer`, and `ddev npm` instead of host PHP/Composer/NPM.

## Document workflow

Work is driven by the `superpowers` skill workflow: brainstorm → spec → phase plan → execute. Artifacts live in `docs/superpowers/`:

- `specs/2026-05-28-smart-rt-design.md` — the authoritative MVP system design. Lists 10 implementation priorities and 14 numbered MVP acceptance tests ("Testing MVP" section). All other docs trace back to this.
- `plans/2026-05-30-phase-0N-*.md` — 10 task-by-task implementation plans, one per design priority. Each is TDD-structured (write failing test → run → implement → pass → commit) and is meant to be executed with `superpowers:executing-plans` or `superpowers:subagent-driven-development`.
- `sprints/` — the 10 phases grouped into 5 dependency-ordered sprints, with Given/When/Then acceptance criteria tagged DT-1…DT-14 back to the spec's test list. `sprints/README.md` has the overview table and the DT→sprint traceability map.

**Phases are implemented sequentially, in order.** Do not write new phase plans or sprint docs ahead of the work; the user defines the next artifact when ready.

## Commands (after Phase 01 scaffolds the app)

The stack is Laravel 12, PHP 8.3, MySQL 8, Livewire 3 + Volt, Alpine.js, Tailwind, Pest, Vite. The user develops through **DDEV** and does not install PHP/MySQL/Composer directly on the host.

```bash
ddev start                                                # start Docker services
ddev launch                                               # open the app in a browser
ddev artisan test                                         # full Pest suite
ddev artisan test tests/Feature/Auth/LoginTest.php        # single test file
ddev artisan test --filter="redirects guests"             # single test by name
ddev artisan migrate:fresh --env=testing                  # reset the testing DB
ddev artisan migrate:fresh --seed                         # reset + demo data (DemoDataSeeder)
ddev npm run build                                        # Vite production build
ddev describe                                             # show URLs and DB connection info
ddev stop                                                 # stop services
```

Seeded logins (from `DemoDataSeeder`): `admin@smartrt.test` / `bendahara@smartrt.test`, password `password`.

### Developing without local PHP

Use DDEV as the runtime. Bootstrap/configure the repo with `ddev config --project-type=laravel --docroot=public --create-docroot`, run Composer/Artisan/NPM through DDEV (`ddev composer`, `ddev artisan`, `ddev npm`), and commit the generated `.ddev/` directory so future setup is repeatable. DDEV serves the site, so do not use `php artisan serve`.

## Architecture (from the design spec)

Single Laravel application split into two surfaces:

- **Portal Warga** — public, no login, reachable from a WhatsApp link. Read-only info (announcements, schedule) is open. Official actions (reports, letters, voting, ronda check-in) are gated by a registered, active phone number, not an account. The gateway is the `ResidentLookup` service (Phase 03), reused everywhere a warga acts.
- **Dashboard Pengurus** — login required, role-gated (`admin_rt`, `bendahara`) via the `EnsurePengurus` middleware. Manages all data and operations.

Cross-cutting invariants the design mandates:

- **Phone number is the warga identity.** It is normalized to a canonical form (`App\Support\PhoneNumber`) and must be unique among *active* residents only — enforced in the app layer (`UniqueActivePhone` rule), not a DB unique index, since the active-only condition isn't portably expressible there.
- **Cash transactions are never hard-deleted.** Mistakes are recorded as cancellations or corrections with a reason. Every important mutation writes to `audit_logs` via `App\Support\Audit::record()`.
- **Kas core is the priority.** The ronda + kas modules (schedule/check-in, QR+PIN cash scan at Rp500, Rp5.000 fines, recap) are the highest-value operational loop. Rp500 per house per day, one paid record per house per date; daily PIN session gates the scan and has an active time window.
- **QR tokens carry no PII** — only an opaque per-household token, rendered as inline SVG (no `imagick`/`gd` dependency).

## Repo notes

- `.mcp.json` holds local MCP server config **with live API keys** — it is gitignored; never commit it or echo its contents.
