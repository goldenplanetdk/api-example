# Meta fields

Meta fields are free key/value data stored on a product, grouped by namespace. The field
`price.volholduntil` is namespace `price`, key `volholduntil`. Themes can read them, which makes
them a simple way to show data from your own system on the storefront.

## Read product meta

Send GET request to `api/v3/products/<product_id>.json`:

```json
{
  "id": <product_id>,
  "title": "iPhone",
  ...
  "_embedded": {
    "meta": {
      "gpp": {
        "secret": "hello world!"
      },
      "price": {
        "volholduntil": "2026-12-31"
      }
    }
  }
}
```

## Write product meta

Send a PUT request to `api/v3/products/<product_id>.json` with a `meta` list:

```json
{
  "meta": [
    {
      "namespace": "gpp",
      "key": "secret",
      "value": "hello world!"
    },
    {
      "namespace": "price",
      "key": "volholduntil",
      "value": "2026-12-31"
    }
  ]
}
```

Writes are a list of `namespace`/`key`/`value` objects; reads come back grouped by namespace, as
shown above.

**`meta` replaces the product's whole meta list.**

- A field you leave out of the list is **deleted**. Send every field the product should keep.
- A PUT **without** `meta` leaves the meta fields alone, so you can update the price or the texts
  without touching them.
- `"meta": []` deletes every meta field of the product.

To change a single field, read the product, change the one value and send the full list back.
[`meta.php`](meta.php) does exactly that.

## Naming rules

- `namespace` and `key` take **lowercase latin letters and digits only**: no dots, dashes,
  underscores or capitals. `vol_hold` is refused:

  ```json
  {"code": 400, "message": "Validation Failed",
   "errors": {"meta": {"1": {"key": ["Use only lowercase latin letters and numbers"]}}}}
  ```

  The number (`"1"`) is the position of the rejected entry in your list. Nothing is saved when
  any entry is refused.
- One `namespace` + `key` pair can appear only once per product.
- Values are stored as text. Send dates and numbers in the form your theme should print them.

## On the storefront

A meta write shows on the storefront without an admin save. The shop refreshes the product in the
background right after the request, so allow a few seconds. See
[Storefront refresh after API writes](product.md#storefront-refresh-after-api-writes).

## Customers

For customers meta field approach is the same.
