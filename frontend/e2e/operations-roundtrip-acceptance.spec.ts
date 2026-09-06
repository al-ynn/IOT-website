import { expect, test } from "./fixtures/test";
import { databaseRow } from "./support/database";

const token = "10303|phase103-admin1-token";
const headers = { Authorization: `Bearer ${token}`, Accept: "application/json" };
const api = "http://127.0.0.1:18000/api";

async function authenticate(page: import("@playwright/test").Page) {
  await page.addInitScript(value => localStorage.setItem("iot_token", value), token);
}

test("Automation created through the UI persists, executes its supported manual action, and reloads", async ({ page, isolatedBackend }) => {
  test.setTimeout(90_000); await authenticate(page); await page.goto("/app/automations/new");
  await page.getByLabel("Name").fill("Phase 2 Manual Automation");
  await page.getByLabel("Trigger type").selectOption("manual");
  await page.getByLabel("Notification message").fill("Phase 2 automation notification");
  const created = page.waitForResponse(r => r.url().endsWith("/api/automations") && r.request().method() === "POST");
  await page.getByRole("button", { name: "Create Automation" }).click();
  const response = await created; expect(response.status()).toBe(201);
  const automation = await response.json() as { id: string; name: string };
  expect(databaseRow(isolatedBackend.databasePath, "select name, enabled from automations where id = ?", [automation.id])).toMatchObject({ name: automation.name, enabled: 0 });
  const enabled = page.waitForResponse(r => r.url().endsWith(`/api/automations/${automation.id}/enable`) && r.request().method() === "POST"); await page.getByRole("button", { name: "Enable" }).click(); expect((await enabled).status()).toBe(200);
  page.once("dialog", dialog => dialog.accept()); const executed = page.waitForResponse(r => r.url().endsWith(`/api/automations/${automation.id}/execute`) && r.request().method() === "POST"); await page.getByRole("button", { name: "Run Now" }).click(); expect((await executed).status()).toBe(200);
  expect(databaseRow(isolatedBackend.databasePath, "select trigger_type, status from automation_executions where automation_id = ? order by created_at desc limit 1", [automation.id])).toMatchObject({ trigger_type: "manual", status: "completed" });
  await page.reload(); await expect(page.getByRole("heading", { name: automation.name })).toBeVisible();
  const fresh = await page.request.get(`${api}/automations/${automation.id}`, { headers }); expect(fresh.status()).toBe(200); expect((await fresh.json()).name).toBe(automation.name);
});

test("Report created through the UI persists, generates a backend CSV run, and reloads", async ({ page, isolatedBackend }) => {
  test.setTimeout(90_000); await authenticate(page); await page.goto("/app/reports");
  await page.getByRole("button", { name: "Create Report" }).click(); await page.getByLabel("Name").fill("Phase 2 Device Summary");
  await page.getByRole("dialog", { name: "Create Report" }).getByLabel("Report type").selectOption("device_summary");
  const device = page.getByRole("group", { name: "Accessible Devices" }).getByRole("checkbox").first(); await device.check();
  const created = page.waitForResponse(r => r.url().endsWith("/api/reports") && r.request().method() === "POST"); await page.getByRole("button", { name: "Create", exact: true }).click();
  const response = await created; expect(response.status()).toBe(201); const report = await response.json() as { data: { id: string; name: string } };
  expect(databaseRow(isolatedBackend.databasePath, "select name, report_type from reports where id = ?", [report.data.id])).toMatchObject({ name: report.data.name, report_type: "device_summary" });
  await page.goto(`/app/reports/${report.data.id}`);
  const run = page.waitForResponse(r => r.url().endsWith(`/api/reports/${report.data.id}/runs`) && r.request().method() === "POST"); await page.getByRole("button", { name: "Generate Report" }).click(); expect((await run).status()).toBe(202);
  await expect.poll(() => databaseRow(isolatedBackend.databasePath, "select status from report_runs where report_id = ? order by created_at desc limit 1", [report.data.id])?.status).toBe("completed");
  await page.reload(); await expect(page.getByText("completed", { exact: true })).toBeVisible();
});

test("Webhook created through the UI stores no exposed secret, persists configuration, and reloads", async ({ page, isolatedBackend }) => {
  test.setTimeout(90_000); await authenticate(page); await page.goto("/app/developer/webhooks"); await page.getByRole("button", { name: "New Webhook" }).click();
  await page.getByLabel("Name").fill("Phase 2 Webhook"); await page.getByLabel("Endpoint URL").fill("https://example.com/phase2-hook");
  const created = page.waitForResponse(r => r.url().endsWith("/api/webhooks") && r.request().method() === "POST"); await page.getByRole("button", { name: "Create Webhook" }).click();
  const response = await created; expect(response.status()).toBe(201); const payload = await response.json() as { data: { id: string; secret: string } }; expect(payload.data.secret.length).toBeGreaterThan(10);
  expect(databaseRow(isolatedBackend.databasePath, "select name, url, signing_secret, secret_prefix from webhooks where id = ?", [payload.data.id])).toMatchObject({ name: "Phase 2 Webhook", url: "https://example.com/phase2-hook" });
  await page.getByRole("button", { name: "I have saved it" }).click(); await page.getByRole("link", { name: "Phase 2 Webhook" }).click(); await page.reload();
  await expect(page.getByRole("heading", { name: "Phase 2 Webhook" })).toBeVisible(); const fresh = await page.request.get(`${api}/webhooks/${payload.data.id}`, { headers }); expect(fresh.status()).toBe(200); expect(JSON.stringify(await fresh.json())).not.toContain(payload.data.secret);
});
