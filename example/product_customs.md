# Product customs data

Customs data of a product: the 6-digit **HS code**, the national tariff codes that extend it per
market, and the **country of origin**. The shop prints them on the invoice when an EU shop ships
outside the EU (e.g. to Norway), and they can be exported in product feeds. v3 only.

One record per **product** — not per variant. All variants of a product share it.

| What | Endpoint |
|---|---|
| List | `GET /api/v3/product-customs.json` |
| Read one | `GET /api/v3/products/{productId}/customs.json` |
| Create | `POST /api/v3/products/{productId}/customs.json` |
| Update | `PUT /api/v3/products/{productId}/customs.json` |
| Delete | `DELETE /api/v3/products/{productId}/customs.json` |

Reads need the `product_read` scope, writes `product_write`. The record is addressed by the
**product id**, not by its own `id` — or by **model**, see [By model](#by-model).

## The resource

```json
{
  "id": 12,
  "product_id": 101,
  "hs_code": "610910",
  "eu_code": "61091000",
  "no_code": "61091000",
  "gb_code": "6109100010",
  "us_code": "6109100012",
  "origin_country": "PT",
  "modified_at": "2026-10-27T14:05:00+01:00"
}
```

| Field | Digits | Meaning |
|---|---|---|
| `hs_code` | 6 | Harmonized System code, the same worldwide |
| `eu_code` | 8 | EU export code (Combined Nomenclature) |
| `no_code` | 8 | Norway (Tolltariffen) |
| `gb_code` | 10 | United Kingdom (UK Global Tariff commodity code) |
| `us_code` | 10 | United States (HTS) |
| `origin_country` | — | ISO 3166-1 alpha-2, e.g. `PT` |

Null fields are omitted rather than sent as `null`: a product without a US code simply has no
`us_code` key.

## Codes are digits

Codes are stored and returned as digit strings. On write, spaces and dots are ignored, so
`"6109.1000"`, `"6109 10 00"` and `"61091000"` are all the same code. **Send codes as strings, never
as numbers**: a JSON number — like a spreadsheet — drops the leading zero, and `030211` becomes
`30211`, which is refused for being one digit short.

A market code normally starts with the HS code (`610910` → `61091000`), but this is not enforced:
a market may classify the product differently, so any code of the right length is accepted.

## Which code the invoice shows

Only when the **shop is in the EU and the shipping address is outside the EU**:

| Destination | Printed |
|---|---|
| Norway | `no_code`, else `eu_code`, else `hs_code` |
| United Kingdom | `gb_code`, else `eu_code`, else `hs_code` |
| United States | `us_code`, else `eu_code`, else `hs_code` |
| any other non-EU country | `eu_code`, else `hs_code` |
| inside the EU, or a shop outside the EU | nothing |

The origin is printed next to the code, and is hidden when no code is printed. The shop can turn
the lines off under *Layout → Print → Invoice*. The invoice reads the product's **current** customs
data when it is printed — orders keep no copy — so a change here also shows on invoices of orders
already placed.

## Create

`POST /api/v3/products/{productId}/customs.json` answers `201` with the record.

```json
{
  "hs_code": "610910",
  "eu_code": "61091000",
  "no_code": "6109.1000",
  "gb_code": "6109 10 00 10",
  "origin_country": "PT"
}
```

A product has at most one record: a second `POST` for the same product is a `400`. Use `PUT` to
change it.

## PUT changes only what you send

Unlike most v3 resources, `PUT` here is a **partial** update: fields you leave out keep their value.
To clear a field send `null` (or `""`).

```json
{
  "us_code": "6109100012",
  "no_code": null
}
```

That sets the US code, clears the Norway code and leaves everything else as it was. A record must
exist first — `PUT` does not create one (`404`).

## The list is a paginated envelope

`GET /api/v3/product-customs.json` does **not** return a bare array:

```json
{
  "total": 2,
  "page": 1,
  "limit": 100,
  "pages": 1,
  "_links": {"self": {"href": "..."}, "first": {"href": "..."}, "last": {"href": "..."}},
  "_embedded": {
    "resources": [ { "id": 12, "product_id": 101, "hs_code": "610910", "...": "..." } ]
  }
}
```

The records are under `_embedded.resources`. Only products that have customs data are listed.
`limit` maxes out at 100; page with `?page=N&limit=N`.

## Validation

Every refusal is a `400` naming the field, and nothing is written:

```json
{
  "code": 400,
  "message": "Validation Failed",
  "errors": {
    "hs_code": ["The HS code has 6 digits. You typed 5. A leading zero may have been removed, for example by a spreadsheet."],
    "origin_country": ["Unknown country code. Use a 2-letter ISO code such as PT."]
  }
}
```

| Request | Refused because |
|---|---|
| `"hs_code": "61091"` | the HS code has 6 digits |
| `"hs_code": "771000"` | chapter 77 (and 00, 98, 99) does not exist for goods |
| `"eu_code": "6109100"` | EU and Norway codes have 8 digits, UK and US codes 10 |
| `"origin_country": "PRT"` | the origin is a 2-letter code |
| a second `POST` for the same product | `_form`: the product already has customs data |
| any unrecognised field | `_form`: the payload rejects extra fields |

**Error messages come back in the shop's language**, not English. Branch on the field name and the
status code, never on the message text.

`404` has two meanings, told apart by `message`: the product does not exist (`Product not found`),
or it exists but has no customs data yet. The second is the normal answer for a product nobody has
classified — create the record with `POST`.

## By model

If your shop uses the model as SKU, address the record by model instead of the product id. Every
call above has a by-model twin with the same body, answers and scopes:

| What | Endpoint |
|---|---|
| Read one | `GET /api/v3/products/bymodel/{model}/customs.json` |
| Create | `POST /api/v3/products/bymodel/{model}/customs.json` |
| Update | `PUT /api/v3/products/bymodel/{model}/customs.json` |
| Delete | `DELETE /api/v3/products/bymodel/{model}/customs.json` |

The model may be the **product's** model or the model of **any of its variants** — models are
unique across both. Customs data stays per product, so every variant model of a product reaches the
same record: writing through one variant's model changes it for all of them.

A model with characters that are awkward in a path (`?`, `#`, `%`, spaces) can go in the query
string instead — URL-encode it:

```
PUT /api/v3/products/bymodel/customs.json?model=TS%2F100%20BLUE
```

| Answer | Means |
|---|---|
| `400` `Missing "model".` | the query form was used without `model` |
| `404` `Product not found` | no product or variant has this model |
| `404` on read, update or delete | the product exists but has no customs data yet |

## Deleting

`DELETE` answers `204` with an empty body. Invoices printed afterwards, including those of existing
orders, no longer show customs lines for the product.

## Bulk

For many products at once, use the CSV import in the admin (*Catalog → Customs data → Import CSV*,
columns `SKU`, `HsCode`, `EuCode`, `NoCode`, `GbCode`, `UsCode`, `OriginCountry`), or send these
calls — by id or by model — through `POST /api/v3/batches` (see `batches.md`).
