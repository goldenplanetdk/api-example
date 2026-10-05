(deprecated, use /api/batches)

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
  },
  {
      "method": "PUT",
      "body":
      {
          "id": "WSHOP999",
          "idField": "model",
          "variants":
          [
            {
              "quantity": 4
            }
          ]
      }
  }
]
```

1st operation will update the product with an id 11, 
2nd operation will create a new product. 
3rd operation will update the quantity for the product with model WSHOP999. MAKE SURE you have only one product with this model

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
    },
    {
        "method": "PUT",
        "body":
        {
            "id": "sm092",
            "idField": "model",
            "quantity": 2
        }
    },
    {
        "method": "PUT",
        "body":
        {
            "id": "34567899",
            "idField": "barcode",
            "quantity": 2
        }
    }
]
```

So 
1st operation creates a new variant for  the product with id 11, 
2nd updates the quantity of variant id:230, 
3rd deletes variant:231
4th updates quantity for the variant with model sm092. MAKE SURE you have only one variant with this model
5th updates quantity for the variant with barcode 34567899. MAKE SURE you have only one variant with this barcode


