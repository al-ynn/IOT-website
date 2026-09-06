import { expect, test } from "./fixtures/test";
import { databaseRow } from "./support/database";

const adminToken = "10303|phase103-admin1-token";

test("Admin creates a Device through the modal and UI, API, DB, list, detail, and refresh agree", async ({ page, isolatedBackend }) => {
  const serial = `BROWSER-E2E-${Date.now()}`;
  await page.addInitScript(token => localStorage.setItem("iot_token", token), adminToken);
  await page.goto("/app/devices");
  await page.getByRole("button", { name: "Add device" }).click();
  await expect(page.getByRole("dialog", { name: "Add device" })).toBeVisible();

  await page.getByLabel("Device name").fill("Browser Round Trip Device");
  await page.getByLabel("Device type").fill("sensor");
  await page.getByRole("button", { name: "Continue" }).click();
  await page.getByLabel("Device identifier / serial number").fill(serial);
  await page.getByLabel("Connection protocol").selectOption("http");
  await page.getByRole("button", { name: "Continue" }).click();
  await page.getByLabel("MAC address").fill("02:00:00:00:10:03");
  await page.getByRole("button", { name: "Continue" }).click();

  const createdResponse = page.waitForResponse(response => response.url().endsWith("/api/devices") && response.request().method() === "POST");
  await page.getByRole("button", { name: "Register device" }).click();
  const response = await createdResponse;
  expect(response.status()).toBe(201);
  const created = await response.json() as { id: string | number; name: string; serialNumber: string; protocol: string };
  expect(created).toMatchObject({ name: "Browser Round Trip Device", serialNumber: serial, protocol: "http" });

  await expect(page.getByRole("dialog", { name: "Add device" })).toHaveCount(0);
  await expect(page.getByRole("table").getByText("Browser Round Trip Device", { exact: true })).toBeVisible();
  const row = databaseRow(isolatedBackend.databasePath, "select name, external_id, protocol, organization_id from devices where id = ?", [created.id]);
  expect(row).toMatchObject({ name: "Browser Round Trip Device", external_id: serial, protocol: "http" });
  expect(Number(row?.organization_id)).toBeGreaterThan(0);

  await page.getByRole("link", { name: "Open Browser Round Trip Device" }).click();
  await expect(page.getByRole("heading", { name: "Browser Round Trip Device" })).toBeVisible();
  await page.reload();
  await expect(page.getByRole("heading", { name: "Browser Round Trip Device" })).toBeVisible();
});

test("same-organization unassigned Staff and Org B cannot discover or open an Admin-created Device", async ({ page }) => {
  await page.addInitScript(token => localStorage.setItem("iot_token", token), "10302|phase103-unassigned-token");
  await page.goto("/app/search?q=PHASE103-D2");
  await expect(page.getByRole("heading", { name: "Search" })).toBeVisible();
  const unassignedSearch = await page.request.get("http://127.0.0.1:18000/api/search?q=PHASE103-D2", { headers: { Authorization: "Bearer 10302|phase103-unassigned-token", Accept: "application/json" } });
  expect((await unassignedSearch.json()).data).toEqual([]);
  await page.goto("/app/devices/2");
  await expect(page.getByText("Device not found or unavailable.")).toBeVisible();

  await page.evaluate(() => localStorage.removeItem("iot_token"));
  await page.goto("/login");
  await page.getByLabel("Email").fill("staff@phase103-org-b.test");
  await page.getByLabel("Password").fill("Browser123!");
  await page.getByRole("button", { name: "Login" }).click();
  await expect(page).toHaveURL(/\/app\/dashboard$/);
  await page.goto("/app/search?q=PHASE103-D1");
  await expect(page.getByRole("heading", { name: "Search" })).toBeVisible();
  const foreignToken = await page.evaluate(() => localStorage.getItem("iot_token"));
  const foreignSearch = await page.request.get("http://127.0.0.1:18000/api/search?q=PHASE103-D1", { headers: { Authorization: `Bearer ${foreignToken}`, Accept: "application/json" } });
  expect((await foreignSearch.json()).data).toEqual([]);
  await page.goto("/app/devices/1");
  await expect(page.getByText("Device not found or unavailable.")).toBeVisible();
});
