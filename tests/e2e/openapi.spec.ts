import { expect, test } from '@playwright/test';

/**
 * Pillar A OpenAPI reference pages, rendered from the hand-crafted 3.1 fixture
 * in workbench/docs/api/openapi.yaml and served at /docs/api by the dedicated
 * `openapi` project (port 8005, LARADOCS_OPENAPI=true).
 *
 * The fixture deliberately exercises the 3.1-specific shapes the renderer must
 * survive: a nullable field written as `type: ["string", "null"]`, an
 * `examples` key, and `oneOf`/`allOf` composition. These specs assert the
 * rendered DOM so a regression in the operation/overview partials is caught.
 *
 * DOM contract (see resources/views/partials/openapi/*.blade.php):
 *   - overview groups operations into collapsible
 *     `<details id="section-{slug}">` resource sections whose summary carries an
 *     `<h2 id="tag-{slug}">` heading (tag name + endpoint count); expanding one
 *     reveals its `<ul class="laradocs-openapi-operation-list">`.
 *   - operation pages mount at summary-based slugs (see OperationSlugger) and
 *     render `<span class="laradocs-openapi-method method-{verb}">VERB</span>`,
 *     `<code class="laradocs-openapi-path">`, a
 *     `section.laradocs-openapi-parameters`, and per-status
 *     `<h3 id="response-{code}">` headings under `section.laradocs-openapi-responses`.
 */

test.describe('OpenAPI reference pages', () => {
    test('the overview groups operations by tag', async ({ page }) => {
        await page.goto('/docs/api');

        await expect(page.locator('.laradocs-openapi-overview')).toBeVisible();

        // Both fixture tags surface as their own collapsible resource section
        // with a tag heading (which also carries the endpoint count).
        const widgetsSection = page.locator('details#section-widgets');
        const ordersSection = page.locator('details#section-orders');
        const widgetsHeading = widgetsSection.locator('h2#tag-widgets');
        const ordersHeading = ordersSection.locator('h2#tag-orders');
        await expect(widgetsHeading).toBeVisible();
        await expect(widgetsHeading).toContainText('widgets');
        await expect(ordersHeading).toBeVisible();
        await expect(ordersHeading).toContainText('orders');

        // Sections start collapsed; expanding one reveals the operation list
        // for that tag, and the operations land under the *correct* group: the
        // widgets list carries the widget paths and not the order path, and
        // vice versa.
        await widgetsSection.locator('summary').click();
        await ordersSection.locator('summary').click();

        const widgetsList = widgetsSection.locator('ul.laradocs-openapi-operation-list');
        const ordersList = ordersSection.locator('ul.laradocs-openapi-operation-list');

        await expect(widgetsList.locator('.laradocs-openapi-path', { hasText: '/widgets' }).first()).toBeVisible();
        await expect(widgetsList).not.toContainText('/orders/{orderId}');
        await expect(ordersList.locator('.laradocs-openapi-path', { hasText: '/orders/{orderId}' })).toBeVisible();
        await expect(ordersList).not.toContainText('/widgets');
    });

    test('an operation page renders method, path, parameters and a response code', async ({ page }) => {
        // Operation slugs prefer the summary ("List all widgets") over the
        // operationId — see OperationSlugger.
        await page.goto('/docs/api/widgets/list-all-widgets');

        const operation = page.locator('.laradocs-openapi-operation');
        await expect(operation).toBeVisible();

        // HTTP method badge — the GET verb with its verb-specific class.
        const method = operation.locator('.laradocs-openapi-method.method-get');
        await expect(method).toBeVisible();
        await expect(method).toHaveText('GET');

        // Endpoint path.
        await expect(operation.locator('code.laradocs-openapi-path')).toHaveText('/widgets');

        // Parameters section (rendered as a list, not a <table>) with the
        // `status` query parameter from the fixture.
        const parameters = page.locator('section.laradocs-openapi-parameters');
        await expect(parameters.locator('h2#parameters')).toBeVisible();
        await expect(parameters.locator('.laradocs-openapi-param-name', { hasText: 'status' })).toBeVisible();

        // At least one response code renders under the Responses section.
        const responses = page.locator('section.laradocs-openapi-responses');
        await expect(responses.locator('h2#responses')).toBeVisible();
        await expect(responses.locator('h3#response-200')).toHaveText('200');
    });

    test('a nullable type-array field renders without a JavaScript console error', async ({ page }) => {
        const jsErrors: string[] = [];

        // Uncaught JS exceptions are the definitive "JavaScript error" signal.
        page.on('pageerror', (error) => jsErrors.push(error.message));

        // console.error is noisier — a failed asset request logs one too — so
        // only count messages that are not resource/network load failures.
        page.on('console', (message) => {
            if (message.type() !== 'error') {
                return;
            }
            const text = message.text();
            if (text.includes('Failed to load resource') || text.includes('net::')) {
                return;
            }
            jsErrors.push(text);
        });

        // The listWidgets response schema is an array of Widget, whose `notes`
        // property is the nullable `type: ["string", "null"]` field — the exact
        // 3.1 shape that must not break client-side rendering.
        await page.goto('/docs/api/widgets/list-all-widgets');
        await expect(page.locator('.laradocs-openapi-operation')).toBeVisible();
        await page.waitForLoadState('networkidle');

        expect(jsErrors, `Unexpected JavaScript errors: ${jsErrors.join(' | ')}`).toEqual([]);
    });
});
