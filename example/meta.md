# Meta fields

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
      }
    }
  }
}
```

### Create product meta

To create new meta send PUT request with such json to `api/v3/products/<product_id>.json`:

```json
{
  "meta": [
    {
      "namespace": "gpp",
      "key": "mykey",
      "value": "test"
    },
     {
      "namespace": "gpp",
      "key": "mykey2",
      "value": "test2"
    }
  ]
}
```

For customers meta field approach is the same
