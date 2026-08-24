# Adoption

## Attach the desk to a panel

```php
use Filament\Facades\Filament;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\CustomerServiceWorkspacePlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(
            CustomerServiceWorkspacePlugin::make()
                ->tenantUsing(fn (): string => (string) Filament::getTenant()?->getKey())
                ->agentUsing(fn (): string => (string) Filament::auth()->id()),
        );
}
```

Two resolvers, because they answer two questions.

`tenantUsing` names **the merchant**. Every domain query and action takes it as an argument, and
`Support\PanelTenant` is the only place the panel decides it. Omit it and the panel's own Filament
tenant is used. There is no fallback to "no merchant": a null tenant compiles to `where tenant_id is
null`, which lists exactly the orphan rows a scope exists to hide, so an unresolvable tenant raises
and every screen closes.

`agentUsing` names **who is at the desk**. It is an identity, never an entitlement — standing is the
conversation's own tenant, decided by the domain's `CustodyPolicy`. The host derived agency from
`hasRole(['super_admin', 'admin'])`, which made an admin of one merchant an agent on every other.
Omit it and the panel's authenticated user is used; resolve it to nothing and the desk is read-only
rather than broken, because a row saying somebody did it and naming nobody is worse than no row.

## Bind the seams, or do not

The domain ships nothing bound. With nothing bound:

- the timeline is empty and **says which sources were not asked, by name**;
- a safe action is recorded and the transmission refused, and the screen says the row exists.

Neither is an error state and neither needs a binding to be useful. Bind them in the host's config
when a module exists to answer:

```php
// config/customer-service-workspace.php
'seams' => [
    'timeline' => [
        'orders' => App\Support\OrdersTimeline::class,
        'payments' => null,
    ],
    'actions' => App\Support\SafeActionGateway::class,
],
```

A timeline source is asked about the **participant** — `subjectKind` is `participant` and
`subjectRef` is the customer reference the conversation carries. This module holds no mapping from a
conversation to an order; an agent who wants one names it on the request form.

## What the host still owns

- **Opening a conversation.** `Actions\OpenConversation` mints the reference and the participant's
  claim together and returns the claim once. A desk that could open one would be minting a
  customer's proof of standing on the customer's behalf. The `-livewire` package is the customer's
  side.
- **Erasure and retention.** `Actions\ForgetParticipant` is person-wide and belongs with the host's
  GDPR path; `Actions\RedactResolvedBefore` is a scheduled job, off until a window is configured.
- **Ratings.** Only the participant can give one, and only against a resolved conversation.
