# Spec: Stripe-inspired Light Canvas Dashboard Redesign

This specification defines the design system, colors, typography, layout, and page-by-page implementation details for modernizing the Smart RT Admin Dashboard into a premium, cohesive Light Canvas theme inspired by Stripe's desktop product interfaces.

---

## 1. Design Direction Summary

*   **Aesthetic Name**: **Stripe Light Canvas**
*   **Design Stance**: High-fidelity, clean corporate white canvas/cool gray-tint canvas dashboard theme with Indigo CTA buttons, thin border lines, and tabular figure numeric alignments.
*   **Key Inspiration**: Stripe Dashboard (dashboard.stripe.com) and premium modern enterprise SaaS tools.
*   **Target Breakpoint Behavior**: Fully responsive, with a bottom floating navigation bar on screens `<768px` and touch target heights scaling to >=44px.

---

## 2. Color System & Typography Tokens

### A. Color Palette (Tailwind v4 theme variables)
*   **Canvas Background** (`bg-[#f6f9fc]`): Default background for the dashboard views.
*   **Card Background** (`bg-white`): White card surfaces where details, indexes, and forms sit.
*   **Primary CTA / Link** (`bg-[#533afd]` / `text-[#533afd]`): Used for pill buttons, text links, active status, and focus indicators.
*   **Primary Press State** (`bg-[#2e2b8c]`): Press-down warmer color for primary buttons.
*   **Secondary Text / Inactive Nav** (`text-[#64748d]`): Slate gray helper text, labels, and inactive navbar pills.
*   **Body Text / Dark Ink** (`text-[#0d253d]`): Primary ink color for all text, headings, and tables.
*   **Hairline Borders** (`border-[#e3e8ee]`): 1px borders on tables, cards, and dividers.
*   **Input Border** (`border-[#a8c3de]`): Cooler hairline color for input borders.
*   **Input Focus Ring**: Focus states swap to `border-[#533afd]` with `ring-1 ring-[#533afd]`.

### B. Typography (Inter font fallback)
*   **Display Font**: **Inter** (weight 300) display headers with negative display tracking (`letter-spacing: -0.64px` to `-1.4px`) and global `font-feature-settings: "ss01"` (single-story `a` substitution).
*   **Body Font**: **Inter** (weight 300 / 400).
*   **Tabular Numbers** (`.tnum`): Uses `font-feature-settings: "ss01", "tnum" !important` and `font-variant-numeric: tabular-nums` for all table cell amounts, currency values, phone numbers, and dates.

---

## 3. Global Layout changes (`components.layouts.app`)

1.  **Body Tag**: Change background from `#0d253d` (dark) to `#f6f9fc` (light canvas) and default text to `#0d253d`.
2.  **Sticky Header**: Change background from `#1c1e54` dark to `#ffffff/80 backdrop-blur-xl border-b border-[#e3e8ee]`.
3.  **Logo Wordmark**: Update text color to `#0d253d`. The `RT` badge remains a solid Indigo circle with white text.
4.  **Desktop Navbar Links**: Use a light gray background pill wrapper (`bg-slate-100 border border-slate-200/50`) instead of white/5. Inactive links will use `text-slate-600 hover:bg-slate-200 hover:text-[#0d253d]`. Active link uses `bg-[#533afd] text-white shadow-level1`.
5.  **Mobile Floating Navbar**: Positioned at the bottom using `bg-white/95 border-t border-[#e3e8ee] backdrop-blur-md text-[#0d253d]`. Active nav uses `bg-[#533afd] text-white`.
6.  **Ambient Glows**: Remove heavy dark neon backdrops. Optional soft ambient wash in the background of main layout container `bg-[#533afd]/2` to add depth.

---

## 4. Page-by-Page Modernization Details

Every admin dashboard view will be restructured into a combination of a clean header card, light card forms, and light data tables:

### A. Dashboard Home (`dashboard/index.blade.php`)
*   **Welcome Card**: White card with a very soft Indigo border and a live system connection pulse dot in Indigo.
*   **Stat Cards**: Flat white panels with Level 1 shadows. Numbers styled in large Inter 300, using `tnum` (e.g. currency balances and resident counts).

### B. House Management (`households/index.blade.php`)
*   **Create Form Section**: White card layout, inputs in white with hairline borders, Indigo primary button.
*   **Table Section**: White card wrapper, table headers in light gray `bg-[#f6f9fc]` with uppercase labels, hoverable rows with bottom border `border-b border-[#e3e8ee]`.

### C. Resident Management (`residents/index.blade.php`)
*   Same card and input alignments as Household management. Phone numbers and household numbers styled with `tnum`.

### D. Kas Rekap (`dashboard/kas/index.blade.php`)
*   **Daily, Weekly, Monthly Stats**: Large numbers styled in `tnum` showing Rp values.
*   **Lists**: Active lists of unpaid/missing items rendered as clean, bordered blocks with light backgrounds.

### E. Kas Transactions (`dashboard/kas/transactions.blade.php`)
*   **Transactions Table**: Convert from dark theme to a white card table with `bg-[#f6f9fc]` headers.
*   **Numbers**: Tabular digits (`tnum`) for transaction values, dates, and IDs.
*   **Status Badges**: Soft background pill badges (e.g., green text on light green for successful, gray text on light gray for cancelled).

### F. Inventory Management (`dashboard/inventory/index.blade.php`)
*   **Asset Grid**: Grid cards styled as white panels with Level 1 shadows, thin borders, and clean status indicators.

### G. Fines / Review Denda (`dashboard/denda/index.blade.php`)
*   **Candidates Table**: Modern white table structure with light headers. Red action buttons styled as outlined pill buttons (`border border-[#ea2261] text-[#ea2261] rounded-full hover:bg-red-50`).
*   **Confirm Modal**: Styled as a clean floating white card (`bg-white border border-[#e3e8ee] shadow-level2`) with soft backdrop dimming.

### H. Announcements (`dashboard/announcements/index.blade.php`)
*   **Draft Form**: White container with clean inputs and labels.
*   **Announcements List**: White cards with category tags and simple, hover-accented buttons.

### I. Letters (`dashboard/letters/index.blade.php`)
*   **Requests List**: Tabular outline of requests, showing requester name, phone (`tnum`), date, and document types.
*   **Workflow Action**: Styled as clean pill action dropdowns.

### J. Reports (`dashboard/reports/index.blade.php`)
*   Similar table layout to Letters, using status pill colors for progress tracks.

### K. Ronda Schedule & Detail (`dashboard/ronda/index.blade.php` & `show.blade.php`)
*   **Calendar / Schedule List**: Clean tabular dates and lists of assignments.
*   **Detail View**: Stat metrics showing attendance percentage, check-in times in WIB (`tnum`), and list of check-in times.

### L. Votes List & Detail (`dashboard/votes/index.blade.php` & `show.blade.php`)
*   **Active/Draft Polls**: White cards showing question, start/end dates (`tnum`), and action links.
*   **Poll Details**: Clean results bar filling with animated Indigo gradients.

---

## 5. Verification & Testing

*   **Pest Test Suite**: Ensure `ddev artisan test` executes with 100% passes.
*   **Visual Review**: Use Playwright to take screenshots of the key pages to verify light theme compliance, spacing, and mobile input readability.
*   **Linting**: Run build commands to verify there are no CSS syntax errors.
