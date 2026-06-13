# Smart RT Admin Operational Command Center Design

## Status

Approved on June 10, 2026.

This specification supersedes `2026-06-10-stripe-dashboard-design.md` for the
admin dashboard and admin modules. `DESIGN.md` remains the source of truth for
brand tokens, typography, colors, spacing, shapes, and button treatment.

## Objective

Redesign the complete Smart RT admin system into a modern, elegant, and
efficient operational workspace. The redesign must help an RT administrator
identify urgent work, understand current conditions, and complete routine
tasks with less navigation and visual inconsistency.

The redesign covers the shared admin shell, dashboard, and all admin modules.
Implementation will be phased, but every phase must use the same approved
design system and page patterns.

## Approved Direction

The approved direction is **Operational Command Center**:

- Action queue first, supported by summary analytics.
- Persistent labeled sidebar on desktop.
- Compact bottom navigation and an additional-module drawer on mobile.
- Light theme only for this phase.
- Balanced information density: comfortable forms and summaries with compact,
  readable tables.
- Complete mobile workflows, including forms and administrative actions.
- Laravel Livewire/Volt remains the application architecture.

Rejected directions:

- A presentation-led executive dashboard that prioritizes a status banner.
- A dense utility workspace that maximizes visible rows at the expense of
  clarity.
- A dashboard-only redesign that leaves modules visually inconsistent.

## Design Principles

1. **Work before decoration.** The first viewport shows tasks and meaningful
   metrics, not a marketing hero or system-status promotion.
2. **One coherent admin product.** Navigation, forms, tables, dialogs, empty
   states, and feedback use shared components and tokens.
3. **Progressive disclosure.** Common actions stay visible. Secondary actions
   live in row menus, drawers, or detail views.
4. **Clear state communication.** Status is conveyed with text and shape, not
   color alone.
5. **Mobile completeness.** Mobile layouts may change presentation, but they
   must not remove required admin functionality.
6. **Restrained visual hierarchy.** Indigo is reserved for primary actions,
   selected navigation, focus indicators, and important links.

## Information Architecture

### Desktop Navigation

Use a persistent, labeled left sidebar. It may collapse to icons at narrower
desktop widths, but labels must be available through expansion or tooltips.

Navigation groups:

- **Ringkasan:** Dashboard
- **Data Warga:** Rumah / KK, Warga
- **Operasional:** Ronda, Sesi Scan, Denda, Kas
- **Layanan:** Pengumuman, Laporan, Surat, Voting, Inventaris

The lower sidebar contains the authenticated user's name and role, portal
access, and logout controls.

### Mobile Navigation

Use a fixed bottom navigation with five destinations:

- Beranda
- Rumah
- Warga
- Kas
- Lainnya

`Lainnya` opens an accessible drawer containing every remaining admin module,
portal access, account information, and logout. The drawer must preserve the
same navigation grouping used on desktop.

### Search

Desktop provides a visible global-search entry point in the top bar. Mobile
uses a search icon that opens a focused search surface. Initial implementation
may search only entities supported by existing application queries, but the
control must not be visually presented as functional until its behavior is
implemented.

## Admin Shell

`resources/views/components/layouts/app.blade.php` owns:

- Responsive sidebar and mobile navigation.
- Active-route treatment.
- Page content region and maximum width.
- Account and portal controls.
- Skip link and landmark structure.
- Mobile drawer state through Alpine.js.

The shell uses:

- Workspace: `{colors.canvas-soft}` (`#f6f9fc`)
- Navigation and panels: `{colors.canvas}` (`#ffffff`)
- Primary text: `{colors.ink}` (`#0d253d`)
- Secondary text: `{colors.ink-mute}` (`#64748d`)
- Borders: `{colors.hairline}` (`#e3e8ee`)
- Primary action and selection: `{colors.primary}` (`#533afd`)

Remove the current dense horizontal chip navigation. Do not use a dark
application background or dark promotional hero in the admin shell.

## Dashboard

### First Viewport

The dashboard first viewport contains:

1. Greeting, current date, and concise operational context.
2. Four summary metrics:
   - Active households
   - Active residents
   - Current-month cash total
   - Items requiring action
