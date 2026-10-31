# Sales Email for Magento 2

Modern order confirmation and shipment emails for Magento 2: a short friendly summary, compact items, totals, a
configurable invitation block (for example a newsletter invitation) and the address and payment details at the
bottom. Table-based, email-client safe markup, English and German copy.

## Configuration

Stores > Configuration > Sales > Modern Layout (store view scope):

| Setting | Default | Meaning |
|---|---|---|
| Use Modern Email Layout | Yes | No = this store view keeps using the core templates |
| Invitation Block > CMS Block | none | CMS block shown in the emails; empty = off |
| Invitation Block > Show In | order, shipment | Emails that include the invitation block |

## Installation

```
composer require pixelperfectat/magento2-module-sales-email
bin/magento setup:upgrade
```

## License

MIT
