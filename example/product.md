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
