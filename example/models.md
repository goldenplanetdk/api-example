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