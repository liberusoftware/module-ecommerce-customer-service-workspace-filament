<?php

declare(strict_types=1);

use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Concerns\DeniesUnpublishedRelationAbilities;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Concerns\DeniesUnpublishedResourceAbilities;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Pages\AgentDesk;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\ConversationResource;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\Pages\ConversationTimeline;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\RelationManagers\HistoryRelationManager;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\RelationManagers\NotesRelationManager;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\RelationManagers\RequestsRelationManager;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\RelationManagers\TranscriptRelationManager;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Support\PanelTenant;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Tests\TestTenant;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Widgets\ServiceQualityWidget;
use Liberu\Ecommerce\CustomerServiceWorkspace\Models\Conversation;

/*
 * The ability matrix, asserted by name.
 *
 * A missing policy is permissive, and a policy that exists but lacks the method
 * asked about is also permissive, because Filament falls through to `allow()`.
 * That is why the host's Admin panel cannot enable `strictAuthorization()` at
 * all: `ChatConversation` is the first of eight resources there with no policy,
 * so turning it on would close screens that are open today.
 *
 * The only way to know an ability is closed is to ask it and be told no.
 */

$managers = [
    [TranscriptRelationManager::class],
    [NotesRelationManager::class],
    [RequestsRelationManager::class],
    [HistoryRelationManager::class],
];

it('closes every destructive resource ability by name', function (): void {
    // A conversation is somebody's transcript, its event ledger is append-only
    // in the domain, and its timings are what a service metric is made of.
    // There is nothing here a desk may drop.
    $record = new Conversation();

    expect(ConversationResource::canDelete($record))->toBeFalse()
        ->and(ConversationResource::canDeleteAny())->toBeFalse()
        ->and(ConversationResource::canForceDelete($record))->toBeFalse()
        ->and(ConversationResource::canForceDeleteAny())->toBeFalse()
        ->and(ConversationResource::canReorder())->toBeFalse()
        ->and(ConversationResource::canReplicate($record))->toBeFalse()
        ->and(ConversationResource::canRestore($record))->toBeFalse()
        ->and(ConversationResource::canRestoreAny())->toBeFalse();
});

it('publishes no Filament create and no Filament edit, because every write here is a domain action', function (): void {
    // A create page would mint a participant's claim on the participant's
    // behalf, and an edit form would assign a state directly — skipping the
    // transition guard, the audit row and the timings in one move.
    expect(ConversationResource::canCreate())->toBeFalse()
        ->and(ConversationResource::canEdit(new Conversation()))->toBeFalse();
});

it('registers no create and no edit page on the resource', function (): void {
    expect(array_keys(ConversationResource::getPages()))->toBe(['index', 'view', 'timeline']);
});

it('answers viewing with the domain policy rather than by defaulting to allow', function (): void {
    $mine = conversation(TestTenant::PRIMARY);
    $theirs = conversation(TestTenant::OTHER);

    expect(ConversationResource::canViewAny())->toBeTrue()
        ->and(ConversationResource::canView($mine))->toBeTrue()
        // Not a different refusal and not a different code: standing is the
        // conversation's own tenant, never a role name.
        ->and(ConversationResource::canView($theirs))->toBeFalse();
});

it('refuses to view anything at all when the panel has no merchant', function (): void {
    $mine = conversation(TestTenant::PRIMARY);

    PanelTenant::resolveUsing(fn (): ?string => null);

    expect(ConversationResource::canViewAny())->toBeFalse()
        ->and(ConversationResource::canView($mine))->toBeFalse()
        ->and(AgentDesk::canAccess())->toBeFalse()
        ->and(AgentDesk::getNavigationBadge())->toBeNull()
        ->and(ServiceQualityWidget::canView())->toBeFalse();
});

it('answers a model that is not a conversation with no', function (): void {
    // `canView` takes Filament's `Model`. A resource that assumed its own model
    // would fatal rather than refuse.
    expect(ConversationResource::canView(new Illuminate\Foundation\Auth\User()))->toBeFalse();
});

