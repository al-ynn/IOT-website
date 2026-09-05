import { defineConfig } from "@playwright/test";
export default defineConfig({
  testDir:"./e2e",
  timeout:30_000,
  expect:{timeout:8_000},
  fullyParallel:false,
  workers:1,
  retries:0,
  reporter:[["line"],["html",{open:"never",outputFolder:"test-results/report"}]],
  outputDir:"test-results/artifacts",
  globalSetup:"./e2e/global-setup.ts",
  use:{baseURL:"http://127.0.0.1:15173",trace:"retain-on-failure",screenshot:"only-on-failure",video:"off",viewport:{width:1440,height:900},launchOptions:{executablePath:"C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe"}},
  // Browser acceptance owns application servers through e2e/run-browser-tests.mjs.
});
