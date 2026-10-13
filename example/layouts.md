# Layout Builder layouts

Read, draft-edit and publish the page layouts that the admin **Layout Builder** (LB) edits: the
header, the footer and the main section of the home, category, product and other storefront pages.
v3 only.

Every change goes into LB's own HTML presets, so the LB canvas in the admin always shows what the
API did, and publishing produces exactly the Twig that LB's Save button produces. Edits made this
way are not lost on the merchant's next LB save — unlike edits to the compiled
`views/layout/*.html.twig` files through `/theme-files`, which LB overwrites.

| What | Endpoint |
|---|---|
| List the pages | `GET /api/v3/layouts.json` |
| Read one page | `GET /api/v3/layouts/{page}.json` |
| Create a draft preset | `POST /api/v3/layouts/{page}/{section}/presets.json` |
| Replace a draft preset | `PUT /api/v3/layouts/{page}/{section}/presets/{id}.json` |
| Delete a draft preset | `DELETE /api/v3/layouts/{page}/{section}/presets/{id}.json` |
| Publish a page | `POST /api/v3/layouts/{page}/publish.json` |

**Drafts do not change the storefront.** POST, PUT and DELETE only change presets. The storefront
changes when you **publish** the page, and only then.

## Scopes

`theme_read` for the two GETs, `theme_write` for everything else. These scopes are **not** included
in the generic `read` / `write` scopes: a client created with `write` gets `403` here. A shop admin
has to tick *theme read/write* for the client at `/admin/api-token`.

```json
{"code": 403, "message": "Expression \"is_granted('ROLE_THEME_WRITE')\" denied access."}
```

`ROLE_THEME_WRITE` is the `theme_write` scope (newer releases say `Missing scope: theme_write`).

## Legacy-only shops

The API serves shops that still use the old Layout Builder. A shop switched to the New Backend has
its own layout editor; there every endpoint answers `409` and points to it:

```json
{"code": 409, "message": "Layouts of this shop are managed by the New Backend",
 "use": "/admin/api/layout?mode=legacy"}
```

## Pages, sections and preset ids

`{page}` is one of `home`, `category`, `product`, `product_set`, `checkout`, `cart`, `user`,
`info`, `basic`. `{section}` is `header`, `main` or `footer`.

- **home** has all three sections, and its `header` and `footer` are the **shared** header and
  footer every page shows.
