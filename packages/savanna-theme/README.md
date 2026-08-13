# Savanna — a Filament theme

A warm, quiet theme for Filament panels. Neutral ground, hairline-bordered
cards, one accent used only for navigation and intent, and small heavily
tracked uppercase labels above large tight numbers.

Requires PHP 8.3+ and Filament 4 or 5. No runtime dependencies, no build step
of its own, no JavaScript.

## Install

```bash
composer require gil/filament-savanna-theme
```

Register the plugin on the panel:

```php
use Savanna\Theme\SavannaThemePlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugins([
        SavannaThemePlugin::make(),
    ]);
}
```

Then import the stylesheet from the panel's theme file, **before** any of your
own rules — see *Living with an existing design* below for why the order
matters:

```css
/* resources/css/filament/admin/theme.css */
@import '../../../../vendor/filament/filament/resources/css/theme.css';
@import '../../../../vendor/gil/filament-savanna-theme/resources/css/savanna.css';
```

```bash
npm run build
```

The CSS is imported rather than shipped prebuilt on purpose: a Filament theme
has to be compiled through your application's own Tailwind build so it can see
the utility classes you use. Injecting a compiled stylesheet would put a second
copy of Tailwind on the page.

## Configuration

```php
SavannaThemePlugin::make()
    ->accent('#2f6f4e')       // any hex; Filament generates the ramp
    ->sidebarWidth('16rem');
```

The plugin only sets what CSS cannot reach — the primary colour ramp Filament
generates at runtime, and the sidebar width the layout is drawn against.
Everything else is in the stylesheet.

## Tokens

Override any of these in your own CSS, after the import:

| Token | Light | Role |
| --- | --- | --- |
| `--sv-ground` | `#faf8f6` | Page background |
| `--sv-surface` | `#ffffff` | Cards, sidebar, tables |
| `--sv-surface-sub` | `#f4f1ee` | Hover fills, pills |
| `--sv-hairline` | `#e7e2dc` | Card and table borders |
| `--sv-hairline-soft` | `#f0ece7` | Row separators |
| `--sv-ink` | `#1c1b19` | Primary text |
| `--sv-ink-soft` | `#4a4641` | Secondary text |
| `--sv-muted` | `#857e75` | Labels, meta |
| `--sv-accent` | `#e2571f` | Navigation, focus, intent |
| `--sv-accent-soft` | `#fdefe7` | Active navigation fill |
| `--sv-accent-ink` | `#b8410f` | Text on the soft accent |
| `--sv-good` / `--sv-warn` / `--sv-bad` | — | Semantic only |

The neutrals are warm: every grey carries a little of the accent's hue, which
is what stops the panel reading as default slate.

Semantic colour is deliberately separate from the accent. Orange never means
"good" here — it means "this is the thing you act on".

## Dark mode

Dark tokens are defined under `.dark` and are inert until the panel allows dark
mode. If your panel calls `->darkMode(false)`, nothing changes.

## Living with an existing design

The theme styles panel **chrome** only: sidebar, topbar, page shell, headings,
cards, stats, tables, inputs and buttons. Every selector is a single class, so
any screen with its own visual language can override it by ordinary
specificity — no `!important` anywhere.

That is why the import goes first. In the application this was built for, the
A/R Invoice document is drawn to a supplied SAP Business One screenshot; those
rules are scoped to `.sap-page`, load after this file, and win on both order
and specificity. The document is untouched.

If your host panel does the same, two things are worth knowing:

- Filament emits its primary ramp as `--primary-50` … `--primary-950`. To hold
  a screen to a different colour, redefine those variables on that screen's
  scope rather than fighting individual controls.
- Nothing in this file targets a scope it does not own.

## Getting the most out of it

Two panel details carry a lot of the look and are worth adding:

- **Navigation groups** become the small tracked section headings.
- **`getNavigationBadge()`** on a resource becomes the count pill on the right
  of a navigation item. A count of open approvals or unallocated receipts is
  useful anyway, and it is the detail that makes the sidebar read as designed
  rather than stock.

For stat tiles, give each `Stat` a `->chart([...])`. The theme bleeds that
sparkline to the card edges so it reads as part of the tile.

## Licence

MIT.
