# Runbook

## Every screen 403s, or the navigation is empty

The panel cannot name a merchant. `PanelTenant::current()` raises rather than falling back, because
`where('tenant_id', null)` compiles to `is null` and lists exactly the orphan rows a scope exists to
hide.

Set one: `CustomerServiceWorkspacePlugin::make()->tenantUsing(...)`, or give the panel Filament
tenancy. The message names the call.

## Nothing can be taken, replied to or noted, but everything reads

The panel cannot name an agent. `PanelAgent` resolves to nothing, so every action that would attribute
work to somebody is hidden and the desk is read-only.

Set one with `->agentUsing(...)`, or sign in. A blank agent reference is refused deliberately: a row
saying somebody did it and naming nobody is worse than no row.

## The timeline is empty and says so

Working as intended. The domain binds no timeline source, so with nothing configured every source is
reported as `not connected` — by name, on the page. Bind one in
`config/customer-service-workspace.php` under `seams.timeline`.

If a source reads `could not be asked` instead, it *is* bound and it raised. That is the source's
problem, not this panel's; the entry it would have contributed is missing and the page says so.

## A safe action says "recorded, not confirmed"

Also working as intended, and the important half is that **the row exists**. The domain writes the
request before it transmits. Three causes read apart on the notification:

- *nothing is bound to carry a request* — set `seams.actions`.
- *the owning module could not be reached* — it is bound and it raised. Retry after fixing it; the
  existing row is on the conversation's requests list.
- *the owning module refused it* — it answered, and the answer was no. Its message is on the row.

Do not ask again to "make sure": every submission mints a fresh request reference, and the reference
exists so a retry cannot authorise a second refund.

## An action is not offered and an agent expected it

Read the state badge. The transitions are `queued → assigned → resolved`, plus `abandoned` from
`queued` only.

- A queued conversation cannot be **resolved**. Nobody has answered it; it is *given up on* instead,
  and that is measured.
- An assigned conversation cannot be **given up on**. An agent has seen it.
- A resolved or abandoned conversation offers nothing. Both are terminal, and reopening one was the
  host's fault that rewrote resolution times.

## An action says "Refused"

Somebody else moved the conversation between the page load and the click. Nothing was written.
Reload; the screen will offer whatever the conversation can do now.

## An action says "Nothing happened — No such conversation."

The conversation is no longer this merchant's, or no longer there. It is the same answer as a
reference that never existed, deliberately: a refusal that differs publishes the row.

## A figure reads "—"

The domain has not measured it, which is not zero. A conversation still open has no resolution time;
one nobody has replied to has no first-reply time; a merchant with nothing closed has no means. The
widget says which in words underneath each figure.

If a figure that *should* exist reads `—`, the timestamp behind it was never written — check the
conversation's history, which records every move.

## The queue badge and the queue disagree

They cannot: both are `Queries\ListQueue` for the same merchant, counted on read. If they look
stale, the page was loaded before the queue changed. A place in the queue is never stored, so there
is no number to have gone wrong — the host stored one, read the global maximum without a lock to
assign it, and never cleared it on the way out.

## Performance

One `COUNT` per queued row on the queue screen, and one measurement query per record screen. Both are
bounded by what a person is expected to read. If a merchant's queue grows past that, the batched
position belongs in the domain, not in a cleverer loop here.
