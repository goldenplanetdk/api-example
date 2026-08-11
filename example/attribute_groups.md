# Attribute groups

Attribute groups are the colour families an attribute value belongs to. The shop uses them to
draw the coloured boxes in category filters, which is why `code` is a hex colour and the column
is only seven characters wide.

Endpoint: `/api/v3/attributegroups.json` (v3 only). Values are assigned to groups from the
**value** side, through the `groups` field of the attribute value.

## List groups

`GET /api/v3/attributegroups.json`

```json
[
  {"id": 1, "title": "Black", "sorting": 0, "code": "#000000"},
  {"id": 2, "title": "Red", "sorting": 0, "code": "#FF0000"}
]
```

## Create a group

`POST /api/v3/attributegroups.json`

```json
{
  "title": "Warm colours",
  "sorting": 30,
  "code": "#FF6600"
}
```

Only `title` is required. `code` is optional, at most 7 characters, and **unique** across groups -
a duplicate or over-long value comes back as a 400 rather than a driver error.

## Read, update and delete

```
GET    /api/v3/attributegroups/<group_id>.json
PUT    /api/v3/attributegroups/<group_id>.json
DELETE /api/v3/attributegroups/<group_id>.json
```

`PUT` is a partial update - send only what you want to change:

```json
{"sorting": 50}
```

`DELETE` returns 204. The join table cascades, so the values simply lose that grouping; the
values themselves are untouched.

## Assign values to groups

Groups are set on the attribute value, and accept **either a group id or a group title**, mixed
freely in one request:

`POST /api/v3/attributes/<attribute_id>/values.json`

```json
{
  "title": "Crimson",
  "sorting": 10,
  "groups": [2, "Warm colours"]
}
```

- `2` resolves to the existing group with that id
- `"Warm colours"` is matched by title, and **created if no group has that title**

This is the same accept-an-id-or-a-title behaviour that `brand`, `supplier` and `categories`
already have on products.

The response echoes the resolved groups, so you can see what a title resolved to:

```json
{
  "id": 61,
  "sorting": 10,
  "title": "Crimson",
  "groups": [
    {"id": 2, "title": "Red", "sorting": 0, "code": "#FF0000"},
    {"id": 30, "title": "Warm colours", "sorting": 0}
  ]
}
```

## Change or clear a value's groups

`PUT /api/v3/attributes/<attribute_id>/values/<value_id>.json`

The list is replaced, so groups left out are removed:

```json
{
  "title": "Crimson",
  "sorting": 10,
  "groups": ["Warm colours"]
}
```

Sending `"groups": []` removes them all.

> **Careful: the value PUT replaces every field, not just the ones you send.** Omitting `title`
> fails validation, and omitting `sorting` nulls a NOT NULL column and returns a 500. Always
> resend the whole value. This is long-standing behaviour of the attribute value endpoint and
> is not specific to `groups` - the group CRUD endpoint above does support partial updates.

## Read a value's groups

`GET /api/v3/attributes/<attribute_id>/values/<value_id>.json`

```json
{
  "id": 6,
  "title": "Red",
  "sorting": 10,
  "groups": [{"id": 2, "title": "Red", "sorting": 0, "code": "#FF0000"}]
}
```

A value with no groups returns `"groups": []`.

## Things worth knowing

- An unknown group title is **created immediately**, so a typo leaves a real group behind in the
  shop. Read the list back after a bulk import.
- A title that matches an existing group is **reused**, never duplicated - so repeating the same
  title across many values is safe.
- Group titles are translatable. Matching is against the base title.
- Deleting a group does not delete the values in it.
