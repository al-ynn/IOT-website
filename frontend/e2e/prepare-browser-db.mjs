import { spawnSync } from "node:child_process";
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const directory = path.dirname(fileURLToPath(import.meta.url));
export const backendDirectory = path.resolve(directory, "../../backend");
export const browserDatabase = path.join(backendDirectory, "storage", "phase103-browser.sqlite");

export function browserBackendEnv(extra = {}) {
  return {
    ...process.env,
    APP_ENV: "testing",
    APP_DEBUG: "false",
    APP_KEY: "base64:UEhBU0UxMDNCUk9XU0VSUUFURVNUSEVZISE=",
    DB_CONNECTION: "sqlite",
    DB_DATABASE: browserDatabase,
    SESSION_DRIVER: "database",
    CACHE_STORE: "database",
    QUEUE_CONNECTION: "sync",
    ...extra,
  };
}

export function prepareBrowserTestDatabase() {
  fs.writeFileSync(browserDatabase, "");
  for (const args of [
    ["artisan", "migrate:fresh", "--force"],
    ["artisan", "db:seed", "--class=Database\\Seeders\\BrowserQaSeeder", "--force"],
    ["artisan", "db:seed", "--class=Database\\Seeders\\DevelopmentUserSeeder", "--force"],
  ]) {
    const result = spawnSync("php", args, { cwd: backendDirectory, env: browserBackendEnv(), encoding: "utf8" });
    if (result.status !== 0) throw new Error(`${result.stdout ?? ""}\n${result.stderr ?? ""}`);
  }
}
