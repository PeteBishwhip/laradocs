import { test, expect } from '@playwright/test';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Strict coverage gate. Every feature declared in features.json must have its
 * spec file present in tests/e2e/. This reads the registry from disk and never
 * touches the network, so it does not depend on (or boot) the webServer.
 */
const e2eDir = dirname(fileURLToPath(import.meta.url));

type FeatureEntry = { spec: string };
const registry: Record<string, FeatureEntry> = JSON.parse(
  readFileSync(join(e2eDir, 'features.json'), 'utf-8'),
);

test.describe('feature registry coverage', () => {
  for (const [feature, { spec }] of Object.entries(registry)) {
    test(`"${feature}" has spec ${spec}`, () => {
      const specPath = join(e2eDir, spec);
      expect(
        existsSync(specPath),
        `Missing spec for feature "${feature}": expected tests/e2e/${spec} to exist`,
      ).toBe(true);
    });
  }
});
