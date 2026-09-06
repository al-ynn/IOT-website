import { expect, test } from "./fixtures/test";

const password = "Browser123!";

test("login validation, invalid credentials, refresh, protected deep link, and logout use real auth", async ({ page }) => {
  await page.goto("/login");
  await page.evaluate(() => localStorage.removeItem("iot_token"));
  await page.goto("/app/devices");
  await expect(page).toHaveURL(/\/login$/);

  await page.getByRole("button", { name: "Login" }).click();
  await expect(page.getByRole("alert")).toHaveText("Enter your email and password.");

  await page.getByLabel("Email").fill("viewer@phase103.test");
  await page.getByLabel("Password").fill("incorrect-password");
  const rejected = page.waitForResponse(response => response.url().endsWith("/api/auth/login"));
  await page.getByRole("button", { name: "Login" }).click();
  expect((await rejected).status()).toBe(422);
  await expect(page.getByRole("alert")).toHaveText("Invalid email or password.");

  await page.getByLabel("Password").fill(password);
  const accepted = page.waitForResponse(response => response.url().endsWith("/api/auth/login"));
  await page.getByRole("button", { name: "Login" }).click();
  expect((await accepted).status()).toBe(200);
  await expect(page).toHaveURL(/\/app\/dashboard$/);
  await page.reload();
  await expect(page.getByRole("button", { name: "Open user menu" })).toBeVisible();

  const me = await page.request.get("http://127.0.0.1:18000/api/auth/me", {
    headers: { Authorization: `Bearer ${await page.evaluate(() => localStorage.getItem("iot_token"))}` },
  });
  expect(me.status()).toBe(200);
  expect((await me.json()).email).toBe("viewer@phase103.test");

  await page.getByRole("button", { name: "Open user menu" }).click();
  const logout = page.waitForResponse(response => response.url().endsWith("/api/auth/logout"));
  await page.getByRole("button", { name: "Logout" }).click();
  expect((await logout).status()).toBe(204);
  await expect(page).toHaveURL(/\/login$/);
  await page.goto("/app/devices");
  await expect(page).toHaveURL(/\/login$/);
});

test("invalid invitation token is rejected without inventing public registration", async ({ page }) => {
  await page.goto("/login");
  await page.evaluate(() => localStorage.removeItem("iot_token"));
  await page.goto("/invite/not-a-real-invitation-token");
  const response = page.waitForResponse(item => item.url().endsWith("/api/invitations/accept"));
  await page.getByRole("button", { name: "Accept invitation" }).click();
  expect((await response).status()).toBe(401);
  await expect(page).toHaveURL(/\/login$/);
});
