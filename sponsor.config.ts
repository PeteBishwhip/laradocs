import { defineConfig } from 'sponsorkit'

export default defineConfig({
  // Write sponsors.svg / sponsors.png to the repo root so the README and the
  // Scheduler workflow's `add: sponsors.*` glob can find them.
  outputDir: '.',
  formats: ['svg', 'png'],

  // Pull sponsors from GitHub Sponsors. Token + login are supplied via the
  // SPONSORKIT_GITHUB_TOKEN / SPONSORKIT_GITHUB_LOGIN env vars in CI.
  github: {
    login: 'PeteBishwhip',
    type: 'user',
  },
})
