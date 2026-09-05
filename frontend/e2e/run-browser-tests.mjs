import { spawn } from "node:child_process";
import net from "node:net";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { browserBackendEnv, backendDirectory, prepareBrowserTestDatabase } from "./prepare-browser-db.mjs";

const frontendDirectory = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const backendPort = 18000;
const frontendPort = 15173;

function isPortFree(port) {
  return new Promise((resolve) => {
    const server = net.createServer().once("error", () => resolve(false)).once("listening", () => server.close(() => resolve(true)));
    server.listen(port, "127.0.0.1");
  });
}
async function waitFor(url, child, timeout = 60_000) {
  const started = Date.now();
  while (Date.now() - started < timeout) {
    if (child.exitCode !== null) throw new Error(`Server exited before readiness (code ${child.exitCode}, signal ${child.signalCode ?? "none"})`);
    try { const response = await fetch(url); if (response.ok) return; } catch { /* retry */ }
    await new Promise((resolve) => setTimeout(resolve, 250));
  }
  throw new Error(`Timed out waiting for ${url}`);
}
function start(command, args, options) {
  const child = spawn(command, args, { ...options, stdio: ["ignore", "inherit", "inherit"], windowsHide: true });
  child.on("error", (error) => console.error(`[harness] child error: ${error.message}`));
  return child;
}
function stop(child, label) {
  if (!child || child.exitCode !== null) return Promise.resolve({ code: child?.exitCode ?? 0, signal: child?.signalCode ?? null });
  child.kill();
  return new Promise((resolve) => child.once("close", (code, signal) => { console.log(`[harness] ${label} stopped code=${code} signal=${signal ?? "none"}`); resolve({ code, signal }); }));
}
async function main() {
  let frontend; let playwright; let resultCode = 1;
  try {
    for (const [port, label] of [[backendPort, "backend"], [frontendPort, "frontend"]]) if (!(await isPortFree(port))) throw new Error(`${label} port ${port} is already in use; refusing stale-server reuse`);
    console.log("[harness] preparing browser database");
    prepareBrowserTestDatabase();
    frontend = start(process.execPath, [path.join(frontendDirectory, "node_modules", "vite", "bin", "vite.js"), "--host", "127.0.0.1", "--port", String(frontendPort)], { cwd: frontendDirectory, env: { ...process.env, VITE_API_URL: "/api", VITE_API_PROXY_TARGET: `http://127.0.0.1:${backendPort}` } });
    await waitFor(`http://127.0.0.1:${frontendPort}/login`, frontend);
    playwright = start(process.execPath, [path.join(frontendDirectory, "node_modules", "@playwright", "test", "cli.js"), "test", "--config=playwright.external.config.ts", ...process.argv.slice(2)], { cwd: frontendDirectory, env: { ...process.env, PLAYWRIGHT_EXTERNAL_SERVERS: "1" } });
    resultCode = await new Promise((resolve) => playwright.once("close", (code, signal) => { console.log(`[harness] playwright stopped code=${code} signal=${signal ?? "none"}`); resolve(code ?? 1); }));
  } catch (error) { console.error(`[harness] ${error instanceof Error ? error.message : String(error)}`); resultCode = 1; }
  finally {
    await stop(frontend, "frontend");
    // PHP is owned and stopped by the test-scoped Playwright fixture.
    for (const [port, label] of [[backendPort, "backend"], [frontendPort, "frontend"]]) if (!(await isPortFree(port))) { console.error(`[harness] ${label} port ${port} remained in use`); resultCode = 1; }
  }
  process.exitCode = resultCode;
}
main().catch((error) => { console.error(error); process.exitCode = 1; });
