import { expect, test } from "./fixtures/test";
import { databaseRow } from "./support/database";

const api = "http://127.0.0.1:18000/api";
const adminToken = "10303|phase103-admin1-token";
const adminHeaders = { Authorization: `Bearer ${adminToken}`, Accept: "application/json" };

test("typed Template metadata persists through Device UI, API, DB, refresh, validation, and permissions", async ({ page, isolatedBackend }) => {
  const templateResponse = await page.request.post(`${api}/device-templates`, {
    headers: adminHeaders,
    data: { name: `Browser Metadata Template ${Date.now()}`, device_type: "sensor", protocol: "mqtt" },
  });
  expect(templateResponse.status()).toBe(201);
  const templateId = String((await templateResponse.json()).data.id);

  const definitions = [
    { name: "Asset owner", key: "asset_owner", data_type: "string", required: true, configuration: { maxLength: 80 } },
    { name: "Floor number", key: "floor_number", data_type: "integer", configuration: { min: 1, max: 50 } },
    { name: "Calibrated", key: "calibrated", data_type: "boolean" },
    { name: "Service tier", key: "service_tier", data_type: "enum", configuration: { options: ["standard", "critical"] } },
  ];
  let templateBody: { data: { metadataDefinitions: Array<{ id: string; key: string }> } } | undefined;
  for (let index = 0; index < definitions.length; index += 1) {
    const response = await page.request.post(`${api}/device-templates/${templateId}/metadata`, {
      headers: { ...adminHeaders, "Idempotency-Key": `9c6e927e-5138-4a62-9bd3-7379e0561a0${index}` },
      data: definitions[index],
    });
    templateBody = await response.json();
    expect(response.status(), JSON.stringify(templateBody)).toBe(200);
  }
  const storedDefinitions = templateBody!.data.metadataDefinitions;
  expect(storedDefinitions.map(item => item.key)).toEqual(expect.arrayContaining(definitions.map(item => item.key)));

  const deviceResponse = await page.request.post(`${api}/device-templates/${templateId}/devices`, { headers: adminHeaders, data: { name: "Browser Metadata Device" } });
  expect(deviceResponse.status()).toBe(201);
  const deviceId = String((await deviceResponse.json()).id);

  await page.addInitScript(token => localStorage.setItem("iot_token", token), adminToken);
  await page.goto(`/app/devices/${deviceId}?tab=metadata`);
  const ownerRow = page.getByText("Asset owner", { exact: true }).locator("xpath=ancestor::div[contains(@class,'flex')][1]");
  await ownerRow.locator("input").fill("North Plant Operations");
  const saved = page.waitForResponse(response => response.url().endsWith(`/api/devices/${deviceId}/metadata`) && response.request().method() === "POST");
  await ownerRow.getByRole("button", { name: "Save" }).click();
  expect((await saved).status()).toBe(201);

  const values: Record<string, unknown> = { floor_number: 7, calibrated: true, service_tier: "critical" };
  for (const [key, value] of Object.entries(values)) {
    const definitionId = storedDefinitions.find(item => item.key === key)!.id;
    const response = await page.request.post(`${api}/devices/${deviceId}/metadata`, { headers: adminHeaders, data: { definitionId, value } });
    expect(response.status()).toBe(201);
  }

  const ownerDefinition = storedDefinitions.find(item => item.key === "asset_owner")!;
  const row = databaseRow(isolatedBackend.databasePath, "select value from device_metadata_values where device_id = ? and metadata_definition_id = ?", [deviceId, ownerDefinition.id]);
  expect(String(row?.value).replace(/^"|"$/g, "")).toBe("North Plant Operations");
  const readBack = await page.request.get(`${api}/devices/${deviceId}/metadata`, { headers: adminHeaders });
  expect(readBack.status()).toBe(200);
  expect((await readBack.json()).data).toEqual(expect.arrayContaining([
    expect.objectContaining({ key: "asset_owner", value: "North Plant Operations" }),
    expect.objectContaining({ key: "floor_number", value: 7 }),
    expect.objectContaining({ key: "calibrated", value: true }),
    expect.objectContaining({ key: "service_tier", value: "critical" }),
  ]));
  await page.reload();
  await expect(ownerRow.locator("input")).toHaveValue("North Plant Operations");

  const bad = await page.request.post(`${api}/devices/${deviceId}/metadata`, {
    headers: adminHeaders,
    data: { definitionId: storedDefinitions.find(item => item.key === "floor_number")!.id, value: 99 },
  });
  expect(bad.status()).toBe(422);

  const viewer = await page.request.post(`${api}/devices/${deviceId}/metadata`, {
    headers: { Authorization: "Bearer 10301|phase103-viewer-token", Accept: "application/json" },
    data: { definitionId: ownerDefinition.id, value: "Forbidden update" },
  });
  expect([403, 404]).toContain(viewer.status());
  expect(String(databaseRow(isolatedBackend.databasePath, "select value from device_metadata_values where device_id = ? and metadata_definition_id = ?", [deviceId, ownerDefinition.id])?.value)).toContain("North Plant Operations");
});
