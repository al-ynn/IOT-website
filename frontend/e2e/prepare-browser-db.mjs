import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const directory = path.dirname(fileURLToPath(import.meta.url));
export const backendDirectory = path.resolve(directory, "../../backend");
const browserRuntimeDirectory = path.join(backendDirectory, "storage", "e2e-runtime");
export const browserDatabase = path.join(browserRuntimeDirectory, "phase103-browser.sqlite");
export const browserBaseline = path.join(browserRuntimeDirectory, "phase103-browser-baseline.sqlite");

export function browserBackendEnv(extra = {}) {
  return {
    ...process.env,
    APP_ENV: "testing",
    APP_DEBUG: "false",
    // Laravel encrypted casts (including Webhook signing secrets) require a
    // decoded 32-byte application key. Keep the E2E key deterministic while
    // matching the production cipher requirement.
    APP_KEY: "base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=",
    DB_CONNECTION: "sqlite",
    DB_DATABASE: browserDatabase,
    SESSION_DRIVER: "database",
    CACHE_STORE: "database",
    QUEUE_CONNECTION: "sync",
    BROADCAST_CONNECTION: "log",
    ...extra,
  };
}

export function prepareBrowserTestDatabase() {
  fs.mkdirSync(browserRuntimeDirectory, { recursive: true });
  fs.writeFileSync(browserDatabase, "");
  for (const args of [
    ["artisan", "migrate:fresh", "--force"],
    ["artisan", "db:seed", "--class=Database\\Seeders\\BrowserQaSeeder", "--force"],
    ["artisan", "db:seed", "--class=Database\\Seeders\\DevelopmentUserSeeder", "--force"],
  ]) {
    const result = spawnSync("php", args, { cwd: backendDirectory, env: browserBackendEnv(), encoding: "utf8" });
    if (result.status !== 0) throw new Error(`${result.stdout ?? ""}\n${result.stderr ?? ""}`);
  }
  fs.copyFileSync(browserDatabase, browserBaseline);
}
