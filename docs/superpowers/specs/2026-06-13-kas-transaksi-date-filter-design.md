# Kas Transaksi Date Filter Design

## Goal

Let the cash transaction list (`/dashboard/kas/transaksi`) be scoped to a single
day so a pengurus can answer "where did this day's money come from", seeing both
the per-day transaction rows and a small active-only breakdown.

## Interaction

- The page initializes its date from the `date` query parameter, falling back to
  today. Malformed input falls back to today without error.
- The page always shows exactly one day. There is no all/latest view.
- The date control reuses the rekap page pattern: a read-only `dd/mm/yyyy`
  display field, a calendar button that opens the native picker, and a
  `Tampilkan` button that navigates to `kas.transactions` with the selected
  date as a `Y-m-d` query parameter.
- A summary row shows Iuran, Denda, Koreksi, and Total for the selected day.
- Cancelled transactions still appear in the list (greyed, "Dibatalkan" badge)
  but are excluded from the summary totals.
- The cancel/correction flow is unchanged; after a cancellation the list and
  summary refresh for the current date.

## Data Flow

The Volt component keeps a single applied `date` state (`Y-m-d`):

- `mount` resolves `date` from `request()->query('date')` or today, guarded by
  `Carbon::canBeCreatedFromFormat($value, 'Y-m-d')`.
- `$transactions` filters `->whereDate('date', $this->date)`, eager-loads
  `household`/`resident`, orders `latest()`, and drops the previous 100-row cap
  (a single day is naturally bounded).
- `$daily` calls `app(KasReport::class)->daily($ref)` with the parsed date,
  reusing the rekap daily logic. Active-only totals come for free because
  `KasReport::daily()` already filters `->active()`, so the figures match the
  rekap page for the same date.

No route, model, or service changes are required; `kas.transactions` already
accepts a query string and `KasReport::daily()` already returns the needed
breakdown.

## Layout

The date field and `Tampilkan` button sit in one responsive row, identical to
the rekap page. The summary uses the shared `x-admin.metric` cards. The existing
responsive desktop table / mobile card list is retained.

## Testing

Feature tests cover: today default, valid `?date=` selection, malformed date
fallback, list scoped to the selected day (excludes other days), and active-only
summary totals (cancelled excluded). Existing cancel tests continue to pass.
Browser QA verifies the formatted display, responsive layout, URL update, and
summary/list refresh after the button click.
