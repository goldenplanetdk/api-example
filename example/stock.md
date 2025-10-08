To add stock presentation in physical warehouses there are Places/Warehouse - /api/v3/places.json
Using places there is possibility to specify stock information in each place per article/variant

send PUT request to variant PUT /api/v3/products/1/variants/1001.json

```json
{
      "place_stocks": [
        {
          "place": 1,
          "local_quantity": 2,
          "low_stock_level": 3,
          "stock_location": {
            "building": "B1"
          },
          "recommended_order_qty": 100
        }
      ]
}
```