it('closes every relation-manager ability by name, including associate and dissociate', function (string $manager): void {
    /** @var object $instance */
    $instance = new $manager();
    $record = new Conversation();

    // `canAssociate` and `canDissociate` are live on a `hasMany` and default
    // open. Dissociating a message from its conversation would move a line of
    // somebody's transcript with no edit form and no audit row.
    expect($instance->canAssociate())->toBeFalse()
        ->and($instance->canDissociate($record))->toBeFalse()
        ->and($instance->canDissociateAny())->toBeFalse()
        ->and($instance->canAttach())->toBeFalse()
        ->and($instance->canDetach($record))->toBeFalse()
        ->and($instance->canDetachAny())->toBeFalse()
        ->and($instance->canCreate())->toBeFalse()
        ->and($instance->canEdit($record))->toBeFalse()
        ->and($instance->canDelete($record))->toBeFalse()
        ->and($instance->canDeleteAny())->toBeFalse()
        ->and($instance->canForceDelete($record))->toBeFalse()
        ->and($instance->canForceDeleteAny())->toBeFalse()
        ->and($instance->canReorder())->toBeFalse()
        ->and($instance->canReplicate($record))->toBeFalse()
        ->and($instance->canRestore($record))->toBeFalse()
        ->and($instance->canRestoreAny())->toBeFalse()
        ->and($instance->canView($record))->toBeFalse()
        // The one ability published, stated rather than inherited.
        ->and($instance->canViewAny())->toBeTrue();
})->with($managers);

it('names no method after a Filament ability outside the two concerns that close them', function (string $class): void {
    // A subclass method wins over a trait's, so a method named for an ability
    // would silently reopen it.
    $abilities = array_diff(
        array_merge(
            get_class_methods(DeniesUnpublishedResourceAbilities::class),
            get_class_methods(DeniesUnpublishedRelationAbilities::class),
        ),
        // The two the resource states and answers with the domain's policy.
        ['canView', 'canViewAny'],
    );

    // A trait's methods report the using class as their declaring class, so the
    // file is what separates "this class wrote it" from "the trait did".
    $reflection = new ReflectionClass($class);
    $file = $reflection->getFileName();

    $declared = array_map(
        fn (ReflectionMethod $method): string => $method->getName(),
        array_filter(
            $reflection->getMethods(),
            fn (ReflectionMethod $method): bool => $method->getFileName() === $file,
        ),
    );

    expect(array_intersect($declared, $abilities))->toBe([]);
})->with(array_merge([[ConversationResource::class]], $managers));

it('applies a closing concern to every resource and relation manager the plugin registers', function (): void {
    // A new screen cannot arrive open: the concern is what makes an ability
    // nobody thought about closed rather than allowed.
    expect(in_array(DeniesUnpublishedResourceAbilities::class, class_uses(ConversationResource::class), true))->toBeTrue();

    foreach ([TranscriptRelationManager::class, NotesRelationManager::class, RequestsRelationManager::class, HistoryRelationManager::class] as $manager) {
        expect(in_array(DeniesUnpublishedRelationAbilities::class, class_uses($manager), true))->toBeTrue();
    }
});

it('guards every page and widget it ships, and not by relying on a policy nobody wrote', function (): void {
    // Fault 4 is a resource with no policy in a panel where that means allow.
    // A page and a widget have the same hole, and neither has a Filament policy
    // at all — so each states its own guard and each is asked for it here.
    expect(AgentDesk::canAccess())->toBeTrue()
        ->and(ServiceQualityWidget::canView())->toBeTrue();

    // A resource page authorizes through its resource, so the timeline is
    // closed by the same answer rather than by a second rule.
    ConversationTimeline::authorizeResourceAccess();

    PanelTenant::resolveUsing(fn (): ?string => null);

    expect(fn () => ConversationTimeline::authorizeResourceAccess())
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});
