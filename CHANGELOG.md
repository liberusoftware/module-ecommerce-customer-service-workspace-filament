# Changelog

## 0.1.0

The support agent's desk, extracted alongside `ecommerce-customer-service-workspace` 0.1.0.

### Screens

- **Waiting.** The queue in arrival order from `Queries\ListQueue`, each place from
  `Queries\QueuePosition`, with this merchant's service quality from `Queries\MeasureService` above
  it. Taking the customer who has waited longest is `Actions\AssignAgent` — the same call the record
  screen makes, because the host had two assignment paths and they behaved differently.
- **Conversations.** Every conversation, keyed in the URL by the module-minted reference. Take it,
  reply, resolve, give up on it, mark the customer's lines read, write a note, ask another module to
  act. The transcript, the notes, the requests and the append-only history are the conversation's own
  relations.
- **What this customer has done.** `Queries\AssembleTimeline` about the participant, with every
  source that could not be asked named and its reason given.

### Decisions

- **A move that cannot be made is not offered, and asking anyway is refused.** Both halves, because a
  hidden button is not authorization. `Support\Apply` re-reads the conversation through
  `Queries\FindConversation` before every write, so the domain's guard runs against the row rather
  than against a screen.
- **Viewing is the domain's `CustodyPolicy`; every other ability is closed by name.** A resource with
  no policy allows everything, which is why the host's Admin panel cannot enable
  `strictAuthorization()` at all. Pages and widgets state their own guard for the same reason.
- **One `where` in the package** — the resource's tenant restriction. The queue, a place in it, the
  measurements, the notes and the timeline are all published queries, and the suite fails if a second
  `where` arrives.
- **Every absent figure renders as absent.** Not queued rather than first, unmeasured rather than
  zero, unrated rather than nought out of five. The conversations still open are excluded from every
  mean and counted out loud, and the ones nobody ever reached are counted rather than omitted.
- **All nine refusal reasons have a sentence**, in a `match` with no default arm, walked by the suite.
- **A request that was recorded but not sent says so.** The domain persists the request before
  transmitting, so a refusal there is not "nothing was recorded" — and an operator told otherwise
  asks again.
- **An unbound seam is told apart from one that raised**, by reading the seam registry back. The
  domain reports both as skipped, and the two need different next steps.
- **Nobody is asked about an erased participant.** The redaction token is shared by every erased
  conversation, so asking a bound module about it would be asking about the wrong person.

### Deliberately not shipped

- A rating action. Only the participant can rate, proved by a claim this desk cannot see.
- An erasure or a retention button. `Actions\ForgetParticipant` is a person-wide host path and
  `Actions\RedactResolvedBefore` is a scheduled policy; neither belongs next to a queue.
- A notes resource. A note's subject is opaque, so one would list notes about orders too, and those
  belong on the order desk.
- Measurements on the conversations listing. Each row would be a query; they are on the record screen
  and on the queue, which is bounded by what one person works through.
- Any dashboard widget registered panel-wide. The service quality widget is a header widget on the
  page that needs it.
