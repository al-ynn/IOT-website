import {defineConfig} from "@playwright/test";
const chromePath = process.env.PLAYWRIGHT_CHROME_PATH
  ?? (process.platform === "win32"
    ? "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe"
    : "/usr/bin/google-chrome");

export default defineConfig({testDir:"./e2e",timeout:30_000,expect:{timeout:8_000},fullyParallel:false,workers:1,retries:0,reporter:"line",globalSetup:"./e2e/global-setup.ts",use:{baseURL:"http://127.0.0.1:15173",launchOptions:{executablePath:chromePath}}});
