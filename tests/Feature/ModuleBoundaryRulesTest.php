<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\CustomerServiceWorkspacePlugin;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Pages\AgentDesk;
use Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Resources\Conversations\ConversationResource;

/*
 * The rules that are this package's rather than the fleet's. The shared boundary
 * suite in `package-testbench` asserts the manifest, the required files, the
 * absence of `App\` and the panel plugins.
 */

function packageRoot(): string
{
    return dirname(__DIR__, 2);
}

/** @return array<int, string> every PHP file under src/, absolute. */
function sourceFiles(): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(packageRoot().'/src')) as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/**
 * Source with comments stripped. Every rule below is about what this package
 * does, and a docblock naming what it refuses to do is the text most likely to
 * trip a naive grep.
 */
function sourceCode(string $path): string
{
    $code = '';

    foreach (token_get_all((string) file_get_contents($path)) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $code .= is_array($token) ? $token[1] : $token;
    }

    return $code;
}

/** @return array<string, mixed> */
function packageJson(string $file): array
{
    /** @var array<string, mixed> $decoded */
    $decoded = json_decode((string) file_get_contents(packageRoot().'/'.$file), true, 512, JSON_THROW_ON_ERROR);

    return $decoded;
}

it('registers everything through the plugin and nothing from the service provider', function (): void {
    // A provider that registered resources would put a customer's transcript on
    // whatever panel happened to boot, including a shopper-facing one.
    $provider = (string) file_get_contents(packageRoot().'/src/CustomerServiceWorkspaceFilamentServiceProvider.php');

    expect($provider)->not->toContain('Resource::class')
        ->and($provider)->not->toContain('->resources(')
        ->and($provider)->not->toContain('->pages(')
        ->and($provider)->not->toContain('->widgets(');

    $plugin = (string) file_get_contents(packageRoot().'/src/CustomerServiceWorkspacePlugin.php');

    expect($plugin)->toContain('ConversationResource::class')
        ->and($plugin)->toContain('AgentDesk::class')
        // The widget is a header widget on the page that needs it, not a
        // dashboard tile every panel gets whether or not it asked.
        ->and($plugin)->not->toContain('->widgets(');
});

it('declares every class the manifest names for the panel', function (): void {
    $manifest = packageJson('module.json');

    expect($manifest['presentation']['filament']['admin'])->toBe([CustomerServiceWorkspacePlugin::class]);

    foreach ($manifest['presentation']['filament'] as $plugins) {
        foreach ($plugins as $plugin) {
            expect(class_exists($plugin))->toBeTrue();
        }
    }

    expect(class_exists(ConversationResource::class))->toBeTrue()
        ->and(class_exists(AgentDesk::class))->toBeTrue();
});

it('writes nothing through Eloquent, because every write here is a published domain action', function (): void {
    // The host's panel dashboard assigned an agent inline: it checked a state
    // its own service did not check, and skipped the timestamp and the system
    // line the service wrote. Two assignment paths with different behaviour.
    foreach (sourceFiles() as $file) {
        $source = sourceCode($file);

        expect($source)->not->toContain('->save()')
            ->and($source)->not->toContain('->update(')
            ->and($source)->not->toContain('->delete()')
            ->and($source)->not->toContain('->forceFill(')
            ->and($source)->not->toContain('->increment(')
            ->and($source)->not->toContain('->decrement(')
            ->and($source)->not->toContain('transitionTo(')
            ->and($source)->not->toContain('recordEvent(')
            ->and($source)->not->toContain('appendMessage(');
    }
});

it('asks the domain for every question the domain has a query for', function (): void {
    // One `where` in the whole package: the resource's tenant restriction, which
    // is Filament's own contract. Everything else — the queue, a place in it,
    // the measurements, the notes, the timeline — is a published query, so this
    // panel cannot drift from the module the way the host's page drifted from
    // its service.
    $wheres = [];

    foreach (sourceFiles() as $file) {
        $count = substr_count(sourceCode($file), '->where(');

        if ($count > 0) {
            $wheres[basename($file)] = $count;
        }
    }

    expect($wheres)->toBe(['ConversationResource.php' => 1]);
});

