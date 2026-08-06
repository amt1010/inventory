# Mobile Category Drill-Down Navigation — Design Spec

Date: 2026-08-06
Status: Approved

## Purpose

The public header's "Products" nav item renders a mega-menu (a Bootstrap
dropdown showing the category tree) that works on desktop but is broken on
mobile: it still renders as the same wide (`min-width: 640px`) dropdown
inside the narrow collapsed mobile nav, with no responsive handling at all.
This replaces the mobile rendering with an AFL-Global-inspired drill-down:
each level's categories render as a stacked list, and tapping into a
category with children slides to a new screen for that level with a back
button to return, rather than trying to force the desktop dropdown into a
small viewport.

Desktop is explicitly out of scope — the existing mega-menu dropdown there
works and isn't changed.

## Data: `CategoryHierarchy::publishedTree()`

A new method on the existing `App\Support\CategoryHierarchy` class (which
already centralizes flat-fetch-then-build-in-PHP category tree logic —
see `descendantAndSelfIds()`), following the same one-query, no-N+1
pattern:

- One query: `Category::where('status', 'published')->orderBy('sort_order')->get(['id', 'parent_id', 'name', 'slug'])`.
- Build a `parent_id => [child ids]` map in PHP (same technique
  `descendantAndSelfIds()` already uses).
- Recursively assemble a nested array from the top-level nodes (`parent_id
  === null`) down: each node is `['id' => int, 'name' => string, 'path' =>
  string, 'children' => array]`.
- `path` is computed by joining slugs as the tree is built (parent's path
  + `/` + this node's slug) — not by calling `Category::path()` per node,
  which would issue one query per ancestor per node and be prohibitively
  N+1 across a full-depth tree.
- A depth/visit guard (mirroring the `$guard = 0` counter already used in
  `CategoryHierarchy::pathLabel()`) stops recursion if a cycle is ever
  present in the data, even though `Category`'s own `saving()` hook should
  already prevent one from being written.
- Only `status = 'published'` categories are included, at every level —
  the same filter the desktop mega-menu already applies. A published
  category under an unpublished ancestor will not appear (it's simply
  never reached while walking down from the published top-level set) —
  this matches the desktop mega-menu's existing behavior, not a new rule.

`AppServiceProvider`'s existing view composer on `layouts.app` gains a new
`$categoryTree` variable (the result of `publishedTree()`), passed
alongside — not replacing — the existing `topLevelCategories` variable
that the desktop mega-menu keeps using unchanged.

## Mobile Markup & Interaction

Inside the existing `#mainNav` collapsed nav, two mutually-exclusive
states, both rendered up front (no AJAX) and toggled via CSS/JS only:

- **Main menu state** (default): today's top-level nav item list. The
  "Products" entry (the `NavItem` with `show_category_menu = true`)
  renders, on mobile only, as a `<button>` (not the existing
  `dropdown-toggle` link) that switches to the drill-down's root panel.
- **Drill-down state**: one `<div class="mcn-panel" data-panel-id="…">`
  per tree node — the synthetic root plus every category node, however
  deep — rendered as flat siblings (not nested in the DOM), each
  `display: none` by default except the currently active one. Each
  panel contains:
  - A header row: "← Products" on the root panel (its back target is the
    main menu state), or "← {Parent Name}" on any deeper panel (back
    target is that parent's panel).
  - One row per child category: the category name as a plain `<a
    href="{{ url('/products/'.$path) }}">` (always navigates immediately,
    per the earlier decision — tapping a category name never requires
    drilling through it first), plus a `›` chevron `<button>` shown only
    when that child has its own children, which switches the active
    panel to that child's instead of navigating.

`public/js/mobile-category-nav.js` (new, vanilla JS, no build step —
matches this project's existing no-bundler approach) handles all
panel-switching via event delegation on the nav container: opening the
root panel and hiding the main menu when "Products" is tapped, chevron
clicks pushing the target panel onto an in-memory stack and activating
it, and back-button clicks popping the stack (or returning to the main
menu state when the stack is empty). No URL/history changes — this is
pure in-page UI state, consistent with the fact that every row is either
a real link (full navigation) or a stack push/pop (no navigation).

CSS scoping: the drill-down UI gets `d-lg-none` (Bootstrap 5 utility, next
to the existing `d-flex`/`d-none` usage already in this layout) so it does
not exist in any visible sense above the `lg` breakpoint. The existing
`.mega-menu` dropdown gets `d-none d-lg-block` added so the two can never
both be visible at the same viewport width.

## Testing

- `CategoryHierarchy::publishedTree()` (new unit test): produces correctly
  nested arrays for a 3+-level tree; excludes draft categories at every
  level; issues a constant, small number of queries regardless of tree
  depth (asserted via query-log count, matching this class's existing
  care around N+1 — see `descendantAndSelfIds()`).
- Mobile drill-down markup (new feature test): a category with children
  renders a chevron button; a leaf category does not; a 3-level-deep
  category is reachable (direct regression guard — today's mega-menu view
  composer caps at 2 levels, this must not); a draft category never
  appears anywhere in the drill-down (mirrors the existing
  `MegaMenuTest::test_a_draft_category_never_appears_in_the_mega_menu`).
- Desktop regression guard: the existing `MegaMenuTest` assertions
  (`.mega-menu` class, live category tree, draft exclusion) continue to
  pass unmodified — confirms the desktop path is untouched.

## Out of Scope (YAGNI)

- Any change to desktop's mega-menu dropdown.
- AJAX/lazy loading of category levels — ruled out in favor of eager
  server-rendering (see the earlier data-loading decision).
- Animated slide transitions between panels — plain show/hide via a CSS
  class; an animation can be layered on later purely in CSS without
  touching the JS interaction model or markup structure.
