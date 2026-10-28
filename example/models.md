In case if you prefer to work with models here is the list of useful endpointsthem:

## Model mapping

Get model to ID mapping using POST to `api/v3/products/mapping.json`:

```json
{
  "models": ["model1", "model2", "model3"]
}
```

Response example:

```json
[
  {
    "model": "model1",
    "product_id": 1,
    "variant_id": 1001
  },
  {
    "model": "model2",
    "product_id": 2,
    "variant_id": 1002
  },
  {
    "model": "model3",
    "product_id": 3,
    "variant_id": 1004
  }
]
```

## Update by model

If you know model of variant you can use it to update it. Send PUT request to `api/v3/products/bymodel/variants/<model>.json`:

```json
{
  "quantity": 10
}
```

## Customs data by model

Customs data (HS code, market codes, country of origin) can be read and written by the model of the
product or of any of its variants. Send PUT request to `api/v3/products/bymodel/<model>/customs.json`:

```json
{
  "hs_code": "610910",
  "origin_country": "PT"
}
```

`GET`, `POST` and `DELETE` work on the same path. A model that does not fit in a path can go in the
query instead: `api/v3/products/bymodel/customs.json?model=<url-encoded model>`. See
`product_customs.md` for the fields and the answers.
