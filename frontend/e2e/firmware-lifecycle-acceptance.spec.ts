import { expect, test } from "./fixtures/test";
import { databaseRow } from "./support/database";

const token = "10303|phase103-admin1-token";

test("Firmware upload is persisted privately, downloadable through the UI, and deleted with its artifact", async ({ page, isolatedBackend }) => {
  test.setTimeout(90_000);
  await page.addInitScript(value => localStorage.setItem("iot_token", value), token);
  await page.goto("/app/developer/firmware");
  await expect(page.getByRole("heading", { name: "Firmware / OTA" })).toBeVisible();

  await page.getByRole("button", { name: "Upload firmware" }).click();
  const dialog = page.getByRole("dialog", { name: "Upload firmware" });
  await dialog.getByLabel("Firmware file").setInputFiles({ name: "phase2-firmware.bin", mimeType: "application/octet-stream", buffer: Buffer.from("phase-2-firmware-content") });
  await dialog.getByLabel("Name").fill("Phase 2 Firmware");
  await dialog.getByLabel("Version").fill("2.0.0");
  const created = page.waitForResponse(response => response.url().endsWith("/api/firmware/artifacts") && response.request().method() === "POST");
  await dialog.getByRole("button", { name: "Upload", exact: true }).click();
  const response = await created;
  const body = await response.json() as { data?: { id: string; name: string; version: string; file: { name: string; sha256: string } }; message?: string; errors?: unknown };
  expect(response.status(), JSON.stringify(body)).toBe(201);
  const artifact = body as { data: { id: string; name: string; version: string; file: { name: string; sha256: string } } };
  expect(artifact.data).toMatchObject({ name: "Phase 2 Firmware", version: "2.0.0", file: { name: "phase2-firmware.bin" } });
  expect(artifact.data.file.sha256).toMatch(/^[a-f0-9]{64}$/);
  expect(databaseRow(isolatedBackend.databasePath, "select name, version, storage_path from firmware_artifacts where id = ?", [artifact.data.id])).toMatchObject({ name: artifact.data.name, version: artifact.data.version });
  await expect(page.getByText("Phase 2 Firmware", { exact: true })).toBeVisible();

  const download = page.waitForEvent("download");
  await page.getByRole("button", { name: "Download" }).click();
  expect((await download).suggestedFilename()).toBe("phase2-firmware.bin");

  page.once("dialog", confirmation => confirmation.accept());
  const deleted = page.waitForResponse(response => response.url().endsWith(`/api/firmware/artifacts/${artifact.data.id}`) && response.request().method() === "DELETE");
  await page.getByRole("button", { name: "Delete Phase 2 Firmware" }).click();
  expect((await deleted).status()).toBe(204);
  await expect(page.getByText("Phase 2 Firmware", { exact: true })).toHaveCount(0);
  expect(databaseRow(isolatedBackend.databasePath, "select id from firmware_artifacts where id = ?", [artifact.data.id])).toBeNull();
});
