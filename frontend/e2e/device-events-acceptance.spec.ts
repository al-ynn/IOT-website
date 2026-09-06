import { expect, test } from "./fixtures/test";
import { databaseRow } from "./support/database";

const api = "http://127.0.0.1:18000/api";
const adminToken = "10303|phase103-admin1-token";
const adminHeaders = { Authorization: `Bearer ${adminToken}`, Accept: "application/json" };

test("Template-defined Device event round-trips through credential auth, DB, API, and refreshed UI", async ({ page, isolatedBackend }) => {
  const templateResponse = await page.request.post(`${api}/device-templates`, {
    headers: adminHeaders,
    data: { name: `Browser Event Template ${Date.now()}`, device_type: "sensor", protocol: "mqtt" },
  });
  expect(templateResponse.status()).toBe(201);
  const templateId = (await templateResponse.json()).data.id as string;

  const definitionResponse = await page.request.post(`${api}/device-templates/${templateId}/events`, {
    headers: { ...adminHeaders, "Idempotency-Key": "c4c77279-42b8-4fbe-8b43-6ee8f1c28472" },
    data: { name: "Temperature Warning", code: "temperature_warning", severity: "warning", description: "Temperature exceeded the warning threshold.", enabled: true },
  });
  const definitionBody = await definitionResponse.json();
  expect(definitionResponse.status(), JSON.stringify(definitionBody)).toBe(200);
  expect(definitionBody.data.eventDefinitions).toEqual(expect.arrayContaining([
    expect.objectContaining({ name: "Temperature Warning", code: "temperature_warning", severity: "warning", enabled: true }),
  ]));

  const deviceResponse = await page.request.post(`${api}/device-templates/${templateId}/devices`, {
    headers: adminHeaders,
    data: { name: "Browser Event Device" },
  });
  expect(deviceResponse.status()).toBe(201);
  const deviceId = String((await deviceResponse.json()).id);

  const credentialResponse = await page.request.post(`${api}/admin/devices/${deviceId}/credentials`, {
    headers: adminHeaders,
    data: { name: "Browser event credential", scopes: ["events:write"] },
  });
  expect(credentialResponse.status()).toBe(201);
  const credential = await credentialResponse.json() as { token: string; data: { id: string } };

  const eventResponse = await page.request.post(`${api}/device/events`, {
    headers: { Authorization: `Bearer ${credential.token}`, Accept: "application/json" },
    data: { code: "temperature_warning", message: "Measured temperature is elevated", value: 31.25 },
  });
  expect(eventResponse.status()).toBe(201);
  const event = await eventResponse.json() as { id: string; code: string; name: string; severity: string; message: string; value: number };
  expect(event).toMatchObject({ code: "temperature_warning", name: "Temperature Warning", severity: "warning", message: "Measured temperature is elevated", value: 31.25 });

  const row = databaseRow(isolatedBackend.databasePath, "select device_id, event_code, event_name, severity, message, value from device_event_facts where id = ?", [event.id]);
  expect(row).toMatchObject({ event_code: "temperature_warning", event_name: "Temperature Warning", severity: "warning", message: "Measured temperature is elevated" });
  expect(Number(row?.device_id)).toBe(Number(deviceId));

  const feed = await page.request.get(`${api}/devices/${deviceId}/events`, { headers: adminHeaders });
  expect(feed.status()).toBe(200);
  expect((await feed.json()).data[0]).toMatchObject({ id: event.id, code: "temperature_warning", value: 31.25 });

  await page.addInitScript(token => localStorage.setItem("iot_token", token), adminToken);
  await page.goto(`/app/devices/${deviceId}?tab=events`);
  await expect(page.getByText("Temperature Warning", { exact: true }).first()).toBeVisible();
  await expect(page.getByText("Measured temperature is elevated", { exact: true }).first()).toBeVisible();
  await page.reload();
  await expect(page.getByText("Temperature Warning", { exact: true }).first()).toBeVisible();

  const unknown = await page.request.post(`${api}/device/events`, {
    headers: { Authorization: `Bearer ${credential.token}`, Accept: "application/json" },
    data: { code: "unknown_event", message: "must not persist" },
  });
  expect(unknown.status()).toBe(422);
  const wrongScopeResponse = await page.request.post(`${api}/admin/devices/${deviceId}/credentials`, {
    headers: adminHeaders,
    data: { name: "Telemetry only", scopes: ["telemetry:write"] },
  });
  const wrongScope = (await wrongScopeResponse.json()).token as string;
  const forbidden = await page.request.post(`${api}/device/events`, {
    headers: { Authorization: `Bearer ${wrongScope}`, Accept: "application/json" },
    data: { code: "temperature_warning" },
  });
  expect(forbidden.status()).toBe(403);
  expect(Number(databaseRow(isolatedBackend.databasePath, "select count(*) as total from device_event_facts where device_id = ?", [deviceId])?.total)).toBe(1);
});
