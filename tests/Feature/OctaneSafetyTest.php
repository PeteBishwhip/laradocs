<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Laradocs\Macros\MacroRegistry;
use Laradocs\Seo\SeoFactory;
use Laradocs\Variables\VariableRegistry;

// ---------------------------------------------------------------------------
// These tests intentionally dispatch multiple requests through the SAME
// application instance within a single test case — this is the Octane
// scenario: a long-lived worker handles request after request without
// restarting, so singletons persist between them.
//
// Each $this->get() below re-uses $this->app, matching Octane behaviour.
// ---------------------------------------------------------------------------

it('variable registry holds only its boot-time values across consecutive requests (octane-safe)', function () {
    // Arrange: register a variable at boot time and verify it survives two
    // requests on the same worker without accumulating extra state.
    app(VariableRegistry::class)->set('org', 'Acme');
    $bootKeys = array_keys(app(VariableRegistry::class)->all());

    $this->makeDocs(['page.md' => '# Page']);

    // Request A
    $this->get('/docs/page')->assertOk();

    // Request B — same singleton, state must not have grown
    $this->get('/docs/page')->assertOk();

    expect(array_keys(app(VariableRegistry::class)->all()))->toBe($bootKeys);
});

it('boot-time variables are interpolated correctly on consecutive requests (octane-safe)', function () {
    app(VariableRegistry::class)->set('product', 'WidgetPro');

    $this->makeDocs(['page.md' => '# {{ product }}']);

    // Both requests see the same boot-time variable.
    $this->get('/docs/page')->assertOk()->assertSee('WidgetPro');
    $this->get('/docs/page')->assertOk()->assertSee('WidgetPro');
});

it('macro registry holds only its boot-time macros across consecutive requests (octane-safe)', function () {
    $bootMacros = app(MacroRegistry::class)->names();

    $this->makeDocs(['page.md' => '# Page']);

    // Request A
    $this->get('/docs/page')->assertOk();

    // Request B — the macro list must not have grown
    $this->get('/docs/page')->assertOk();

    expect(app(MacroRegistry::class)->names())->toBe($bootMacros);
});

it('SeoFactory x-card type does not bleed from one request to the next (octane-safe)', function () {
    $this->makeDocs([
        'custom-card.md' => implode("\n", [
            '---',
            'seo:',
            '  x_card: summary',
            '---',
            '# Custom Card',
            '',
            'Opening paragraph so the SEO excerpt resolver has content.',
        ]),
        'default-card.md' => implode("\n", [
            '# Default Card',
            '',
            'Opening paragraph.',
        ]),
    ]);

    // Request A: document with an explicit x_card in its seo block.
    // forDocument() must write 'summary' into the singleton's $lastXCard.
    $this->get('/docs/custom-card')->assertOk();
    expect(app(SeoFactory::class)->xCard())->toBe('summary');

    // Request B (same worker, same singleton): document with no x_card.
    // forDocument() always overwrites $lastXCard before xCard() is read, so
    // 'summary' from request A must NOT bleed through — the default must win.
    $this->get('/docs/default-card')->assertOk();
    expect(app(SeoFactory::class)->xCard())->toBe('summary_large_image');
});

it('resetForNextRequest() clears SeoFactory state to the default (octane-safe)', function () {
    $this->makeDocs([
        'custom-card.md' => implode("\n", [
            '---',
            'seo:',
            '  x_card: summary',
            '---',
            '# Custom Card',
            '',
            'Opening paragraph.',
        ]),
    ]);

    // Simulate a request that sets lastXCard to a non-default value.
    $this->get('/docs/custom-card')->assertOk();
    expect(app(SeoFactory::class)->xCard())->toBe('summary');

    // Simulate the Octane RequestReceived hook firing before the next request.
    app(SeoFactory::class)->resetForNextRequest();

    // The singleton is now clean regardless of what the previous request set.
    expect(app(SeoFactory::class)->xCard())->toBe('summary_large_image');
});

it('the registered Octane RequestReceived listener flushes SeoFactory state', function () {
    $this->makeDocs([
        'custom-card.md' => implode("\n", [
            '---',
            'seo:',
            '  x_card: summary',
            '---',
            '# Custom Card',
            '',
            'Opening paragraph.',
        ]),
    ]);

    // Put the singleton into a non-default state.
    $this->get('/docs/custom-card')->assertOk();
    expect(app(SeoFactory::class)->xCard())->toBe('summary');

    // The provider registers a listener keyed by Octane's event class name as
    // a string; dispatching that event name drives the same flush Octane would
    // perform at the start of the next request — without Octane installed.
    Event::dispatch('Laravel\Octane\Events\RequestReceived');

    expect(app(SeoFactory::class)->xCard())->toBe('summary_large_image');
});
