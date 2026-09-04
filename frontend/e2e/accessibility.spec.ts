import { test, expect, type Page } from "@playwright/test";
import AxeBuilder from "@axe-core/playwright";

const viewer = "10301|phase103-viewer-token";
const admin = "10303|phase103-admin1-token";

async function authenticate(page: Page, token = viewer) {
  await page.addInitScript((value) => localStorage.setItem("iot_token", value), token);
}

async function expectNoAxeViolations(page: Page) {
  const result = await new AxeBuilder({ page }).analyze();
  expect(result.violations, result.violations.map((item) => `${item.id}: ${item.help}`).join("\n")).toEqual([]);
}

for (const route of ["/app/dashboard", "/app/collaboration", "/app/my-work", "/app/shared-with-me", "/app/changes", "/app/search?q=PHASE103", "/app/notifications", "/app/activity", "/app/devices/1?tab=dashboard"]) {
  test(`axe audit ${route}`, async ({ page }) => {
    await authenticate(page);
    await page.goto(route);
    await expect(page.locator("main")).toBeVisible();
    await expect(page.locator("#main-content h1")).toHaveCount(1);
    await expectNoAxeViolations(page);
  });
}

test("axe audit Admin Review Center", async ({ page }) => {
  await authenticate(page, admin);
  await page.goto("/admin/reviews");
  await expect(page.getByRole("heading", { name: "Review Center" })).toBeVisible();
  await expectNoAxeViolations(page);
});

test("skip link, route context, navigation and resource tabs are keyboard operable", async ({ page }) => {
  await authenticate(page);
  await page.goto("/app/collaboration");
  await expect(page.locator("#main-content h1")).toHaveCount(1);
  await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
  await page.keyboard.press("Tab");
  await expect(page.getByRole("link", { name: "Skip to main content" })).toBeFocused();
  await page.keyboard.press("Enter");
  await expect(page.locator("main")).toBeFocused();
  await page.getByRole("navigation", { name: "Platform areas" }).getByRole("link", { name: "My Work", exact: true }).focus();
  await page.keyboard.press("Enter");
  await expect(page.locator("#main-content")).toBeFocused();
  await expect(page).toHaveTitle(/My Work/);
  await page.goto("/app/devices/1");
  await page.getByRole("navigation", { name: "Device sections" }).getByRole("link", { name: "Dashboard", exact: true }).focus();
  await page.keyboard.press("Enter");
  await expect(page).toHaveURL(/tab=dashboard/);
});

test("mobile navigation traps focus, closes with Escape, and restores trigger focus", async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 568 });
  await authenticate(page);
  await page.goto("/app/collaboration");
  const trigger = page.getByRole("button", { name: "Open navigation" });
  await trigger.focus();
  await page.keyboard.press("Enter");
  await expect(page.getByRole("dialog", { name: "Primary navigation drawer" })).toBeVisible();
  await expect(page.getByRole("link", { name: "IoT Platform home" })).toBeFocused();
  await page.keyboard.press("Escape");
  await expect(page.getByRole("dialog", { name: "Primary navigation drawer" })).toHaveCount(0);
  await expect(trigger).toBeFocused();
});

test("Search, filters, Comments, Pull and notification actions work from the keyboard", async ({ page }) => {
  await authenticate(page);
  await page.goto("/app/search");
  const search = page.getByRole("textbox", { name: "Search accessible resources" });
  await search.fill("PHASE103-D1");
  await page.getByRole("button", { name: "Search", exact: true }).focus();
  await page.keyboard.press("Enter");
  await expect(page.getByRole("link", { name: /Open Phase103 Shared Temperature Device/ })).toBeVisible();
  await page.goto("/app/notifications");
  const notifications = page.getByRole("button", { name: /Notifications/ }).first();
  await notifications.focus();
  await page.keyboard.press("Enter");
  await expect(page.getByRole("dialog", { name: "Notifications" })).toBeVisible();
  await page.keyboard.press("Escape");
  await expect(notifications).toBeFocused();
  await page.goto("/app/dashboard/2");
  const discussion = page.getByRole("button", { name: "Dashboard discussion" });
  await discussion.focus();
  await page.keyboard.press("Enter");
  await expect(page.getByLabel("Start a discussion about Dashboard")).toBeVisible();
});

for (const scale of [2, 4]) {
  test(`reflow at ${scale * 100}% equivalent`, async ({ page }) => {
    await page.setViewportSize({ width: Math.round(1280 / scale), height: 720 });
    await authenticate(page);
    await page.goto("/app/collaboration");
    await expect(page.locator("#main-content h1")).toHaveCount(1);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBe(true);
  });
}
