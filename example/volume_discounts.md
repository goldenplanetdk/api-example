# Volume discounts

A volume discount is a **cart-level** rule — known in the admin as "Mængderabat". Once the basket
reaches a quantity or a subtotal, a discount line is added to the cart. It never changes a product's
price, so nothing here triggers a reindex and nothing here returns a job: these are plain CRUD calls
that answer immediately. v3 only.

| What | Endpoint |
|---|---|
| List | `GET /api/v3/volumediscounts.json` |
| Read one | `GET /api/v3/volumediscounts/{id}.json` |
| Create | `POST /api/v3/volumediscounts.json` |
| Replace | `PUT /api/v3/volumediscounts/{id}.json` |
| Delete | `DELETE /api/v3/volumediscounts/{id}.json` |

Reads need the `product_read` scope, writes `product_write`.

Contrast with the neighbours: **tier prices** (`bulk_pricing.md`) change what a product costs at a
given quantity and are per-product; a volume discount looks at the whole basket and discounts the
order. If you want "buy 10 of *this yarn*, pay less per skein", you want tier prices, not this.

## The resource

```json
{
  "id": 42,
  "is_enabled": true,
  "title": "Quantity discount",
  "priority": 0,
  "created_at": "2026-08-15T09:30:00+02:00",
  "starts_at": "2026-09-01T00:00:00+02:00",
  "expires_at": "2026-12-31T00:00:00+01:00",
  "graduations": [
    {"min_quantity": 10, "min_amount": 0,    "discount": "5%"},
    {"min_quantity": 0,  "min_amount": 2500, "discount": "200"}
  ],
  "exclude_options": ["special", "tier"],
  "is_exclude_customer_groups": false,
  "is_exclude_categories": false,
  "allowed_category_ids": [12, 44],
  "allowed_product_ids": [],
  "allowed_customer_group_ids": [3],
  "allowed_customer_type_ids": [],
  "allowed_shipping_zone_ids": [],
  "allowed_shipping_module_ids": [],
  "_links": {"self": {"href": "/api/v3/volumediscounts/42"}}
}
```

Null fields are omitted rather than sent as `null`, so `expires_at` simply will not be there on a
discount that never expires.

## Graduations

A graduation is one tier. It fires when the cart meets `min_quantity` **and** `min_amount` — both,
not either. Set one to `0` to key the tier off the other alone:

| Graduation | Means |
|---|---|
| `{"min_quantity": 10, "min_amount": 0, "discount": "5%"}` | 10 or more items → 5% off |
| `{"min_quantity": 0, "min_amount": 2500, "discount": "200"}` | subtotal 2500+ → 200 off |
| `{"min_quantity": 10, "min_amount": 2500, "discount": "8%"}` | 10+ items *and* 2500+ → 8% off |

At least one graduation is required; `"graduations": []` is a 400.

`discount` is either a percentage (`"5%"`) or a fixed amount in the shop's default currency
(`"200"`). **It carries no sign** — it is always a reduction. `"-5%"` is rejected outright rather
than read as a discount.

Overlapping tiers are allowed and expected. The customer is given the most favourable matching
graduation, so you do not have to keep them disjoint or ordered.

## Targeting

Six lists narrow who and what a discount applies to. All are sent and returned as id arrays:

`allowed_category_ids`, `allowed_product_ids`, `allowed_customer_group_ids`,
`allowed_customer_type_ids`, `allowed_shipping_zone_ids`, `allowed_shipping_module_ids`

**An empty array means "applies to everything", not "applies to nothing".** That is the single most
important thing on this page. A discount with no targeting applies shop-wide.

Two booleans flip a list from an allow-list to a deny-list:

| Field | Effect when `true` |
|---|---|
| `is_exclude_categories` | the categories listed are the ones EXCLUDED |
| `is_exclude_customer_groups` | the groups listed are the ones EXCLUDED |

Unknown ids are refused with a 400 on that field — they are not silently skipped, so a typo cannot
quietly narrow your discount.

Do **not** send `is_all_categories_allowed` or any other `is_all_*_allowed` flag. They are not part
of the payload. The server derives them from whether each list is empty, which is exactly what keeps
"empty means everything" true.

`exclude_options` stops the discount stacking on already-reduced items. It is a string array drawn
from `special`, `tier`, `group`, `productSet`.

## PUT replaces everything

There is no `PATCH`. `PUT` is a full replace, and **every field you leave out is reset, not
preserved**:

| Omitted from a PUT | Result |
|---|---|
| `graduations` | 400 — at least one is required |
| any `allowed_*_ids` | that restriction is **cleared**, widening the discount |
| `exclude_options` | cleared |
| `is_enabled` | **false** — the discount is disabled (see below) |
| `priority` | `0` |

To change one field: `GET` the resource, modify that field, `PUT` the whole thing back.

### Two fields that bite when omitted

**`is_enabled`** is a checkbox. Omitting it is indistinguishable from sending `false`, so a `POST`
that leaves it out creates a **disabled** discount — even though the same discount created through
the admin screen defaults to enabled. Always send it explicitly.

**`priority`** orders discounts against each other, highest first. On releases before the
`empty_data` fix, omitting it stored `NULL` rather than `0`, and the ordering puts NULLs last — so a
`PUT` that merely forgot the field silently demoted the discount below every other one. Send it
explicitly on any version.

## The list is a paginated envelope

`GET /api/v3/volumediscounts.json` does **not** return a bare array:

```json
{
  "total": 3,
  "page": 1,
  "limit": 100,
  "pages": 1,
  "_links": {"self": {"href": "..."}, "first": {"href": "..."}, "last": {"href": "..."}},
  "_embedded": {
    "resources": [ { "id": 42, "title": "Quantity discount", "...": "..." } ]
  }
}
```

The discounts are under `_embedded.resources`. Iterating the response directly gets you the four
counters, not your data. `limit` maxes out at 100; page with `?page=N&limit=N`.

## Validation

Every refusal is a `400` naming the offending field, and none of them create anything — the request
is validated before any record is written.

| Request | Refused because |
|---|---|
| `"graduations": []` | at least one graduation is required |
| `"discount": "-5%"` | discounts carry no sign |
| `"discount": "free"` | must match `^[0-9.]+%?$` |
| `"min_quantity": -1` | thresholds cannot be negative |
| `"allowed_category_ids": [999999]` | no such category |
| any unrecognised field | the payload rejects extra fields |
| `"starts_at": "01-09-2026"` | dates are written as `yyyy-MM-dd` |

**Error messages come back in the shop's language**, not English — a Danish shop answers
`"Værdien må ikke være blank."`. Branch on the field name and the status code, never on the message
text.

### Dates are written short and read long

`starts_at` and `expires_at` are **written** as `yyyy-MM-dd` (`"2026-09-01"`) but **read back** as
full ISO-8601 (`"2026-09-01T00:00:00+02:00"`). A naive round-trip — GET the resource, change one
field, PUT it back unchanged — is therefore refused on the date fields. Reformat them to
`yyyy-MM-dd` before sending.

## Deleting

`DELETE` answers `204` with an empty body. Orders that already used the discount are unaffected:
order lines keep their own copy of what was charged, so historical orders never change.

## Title and languages

`title` is a plain string written in the request's language, matching the coupon and free-shipping
resources. There is no per-language object here — set the other languages in the admin.
