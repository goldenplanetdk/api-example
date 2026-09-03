# Bulk pricing

Four things live behind one contract: mass price and cost updates, pricing campaigns, tier price
presets, and the jobs that report on all of them. Everything here is v3 only.

| What | Endpoint |
|---|---|
| Mass update selling price or cost price | `/api/v3/products/bulk/price.json` |
| Pricing campaigns (scheduled discounts) | `/api/v3/specialcampaigns.json` |
| Tier price presets (quantity breaks) | `/api/v3/tierpricepresets.json` |
| Job status | `/api/v3/jobs.json` |

## The job contract

**Every endpoint that moves prices answers `202 Accepted` with a job, never with a result.** The
body is the job, and `Location` points at it:

```
POST /api/v3/products/bulk/price.json
→ 202 Accepted
  Location: /api/v3/jobs/8831.json
```

```json
{
  "id": 8831,
  "type": "product.bulk_price",
  "status": "queued",
  "request": {"target": "price", "price": "+7.5%", "categories": [12, 45]},
  "result": null,
  "error": null,
  "created_at": "2026-09-03T10:12:00+02:00",
  "started_at": null,
  "finished_at": null,
  "_links": {"self": {"href": "/api/v3/jobs/8831"}}
}
```

You then poll `GET /api/v3/jobs/8831.json` until `status` is `done` or `failed`:

```json
{
  "id": 8831,
  "status": "done",
  "result": {"products": 4120, "variants": 9877, "reindex_scheduled": true},
  "error": null,
  "finished_at": "2026-09-03T10:12:03+02:00"
}
```

`status` is one of `queued`, `running`, `done`, `failed`. On `failed`, `error` carries the message.

The reason the answer is always a job is that a small selection is applied during the request while
a catalogue-wide one is handed to a background worker, and **you should not have to care which**.
For the fast paths the job is already `done` on your first poll - that is normal, not a race.

`GET /api/v3/jobs.json` lists your own jobs, newest first. **You only ever see jobs your own API
client created.** Someone else's job id returns `404`, the same as an id that does not exist, so
ids cannot be probed.

### Retries are safe - and the same rule can block you

Repeating an identical request while its job is still running - or within ten minutes of it
finishing - returns **`200` with the same job** instead of `202` with a new one. Nothing is applied
twice. So if a request times out on your side, just send it again: you either start the work or
get a handle on the work already in flight.

Two requests are "identical" when the client, the endpoint and the payload all match. Key order and
the order of ids inside `categories`, `brands` etc. do not matter.

**Read the status code.** This is not only a safety net - it is also a lock, and it is the one
thing about this API most likely to surprise you:

- `202` means *your* call started the work.
- `200` means an identical job already existed and **nothing was applied for this call**.

That distinction matters when you legitimately want to run the same operation twice. Re-applying
the same campaign an hour after your catalogue changed is a new piece of work to you, but an
identical request to the API - if the earlier job is still inside its window you get `200` and no
new work. There is no way to force a re-run and no `Retry-After` header, so:

- **Always branch on `200` versus `202`**, and treat `200` as "already done, check the job" rather
  than as success for this call.
- If you need the work to actually happen, `GET /api/v3/jobs/{id}` and look at `finished_at` -
  that tells you *when* the run you were folded into happened, which `200` alone does not.
- If a job is stuck `queued` or `running`, wait for it to finish rather than retrying in a loop;
  a retry cannot displace it.

## Selecting products

Every bulk endpoint takes the same selection fields. All are optional, all are arrays of ids:

| Field | Available on |
|---|---|
| `categories` | everything |
| `brands` | everything |
| `products` | bulk price, campaigns, campaign bulk remove |
| `suppliers`, `customer_groups` | bulk price |
| `is_include_sub_categories` | anything taking `categories` |
| `is_exclude_categories`, `is_exclude_products` | inverts that selection into "everything except" |

**An empty selection is refused with `400`.** In the admin a blank form means "the whole
catalogue"; over the API that is a footgun with no undo, so you must be explicit:

```json
{"target": "price", "price": "+1%", "apply_to_all": true}
```

`apply_to_all` is only a confirmation - it is not stored anywhere and does nothing else.

## Price syntax

The same grammar everywhere a price or discount is written, as a **string**:

| Example | Meaning |
|---|---|
| `"199"` | set to a fixed 199 |
| `"+10%"` / `"-20%"` | raise / reduce by a percentage |
| `"+15"` / `"-5"` | raise / reduce by an absolute amount |
| `"[costprice]+30%"` | 30% on top of the product's cost price |

Write the sign explicitly on percentages. A bare `"50%"` is **not** "half price" - it is rejected
with `Invalid sign`, and the job comes back `failed`.

## Mass price and cost updates

`POST /api/v3/products/bulk/price.json`

```json
{
  "target": "price",
  "price": "+7.5%",
  "rounding": "nearest",
  "categories": [12, 45],
  "is_include_sub_categories": true
}
```

`target` picks which number you are changing: `price` for the selling price, `costPrice` for what
you buy at. `rounding` applies **only to percentage adjustments** and is one of `""` (default),
`nearest`, `down`, `up`.

Setting a fixed cost price on one brand:

```json
{"target": "costPrice", "price": "42", "brands": [3]}
```

Two extra fields worth knowing:

