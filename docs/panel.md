# Why the screens are shaped this way

Three of the host's faults live in its panel. Each one decided a shape here.

## Fault 4 — a resource with no policy allows everything

`AppPanelProvider` records that `strictAuthorization()` is deliberately withheld from the Admin
panel because `ChatConversation` and seven other resources have no policy. Filament returns
`allow()` when no policy exists, so "we wrote no delete policy" reads as "delete is on and nobody
decided".

So this package never relies on a default:

- `canViewAny()` is `PanelTenant::resolvable()` — a panel with no merchant has nothing to be about.
- `canView($record)` is `CustodyPolicy::agentMayWork($record, PanelTenant::current())`. Asked of the
  domain, not re-derived.
- Every other resource ability is `false`, by name, in `Concerns\DeniesUnpublishedResourceAbilities`.
- Every relation-manager ability is `false`, by name, including `canAssociate` and `canDissociate` —
  which are live on a `hasMany` and default open, and would move a line of somebody's transcript
  with no edit form and no audit row.
- `AgentDesk::canAccess()` and `ServiceQualityWidget::canView()` state their own guard. A page and a
  widget have the same hole and neither has a Filament policy at all.

`AuthorizationTest` asks every one of them by name, and asserts that no class declares a method
named after an ability outside the two concerns — a subclass method wins over a trait's, so a method
named for an ability would silently reopen it.

## Fault 8 — no transition guard, and fault 18 — two assignment paths

`ChatService::assignAgent` assigned in any state: a closed conversation reopened, `started_at` was
overwritten, and the resolution computed from it became wrong. `ChatAgentDashboard` did the same job
inline, checking `status === 'queued'` — a condition the service did not check — and skipping the
response time and the system message the service did write.

Both halves are answered here, and they are two halves rather than one:

**A move the conversation cannot make is not offered.** Each action's `visible()` asks
`ConversationState::canTransitionTo()`. A queued conversation offers *take* and *give up on it* and
not *reply* or *resolve*; an assigned one offers *reply* and *resolve* and not *give up on it*,
because a conversation an agent has seen never becomes nobody's again; a resolved one offers none of
them.

**And asking anyway is refused.** `Support\Apply` re-reads the conversation through
`FindConversation` and hands the domain what it finds, so the guard runs against the row rather than
against a copy of it on a screen. A conversation somebody else resolved between two clicks is
refused and the refusal is rendered.

Filament re-reads the record on every request, so the race cannot be produced from inside a single
Livewire request — which is why `ApplyTest` drives `Apply` directly. A guard nothing exercises is a
guard nobody knows works.

**And there is one assignment path.** The queue screen's *take the one who has waited longest* and
the record screen's *take it* are the same `Actions\AssignAgent` call through the same `Apply`.

## Nine refusals, all of them handled

`Support\Render::refusal()` is a `match` with no default arm over every case `RefusalReason`
publishes. A tenth case is a compile-time hole rather than a blank in a notification, and
`RenderTest` walks `cases()` so the hole is found by the suite.

Three of the nine are reachable from this panel — the gateway ones, when a safe action is asked of a
module nothing is bound to. The rest are reachable only in the race above, which is exactly why they
are rendered rather than assumed away.

**One refusal reads differently on purpose.** `RequestAction` persists the request *before* it
transmits, so `GatewayUnbound`, `GatewayUnreachable` and `GatewayRefused` all leave a row behind.
`Render::actionRequest()` says the row exists and nothing was done to the order. An operator told
"nothing was recorded" asks again, and a retry of a refund is what the request reference exists to
stop.

## Nothing is ever a zero it did not measure

Four of the host's faults were figures that had drifted from what they claimed to measure, and all
four read as healthy numbers.

| The domain says | The screen says | Never |
|---|---|---|
| `QueuePosition` → `null` | Not queued | `#1`, or `0` |
| `ServiceMeasurement` figure → `null` | — | `0s` |
| `ServiceSummary` mean → `null` | — and a sentence saying nothing has closed yet | `0` |
| no rating row | Not rated | `0/5` |

The widget also reports the count still open, out loud, so a good-looking mean is never a small
sample in disguise; and it counts the conversations nobody ever reached, because those are the ones
a service metric most needs to see and the ones the host recorded nothing at all for.

## The timeline names what it could not ask

`Queries\AssembleTimeline` returns `answered` and `skipped`. An empty timeline with four unbound
seams is four questions nobody answered, not a customer who has done nothing — so the page never
shows a bare empty table.

The domain puts an unbound source and a source that raised into the same `skipped` list. The panel
reads the seam registry back through `Support\Seams` to tell them apart: *payments: not connected*
needs somebody to bind a module, *payments: could not be asked* needs somebody to fix one.

The subject is the **participant**, not the conversation: another module can key on a customer, and
this module holds no mapping from a conversation to an order. After an erasure the participant
reference is the redaction token, shared by every erased conversation, so the page asks nobody and
says why — asking a payments module about `redacted` would be asking about the wrong person, loudly.

## Shapes that were rejected

- **A notes resource.** Notes carry an opaque subject, so one could list every note a merchant has,
  including notes about orders. Notes about an order belong on the order desk; here they are the
  conversation's own relation.
- **A rating action.** Only the participant can rate, proved by a claim this desk cannot see. A desk
  affordance would have to either forge one or bypass the check.
- **An erasure or retention button.** Person-wide and scheduled respectively, and a button that
  destroys transcripts does not belong next to a queue.
- **Measurements on the conversations listing.** Each row's figures are a query and each row's place
  is a count. They are on the record screen and on the queue, which is bounded by what one person is
  expected to work through.
- **A stored `agent_ref` allowlist or a role check.** Agency is standing on this merchant's
  conversation. The host's role check is fault 1.
