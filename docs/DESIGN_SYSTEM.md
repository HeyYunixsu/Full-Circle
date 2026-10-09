# Full_Event Design System

Source of truth for how screens in the Full Circle Events Asia, Inc. check-in system look and behave.
Implementation lives in `assets/css/style.css`. If this doc and the CSS disagree, fix one of them.

Reference screen: `pages/auth/login.php` (dark wine brand panel, dotted grid, "full circle" rings,
cream admission ticket with live QR). Every other screen should feel like it belongs next to it.

Method: the ui-ux-pro-max design-system search was run for "event check-in admin dashboard" (it matched
the Real-Time/Operations pattern, Glassmorphism style, a slate/green palette and Fira fonts). The palette
and style were rejected because the wine/magenta direction is already approved; the token structure,
UX guideline checks (contrast 4.5:1, touch targets, visible labels, inline errors, no colour-only
status, scrolling tables on mobile, reduced motion) and the pre-delivery checklist were kept.

## 1. Principles

1. **One palette.** Wine family for structure, magenta/pink for emphasis, neutrals for everything else.
   Never reintroduce purple (`#7367F0`) or ad-hoc hex values in pages.
2. **Flat and quiet.** White surfaces, 1px borders, no gradients or drop shadows on cards. The only
   gradients allowed are the wine `.hero`, the event topbar and the login brand panel.
3. **Hover changes colour, not position.** Buttons go wine to magenta. No lift, no glow, no scale.
4. **Status needs text or an icon**, never colour alone (a checked-in pill says "Checked in").
5. **Mobile is a first-class target** (390px). Sidebar collapses to icons; tables scroll inside their
   panel; toolbars wrap; modals stack their actions.

## 2. Tokens (three layers)

### Primitive (raw values, rarely change)

| Token | Value | Notes |
|---|---|---|
| `--wine-darkest` | `#2e0f26` | sidebar, strong headings |
| `--wine-dark` | `#431a38` | event topbar and tabs |
| `--wine-mid` | `#5c2249` | primary buttons, links |
| `--wine-light` | `#7a2f5f` | hero gradient end |
| `--magenta` | `#c23b8e` | hover, meters, focus ring |
| `--pink-bright` | `#e8579f` | sidebar active bar, brand ring |
| `--pink-soft` | `#f7d9ec` | soft tags, selected states |
| `--shell-bg` | `#f4f2f5` | app background |
| `--white` / `--off-white` | `#ffffff` / `#faf7f9` | surfaces / hover rows |
| `--gray-light` / `--gray-faint` | `#e9e3ea` / `#f1ecf2` | borders / table row lines |
| `--gray-mid` | `#9b8fa0` | icons and placeholders only (3.1:1, fails text contrast) |
| `--gray-dark` | `#4a3d52` | muted text (9:1) |
| `--black` | `#221a24` | body text |
| `--success` / `--warning` / `--danger` / `--info` | `#2fb872` / `#f5a524` / `#e5484d` / `#3b82f6` | fills |

The old `--purple-*` names still exist but are aliases of the wine values. Do not use them in new code.

### Semantic (what components reference)

| Token | Maps to | Use for |
|---|---|---|
| `--color-primary` / `-hover` / `-soft` | wine-mid / magenta / pink-soft | buttons, links, selected |
| `--color-accent` | pink-bright | active indicators |
| `--color-bg` / `--color-surface` / `--color-surface-alt` | shell-bg / white / off-white | page / cards / hover rows |
| `--color-border` / `--color-border-faint` | gray-light / gray-faint | card borders / row lines |
| `--color-text` / `-strong` / `-muted` / `-faint` | black / wine-darkest / gray-dark / gray-mid | body / headings / labels / icons |
| `--success-text` + `--success-bg` | `#1b7a4b` on 14% green | pills, alerts |
| `--warning-text` + `--warning-bg` | `#9a5a00` on 16% amber | pills, alerts |
| `--danger-text` + `--danger-bg` | `#b91c1c` on 12% red | destructive buttons, errors |
| `--info-text` + `--info-bg` | `#1d4ed8` on 12% blue | notices |
| `--focus-ring` | 3px magenta at 18% | inputs on focus |

Spacing `--space-1..10` (4px base: 4, 8, 12, 16, 20, 24, 32, 40).
Type `--text-xs 11`, `sm 13`, `base 14`, `md 16`, `lg 20`, `xl 26`, `2xl 34`.
Radii `--radius-sm 8`, `md 12`, `lg 18`, `full`. Controls `--control-h 40`, `--control-h-sm 34`.
Motion `--dur-fast 150ms`, `--dur-base 200ms`, `--ease cubic-bezier(.2,.7,.3,1)`.

