# Filters

Comparison operators are in FIQL notation and some of them has an alternative syntax as well:

Equal to: ==

Not equal to: =!=

Less than: =lt=

Less than or equal to: =lte=

Greater than operator: =gt=

Greater than or equal to: =gte=

In: =in=  (supported by numeric fields)

Not in: =not-in= (supported by numeric fields)

Contains: =like= (supported by text fields)

Not contains: =not-like= (supported by text fields)

Empty: =null=

Not Empty: =not-null=

Expression could have several comparsion separated with ; which is equivalent for logic AND or , which is equivalent for logic OR. OR comparsions could be grouped with parenthesis

## Orders

Suppoted fields:
 
id: numeric, ID of order
name: text, name of customer
email: text, email of customer
customer: numeric, ID of customer
status: numeric, ID of order's status
created_at: date, created date 
modified_at: date, modified date 
model: text, product model

Examples

```
- /api/v3/orders.json?filter=id=gte=1017
- /api/v3/orders.json?filter=name=like=Yaroslav*
- /api/v3/orders.json?filter=customer==1507;email==user@openbizbox.com;(name==Angelina Doe,name==John Doe)
``
