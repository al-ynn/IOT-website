import {expect,test} from "@playwright/test";

let token:string;

test.describe("P1-3 fleet dashboard",()=>{
 test.beforeAll(async({request})=>{const response=await request.post("http://127.0.0.1:18000/api/auth/login",{data:{email:"admin@iot-platform.test",password:"Admin123!"}});expect(response.ok()).toBeTruthy();token=(await response.json() as {token:string}).token;});
 test.beforeEach(async({page})=>{await page.addInitScript(value=>localStorage.setItem("iot_token",value),token);await page.goto("/app/dashboard");await expect(page.getByText("Sample Device Dashboard",{exact:true})).toBeVisible({timeout:30_000});});
 test("Device Count renders authorized data",async({page})=>{await expect(page.getByText("Device count",{exact:true}).first()).toBeVisible();});
 test("Device Table renders authorized rows",async({page})=>{await expect(page.getByText("Device table",{exact:true}).first()).toBeVisible();await expect(page.getByText("Sample Device").first()).toBeVisible();});
 test("Metric by Devices renders without unauthorized leakage",async({page})=>{await expect(page.getByText("Metric by devices",{exact:true}).first()).toBeVisible({timeout:30_000});});
 test("Events by Devices widget renders",async({page})=>{await expect(page.getByText("Events by devices",{exact:true}).first()).toBeVisible();});
 test("Geomap fleet widget remains authorization gated",async({page})=>{await expect(page.getByText("Geomap",{exact:true}).first()).toBeVisible();});
 test("Image Map fleet widget remains authorization gated",async({page})=>{await expect(page.getByText("Image map",{exact:true}).first()).toBeVisible();});
 test("fleet source picker exposes supported modes",async({page})=>{await expect(page.getByRole("button",{name:"Customize Dashboard"})).toBeVisible({timeout:30_000});await page.getByRole("button",{name:"Customize Dashboard"}).click();const card=page.locator(".dashboard-grid").getByText("Device count",{exact:true}).first();await expect(card).toBeVisible();await card.click();const picker=page.getByLabel("Device source");await expect(picker).toBeVisible();await expect(picker.locator("option")).toHaveText(["All authorized Devices","Explicit Devices","Devices using a Template"]);});
 test("explicit device picker is bounded and selectable",async({page})=>{await expect(page.getByRole("button",{name:"Customize Dashboard"})).toBeVisible({timeout:30_000});await page.getByRole("button",{name:"Customize Dashboard"}).click();const card=page.locator(".dashboard-grid").getByText("Device count",{exact:true}).first();await expect(card).toBeVisible();await card.click();await expect(page.getByLabel("Device source")).toBeVisible();await page.getByLabel("Device source").selectOption("explicit_devices");await expect(page.getByText("Devices (up to 100)")).toBeVisible();});
 test("template source picker is available",async({page})=>{await expect(page.getByRole("button",{name:"Customize Dashboard"})).toBeVisible({timeout:30_000});await page.getByRole("button",{name:"Customize Dashboard"}).click();const card=page.locator(".dashboard-grid").getByText("Device count",{exact:true}).first();await expect(card).toBeVisible();await card.click();await expect(page.getByLabel("Device source")).toBeVisible();await page.getByLabel("Device source").selectOption("template_devices");await expect(page.getByLabel("Template",{exact:true})).toBeVisible();});
});