### Component (defined on the component, not globally)

Only when a component needs its own knob (for example the badge canvas size). Otherwise use semantic tokens.

## 3. Typography

Two families, both offline-safe. `--font-display` is Poppins (500/600/700, self-hosted in `assets/fonts`) for headings, page titles, big numbers, navigation, buttons and tabs. `--font-body` is the system stack (`Segoe UI`, `-apple-system`, sans-serif) for body copy, tables and form inputs, because Poppins is too wide for dense data. Long single-line labels in tight spaces (the sidebar brand name) stay on the body font.

| Role | Size / weight | Colour | Extra |
|---|---|---|---|
| Page title `.page-title` | 26 / 700 | text-strong | letter-spacing -0.6px |
| Section `.page-intro h2`, `.hero h2` | 20 / 700 | text-strong (white in hero) | -0.3px |
| Panel title `.panel > h3` | 16 / 700 | text-strong | -0.2px |
| Body | 14 / 400 | text | line-height 1.6 |
| Meta and help | 13 or 12 / 400 | text-muted | never gray-mid |
| Table header, KPI label, stat label | 11 / 600 uppercase | text-muted | letter-spacing .5 to .6px |
| Big numbers `.kpi b`, `.stat-value` | 28 / 34 / 700 | text-strong (or accent) | tabular-nums |
| Codes (attendee code, passkey) | 12 monospace in `<code>` | text | off-white chip |

## 4. Components

All classes are global in `style.css`. Pages add only layout-specific CSS in their `<style>` block.

| Component | Class | States and variants |
|---|---|---|
| Primary button | `.btn.btn-primary` (pill, 40px) | hover magenta; `:disabled` 55% |
| Secondary | `.btn.btn-secondary` | white with border; hover magenta border and text |
| Destructive | `.btn.btn-danger` | soft red; hover solid red |
| Small button | `.btn-sm` (34px pill) | `.light` `.ghost` `.danger` `.success` `.lg` `.block` |
| Icon button | `.icon-btn` (34px) | `.danger`; must carry `title` and `aria-label` |
| Top bar | `.page-header` (sticky, 64px, white, 1px bottom border; same height as the sidebar logo row) | title left, account menu right |
| Account menu | `<details class="user-menu">` + `.user-info` summary + `.user-dd` | name, email, Manage accounts (if allowed), Sign out; closes on outside click and Esc |
| Toolbar | `.toolbar` + `.spacer` | holds the event `<select>` picker and page actions |
| Segmented tabs | `.seg-tabs > .seg-tab.active` | filter switches |
| Panel | `.panel` + `h3` | `.panel-grid` for 2-up; `.panel` scrolls wide tables |
| KPI tile | `.kpi-grid > .kpi > b + small` | `.accent` `.ok` `.warn` |
| Stat card | `.stats-grid > .stat-card` | `.accent` `.ok` `.warn`, optional `.stat-bar` |
| Hero | `.hero` | wine gradient banner for session or scan context |
| Table | `.tbl` inside `.panel` or `.table-wrap` | `th.num/td.num`, `.actions` cell, wine links |
| Meter | `meter($value, $max, $label)` renders `.meter` | magenta fill on gray track |
| List row | `.list-row` + `.avatar` + `.progress` | recent check-ins, company breakdown |
| Pills | `.pill-brand .pill-ok .pill-warn .pill-bad .pill-info .pill-muted`, `.role-tag` | light surfaces; dark `.pill-*` kept for the event body |
| Alerts | `.alert.alert-success/-error/-warning/-info` | icon + escaped text |
| Form | `.form-group > label + .form-input`, `.form-grid`, `.form-help`, `.form-error`, `.check`, `.form-actions` | focus = magenta border + ring |
| Inline form | `.inline-form` | row-level actions in tables |
| Empty state | `.empty-state` (icon, `strong`, `p`, optional button) or `.empty-note` | dashed border |
| Modal | `.modal-overlay.show > .modal` + `.modal-actions` | Esc and backdrop click close |
| Toast | `.toast.show` `.toast-g/.toast-r` | bottom-right, auto-hide |
| Confirm dialog | `data-confirm` attributes or `appConfirm()` (assets/js/dialog.js) | danger variant (red icon + solid red OK, Cancel focused first); Esc and backdrop cancel; never use native `confirm()` |
| Dropdown | any single `<select>` | styled panel via `appearance: base-select` (Chrome/Edge 135+), chevron icon, pink hover/selected, magenta check; other browsers fall back to native |
| Capacity counter | `.cap-row` + `.cap-top` + `.cap-count` + `.progress(.is-full)`, `capacityBadge()` | Full / Over by N (red pill), Almost full (amber pill) |
| Steps and drop zone | `.steps > .step.active/.done`, `.drop-zone` | import wizard |
| Spinner | `.spinner` | async progress |
| Public shell | `body.auth-body > .auth-card` + `.auth-mark` | register, forgot, feedback, QR |

