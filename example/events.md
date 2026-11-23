# Events: what changed in the shop

Read the shop's event log: every `order.created`, `product.updated`, `customer.deleted`, … the shop
has recorded. These are the same events [webhooks](webhooks.php) deliver, with the same payload. Use
the list to **poll** for changes instead of running a webhook receiver, or to **catch up** on
webhooks your receiver missed. Read-only, v3 only.

> Requires a shop release that includes the events endpoint (openbizbox PR #5056, November 2026).
> On older releases this URL answers `404`.

| What | Endpoint |
|---|---|
| List events, oldest first | `GET /api/v3/events.json?filter=&sort=&order=&page=&limit=` |

Scope: the generic `read` scope. Events cover every kind of entity, so a narrower scope such as
`product_read` or `order_read` is refused:

```json
{"code": 403, "message": "Missing scope: read"}
```

## Reading

```bash
curl -G 'http://SHOP_DOMAIN/api/v3/events.json' \
  -H 'Authorization: Bearer ACCESS_TOKEN' \
  --data-urlencode 'filter=id=gt=4180;name=like=order.*' \
  --data-urlencode 'limit=100'
```

```json
{
  "page": 1, "limit": 100, "pages": 1, "total": 3,
  "_links": {"self": {"href": "/api/v3/events.json?page=1&limit=100"}, "…": "…"},
  "_embedded": {
    "resources": [
      {
        "id": 4181,
        "name": "order.created",
        "type": 1,
        "created_at": "2026-11-16T10:42:07+01:00",
        "data": {"id": 1093, "status": {"id": 1, "…": "…"}, "products": ["…"], "…": "…"},
        "changed_fields": {},
        "admin_id": null,
        "customer_id": 1507,
        "path": "/checkout/…",
        "source": "…",
        "is_cli_mode": false,
        "note": null,
        "ip": null,
        "user_agent": null,
        "api_client": null
      },
      {
        "id": 4186,
        "name": "order.updated",
        "type": 2,
        "created_at": "2026-11-16T10:45:31+01:00",
        "data": {"id": 1093, "status": {"id": 3, "…": "…"}, "…": "…"},
        "changed_fields": {"status": {"before": "…", "after": "…"}},
        "admin_id": 4,
        "customer_id": null,
        "path": "/admin/…",
        "source": "…",
        "is_cli_mode": false,
        "note": null,
        "ip": "203.0.113.17",
        "user_agent": "Mozilla/5.0 (…) Chrome/131.0 Safari/537.36",
        "api_client": null
      },
      {
        "id": 4190,
        "name": "product.updated",
        "type": 21,
        "created_at": "2026-11-16T11:02:55+01:00",
        "data": {"id": 2210, "…": "…"},
        "changed_fields": {"articles": {"7314": {"localQuantity": {"before": 12, "after": 9}}}},
        "admin_id": null,
        "customer_id": null,
        "path": "/api/v3/products/2210/variants/7314",
        "source": "Openbizbox\\ApiBundle\\Controller\\V1\\ProductVariantController::putProductVariantAction",
        "is_cli_mode": false,
        "note": null,
        "ip": "198.51.100.24",
        "user_agent": "axios/0.21.4",
        "api_client": {"id": 12, "name": "Stock sync"}
      }
    ]
  }
}
```

| Field | Meaning |
|---|---|
| `id` | Grows with every new event. Use it as your cursor. There are gaps: old events are deleted, and other apps' events are hidden. |
| `name` | `<entity>.<action>`, see the table below. |
| `type` | The same event as a number (`order.created` = `1`, …). |
| `created_at` | When the change was saved, in the shop's time zone. |
| `data` | The entity **after** the change, as JSON. This is exactly the body a webhook for this event sends: orders, products and customers in their **API v2** shape. For `*.deleted`, it is the entity as it was just before it was deleted. `null` when nothing is stored, see [Product payloads](#product-payloads-are-only-kept-until-the-night). |
| `changed_fields` | What changed, as `{"field": {"before": …, "after": …}}`. Treat it as a hint. It is often empty for `*.created` and `*.deleted`, and some changes, such as to a list of related records, show only the field name. |
| `admin_id` | The admin user who made the change, if it was one. |
| `customer_id` | The logged-in customer who made the change, if it was one (for example, placing an order). |
| `path` | The URL path of the request that made the change. Empty for cron jobs and console commands. |
| `source` | The controller that handled the request, or for a cron job the command line (`'sales:aggregate'`). |
| `is_cli_mode` | `true` when a cron job or console command made the change, not a web request. |
| `note` | Why the system made the change, when it says. For example, a bundle that was turned off because one of its products sold out. |
| `api_client` | The API app whose access token made the change: `{"id": 12, "name": "Stock sync"}`. `name` is the app's title in the shop admin (or its alias) when the event was recorded, and is left out when the app has neither. `null` for changes made in the storefront, by an admin user or by a cron job of their own. This is the most reliable way to tell apart the services that write through the API: their user agents and IPs can change. |
| `ip` | The IP address of the request that made the change. Only for API and admin requests, see below. |
| `user_agent` | The `User-Agent` header of that request, for example `axios/0.21.4` or `python-requests/2.34.2`. Only for API and admin requests, see below. |

A field without a value is left out of the response rather than sent as `null` (the examples above
show the `null`s for clarity), so read a missing field as `null`.

#### Who made the change: `ip` and `user_agent`

The IP address of a shopper is personal data, so `ip` and `user_agent` are shown only when the
change was made **through the API** (the event has an `api_client`, or its `path` starts with
`/api/`) or **by an admin user** (`admin_id` is set). They are `null` for everything else: a guest
or logged-in customer in the storefront (an order, a review, a newsletter signup), and a cron job
of its own. Any token with the `read` scope sees them under this rule; there is no extra scope.

A cron job that finishes work started by an API or admin request (`is_cli_mode` is `true`, for
example the follow-up of a bulk update) has the `api_client`, `ip` and `user_agent` of that
request.

Events recorded before the shop started storing the API client have no `api_client`. Their `ip`
and `user_agent` are shown under the same rule, when the shop stored them.

### Event names

| Entity | created | updated | deleted |
|---|---|---|---|
| `order` | 1 | 2 | 3 |
| `product` | 20 | 21 | 22 |
| `customer` | 30 | 31 | 32 |
| `module` (payment and shipping methods) | 90 | 91 | 92 |
| `configuration` (shop settings) | 100 | 101 | 102 |
| `category` | 120 | 121 | 122 |
| `app` | `app.installed` = 110 | | `app.uninstalled` = 111 |

- A save makes **one** event per entity. A product saved together with its variants gives one
  `product.updated`, not one per variant. When something is created and then changed in the same
  save, you get only the `*.created`.
- Events about another app (an `app.uninstalled` sent to that app) are not listed. You see every
  shared event and the events sent to your own app. A token with admin rights sees all of them.

## Filtering and sorting

`filter` uses the same syntax as the other v3 lists, see [filters.md](filters.md). The event list
filters on these fields:

| Field | Type | Example |
|---|---|---|
| `id` | numeric | `id=gt=4180` |
| `name` | text | `name==order.created`, `name=like=order.*`, `name=in=(order.created,order.updated)` |
| `type` | numeric | `type=in=(1,2)` |
| `created_at` | date | `created_at=gte=2026-11-16 00:00:00` (shop time zone) |

Combine conditions with `;` (AND) and `,` (OR). URL-encode the whole value: `curl -G
--data-urlencode` or Guzzle's `query` option do that for you.

```
# Everything after the last event you handled
/api/v3/events.json?filter=id=gt=4180

# New and changed orders since midnight
/api/v3/events.json?filter=name=in=(order.created,order.updated);created_at=gte=2026-11-16 00:00:00

# The 20 most recent events of any kind
/api/v3/events.json?sort=id&order=desc&limit=20
```

- `sort` is one of `id` (the default), `createdAt`, `name` or `type`. `order` is `asc` (the
  default) or `desc`.
- `limit` is at most 100, and 100 is the default. The events are under `_embedded.resources`, as in
  every v3 list.
- The old parameters work too: `from=4180` is `id=gt=4180`, and `created_from=2 days` means
  "created in the last two days". They are **ignored when `filter` is present**, so don't mix the
  two styles.
- `api_client`, `ip` and `user_agent` cannot be filtered on. Filter on them in your own code
  while you read the list.
- Filtering on any other field, such as `modified_at`, answers `500`, not `400`. Events never
  change, so they have no modification date.

## Polling

Keep the `id` of the last event you handled, and ask for what came after it:

```
GET /api/v3/events.json?filter=id=gt=<last id>&sort=id&order=asc&limit=100
```

1. Handle the events in the order they are listed, and store the last `id` **after** handling
   them. If your process dies halfway, the next run repeats those events instead of skipping them.
   Make handling an event twice harmless, for example by keeping the ids you have handled.
2. If you got `limit` events, ask again right away with the new last id. If you got fewer, you are
   up to date.
3. Always use the `id` cursor rather than `page=2`, `page=3`, … Old events are deleted every night,
   so page numbers shift.
4. On the first run, decide where to start. Use `sort=id&order=desc&limit=1` and take that `id` to
   start "from now". Or start at `0` to replay everything still kept, which is up to 14 days.

`data` is the entity as it was when the event was recorded, not as it is now. When only the current
state matters, take the entity's id from `data` and read it fresh. For example,
`GET /api/v3/orders/1093.json` after any `order.*` event.

Rarely, two saves made at the same moment can make an event appear after one with a higher `id`.
If missing an event would hurt, also re-read a short window each time, such as
`id=gt=<last id - 50>`, and skip the ids you have already handled.

### Catching up on webhooks

A webhook's `X-OBB-Event-Id` header is the event's `id` here. Store the highest one your receiver
handled. After an outage, list `filter=id=gt=<that id>` to get what you missed, with the same
payload the webhooks would have delivered.

## Events are not kept forever

The shop cleans its event log **once a night**, around 04:00 shop time.

### Old events are deleted

Events older than **14 days** are deleted. The shop can change this in the
`EVENTS_CLEANUP_TIME_DAYS` setting. A poller that is offline longer than that loses events, and
nothing in the list tells you so: `id` gaps are normal. If your last poll was longer ago than the
retention period, read the data you sync in full again (orders, products, …) before you go on
polling.

### Product payloads are only kept until the night

Every night, the cleanup sets `data` to `null` on **all** `product.*` events, the new ones too. After
that, a product event says only *that* some product changed, not which one: `changed_fields` has no
id either. Order, customer and the other events keep their payload until they are deleted.

So for products, either:

- poll at least a few times a day and read the product id from `data` while it is still there, or
- use the `product.*` webhooks, which deliver the payload right away, and use the event list only
  for orders, customers and the rest.

## Errors

| Status | When |
|---|---|
| 400 | a filter that cannot be parsed, or `limit` over 100 |
| 401 | no token, or an expired one |
| 403 | the token lacks the generic `read` scope: `Missing scope: read` |
| 404 | the shop's release does not have this endpoint yet |
| 500 | filtering on a field the list does not have (for example `modified_at`) |

## Example

[events.php](events.php) shows the five latest events and today's order events. It then polls the
way described above, with the cursor saved in a file, and says when a product event has already lost
its payload. Run it again to get only what happened since the last run.
