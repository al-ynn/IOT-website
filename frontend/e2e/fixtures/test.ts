/* eslint-disable react-hooks/rules-of-hooks, no-unsafe-finally, no-empty, no-empty-pattern */
import { test as base, expect, type TestInfo } from "@playwright/test";
import { spawn, type ChildProcess } from "node:child_process";
import fs from "node:fs";
import net from "node:net";
import path from "node:path";
import { backendDirectory, browserBaseline, browserBackendEnv } from "../prepare-browser-db.mjs";

const port = 18000;
const runtimeDir = path.join(backendDirectory, "storage", "e2e-runtime");
const free = () => new Promise<boolean>(resolve => { const s = net.createServer().once("error", () => resolve(false)).once("listening", () => s.close(() => resolve(true))); s.listen(port, "127.0.0.1"); });
async function health(child: ChildProcess) { const until = Date.now() + 30_000; while (Date.now() < until) { if (child.exitCode !== null) throw new Error(`backend exited (${child.exitCode})`); try { if ((await fetch(`http://127.0.0.1:${port}/up`)).ok) return; } catch {} await new Promise(r => setTimeout(r, 100)); } throw new Error("backend health timeout"); }
async function stop(child: ChildProcess) { if (child.exitCode === null) { child.kill(); await new Promise<void>(resolve => child.once("close", () => resolve())); } }
function id(info: TestInfo) { return info.testId.replace(/[^A-Za-z0-9_-]/g, "_").slice(-100); }
function removeDatabaseFiles(db: string) {
  for (const suffix of ["", "-journal", "-wal", "-shm"]) {
    const file = `${db}${suffix}`;
    fs.rmSync(file, { force: true });
    if (fs.existsSync(file)) throw new Error(`E2E database artifact remained: ${file}`);
  }
}

export type IsolatedBackend = { databasePath: string };

export const test = base.extend<{ isolatedBackend: IsolatedBackend }>({
  isolatedBackend: [async ({}, use, info) => {
    if (!(await free())) throw new Error(`backend port ${port} is not free`);
    fs.mkdirSync(runtimeDir, { recursive: true });
    const db = path.join(runtimeDir, `${id(info)}-${info.workerIndex}.sqlite`);
    fs.copyFileSync(browserBaseline, db);
    const child = spawn("php", ["-S", `127.0.0.1:${port}`, "-t", "public", "public/index.php"], { cwd: backendDirectory, env: browserBackendEnv({ DB_DATABASE: db }), stdio: ["ignore", "ignore", "ignore"], windowsHide: true });
    try { await health(child); await use({ databasePath: db }); } finally { await stop(child); if (!(await free())) throw new Error(`backend port ${port} remained in use`); removeDatabaseFiles(db); }
  }, { auto: true }],
  page: async ({ page, isolatedBackend: _isolatedBackend }, use) => {
    void _isolatedBackend;
    try { await use(page); } finally { await page.close(); }
  },
});
export { expect };
