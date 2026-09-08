# Development Rules

These rules apply to every project built from this template. They're
written to be copied verbatim — don't remove sections just because a
project hasn't touched that area yet; add to them as the project grows.

## Documentation

Every project document lives under `doc/` or a subfolder. Nothing outside.
Keep every document concise. Run prose through the `ste-writing` skill.

Folder layout and responsibility:

| Path | Holds |
| --- | --- |
| `doc/` | This repo: `concept.md` (the why), `roadmap.md` (the what, in order), `extensions.md` (the extension mechanism). |
| `doc/domain/` | The wider multi-app system this repo feeds. Not repo-specific. |
| `doc/reference/` | External source material. Not maintained here. |
| `doc/features/<name>/` | One folder per feature: `spec.md`, then `plan.md`. |

Every feature follows this order:

1. Write `doc/features/<name>/spec.md` before any code. It states the
   problem, the solution, the key decisions, and the non-goals.
2. Write `doc/features/<name>/plan.md`: the implementation steps as a
   checkbox list, with a one-line status header (for example
   `Status: in progress — 3/8`).
3. Keep `plan.md` progress current during the build. Check off each step
   as it lands.

The `implement-feature` skill runs this flow.

## Verification

Do not execute UI/browser verification of changes yourself (no dev-login
routes, no headless-browser driving, no starting dev servers to click
through flows). The user verifies UI behavior themselves. Static checks
(PHP lint, `vite build`, migrations against a copied DB, the test suite)
are fine.

## Design Rules

### Styling

Always use Tailwind CSS utility classes for styling. Do not write custom
CSS unless there is no Tailwind equivalent. Never use inline `style`
attributes.

**Exception — the theme-builder page's live preview:**
`resources/js/pages/ThemeBuilder.vue` injects CSS custom property values
dynamically at runtime to power its preview panel, which cannot be
achieved with Tailwind classes alone. This is the only sanctioned
exception.

### Colors — token-driven theming

Never hardcode colors or use raw Tailwind color classes (`text-zinc-500`,
`bg-indigo-600`) in components. Every color is a CSS custom property,
`var(--color-*)`, resolved through a two-layer token system defined in
`app/Services/ThemeTokens.php`:

- A small set of **roles** (`surface`, `border`, `text-primary`, `brand`,
  `danger`, etc.) are what a person actually edits on the theme-builder
  page — plus a few **badge families** (a Tailwind color family name like
  `green`/`red`, expanded into a full bg/text/border set via a fixed shade
  formula).
- The ~100 **component-level** `--color-*` vars used throughout the app
  (`--color-input-bg`, `--color-btn-primary-bg`, `--color-table-row-hover-bg`,
  etc.) are never independently stored — each is an alias to a role,
  declared in `ThemeTokens::COMPONENT_ALIASES` and mirrored in
  `resources/js/utils/colorToken.js` (no shared source of truth between the
  two — update both by hand).

**When you need a new color somewhere:**
1. Check whether an existing component var already fits (most UI states
   already have one — chrome, navigation, surfaces, typography, buttons,
   inputs, dropdown, tabs, table, badges).
2. If not, **discuss it with the user first** before adding a new
   component var or role. A new component var aliases to an *existing*
   role wherever the concept matches (most cases); a genuinely new color
   concept becomes a new role, which needs a picker added to the relevant
   section in `ThemeTokens::sections()` so it's actually configurable.

### Input components

No raw `<input>`, `<textarea>`, or `<select>` in page/feature components. A
typed component set lives under `resources/js/components/ui/Input/` with a
barrel `index.js`:

```js
import {
    TextInput,
    EmailInput,
    PasswordInput,
    CheckboxInput,
    NumberInput,
    MultilineInput,
    SelectInput,
    DateInput,
    MonthInput,
    TimeInput,
    PhoneInput,
    SearchInput,
    FormattedInput,
    InputShell,
    FileInput,
    PhotoInput,
} from "@/components/ui/Input";
```

Use the specific typed component matching the field. `InputShell` is the
underlying styled wrapper every typed input composes — don't use it
directly for a form field. `LabeledInput.vue` wraps an `Input/*` component
with a label + error message; use it for any labeled form field.

If a new input type is needed that no existing component covers,
**discuss it with the user first** before creating one.

### Buttons and cards

Use `ButtonPrimary`/`ButtonSecondary` (`resources/js/components/ui/`) for
buttons — no raw `<button>` with hand-rolled color classes. Use `Card` (with
its `header`/`footer` slots) for any card-shaped container, and
`CardSeparator` for dividing sections within one. If a case genuinely needs
something these don't cover (e.g. a danger-variant button — none exists in
this template yet), **discuss it with the user first** rather than
hand-rolling styled markup that drifts from the token system.

### Icons

Heroicons only, via a single `Icon.vue` wrapper component that maps short
kebab-case names to imported Heroicon components:

```vue
<Icon name="chevron-right" class="size-4" />
```

Extend the internal `iconMap` when a new icon is needed rather than
importing Heroicons directly elsewhere.

### Layouts

`resources/js/layouts/Layout.vue` is the internal chrome shell (logo/topbar/
sidebar/content slots) — pages never use it directly. Two named layouts
wrap it:

- **`AppLayout.vue`** — the standard layout (sidebar, nav, breadcrumb, dark
  mode toggle). Use this for any authenticated/internal-app page.
- **`CenteredLayout.vue`** — no sidebar, a centered card on the page. Use
  this for login/register-shaped pages, standalone forms, or any
  single-purpose page that isn't part of the main app chrome.

If a page's shape doesn't fit either, **discuss it with the user first**
before adding a new layout — most new pages fit one of these two.

### Multi-language (i18n)

All user-facing text lives in language JSON, not hardcoded in components.
English-only initially, architected for more:

- `resources/lang/en.json` — flat dot-notation keys namespaced by feature
  (`nav.*`, `theme.*`, etc.).
- Backend: `HandleInertiaRequests` middleware shares `locale` and
  `translations` (the parsed JSON for the current locale) as Inertia props
  — no client-side i18n library needed.
- Frontend: a `useI18n()` composable returns a `__()` function:

    ```js
    import { useI18n } from "@/composables/useI18n";
    const __ = useI18n();
    __("nav.theme_builder");
    __("some.key", { name: "John" }); // :name placeholder replacement
    ```

Only implement the locale(s) actually needed — the JSON-file-per-locale
structure already supports adding more later without any code change.

## Extensions

This project may have one or more extensions from the template applied
(see the template repo's `doc/extensions.md` if you're building or
retrofitting one). An applied extension's own instructions, if any, take
precedence over generic guidance here for the area it touches.
