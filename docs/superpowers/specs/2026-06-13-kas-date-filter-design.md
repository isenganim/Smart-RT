# Kas Date Filter Design

## Goal

Make the cash recap date control display a stable Indonesian-style date such as
`13/06/2026`, while only refreshing the report after the user clicks
`Tampilkan`.

## Interaction

- The page initializes both the applied report date and the editable date from
  the `date` query parameter, falling back to today.
- The visible field shows the editable date as `dd/mm/yyyy`.
- The browser-native date picker remains available through a calendar control,
  but its browser-formatted text is not layered beneath the formatted value.
- Selecting a date only updates the editable value.
- The `Tampilkan` link is generated from the editable value and opens the recap
  route with a canonical `Y-m-d` query parameter.
- The server validates the query value and falls back to today for malformed
  dates.

## Layout

The date field and primary `Tampilkan` button sit in one responsive row on
desktop and stack on narrow screens. Existing admin panel and button components
remain in use.

## Data Flow

The Volt component keeps the applied `date` state used by `KasReport`. Alpine
keeps a local editable `raw` value for the picker, formats it for display, and
builds the `Tampilkan` URL. No report computation or Livewire request occurs
while the user is only choosing a date.

## Testing

Feature tests cover query-date application and the generated filter URL.
Browser QA verifies the
formatted display, responsive layout, URL update, and report update after the
button click.
