# ecommerce-customer-service-workspace-filament

The support agent's desk. A Filament panel over
[`liberusoftware/ecommerce-customer-service-workspace`](https://github.com/liberusoftware/module-ecommerce-customer-service-workspace):
the customers waiting, the conversation an agent answers, its transcript, the notes, what other
modules hold about the customer, what this workspace has asked those modules to do, and how the
service is going.

It is a one-to-one adapter. It contains no business rules: every decision is a published domain
action, query or policy, and every figure is one the domain takes on read.

## The three faults it is shaped around

**A resource with no policy is a resource that allows everything.** The host's Admin panel cannot
turn `strictAuthorization()` on, and its provider says why: `ChatConversation` and seven other
resources have no policy, and Filament falls through to `allow()` for an ability nobody covered. So
here, viewing is answered by the domain's `CustodyPolicy`, every other ability is forced closed by
name, and each page and widget states its own guard. That is a test, not a convention.

**A hidden button is not authorization.** The host assigned an agent to a conversation in any state,
so a closed one reopened and its resolution time was rewritten — and its panel dashboard did the
same job inline against a *different* condition. Here a move the conversation cannot make is not
offered, *and* every write re-reads the conversation through `FindConversation` and lets the domain
decide, so asking anyway is refused rather than done.

**A panel that re-implements the service is a second service.** Nothing on these screens writes a
query against the module's tables except the resource's own tenant restriction — one `where` in the
package, and the suite fails if a second arrives.

## What it publishes

| | |
|---|---|
| `CustomerServiceWorkspacePlugin` | The entry point. The host attaches it to the panels it means to. |
| **Waiting** | The queue in arrival order, each place derived on read, with this merchant's service quality above it. Take the one who has waited longest. |
| **Conversations** | Every conversation, its transcript, its notes, what has been asked of other modules, and its history. Take it, reply, resolve, give up on it, note it, ask. |
| **What this customer has done** | The timeline, assembled from named seams at the moment of reading, with every source that could not be asked named and explained. |

## What it does not do

- **No figure is ever a zero it did not measure.** A duration the domain could not take renders as
  unmeasured, a conversation out of the queue as not queued, an unrated conversation as unrated. The
  host's response time was written at assignment, its resolution time excluded the whole queue wait,
  and a conversation abandoned in the queue recorded nothing at all.
- **No rating.** A rating is bound to a resolved conversation and given once by the participant,
  proved by a claim this desk does not have and cannot see.
- **No erasure and no retention screen.** `ForgetParticipant` is a person-wide host path, and
  `RedactResolvedBefore` is a scheduled policy the host configures — a merchant desk is the wrong
  place for a button that destroys transcripts.
- **No create, no edit, no delete, anywhere.** A conversation is opened by a customer, every change
  to one is a guarded transition, and its messages, notes and events are append-only in the domain.
- **No adapter.** No order module, no payment module, no refund. A safe action is recorded, handed
  to whatever the host bound, and the answer recorded against it.

## Installing

```bash
composer require liberusoftware/ecommerce-customer-service-workspace-filament
```

Nothing boots on install: the module manager registers the provider when the module is named in
`MODULES_ENABLED`. Attaching the desk to a panel is one call — see [`docs/adoption.md`](docs/adoption.md).

Why every screen is shaped the way it is, including the shapes that were rejected, is in
[`docs/panel.md`](docs/panel.md). What breaks and what to do about it is in
[`docs/runbook.md`](docs/runbook.md).
