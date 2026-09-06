import { expect, test } from "./fixtures/test";
import { databaseRow } from "./support/database";

test("personal Dashboard registry, Label configuration, layout, DB, GET, and reload round-trip", async ({ page, isolatedBackend }) => {
  test.setTimeout(90_000);
  const login = await page.request.post("http://127.0.0.1:18000/api/auth/login", { data: { email: "admin@iot-platform.test", password: "Admin123!" } });
  expect(login.status()).toBe(200);
  const token = (await login.json() as { token: string }).token;
  await page.addInitScript(value => localStorage.setItem("iot_token", value), token);
  const initial = await page.request.get("http://127.0.0.1:18000/api/dashboards/default", { headers: { Authorization: `Bearer ${token}`, Accept: "application/json" } });
  expect(initial.status()).toBe(200);
  const before = await initial.json() as { id: string; widgetDefinitions: Array<{ type: string }>; widgets: Array<{ id: string; type: string; layout: { x: number; y: number; w: number; h: number } }> };
  const expected = ["switch","slider","label","device_count","device_table","geo_map","image_map","device_connection_map","metrics_over_time","metric_by_devices","event_count_tile","latest_events","event_count_chart","events_over_time","events_breakdown_over_time","events_by_organization","events_by_device","events_by_template","activations","metric","gauge","chart","table","status","device"];
  expect(before.widgetDefinitions.map(item => item.type)).toEqual(expected);
  expect(new Set(before.widgets.map(item => item.type))).toEqual(new Set(expected));

  await page.goto("/app/dashboard");
  await page.getByRole("button", { name: "Customize Dashboard" }).click();
  const labelCard = page.locator('[aria-label="Label widget"]');
  await labelCard.click();
  await page.getByLabel("Label value").fill("Phase 2 persisted label");
  await page.getByLabel("Font size").selectOption("16");
  await page.getByLabel("Font style").selectOption("italic");
  const save = page.waitForResponse(r => r.url().includes(`/api/dashboards/${before.id}`) && r.request().method() === "PUT");
  await page.getByRole("button", { name: "Save Dashboard" }).click();
  expect((await save).status()).toBe(200);

  const label = databaseRow(isolatedBackend.databasePath, "select configuration, layout from dashboard_widgets where dashboard_id = ? and widget_type = 'label'", [before.id]);
  expect(JSON.parse(String(label?.configuration))).toMatchObject({ staticValue: "Phase 2 persisted label", fontSize: 16, fontStyle: "italic" });
  const fresh = await page.request.get(`http://127.0.0.1:18000/api/dashboards/${before.id}`, { headers: { Authorization: `Bearer ${token}`, Accept: "application/json" } });
  expect(fresh.status()).toBe(200);
  const dashboard = await fresh.json() as { widgets: Array<{ type: string; settings: { staticValue?: string; fontSize?: number; fontStyle?: string }; layout: { x: number; y: number; w: number; h: number } }> };
  expect(dashboard.widgets.find(widget => widget.type === "label")?.settings).toMatchObject({ staticValue: "Phase 2 persisted label", fontSize: 16, fontStyle: "italic" });
  for (let left = 0; left < dashboard.widgets.length; left++) for (let right = left + 1; right < dashboard.widgets.length; right++) {
    const a = dashboard.widgets[left].layout, b = dashboard.widgets[right].layout;
    expect(a.x + a.w <= b.x || b.x + b.w <= a.x || a.y + a.h <= b.y || b.y + b.h <= a.y, `widgets ${left} and ${right} overlap`).toBe(true);
  }
  await page.reload();
  await expect(page.getByText("Phase 2 persisted label", { exact: true })).toBeVisible();
});