3. Action queue.
4. Thirty-day cash summary or trend.

The action queue has higher visual priority than analytics.

### Action Queue

Aggregate actionable data already represented by the domain:

- Households with unpaid contributions for the selected operational date.
- Open or unresolved resident reports.
- Pending letter requests.
- Scheduled residents missing ronda check-in.
- Other existing actionable statuses that can be queried without inventing a
  new workflow.

Each item shows a label, count, urgency treatment, and direct link to the
relevant module. Where supported, the destination opens with the corresponding
filter active.

Urgency colors are semantic and restrained:

- Ruby/red: overdue or high priority.
- Amber: attention required.
- Indigo: informational or selected.
- Green: completed or healthy.

### Analytics

Analytics support decisions instead of filling space. The dashboard may show:

- Current-month cash total and collection progress.
- Thirty-day cash movement.
- Household and resident changes when historical data supports the claim.

Do not show invented comparisons, percentages, or trends. If historical data
is unavailable, use current totals or omit the comparison.

### Recent Activity

Show a compact audit-log table when current authorization and data permit it.
Columns are activity, actor, and time. Mobile may render this as stacked rows.

## Shared Page Patterns

### Collection Page

Order:

1. Page header with title, description, and primary action.
2. Filter/search toolbar.
3. Responsive data table.
4. Empty state or pagination.

Use for transaction lists, scan sessions, reports, letters, and similar
record collections.

### Management Page

Order:

1. Page header.
2. Compact create/edit form or explicit form trigger.
3. Responsive collection.
4. Edit, status, or confirmation surface.

Use for households, residents, announcements, voting, inventory, and schedule
management.

### Detail Page

Order:

1. Back navigation and summary header.
2. Status and primary actions.
3. Key metrics.
4. Related records and activity.
5. Destructive actions behind confirmation.

Use for ronda schedule details, vote details, household QR views, and other
focused records.

## Shared Components

Create reusable Blade components or established local equivalents for:

- Admin page header.
- Primary and secondary buttons.
- Summary metric.
- Panel.
- Action queue and queue row.
- Responsive data table primitives.
- Status badge.
- Form field wrapper, label, help text, and validation message.
- Filter toolbar.
- Modal or confirmation dialog.
- Empty state.
- Pagination container.
- Mobile navigation drawer.
- Loading and disabled states.

Components must expose clear slots and variants. Avoid a single large generic
component with many unrelated conditionals.

## Table Rules

All administrative tables use the approved light treatment:

- Header background: `#f6f9fc`.
- Header labels: `#64748d`.
- Body text: `#0d253d`.
- Dividers and outer borders: `#e3e8ee`.
- Hover background: a subtle cool tint such as `#fafbff`.
- Numeric and monetary cells use tabular figures.
- Header labels may use compact uppercase styling with restrained tracking.

Dark, black, brown, brown-black, or deep-navy filled table headers are
explicitly prohibited. Deep navy is allowed for text and selected navigation,
not table-header fills.

Tables must use semantic table markup on desktop. On mobile:

- Keep a table with horizontal scrolling when column relationships are
  essential and the result remains readable.
- Convert rows to cards when actions and label/value pairs are clearer in a
  stacked format.
- Preserve all fields and actions required to complete the workflow.

## Forms And Actions

- Minimum mobile control height is 44px.
- Labels remain visible; placeholders do not replace labels.
- Validation messages appear next to the affected field.
- A concise form-level message may summarize multiple errors.
- Primary actions use the indigo pill button.
- Secondary actions use a white or transparent outlined treatment.
- Destructive actions use ruby/red and require confirmation when the operation
  cannot be easily reversed.
- Livewire loading disables duplicate submission and communicates progress.
- Successful mutations provide consistent visible feedback.

## Feedback, Empty, And Error States

- Empty states explain what is missing and provide the relevant next action.
- Loading states use reserved space, disabled controls, or subtle skeletons to
  avoid layout shift.
- Errors use plain Indonesian and explain recovery where possible.
- Success feedback should be concise and should not block continued work.
- Modal dialogs use focus trapping, an accessible name, Escape dismissal when
  safe, and focus restoration.

## Responsive Behavior

### Wide Desktop: 1280px And Above

