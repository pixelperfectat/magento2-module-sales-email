# Changelog

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- Module skeleton and store-view scoped configuration (modern layout switch, invitation CMS block, emails that show it).
- Translations `en_US` and `de_DE`.
- Order and shipment email templates (customer and guest) registered as the default templates; a store view with
  the modern layout switched off falls back to the core templates.
- Modern order confirmation email with compact items, totals (one row per discount line when
  `pixelperfectat/magento2-module-discount-lines` is installed), invitation block and address and payment details.
- Modern shipment email with tracking strip and shipped items.
- Email-safe invitation block (default CMS block `pp_sales_email_invitation`), sanitised at render with a warning on save.
- Documentation, screenshots and `ext-intl` requirement.
