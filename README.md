# PGCO Sale (فروش ویژه)

A WordPress plugin for a "special sale" slider section and an announcement bar above the site header. The slider rows are picked from the tables of the [PGCO Tables](https://github.com/moeinoa/pgco-tables) plugin.

> افزونه‌ی «فروش ویژه»: اسلایدر محصولات فروش ویژه با تایمر معکوس و نوار تبلیغاتی بالای هدر. ردیف‌ها از جدول‌های افزونه‌ی PGCO Tables انتخاب می‌شوند.

## Features

- Slider of offers (title, price, WhatsApp / inquiry button) with an optional countdown to the campaign's end, in Jalali dates.
- Announcement bar above the header (badge, up to three texts, a link), printed automatically or by shortcode.
- Styles and script load in `<head>` / deferred only when needed.

## Requirements

WordPress 6.0+, PHP 8.0+, the PGCO Tables plugin (the rows come from its tables).

## Install

1. Copy the plugin folder to `wp-content/plugins/pgco-sale` and activate it.
2. Open **فروش ویژه** in the admin menu, choose the table rows, set the end date and save.
3. Add the shortcode where the section should appear.

## Shortcodes

```
[pgco_sale]        the slider section
[pgco_sale_bar]    the announcement bar
```

## Hooks

| Hook | Default | Purpose |
|---|---|---|
| `pgco_sale_enqueue_early` | detected from the page | force the style/script into `<head>` |
| `pgco_sale_heading_tag` | `h2` | heading tag of the section title |

## License

GPL-2.0-or-later, see [LICENSE](LICENSE).
