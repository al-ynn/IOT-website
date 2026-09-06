import { expect, test } from "./fixtures/test";
import { databaseRow } from "./support/database";

const userToken = "10305|phase103-full-token";
const api = "http://127.0.0.1:18000/api";

test("Device credential and telemetry values round-trip through auth, Laravel, DB, API, and refreshed UI", async ({ page, isolatedBackend }) => {
  const headers = { Authorization: `Bearer ${userToken}`, Accept: "application/json" };
  const search = await page.request.get(`${api}/search?q=PHASE103-D1`, { headers });
  expect(search.status()).toBe(200);
  const device = (await search.json()).data.find((item: { resourceType: string }) => item.resourceType === "device");
  expect(device).toBeTruthy();

  const credentialResponse = await page.request.post(`${api}/admin/devices/${device.resourceId}/credentials`, {
    headers: { Authorization: `Bearer 10303|phase103-admin1-token`, Accept: "application/json" },
    data: { name: "Browser ingestion credential", scopes: ["telemetry:write"] },
  });
  expect(credentialResponse.status()).toBe(201);
  const credential = await credentialResponse.json() as { token: string; data: { id: string } };
  expect(credential.token).toMatch(/^iotd_/);

  const deviceDetail = await page.request.get(`${api}/devices/${device.resourceId}`, { headers });
  const configuredKey = ((await deviceDetail.json()).parameters ?? [])[0]?.key ?? "temperature";
  for (const value of [23.417, 27.863]) {
    const response = await page.request.post(`${api}/device/telemetry`, {
      headers: { Authorization: `Bearer ${credential.token}`, Accept: "application/json" },
      data: { key: configuredKey, value, unit: "C" },
    });
    expect(response.status()).toBe(201);
    expect((await response.json()).data).toMatchObject({ device_id: String(device.resourceId), key: configuredKey, value });
  }

  const latest = databaseRow(isolatedBackend.databasePath, "select device_id, key, value, unit from telemetry_records where device_id = ? order by id desc limit 1", [device.resourceId]);
  expect(latest).toMatchObject({ key: configuredKey });
  expect(Number(latest?.value)).toBe(27.863);

  const detail = await page.request.get(`${api}/analytics/devices/${device.resourceId}`, { headers });
  expect(detail.status()).toBe(200);
  expect(JSON.stringify(await detail.json())).toContain("27.863");

  await page.addInitScript(token => localStorage.setItem("iot_token", token), userToken);
  await page.goto(`/app/analytics/device/${device.resourceId}`);
  await expect(page.getByText("27.863", { exact: false }).first()).toBeVisible();
  await page.reload();
  await expect(page.getByText("27.863", { exact: false }).first()).toBeVisible();

  const invalid = await page.request.post(`${api}/device/telemetry`, {
    headers: { Authorization: "Bearer iotd_invalid", Accept: "application/json" },
    data: { key: configuredKey, value: 99 },
  });
  expect(invalid.status()).toBe(401);

  const revoked = await page.request.post(`${api}/admin/devices/${device.resourceId}/credentials/${credential.data.id}/revoke`, { headers: { Authorization: `Bearer 10303|phase103-admin1-token`, Accept: "application/json" } });
  expect(revoked.status()).toBe(200);
  const denied = await page.request.post(`${api}/device/telemetry`, {
    headers: { Authorization: `Bearer ${credential.token}`, Accept: "application/json" },
    data: { key: configuredKey, value: 99 },
  });
  expect(denied.status()).toBe(401);
  expect(Number(databaseRow(isolatedBackend.databasePath, "select count(*) as total from telemetry_records where device_id = ? and value = 99", [device.resourceId])?.total)).toBe(0);
});
