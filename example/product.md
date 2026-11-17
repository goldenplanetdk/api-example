# Product listing

## Filters

Get products created in last 5 seconds:

`api/v3/products.json?created_from=5 seconds`


Get products updated in last 2 days:

`api/v3/products.json?updated_from=2 days`


Get products updated after exact date in format `yyyy-mm-dd`:

`api/v3/products.json?updated_from=2019-02-28`


Get products updated before exact date in format `yyyy-mm-dd`:

`api/v3/products.json?updated_to=2019-02-28`


Get specific products with IDs equal 1,2 and 11:

`api/v3/products.json?ids=1,2,11`


Get products starting from some ID(e.g. 1200):

`api/v3/products.json?from=1200`

## Relations assignment

The assignment could be done by either id
```json
{
    "brand": 1,
    "supplier": 2
}
```

.. or title. If an entity is missed it will be auto-created:
```json
{
    "brand": "Apple",
    "supplier": "Good supplier Inc."
}
```

## Relative stock update via API

Send PUT request to the proper product's endpoint `api/v3/products/<product_id>.json`

```json
{
    "variants": [
      {
        "quantity_diff": -2
      }
    ]
}
```

## Localization
In order to get information in a non-default language or to change some localized field use  prefix with locale in URL, e.g. `de/api/v3/products.json`

## Cross-sells and Up-sells

To add some product as cross-sell send POST to the following endpoint `api/v3/products/<product_id>/xsells.json`
```json
{
    "product": <cross_product_id>,
    "sorting": 20 //optional
}
```

To add some product as up-sell send POST to the following endpoint `api/v3/products/<product_id>/extras.json`
```json
{
    "product": <cross_product_id>,
    "sorting": 20 //optional
}
```

To list cross-sells and up-sells use same endpoints with GET

## Storefront refresh after API writes

The storefront picks up a product written over the API by itself, with no save in the admin
needed. The shop refreshes the product in the background right after the request returns, so a
change shows within a few seconds rather than at once.

These writes refresh the product on their own:

- a product PUT (by id or by model) that changes any product field: title, short and long
  description, SEO title, meta description and keywords, note, price, `is_enabled` and so on, in
  any language (see [Localization](#localization))
- [meta fields](meta.md): adding, changing and removing them
- [features](features.md): `PUT`, `POST` and `DELETE` on `/features`, and the `features` field of
  a product PUT
- variants, categories and tier prices sent in a product PUT

A write that changes nothing, such as posting a feature the product already has, refreshes
nothing, and there is nothing to refresh.

Not covered yet: when products are deleted and created again, product lists such as the featured
products on the front page or related products may stay stale until the admin uses
*Misc. settings → Purge cache*. There is no API call to clear the storefront cache.
