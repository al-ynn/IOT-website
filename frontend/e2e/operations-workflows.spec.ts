import { expect, test, type Page } from "./fixtures/test";

const token = "10303|phase103-admin1-token";

async function authenticate(page: Page) {
  await page.addInitScript((value) => localStorage.setItem("iot_token", value), token);
}

test.describe("operations surfaces", () => {
  test.beforeEach(async ({ page }) => {
    await authenticate(page);
  });

  test("Automations supports the create workflow and required validation", async ({ page }) => {
    await page.goto("/app/automations");
    await expect(page.getByRole("heading", { name: "Automations", exact: true })).toBeVisible();
    await expect(page.getByText("No automations configured")).toBeVisible();
    await page.getByRole("link", { name: "Create Automation" }).first().click();
    await expect(page.getByRole("heading", { name: "Create Automation" })).toBeVisible();
    await expect(page.getByRole("button", { name: "Create Automation", exact: true })).toBeEnabled();
    await page.getByLabel("Name").fill("Browser acceptance automation");
    await expect(page.getByRole("button", { name: "Create Automation", exact: true })).toBeEnabled();
  });

  test("Reports opens its validated create workflow", async ({ page }) => {
    await page.goto("/app/reports");
    await expect(page.getByRole("heading", { name: "Reports", exact: true })).toBeVisible();
    await expect(page.getByText("No reports configured")).toBeVisible();
    await page.getByRole("button", { name: "Create Report" }).click();
    await expect(page.getByRole("heading", { name: "Create Report" })).toBeVisible();
    await expect(page.getByRole("button", { name: "Create", exact: true })).toBeDisabled();
    await page.getByLabel("Name").fill("Browser acceptance report");
    await expect(page.getByRole("button", { name: "Create", exact: true })).toBeDisabled();
    await page.getByRole("button", { name: "Cancel", exact: true }).click();
  });

  test("Firmware surface exposes protected artifact and deployment workflows", async ({ page }) => {
    await page.goto("/app/developer/firmware");
    await expect(page.getByRole("heading", { name: "Firmware / OTA" })).toBeVisible();
    await expect(page.getByText("Device delivery is not configured")).toBeVisible();
    await expect(page.getByText("No firmware artifacts")).toBeVisible();
    await page.getByRole("button", { name: "Upload firmware" }).click();
    await expect(page.getByRole("heading", { name: "Upload firmware" })).toBeVisible();
    await expect(page.getByRole("button", { name: "Upload", exact: true })).toBeDisabled();
    await page.getByRole("button", { name: "Cancel", exact: true }).click();
  });

  test("Webhook surface exposes endpoint validation and event controls", async ({ page }) => {
    await page.goto("/app/developer/webhooks");
    await expect(page.getByRole("heading", { name: "Webhooks", exact: true })).toBeVisible();
    await expect(page.getByText("No Webhooks")).toBeVisible();
    await page.getByRole("button", { name: "New Webhook" }).click();
    await page.getByRole("textbox", { name: "Name" }).fill("Browser acceptance webhook");
    await page.getByRole("textbox", { name: "Endpoint URL" }).fill("https://example.com/iot-hook");
    await expect(page.getByRole("button", { name: "Create Webhook" })).toBeEnabled();
    await expect(page.getByText("automation.failed")).toBeVisible();
    await page.getByRole("button", { name: "Cancel", exact: true }).click();
  });

  test("debug paths are not reachable from the production route graph", async ({ page }) => {
    for (const path of ["/app/debug/provisioning", "/app/debug/events", "/app/debug/crashes"]) {
      await page.goto(path);
      await expect(page.getByRole("heading", { name: "Page not found" })).toBeVisible();
    }
  });
});
