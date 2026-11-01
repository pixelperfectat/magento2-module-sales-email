# Changelog

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [1.0.0] - 2026-11-01

### Added

- Modern order confirmation email (customer and guest): short summary, compact items, totals, an invitation block, and address and payment details at the bottom.
- Modern shipment email (customer and guest) with a tracking strip and the shipped items.
- Switch per store view; a store view with the modern layout off keeps the core templates.
- Invitation block from a CMS block, sanitised to an email-safe allow-list at render time, with an admin warning on save that lists what will be removed; a safe default block `pp_sales_email_invitation`.
- One totals row per discount line when `pixelperfectat/magento2-module-discount-lines` is installed, the core discount row otherwise.
- Emails sent from the admin or by cron render in the order's store, language and timezone.
- Translations `en_US` and `de_DE`.