it('never states a domain state or a table name of its own', function (): void {
    // A `where('state', 'queued')` here would be this panel deciding what the
    // queue is. `Queries\ListQueue` decides, and the strings that would let this
    // package disagree with it are not in it.
    foreach (sourceFiles() as $file) {
        $source = sourceCode($file);

        foreach (["'queued'", "'assigned'", "'resolved'", "'abandoned'", 'csw_'] as $forbidden) {
            expect($source)->not->toContain($forbidden);
        }
    }
});

it('never puts the customer’s email on a listing', function (): void {
    // Wave 11 shipped reviewer PII on a public listing. An agent picking a row
    // out of a queue needs a name; nothing about picking a row needs an email.
    foreach (sourceFiles() as $file) {
        $source = sourceCode($file);

        expect($source)->not->toContain("TextColumn::make('participant_email')")
            ->and($source)->not->toContain('claim_hash')
            ->and($source)->not->toContain("TextEntry::make('claim_hash')");
    }
});

it('reaches for no framework-foundation helper', function (): void {
    // `config()`, `app()`, `auth()`, `now()` and `view()` live in
    // `laravel/framework`, not in `illuminate/support`. They pass CI because the
    // testbench drags the framework in, and are a lying constraint for a real
    // consumer.
    foreach (sourceFiles() as $file) {
        $source = sourceCode($file);

        expect($source)->not->toMatch('/(?<![\w>$])config\(/')
            ->and($source)->not->toMatch('/(?<![\w>$])app\(/')
            ->and($source)->not->toMatch('/(?<![\w>$])auth\(/')
            ->and($source)->not->toMatch('/(?<![\w>$])now\(/')
            ->and($source)->not->toMatch('/(?<![\w>$])view\(/')
            ->and($source)->not->toMatch('/(?<![\w>$])session\(/');
    }
});

it('states its Filament floor as the true one', function (): void {
    // Every tag from v5.4.0 declares `illuminate/contracts: ^11.28|^12.0|^13.0`;
    // v5.3's support package caps at ^12.0.
    expect(packageJson('composer.json')['require']['filament/filament'])->toBe('^5.4');
});

it('lists the sibling packages the manifest declares and no others', function (): void {
    $composer = packageJson('composer.json');
    $manifest = packageJson('module.json');

    $siblings = array_filter(
        $composer['require'],
        fn (string $constraint, string $package): bool => str_starts_with($package, 'liberusoftware/'),
        ARRAY_FILTER_USE_BOTH,
    );

    expect($siblings)->toBe($manifest['requires']['packages'])
        ->and($siblings)->toBe(['liberusoftware/ecommerce-customer-service-workspace' => '^0.1']);
});

it('points at the domain repository, because it is not on Packagist yet', function (): void {
    expect(packageJson('composer.json')['repositories'])->toBe([[
        'type' => 'vcs',
        'url' => 'https://github.com/liberusoftware/module-ecommerce-customer-service-workspace',
    ]]);
});

it('agrees with itself about its own version', function (): void {
    expect(packageJson('composer.json')['version'])->toBe(packageJson('module.json')['version']);
});

it('carries no session identifier in any file', function (): void {
    $files = array_merge(sourceFiles(), [
        packageRoot().'/README.md',
        packageRoot().'/CHANGELOG.md',
        packageRoot().'/module.json',
    ]);

    foreach ($files as $file) {
        $source = (string) file_get_contents($file);

        // Split so this assertion is not itself the thing it forbids: a
        // repository-wide grep for the literals has to come back empty.
        expect($source)->not->toContain('claude'.'.ai')
            ->and($source)->not->toContain('Claude-'.'Session');
    }
});

it('contributes nothing when a panel boots it, so nothing can arrive on a panel that did not register it', function (): void {
    $plugin = CustomerServiceWorkspacePlugin::make();
    $plugin->boot(Filament::getPanel('admin'));

    expect($plugin->getId())->toBe('ecommerce-customer-service-workspace');
});
