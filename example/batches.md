At this moment there are 2 batch endpoints for products and variants: `api/products/variants/batches.json` and `api/products/batches.json`. 

Send multiple operations in array using POST to `api/products/batches.json`:

```json
[
  {
      "method": "PUT",
      "body":
      {
          "id": 11,
          "variants": [
              {
                "quantity": 4
              }
          ]
      }
  },
  {
      "method": "POST",
      "body":
      {
          "title": "New product",
          "main_category": 5,
          "is_enabled": true
      }
  }
]
```

First operation will update product with id 11, second operation will create new product. 

There is no limit how many operations could be inside one request, you are limited with only length of POST request on our server. But we advise to start from 1000.

If everything is valid batch command will be created and you will get ID of it in response.
Use this to make GET request to `api/batches/<ID>.json` in order to obtain current status of batch command. You should get response like this:

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


Also you can use `api/products/variants/batches.json` to update specific variant:

```json
[
    {
        "method": "POST",
        "body":
        {
            "product": 11,
            "quantity": 2,
            "attribute_values":[
              {"attributeValue": 8}
            ]
        }
    },
    {
        "method": "PUT",
        "body":
        {
            "id": 230,
            "quantity": 2
        }
    },
    {
        "method": "DELETE",
        "body":
        {
            "id": 231
        }
    }
]
```

So 1st operation creates new variant for product with id 11, 
and 2nd updates quantity of variant id:230, 3rd deletes variant:231
