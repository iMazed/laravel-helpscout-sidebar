# Header links

You can add icons across the top of the sidebar, linking to the customer data elsewhere. They live in `config/helpscout-sidebar.php`, so they work without a builder:

```php
'links' => [
    ['label' => 'Open in admin', 'icon' => 'user', 'url' => 'https://example.com/admin/customers/{id}'],
    ['label' => 'Billing', 'icon' => 'card', 'url' => 'https://example.com/admin/customers/{id}/billing'],
],
```

URL placeholders:

| Placeholder | Filled from |
| --- | --- |
| `{id}` | The resolved record's key |
| `{email}` | The resolved record's email address |
| `{customer_id}` | Help Scout's customer ID |
| `{conversation_id}` | Help Scout's conversation ID |

Relative URLs are resolved against `link_base`, or `APP_URL` when that is null. Anything with its own scheme (an external dashboard, a `mailto:`) is left untouched:

```php
['label' => 'Open in admin', 'icon' => 'user', 'url' => '/admin/customers/{id}'],
```

Rules:

- `{customer_id}` and `{conversation_id}` are read through the signature, so they are only filled on a verified request.
- A link whose placeholder cannot be filled is dropped, not rendered broken.
- Every dropped link logs a warning naming the entry, the reason, and the placeholders that were available:

```text
Help Scout sidebar dropped a configured header link.
{"index":0,"reason":"the URL is missing or has a placeholder that could not be filled (available: {id}, {email})"}
```

Icons: `user`, `card`, `chart`, `cog`, `mail`, `ticket`, `external`. Unknown names fall back to `external`. All are inline SVG, so they cost no extra request. Labels render as `title` and as visually hidden text, so the meaning survives both a hover and a screen reader.

**These are links, not actions.** They open in a new tab as plain GET requests, and the iframe URL keeps working for anyone who captures it (see the [security model](security.md)).

A builder that calls `$sidebar->links([...])` replaces the configured set.