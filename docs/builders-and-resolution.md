# Builders, header links, and resolution

## Building the sidebar

When [sections](sections.md) are not enough, implement `BuildsSidebar` and point `config/helpscout-sidebar.php` at it:

```php
namespace App\Support\HelpScout;

use Imazed\HelpScoutSidebar\Contracts\BuildsSidebar;
use Imazed\HelpScoutSidebar\Sidebar\Section;
use Imazed\HelpScoutSidebar\Sidebar\Sidebar;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;

class CustomerSidebar implements BuildsSidebar
{
    public function build(Sidebar $sidebar, mixed $customer, HelpScoutContext $context): Sidebar
    {
        return $sidebar
            ->title($customer->name)
            ->subtitle($customer->email)
            ->section('Account', function (Section $section) use ($customer): void {
                $section
                    ->row('Plan', $customer->plan)
                    ->row('Signed up', $customer->created_at->toFormattedDateString())
                    ->badge($customer->is_active ? 'Active' : 'Cancelled', $customer->is_active ? 'positive' : 'negative');
            })
            ->section('Billing', function (Section $section) use ($customer): void {
                $section
                    ->metric('Lifetime value', '$'.number_format($customer->lifetime_value))
                    ->link('Open in admin', route('admin.customers.show', $customer));
            });
    }
}
```

```php
'builder' => App\Support\HelpScout\CustomerSidebar::class,
```

Available on a `Section`: `row()`, `metric()`, `badge()`, `link()`, `when()`, plus two escape hatches. `blade()` renders one of your own views, `html()` inserts pre-rendered markup.

Everything added through the fluent methods is **escaped on render**. `blade()` and `html()` are not, by design. Only pass them content you control.



## Customizing customer resolution

### Adding an email source

Implement `ProvidesCustomerEmails` and add it to the chain:

```php
namespace App\Support\HelpScout;

use Imazed\HelpScoutSidebar\Contracts\ProvidesCustomerEmails;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;

class LegacyAliasProvider implements ProvidesCustomerEmails
{
    public function emails(HelpScoutContext $context): array
    {
        return LegacyAlias::query()
            ->where('helpscout_conversation_id', $context->conversationId())
            ->pluck('email')
            ->all();
    }
}
```

```php
'email_providers' => [
    ContextEmailProvider::class,
    App\Support\HelpScout\LegacyAliasProvider::class,
    MailboxApiEmailProvider::class,
    FallbackEmailProvider::class,
],
```

### Replacing resolution entirely

If matching by email is wrong for your data model, implement `CustomerResolver` instead:

```php
namespace App\Support\HelpScout;

use App\Models\Account;
use Imazed\HelpScoutSidebar\Contracts\CustomerResolver;
use Imazed\HelpScoutSidebar\Support\HelpScoutContext;

class AccountResolver implements CustomerResolver
{
    public function resolve(HelpScoutContext $context): ?Account
    {
        return Account::query()
            ->where('helpscout_customer_id', $context->customerId())
            ->first();
    }
}
```

```php
'resolver' => App\Support\HelpScout\AccountResolver::class,
```

This is the right shape if you already store Help Scout customer IDs against your own records. `customerId()` is Help Scout's identifier, not one of your primary keys; you need a column that maps between them, and something that populates it.
