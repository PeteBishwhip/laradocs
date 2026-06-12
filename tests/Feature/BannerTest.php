<?php

declare(strict_types=1);

it('renders no banner when disabled', function () {
    config()->set('laradocs.ui.banner.enabled', false);
    config()->set('laradocs.ui.banner.message', 'Hello world');

    $this->get('/docs')->assertOk()->assertDontSee('laradocs-banner', false);
});

it('renders no banner when message is empty', function () {
    config()->set('laradocs.ui.banner.enabled', true);
    config()->set('laradocs.ui.banner.message', '');

    $this->get('/docs')->assertOk()->assertDontSee('laradocs-banner', false);
});

it('renders an info banner', function () {
    config()->set('laradocs.ui.banner.enabled', true);
    config()->set('laradocs.ui.banner.type', 'info');
    config()->set('laradocs.ui.banner.message', 'New version available.');

    $this->get('/docs')
        ->assertOk()
        ->assertSee('laradocs-banner laradocs-banner-info', false)
        ->assertSee('New version available.');
});

it('renders an alert banner', function () {
    config()->set('laradocs.ui.banner.enabled', true);
    config()->set('laradocs.ui.banner.type', 'alert');
    config()->set('laradocs.ui.banner.message', 'Scheduled maintenance tonight.');

    $this->get('/docs')
        ->assertOk()
        ->assertSee('laradocs-banner laradocs-banner-alert', false)
        ->assertSee('Scheduled maintenance tonight.');
});

it('renders a danger banner', function () {
    config()->set('laradocs.ui.banner.enabled', true);
    config()->set('laradocs.ui.banner.type', 'danger');
    config()->set('laradocs.ui.banner.message', 'Service degraded.');

    $this->get('/docs')
        ->assertOk()
        ->assertSee('laradocs-banner laradocs-banner-danger', false)
        ->assertSee('Service degraded.');
});

it('renders raw html in the banner message', function () {
    config()->set('laradocs.ui.banner.enabled', true);
    config()->set('laradocs.ui.banner.type', 'info');
    config()->set('laradocs.ui.banner.message', '<a href="/changelog">v2 is out</a> — see what\'s new.');

    $this->get('/docs')
        ->assertOk()
        ->assertSee('<a href="/changelog">v2 is out</a>', false)
        ->assertSee("see what's new.", false);
});