Interaction states for every interactive element:

| State | Treatment |
|---|---|
| hover | colour shift only, 150ms |
| focus-visible | 2px magenta outline (global); inputs add `--focus-ring` |
| active | nothing beyond hover (no scale) |
| disabled | opacity .55, `cursor: not-allowed` |
| loading | `.spinner` + disabled button, text "Sending..." |

### Forms

- Required fields have no marker; optional fields end their label with `<span class="optional">(optional)</span>`. No asterisks.
- Labels in sentence case and linked to their input (`for` / `id`). Hints go in `.form-help` below the input, tied with `aria-describedby`.
- Long forms: a `.form-head` (kicker, title, one line on what happens next) and `.form-section` groups with a `.form-section-title`.
- After a failed submit, every field keeps what was typed.
- `.form-actions`: Cancel then the primary action, right-aligned and sized to their text (full width only on phones).

## 5. Page templates

**Admin page** (every screen behind login)

```
require bootstrap -> requireRole -> handle POST -> queries
<body class="dashboard-body"><div class="dashboard-container">
  sidebar.php
  <main class="main-content">
    header.php            (page title + user chip)
    flash alert           (.alert)
    .toolbar              (event picker + actions)  OR  .page-intro / .back-link
    content               (.kpi-grid / .panel / .card-grid / .narrow form)
  </main>
</div>
```

**Dashboard** (`pages/dashboard/index.php`): `.dash` two-column grid (main + 300px rail), `.tiles` stat tiles with `.tile-badge` colour variants, `.covers` cover cards, `.up-grid` date-pill cards with `.stack` avatars, rail panels for activity, sparkline and calendar. Page-local CSS; reuse the patterns before inventing new ones.

**Focused task** (walk-in, scan, badge preview, import): set `$back_url` and `$back_label` before including header.php (the round back arrow sits before the title in the sticky top bar), then `.narrow > .panel`. Do not add in-page `.back-link` rows.

**Public page** (QR ticket, feedback form, register, forgot): `body.auth-body > .auth-card`,
`.auth-mark` brand row on top, no sidebar.

## 6. Responsive rules

**Phone CSS lives in `assets/css/mobile.css`** (loaded after each page's styles with `media="(max-width: 768px)"`), so desktop never reads it. Add phone fixes there, not in style.css or page `<style>` blocks. Page-only rules are scoped by the page's body class, e.g. `.page-attendees .fb-search { … }`; add a `page-<name>` class to that page's `<body>` if it has none.


- Breakpoint 768px: sidebar becomes a 70px icon rail, `.main-content` padding 18/14, toolbar form full
  width, KPI grid 2-up, modal actions stacked, form actions stacked, steps stacked.
- Tables never shrink below readable: `.panel` and `.table-wrap` scroll horizontally.
- Touch targets at least 34px tall with 8px gaps; primary actions 40 to 44px.
- Verify every page at 1366px and 390px.

## 7. Accessibility checklist (run before calling a page done)

- [ ] Text contrast at least 4.5:1 (no `--gray-mid` text).
- [ ] Every input has a `<label for>` or `aria-label`.
- [ ] Errors: alert at top **and** text near the field when the field is known.
- [ ] Icon-only buttons have `title` and `aria-label`; decorative icons are `aria-hidden`.
- [ ] Focus visible on all controls; modals close on Esc.
- [ ] Status shown with text, not colour alone.
- [ ] No emoji as icons; use `icon('name')` from `includes/icons.php`.
- [ ] `prefers-reduced-motion` honoured (global rule in CSS).
- [ ] Page has `<meta name="viewport">`.

## 8. Do and Don't

| Do | Don't |
|---|---|
| `background: var(--color-primary)` | `background: linear-gradient(135deg,#7a2f5f,#5c2249)` |
| `.btn-sm.light` for secondary actions | new one-off button classes per page |
| `.panel > h3` with the event name | `<h2 style="color:var(--purple-mid)">` |
| `.empty-state` with an icon and a next step | a bare "No data." paragraph |
| `.pill-ok` "Checked in" | a green dot only |
| Keep page `<style>` to layout (grid columns, widths) | Re-declare colours, radii, shadows per page |