- `with_cost_price: true` restricts the update to products that actually have a cost price.
- `[costprice]` is **not allowed when `target` is `costPrice`** - the cost price cannot be
  calculated from itself. You get a `400` naming the `price` field.

Variants follow their product when they hold a *relative* price (`+20`, `-10%`); only variants
with an absolute price of their own are rewritten. `result` reports both counts:

```json
{"products": 4120, "variants": 9877, "reindex_scheduled": true}
```

## Pricing campaigns

A campaign is a discount over a selection, with a start and an end date.

`POST /api/v3/specialcampaigns.json`

```json
{
  "title": "Autumn sale",
  "date_start": "2026-09-10",
  "date_end": "2026-09-30",
  "discount": "-20%",
  "categories": [12, 45],
  "is_include_sub_categories": true,
  "is_keep_better_special": true
}
```

**Creating a campaign does not change any price.** This differs from the admin, which applies a
campaign immediately if it starts today. Over the API a `POST` that silently repriced your
catalogue would be indefensible, so applying is a separate call:

```
POST /api/v3/specialcampaigns/12/apply.json    → 202 + job
POST /api/v3/specialcampaigns/12/remove.json   → 202 + job, campaign kept
DELETE /api/v3/specialcampaigns/12.json        → 202 + job, prices unwound and campaign deleted
```

Note `remove` versus `DELETE`: **`remove` puts the prices back and keeps the campaign** so you can
apply it again; `DELETE` does the same unwinding and then deletes the campaign itself. Both are
jobs, because both touch every affected product.

Both dates are required. `PUT` is a full replace - a boolean you leave out becomes `false`.

Reading a campaign back adds `status`, derived from the dates:

```json
{"id": 12, "status": "upcoming", "categories": [12, 45], "brands": [], "products": []}
```

and you can filter the list by it: `GET /api/v3/specialcampaigns.json?status=running`. Valid values
are `upcoming`, `running`, `expired`; anything else is a `400`.

The behaviour flags are worth reading before a first run, since they decide what happens to prices
that are *already* discounted:

| Flag | Effect |
|---|---|
| `is_keep_better_special` | leave a product alone if its existing discount is better |
| `is_keep_former_special` | remember the previous special, restore it when the campaign is removed |
| `is_replace_with_standard` | on removal, write the campaign price as the normal price |
| `exclude_with_campaign` | skip products already covered by another campaign |
| `is_include_sets` | include product sets / bundles |
| `disable_when_sold`, `quantity_limit` | stop the discount once stock runs out / below a quantity |

### Removing specials without a campaign

`POST /api/v3/specialcampaigns/bulk/remove.json` clears special prices by ad-hoc criteria, with no
campaign involved:

```json
{"brands": [3], "is_keep_former_special": true}
```

## Tier price presets

A preset is a named set of quantity breaks, attached to many products at once.

`POST /api/v3/tierpricepresets.json`

```json
{
  "title": "Wholesale 10/50/100",
  "ranges": [
    {"quantity": 10,  "price": "-5%"},
    {"quantity": 50,  "price": "-12%"},
    {"quantity": 100, "price": "[costprice]+15%"}
  ]
}
```

`quantity` is at least `2` and must be unique within the preset - a duplicate is a `400`, not a
silently dropped row. `PUT` replaces the whole `ranges` collection; `DELETE` removes the preset and
leaves the products' own prices alone.

Attaching and detaching are the bulk endpoints:

```
POST /api/v3/tierpricepresets/bulk/apply.json    {"preset": 4, "categories": [12], "is_include_sub_categories": true}
POST /api/v3/tierpricepresets/bulk/remove.json   {"categories": [12]}
```

Remove takes no `preset` - it detaches whatever preset the matching products carry. Sending
`preset` to the remove endpoint is a `400`.

## Things worth knowing

- **Nothing reaches the storefront until the search index catches up.** Where a reindex was
  queued, the job result says `"reindex_scheduled": true`. For tier presets the reindex only runs
  when the shop has `USE_TIERED_PRICE` enabled, since that is the only case where presets affect
  the shop front.
- **Per-product prices are a different API.** `cost_price` on `/api/v3/products/<id>.json` and
  `cost_price_with_tax` on a variant have been writable all along - the endpoints here are for
  changing many products at once.
- A **failed job is still a `202`**. Once the job row exists, problems are reported on the job, not
  as an HTTP error - so always read `status` rather than trusting the status code alone. Validation
  errors, unknown ids and a missing selection happen *before* the job exists and are ordinary
  `400`s.
- **Deleting a preset is `204`, deleting a campaign is `202` + a job.** Deleting a preset does not
  change any price, so there is nothing to report; deleting a campaign unwinds the discount from
  every product it touched. If you write one generic delete handler, account for both.
- **`reindex_scheduled` is only present when a reindex was actually queued** - always for a bulk
  price change, only on tier-priced shops for presets, never for campaigns. Treat a missing key as
  "not applicable", not as `false`.
- A `GET` returns fields a `PUT` will not accept - `id`, `status`, `created_at`, `_links`. Read,
  modify and send back **only the writable fields**; posting the whole document back is a `400`.
- **Relation ids must exist.** An unknown category or brand id is a field-level `400`; nothing is
  auto-created, unlike product features.
- `is_notify` is accepted on a campaign for round-trip compatibility but does nothing over the API -
  there is no admin user behind an API client to notify.
- Jobs are kept for **30 days** after they finish, then pruned.