- **Every other page** has `main`. Its `header` / `footer` only appear when that page has its own
  ("unlocked") copy of the shared one — see [Unlocked header and footer](#unlocked-header-and-footer).

Each section holds several **presets** (alternative layouts); one of them is **active**. A preset id
is what `GET` shows: a number for core and user presets, a name for theme presets (e.g.
`kuki-home-1`), or `custom` for an unlocked section.

## Reading

```bash
curl 'http://SHOP_DOMAIN/api/v3/layouts.json' -H 'Authorization: Bearer ACCESS_TOKEN'
```

```json
[
  {"page": "home", "title": "Home page", "locked": false, "has_unpublished_changes": false,
   "sections": {"header": {"active_preset_id": 2}, "main": {"active_preset_id": 1},
                "footer": {"active_preset_id": 1}}},
  {"page": "checkout", "title": "Checkout page", "locked": false, "has_unpublished_changes": true,
   "sections": {"main": {"active_preset_id": 1}, "header": {"custom": true}}}
]
```

- `locked`: the merchant locked the page in LB; publishing it answers `409`.
- `has_unpublished_changes`: some preset of the page changed since it was last published.

`GET /api/v3/layouts/home.json` returns every preset with its HTML:

```json
{
  "page": "home", "locked": false, "has_unpublished_changes": false,
  "sections": {
    "main": {
      "active_preset_id": 2,
      "presets": [
        {"id": 1, "title": "home page preset #1", "source": "core", "active": false,
         "html": "<div class=\"container\"></div>", "hash": "9a0b…"},
        {"id": 2, "title": "main preset #2", "source": "user", "overrides": "core", "active": true,
         "html": "<div class=\"container\"><div class=\"custom-text\"><script class=\"params\" type=\"application/json\">{\"block_id\": 7}</script><p>Welcome</p></div></div>",
         "hash": "c47d…"}
      ]
    },
    "header": {"…": "…"}, "footer": {"…": "…"}
  },
  "translations": {"7": {"en": "<p>Welcome</p>", "da": "<p>Velkommen</p>"}}
}
```

- `html` is the section's inner HTML, exactly what LB edits.
- `source` is where the preset lives: `core`, `theme` or `user`. `overrides` is set when a user
  preset replaces a core or theme preset with the same id.
- `hash` identifies this version of the preset; send it as `If-Match` when you change it.
- `translations` holds the text of every text block the returned HTML references, in every language.

## The HTML: Layout Builder rails

A preset is LB's HTML, not free HTML. Anything LB cannot show in its canvas is refused, so the
merchant can always keep editing what an API client wrote.

- **Components** are elements whose **first** class is an LB component, e.g. `obb-shop-logo`,
  `obb-image`, `custom-text`, `custom-html`, `custom-title`, `space-separator`.
  A component's settings live in its first child, `<script class="params" type="application/json">{…}</script>`.
- **Layout** uses `container`, `row` and columns, `block`, `well`, `panel`, tabs, accordions and
  navbars. A column is exactly one `col-md-<1..24>` — the storefront grid has **24** columns, so
  `col-md-12` is half the width. `col-xs-*`, `col-sm-*`, `col-lg-*` and offsets are refused.
- **No `<script>`** other than component params, **no `<style>`**, and no Twig (`{{`, `{%`, `{#`) —
  also not as HTML entities. Embeds such as `<iframe>` inside a `custom-html` block are allowed, as
  in LB.
- The `main` section of `category`, `checkout`, `cart`, `user`, `info` and `basic` must contain
  exactly one `<div class="obb-content"></div>` (where the page's own content goes); no other
  section may contain one.

A refusal is a `400` listing every problem, and nothing is written:

```json
{"code": 400, "message": "Validation Failed",
 "errors": ["Every child of .row must be a column with exactly one width class col-md-1..24 and no other class containing a digit; found <div class=\"col-sm-6\">"]}
```

Read `errors` as text for a human or an agent to act on; do not branch on its exact wording.
Successful saves can also return `warnings` (e.g. a row whose columns do not add up to 24, or inline
`style=` attributes): they are advice, the preset was saved.

**Existing hand-made HTML is tolerated on PUT.** An element LB does not know, which was already in
the preset you replace, is a warning rather than an error, so an older manual edit by the merchant
does not block your save. New unknown elements are refused — and so is a `POST` carrying such an
element copied from another preset, because a new preset has no previous version to compare with.

## Text blocks

Translated text lives in **text blocks**, referenced by number from a component's params:
`{"block_id": 7}`. To add new text, reference a **new** block by a label of your choice and send its
text in `translations`:

```json
{
  "html": "<div class=\"container\"><div class=\"custom-text\"><script class=\"params\" type=\"application/json\">{\"block_id\": \"new:usp1\"}</script><p>Free shipping</p></div></div>",
  "translations": {"new:usp1": {"da": "<p>Fri fragt</p>", "en": "<p>Free shipping</p>"}}
}
```

The shop creates the block, writes its number into the HTML everywhere `new:usp1` appeared, and
returns the mapping:

```json
{"id": 3, "source": "user", "active": false, "html": "…{\"block_id\": 41}…", "hash": "e91a…",
 "warnings": [], "block_ids": {"new:usp1": 41}}
```

- The text in the shop's **default language** is required; other languages are optional (LB can
  translate them later). A missing default language is refused with a message naming it.
- **Existing blocks cannot be edited here.** A draft must never change text the live storefront
  already shows, so to change a text, reference a new block with the new text. The old block stays
  unused, as it does when the merchant edits text in LB.
- A text may not contain `#{`, and neither it nor any of its lines may end with a backslash: both
  would break the compiled template.

## Drafts

`POST` creates a preset with the next free number and answers `201`; `PUT` replaces one and answers
`200`. Both take the same body:

```json
{"html": "…", "translations": {"new:label": {"da": "…"}}}
```

`translations` is optional. A preset's title cannot be set through the API: `PUT` keeps the
preset's title, and a `POST`ed preset gets LB's default title (e.g. `main preset #3`).

- **`If-Match`.** Send the `hash` you read. If someone (the merchant in LB, or another client)
  changed the preset since, you get `412` and nothing is written — read it again and redo your
  change on the current HTML.
- `PUT` on a **core or theme** preset id creates a user copy that overrides it; `DELETE` on that id
  later removes the copy and the original reappears.
- `PUT` on an id that does not exist is `404` — use `POST` to create presets.

`DELETE` answers `204`. The **active** preset cannot be deleted (`409`): publish another preset as
active first.

## Publishing

```bash
curl -XPOST 'http://SHOP_DOMAIN/api/v3/layouts/home/publish.json' \
  -H 'Authorization: Bearer ACCESS_TOKEN' -H 'Content-Type: application/json' \
  -d '{"active": {"main": 3}}'
```

- `active` picks the active preset per section; sections you leave out keep theirs. To publish the
  current drafts without switching presets, send `{}`.
- **Always send a body**, at least `{}`. A POST without one is rejected by the proxy in front of the
  shop with an empty `400` before the shop sees it.
- The shop makes a backup (restorable from LB), writes all presets, compiles the page and clears
  the caches — the same as LB's Save. The storefront changes immediately.
- Every section is test-compiled first; a section that would not compile is a `400` naming it, and
  nothing is published.
- `200` returns the page as `GET` shows it, with `has_unpublished_changes: false`.
- A **locked** page is `409`.

To undo a publish, publish again with the previous `active_preset_id`s you read before.

## Unlocked header and footer

A page other than home normally shows the shared header and footer. Giving it its own copy is done
with the id `custom`:

```bash
curl -XPUT 'http://SHOP_DOMAIN/api/v3/layouts/checkout/header/presets/custom.json' \
  -H 'Authorization: Bearer ACCESS_TOKEN' -H 'Content-Type: application/json' \
  -d '{"html": "<div class=\"container\"><div class=\"obb-shop-logo\"></div></div>"}'
```

Publish the page (`{}` is enough) to make it live. `DELETE …/checkout/header/presets/custom.json`
removes the copy, and the page shows the shared header again after the next publish. `custom` is
always edited with `PUT`; a `POST` to an unlocked section is refused.

## Working next to a merchant

The admin LB does not know about API changes while it is open. A merchant who has LB open and
presses **Save** writes the canvas they loaded — including presets as they were before your draft.
After an agent changed layouts, the merchant should reload LB before editing.

## Errors

| Status | When |
|---|---|
| 400 | malformed body; HTML off the LB rails (`errors` lists why); an unknown or unsupported `block_id`; `POST` to an unlocked section; a section that would not compile on publish |
| 403 | missing `theme_read` / `theme_write` scope |
| 404 | unknown page, section or preset id |
| 409 | page locked; deleting the active preset; New Backend shop |
| 412 | `If-Match` does not match the current preset |
| 500 | publishing failed after files were written (same as a failed LB save) |

## Gotchas

- **Empty objects come back as `[]`.** An empty `translations` or `block_ids` is serialised as a
  JSON array, not `{}`. Treat both as "nothing".
- **Ids are numbers or strings.** Core and user presets have numeric ids, theme presets names, an
  unlocked section `"custom"`. Compare them as strings.
- The API URLs end in `.json`, so they are not affected by caches that treat `….png` / `….js` URLs
  as static files.

## Example

[layouts.php](layouts.php) — lists the pages, adds a draft with a new text block to the home page,
shows a refused off-rails draft and a stale `If-Match`, optionally publishes, and cleans up.
