# Filters

Comparison operators are in FIQL notation and some of them have an alternative syntax as well:

Equal to: ==

Not equal to: =!=

Less than: =lt=

Less than or equal to: =lte=

Greater than operator: =gt=

Greater than or equal to: =gte=

In: =in=

Not in: =not-in=

Contains: =like= (supported by text fields)

Not contains: =not-like= (supported by text fields)

Empty: =null=

Not Empty: =not-null=

The expression could have several comparisons separated with `;` which is equivalent for logic `AND` or `,` which is equivalent to logic `OR`. `OR` comparisons could be grouped with parenthesis: `(conditionA,conditionB)`

## Orders

Supported fields:
 
`id`: numeric type, ID of order

`name`: text, name of customer

`email`: text, email of customer

`customer`: numeric, ID of customer

`status`: numeric, ID of order's status

`created_at`: date, created date 

`modified_at`: date, modified date 

`model`: text, product model

`shipping_method`: numeric type, ID of shipping module


Examples

```
- /api/v3/orders.json?filter=id=gte=1017
- /api/v3/orders.json?filter=name=like=Yaroslav*
- /api/v3/orders.json?filter=customer==1507;email==user@openbizbox.com;(name==Angelina Doe,name==John Doe)
- /api/v3/orders.json?filter=customer=in=(1507,1509)
- /api/v3/orders.json?filter=email=in=(olekirk@openbibox.com,ud@example.org)
```
