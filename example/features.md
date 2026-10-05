# Product features

Features are product level attributes - "Material: Cotton" - as opposed to the attributes that
make up variants. They are managed entirely by title: an attribute or value that does not exist
yet is created on the fly, so no ids are needed anywhere.

Endpoint: `/api/v3/products/<product_id>/features.json` (v3 only).

The payload is always an **array of single key objects**, never one object, because a product may
carry several values of the same attribute:

```json
[{"Material": "Cotton"}, {"Material": "Wool"}, {"Origin": "Denmark"}]
```

## Read the features

`GET /api/v3/products/<product_id>/features.json`

```json
[
  {"Material": "Cotton"},
  {"Origin": "Denmark"}
]
```

## Replace the whole list

`PUT /api/v3/products/<product_id>/features.json`

```json
[
  {"Material": "Cotton"},
  {"Colour": "Blue"}
]
```

The resulting set is exactly what you sent - anything missing from the payload is removed.
Sending `[]` clears every feature.

## Add a feature, keeping the rest

`POST /api/v3/products/<product_id>/features.json`

```json
{"Origin": "Denmark"}
```

A bare object or an array both work. Posting a feature the product already has changes nothing,
so retries are safe.

## Remove features

`DELETE /api/v3/products/<product_id>/features.json`

```json
[{"Material": "Cotton"}]
```

The body carries the pairs to remove, so titles containing a slash - `Vægt/Weight` - work without
any URL encoding. A `null` value removes every value of that attribute:

```json
[{"Material": null}]
```

Removing something the product does not have is not an error, and never creates the missing
attribute.

## Ordering

**Reordering the array does not reorder anything.** A feature that is present both before and
after a `PUT` keeps its database row, so it keeps its position. This is deliberate: the row id is
stable, which matters if you reference features from an external system.

To change the order returned by the API, pass `reorder`:

`PUT /api/v3/products/<product_id>/features.json?reorder=1`

```json
[
  {"Warranty": "2 years"},
  {"Material": "Cotton"}
]
```

Every row is recreated in payload order, and **every feature id changes**. The whole rebuild runs
in one transaction, so if the request is rejected - see the validation note below - the product
keeps the features it had rather than being left empty.

**Neither of these affects the shop.** The storefront orders features by the attribute's own
`sorting`, which you set on the attribute itself:

`PUT /api/v3/attributes/<attribute_id>.json`

```json
{"sorting": 10}
```

That is a property of the attribute, so it reorders the feature on *every* product using it.

## Assigning by id instead of title

Both sides may be ids, which resolves to existing entities and never creates anything:

```json
[{"2": 6}]
```

An attribute value can also be addressed by its external id:

```json
[{"external_id": "ABC-123"}]
```

## Things worth knowing

- **Attribute titles match case-insensitively**, and `internal_title` is matched too, so
  `Material` and `material` reuse one attribute. **Value titles match case-sensitively**, so
  `Cotton` and `cotton` become two separate values. Be consistent with your casing.
- An attribute already used by the product's **variants** cannot also be a feature - the request
  fails with `Attribute X is already used in variants!`.
- Several values of one attribute stay separate in the API but the shop **groups them into a
  single row**, joined with `, ` - `[{"Material": "Cotton"}, {"Material": "Wool"}]` renders as
  `Material: Cotton, Wool`.
- On `/api/v1` and `/api/v2` the same data is read under `attributes`, and that shape carries
  **no attribute title at all** - only `attribute_id`. Use v3 if you want to work by title.
- Unknown attribute and value titles are created immediately, so a typo leaves a real attribute
  behind in the shop. Read the list back after a bulk import.
