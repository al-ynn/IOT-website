import { expect, test, type BrowserContext, type Page } from "./fixtures/test";
import { databaseRow } from "./support/database";

const senderToken = "10305|phase103-full-token";
const recipientToken = "10307|phase103-recipient-token";
const origin = "http://127.0.0.1:18000/api";
const app = "http://127.0.0.1:15173";

async function authenticatedPage(context: BrowserContext, token: string) {
  await context.addInitScript(value => localStorage.setItem("iot_token", value), token);
  return context.newPage();
}

async function createDashboard(page: Page) {
  const response = await page.request.post(`${origin}/dashboards`, { headers: { Authorization: `Bearer ${senderToken}`, Accept: "application/json" }, data: { name: `Phase 2 Share ${Date.now()}`, description: "Dashboard sharing acceptance", widgets: [] } });
  expect(response.status()).toBe(201);
  return await response.json() as { id: string; name: string };
}

async function selectRecipient(page: Page) {
  const select = page.getByLabel("Recipient");
  const value = await select.locator("option", { hasText: "Phase103 Share Recipient" }).getAttribute("value");
  expect(value).toBeTruthy();
  await select.selectOption(value!);
}

test("Dashboard share acceptance, permission change, revoke, DB, and next-request authorization", async ({ browser, page, isolatedBackend }) => {
  test.setTimeout(90_000);
  const dashboard = await createDashboard(page);
  const senderContext = await browser.newContext(), recipientContext = await browser.newContext();
  const sender = await authenticatedPage(senderContext, senderToken);
  const recipient = await authenticatedPage(recipientContext, recipientToken);
  try {
    await sender.goto(`${app}/app/dashboard/${dashboard.id}/share`);
    await selectRecipient(sender);
    await sender.getByLabel("Access").selectOption("view");
    await sender.getByLabel(/Handoff note/).fill("Phase 2 canonical Dashboard handoff");
    const sent = sender.waitForResponse(r => r.url().endsWith("/api/shares") && r.request().method() === "POST");
    await sender.getByRole("button", { name: "Send invitation" }).click();
    const sentResponse = await sent;
    expect(sentResponse.status()).toBe(201);
    const shareId = String((await sentResponse.json() as { data: { id: number } }).data.id);
    expect(databaseRow(isolatedBackend.databasePath, "select resource_type, resource_id, requested_permission, status from resource_share_requests where id = ?", [shareId])).toMatchObject({ resource_type: "dashboard", requested_permission: "view", status: "pending_recipient" });

    await recipient.goto(`${app}/app/shares/${shareId}`);
    const accepted = recipient.waitForResponse(r => r.url().endsWith(`/api/shares/${shareId}/accept`) && r.request().method() === "POST");
    await recipient.getByRole("button", { name: "Accept request" }).click();
    expect((await accepted).status()).toBe(200);
    await expect(recipient.getByRole("link", { name: "Open original Dashboard" })).toBeVisible();
    await recipient.goto(`${app}/app/dashboard/${dashboard.id}`);
    await expect(recipient.getByText(dashboard.name, { exact: true })).toBeVisible();
    expect(databaseRow(isolatedBackend.databasePath, "select permission from resource_collaborators where resource_type = 'dashboard' and resource_id = ? and user_id = (select id from users where email = 'recipient@phase103.test')", [dashboard.id])).toMatchObject({ permission: "view" });

    await sender.goto(`${app}/app/dashboard/${dashboard.id}/share`);
    await sender.getByLabel(/Permission for Phase103 Share Recipient/).selectOption("edit");
    await expect.poll(() => databaseRow(isolatedBackend.databasePath, "select permission from resource_collaborators where resource_type = 'dashboard' and resource_id = ? and user_id = (select id from users where email = 'recipient@phase103.test')", [dashboard.id])?.permission).toBe("edit");
    await sender.getByRole("button", { name: "Revoke" }).click();
    await expect.poll(() => databaseRow(isolatedBackend.databasePath, "select status from resource_share_requests where id = ?", [shareId])?.status).toBe("revoked");
    await recipient.reload();
    await expect(recipient.getByText("Dashboard could not be loaded.")).toBeVisible();
  } finally {
    await senderContext.close(); await recipientContext.close();
  }
});

test("Dashboard share decline and sender cancellation remain non-authorizing terminal states", async ({ browser, page, isolatedBackend }) => {
  test.setTimeout(90_000);
  for (const action of ["decline", "cancel"] as const) {
    const dashboard = await createDashboard(page);
    const senderContext = await browser.newContext(), recipientContext = await browser.newContext();
    const sender = await authenticatedPage(senderContext, senderToken), recipient = await authenticatedPage(recipientContext, recipientToken);
    try {
      await sender.goto(`${app}/app/dashboard/${dashboard.id}/share`);
      await selectRecipient(sender);
      const sent = sender.waitForResponse(r => r.url().endsWith("/api/shares") && r.request().method() === "POST");
      await sender.getByRole("button", { name: "Send invitation" }).click();
      const sentResponse = await sent; expect(sentResponse.status()).toBe(201);
      const shareId = String((await sentResponse.json() as { data: { id: number } }).data.id);
      if (action === "decline") {
        await recipient.goto(`${app}/app/shares/${shareId}`);
        const declined = recipient.waitForResponse(response => response.url().endsWith(`/api/shares/${shareId}/decline`) && response.request().method() === "POST");
        await recipient.getByRole("button", { name: "Decline" }).click();
        expect((await declined).status()).toBe(200);
      } else {
        await sender.goto(`${app}/app/dashboard/${dashboard.id}/share`);
        const cancelled = sender.waitForResponse(response => response.url().endsWith(`/api/shares/${shareId}/cancel`) && response.request().method() === "POST");
        await sender.getByRole("button", { name: "Cancel invitation" }).click();
        expect((await cancelled).status()).toBe(200);
      }
      await expect.poll(() => databaseRow(isolatedBackend.databasePath, "select status from resource_share_requests where id = ?", [shareId])?.status).toBe(action === "decline" ? "declined" : "cancelled");
      await recipient.goto(`${app}/app/dashboard/${dashboard.id}`);
      await expect(recipient.getByText("Dashboard could not be loaded.")).toBeVisible();
    } finally { await senderContext.close(); await recipientContext.close(); }
  }
});
