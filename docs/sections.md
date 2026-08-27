# Sections

You can add sections to the Help Scout sidebar by adding them to `config/helpscout-sidebar.php`. Each section has a `title` and a list of `items`:

```php
'sections' => [
    [
        'title' => 'Account',
        'items' => [
            ['type' => 'row', 'label' => 'Customer since', 'value' => 'created_at', 'format' => 'date'],
            ['type' => 'row', 'label' => 'Email verified', 'value' => 'email_verified_at', 'format' => 'date'],
        ],
    ],
],
```

## Item types

| Type | Renders | Keys |
| --- | --- | --- |
| `row` | A label and value pair | `label`, `value`, optional `url` |
| `metric` | A prominent number | `label`, `value`, optional `description` |
| `badge` | A status pill | `value`, optional `label`, `tone`, `tones` |
| `note` | A full sentence | `value` or a literal `text` |
| `link` | A link out | `label`, `url`, optional `description` |
| `list` | One row per entry in a collection | `source`, `label`, `value`, optional `limit`, `value_format` |

`url` on a `row` or a `link` takes the same placeholders as [header links](header-links.md).

## Formats

`date`, `datetime`, `diff`, `currency`, `number`, `percent`, `bool`. Add `prefix` and `suffix` for symbols and units:

```php
['type' => 'metric', 'label' => 'MRR', 'value' => 'mrr', 'format' => 'currency', 'prefix' => '$'],
```

## Conditions

`when` takes a path and renders the section or item only when that path is truthy:

```php
['type' => 'row', 'label' => 'Cancelled', 'value' => 'cancelled_at', 'format' => 'date', 'when' => 'cancelled_at'],
```

An item whose value is missing is dropped, so most conditions are unnecessary. Logic beyond truthiness belongs in a [builder](builders-and-resolution.md#building-the-sidebar).

## Computed values

When a path is not enough, set `value` to an invokable class. It is called with the record:

```php
['type' => 'metric', 'label' => 'Lifetime value', 'value' => App\Support\LifetimeValue::class, 'format' => 'currency'],
```

```php
class LifetimeValue
{
    public function __invoke(mixed $customer): int
    {
        return $customer->orders()->sum('total');
    }
}
```

## Protected attributes

Never rendered, whatever the configuration says:

- anything listed in the model's `$hidden`
- `password`, `remember_token`, `api_token`, `secret`, and their obvious relatives

Each refusal is logged.

## Example sections

**Billing, with Laravel Cashier**

```php
[
    'title' => 'Billing',
    'when' => 'subscription',
    'items' => [
        ['type' => 'row', 'label' => 'Plan', 'value' => 'subscription.type'],
        ['type' => 'row', 'label' => 'Renews', 'value' => 'subscription.ends_at', 'format' => 'date'],
        ['type' => 'row', 'label' => 'Card', 'value' => 'pm_last_four', 'prefix' => 'Ending '],
        ['type' => 'badge', 'value' => 'subscription.stripe_status', 'tones' => [
            'active' => 'positive', 'past_due' => 'negative', 'trialing' => 'warning', 'canceled' => 'negative',
        ]],
        ['type' => 'link', 'label' => 'Open in Stripe', 'url' => 'https://dashboard.stripe.com/customers/{id}'],
    ],
],
```

**Recent orders**

```php
[
    'title' => 'Recent orders',
    'items' => [
        ['type' => 'list', 'source' => 'orders', 'limit' => 3,
         'label' => 'created_at', 'format' => 'diff', 'value' => 'total', 'value_format' => 'currency'],
    ],
],
```

Order in the relation, not here: `orders()` should carry its own `latest()`.

**Account health**

```php
[
    'title' => 'Account',
    'items' => [
        ['type' => 'row', 'label' => 'Customer since', 'value' => 'created_at', 'format' => 'date'],
        ['type' => 'row', 'label' => 'Last seen', 'value' => 'last_seen_at', 'format' => 'diff'],
        ['type' => 'metric', 'label' => 'Team members', 'value' => 'users_count'],
        ['type' => 'badge', 'label' => 'Unverified email', 'tone' => 'warning', 'when' => 'email_verified_at'],
    ],
],
```

## When nothing resolves

An item whose value is missing is dropped, and a section with no items left is dropped too. When every configured value is missing, the package renders the default builder output instead of a bare header and logs a warning:

```text
Help Scout sidebar rendered no content: sections are configured, but every value was missing on this record.
```

The shipped configuration starts with an `Account` section reading `created_at` and `email_verified_at`, which exist on a stock Laravel `User`. It is meant to be replaced, and it disappears cleanly on a model without those columns.

## Previewing in a browser

Point the development fallback at a real record:

```dotenv
HELPSCOUT_SIDEBAR_VERIFY_SIGNATURE=false

# The address of a record that exists in your application, not a placeholder.
HELPSCOUT_SIDEBAR_FALLBACK_EMAIL=
```

Then open `your-site.com/helpscout/sidebar`. Neither setting belongs in production. See [Local development](troubleshooting.md#local-development).

## Sections and builders do not combine

When you point `builder` at your own `BuildsSidebar` class, everything under `sections` is ignored.