- Full labeled sidebar.
- Dashboard queue and analytics use a two-column composition.
- Tables display all priority columns.

### Compact Desktop And Tablet: 768px To 1279px

- Sidebar may collapse or become an off-canvas drawer.
- Dashboard sections stack when needed.
- Toolbars wrap without hiding primary actions.

### Mobile: Below 768px

- Fixed five-item bottom navigation.
- `Lainnya` drawer exposes all modules.
- Summary metrics use a two-column grid, then one column where necessary.
- Action queue appears before analytics.
- Forms use full-width controls and actions.
- Tables switch to an explicitly selected mobile strategy.
- Content includes enough bottom padding to remain clear of fixed navigation.

## Accessibility

- Meet WCAG 2.2 AA color contrast for text and interactive states.
- Provide visible keyboard focus using the primary indigo.
- Include a skip link and semantic header, navigation, main, section, table,
  and dialog landmarks.
- Use `aria-current="page"` for active navigation.
- Icon-only buttons require accessible names.
- Color is never the sole indicator of urgency, status, or selection.
- Touch targets are at least 44 by 44 CSS pixels on mobile.
- Respect `prefers-reduced-motion`.
- Maintain logical heading order and DOM order across responsive layouts.

## Implementation Boundaries

- Preserve Laravel 12, Livewire 4, Volt, Tailwind CSS 4, and Alpine.js.
- Preserve existing routes, authorization, audit behavior, validation, and
  business services.
- Prefer presentation-only changes in view files.
- Add dashboard queries or focused support classes only when required for the
  approved action queue and analytics.
- Do not introduce a React/Vue SPA or charting dependency unless a later
  approved plan demonstrates a concrete need.
- Do not refactor unrelated portal pages as part of the admin redesign.
- Preserve existing uncommitted user work and integrate with it.

## Delivery Phases

### Phase 1: Foundation And Dashboard

- Shared tokens and admin components.
- Responsive admin shell.
- Dashboard action queue, metrics, analytics, and recent activity.

### Phase 2: Core Operations

- Rumah / KK.
- Warga.
- Kas and transactions.
- Ronda schedule and detail.
- Sesi Scan and Denda.

### Phase 3: Services And Assets

- Pengumuman.
- Laporan.
- Surat.
- Voting list and detail.
- Inventaris.
- Household QR and remaining admin-adjacent states.

Each phase must leave the included routes visually complete and usable on
desktop and mobile.

## Verification And Acceptance

### Automated

- Existing Pest feature tests continue to pass.
- Add focused rendering or behavior tests for new dashboard queries, filters,
  and shared interactions.
- Run `ddev artisan test`.
- Run `ddev npm run build`.

### Playwright

Verify the real DDEV application at desktop and mobile viewports:

- Login and dashboard page identity.
- No blank page or framework error overlay.
- No relevant console errors.
- Sidebar active states and module navigation.
- Mobile bottom navigation and `Lainnya` drawer.
- Action queue links to the correct module/filter.
- One collection, management, and detail workflow.
- Form validation and successful submission feedback.
- Confirmation dialog keyboard behavior.
- Table readability and the approved light header treatment.
- No clipping, hidden actions, overlap, horizontal page overflow, or fixed-nav
  obstruction.

Capture screenshots for:

- Dashboard desktop.
- Dashboard mobile.
- A representative data table desktop and mobile.
- A representative form or management workflow.
- A confirmation or status-change state.

### Visual Acceptance

The admin passes visual review when:

- All included routes use the same shell and component language.
- The action queue is more prominent than analytics.
- The first viewport contains operational information rather than a marketing
  hero.
- No dark or brown table headers remain.
- Typography, spacing, color, borders, radii, and numeric formatting follow
  `DESIGN.md`.
- Mobile users can complete the same core tasks as desktop users.

## Research Basis

The design incorporates current patterns from:

- Carbon Design System guidance for UI shells, data tables, and empty states.
- Material Design guidance for adaptive breakpoints, cards, text fields, and
  tabs.
- Atlassian Design System guidance for accessible forms, dialogs, messages,
  and empty states.

These references inform interaction and accessibility patterns. `DESIGN.md`
continues to control Smart RT's visual identity.
