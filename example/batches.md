To create batch send multiple operations in array using POST to `api/batches.json`:

```json
[
  {
    "url": "/api/v3/products",
    "method": "POST",
    "body":
    {
      "title": "My shiny new product",
      "is_enabled": true,
      "variants": [
        {"quantity": 4}
      ]
    }
  },
  {
    "url": "/api/products/bymodel/variants/UNIQ-MODEL-99",
    "method": "PUT",
    "body":
    {
      "quantity": 4
    }
  },
  {
    "url": "/api/v3/products/bymodel/UNIQ-MODEL",
    "method": "PUT",
    "body":
    {
      "variants": [
        {"quantity": 4}
      ]
    }
  },
  {
    "url": "/api/v3/customers",
    "method": "POST",
    "body":
    {
      "name": "Luke Skywalker",
      "comment": "super cool",
      "email": "somedummy@email.tt",
      "street": "Unknown planet 1",
      "city": "Unknown city",
      "created_at": "2009-01-01 00:00:00",
      "modified_at": "2011-01-01 00:00:00",
      "zip": "123"
    }
  }
]
```

There is no limit on how many operations can be inside one request, you are limited  only by the length of POST request on our server. But we advise  starting from 1000.

If everything is valid batch command will be created and you will get ID of it in response.
Use this to make GET request to `api/batches/<ID>.json` in order to obtain the current status of the batch command. You should get a response like this:

```json
{
  "left": 1,
  "total": 200,
  "is_processed": false,
  "results": {
      "3": {
        "error": "There is no product with such id"
      }
  }
}
```
In this response 
`total` - number of all operations you sent,
`left` - how many left to process,
`is_processed` - general status of batch command,
`result` - if some of operations body contains an error you will find it here(this particular message says that in 3rd operation id of product you have specified doesn't exist)

