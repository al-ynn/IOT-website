import {expect,test,type Page} from "@playwright/test";

const token="10303|phase103-admin1-token";
async function authenticate(page:Page){await page.addInitScript(value=>localStorage.setItem("iot_token",value),token)}

test("authorized user completes the Template onboarding workspace",async({page})=>{
 test.setTimeout(60_000);await authenticate(page);await page.goto("/app/developer/templates");
 await page.getByRole("button",{name:"Create template"}).click();
 await page.getByLabel("Name").fill("Browser Air Template");
 await page.getByLabel("Hardware").selectOption("esp32");await page.getByLabel("Connection type").selectOption("wifi");
 await page.getByLabel("Description").fill("Browser-created safe Template");await page.getByRole("button",{name:"Create",exact:true}).click();
 await expect(page).toHaveURL(/\/app\/developer\/templates\/\d+\/home$/);await expect(page.getByText(/Template ID:/)).toBeVisible();
 await page.getByRole("button",{name:"Settings"}).click();await page.getByLabel("Description").fill("Persisted Template settings");await page.getByRole("button",{name:"Save",exact:true}).click();await page.reload();await expect(page.getByText("Persisted Template settings")).toBeVisible();
 await page.getByRole("link",{name:"Datastreams"}).click();await expect(page.getByText("No Datastreams yet")).toBeVisible();await page.getByRole("button",{name:"Create new"}).first().click();await page.getByLabel("Datastream name").fill("Temperature");await page.getByLabel("Identifier").fill("temperature");await page.getByRole("button",{name:"Units"}).click();await page.getByLabel("Unit").fill("°C");await page.getByRole("button",{name:"Save",exact:true}).click();await page.reload();await expect(page.getByText("temperature",{exact:true})).toBeVisible();
 await page.getByRole("link",{name:"Web Dashboard"}).click();await page.getByRole("button",{name:"Customize Dashboard"}).click();await page.getByRole("button",{name:"Add Label widget"}).click();const dashboardSave=page.waitForResponse(response=>response.url().includes("/device-templates/")&&response.url().endsWith("/dashboard")&&response.request().method()==="PATCH");await page.getByRole("button",{name:"Save Dashboard"}).click();expect((await dashboardSave).status()).toBe(200);const dashboardReload=page.waitForResponse(response=>response.url().includes("/device-templates/")&&response.url().endsWith("/dashboard")&&response.request().method()==="GET");await page.reload();expect((await dashboardReload).status()).toBe(200);await expect(page.getByText("Label",{exact:true}).first()).toBeVisible();
 await page.getByRole("link",{name:"Home",exact:true}).click();await page.getByRole("button",{name:"Add first Device"}).click();await page.getByLabel("Device name").fill("Browser Air Node");await page.getByRole("button",{name:"Create",exact:true}).click();await page.reload();
 await expect(page.getByRole("button",{name:"Set up Datastreams"}).locator("span").first()).toHaveClass(/bg-emerald-500/);await expect(page.getByRole("button",{name:"Set up the Web Dashboard"}).locator("span").first()).toHaveClass(/bg-emerald-500/);await expect(page.getByRole("button",{name:"Add first Device"}).locator("span").first()).toHaveClass(/bg-emerald-500/);
 await page.goto(page.url().replace("/home","/datastreams"));await expect(page.getByText("temperature",{exact:true})).toBeVisible();
});
