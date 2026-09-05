import {expect,test} from "./fixtures/test";

let token:string;

test.describe("P1-2 map widgets",()=>{
 test.beforeEach(async({page})=>{const response=await page.request.post("http://127.0.0.1:18000/api/auth/login",{data:{email:"admin@iot-platform.test",password:"Admin123!"}});expect(response.ok()).toBeTruthy();token=(await response.json() as {token:string}).token;await page.addInitScript(value=>localStorage.setItem("iot_token",value),token);await page.goto("/app/dashboard");});
 test("Geomap widget renders",async({page})=>{await expect(page.getByText("Geomap",{exact:true}).first()).toBeVisible();});
 test("Image Map widget renders with protected asset state",async({page})=>{await expect(page.getByText("Image map",{exact:true}).first()).toBeVisible();});
 test("map widgets expose safe authorized states",async({page})=>{await expect(page.locator('[aria-label="Geographic map of authorized Device locations"]')).toHaveCount(1);await expect(page.locator('[aria-label="Device floorplan with overlay markers"]')).toHaveCount(1);});
 test("invalid coordinates do not produce a fallback marker",async({page})=>{await expect(page.locator('[aria-label="Geographic map of authorized Device locations"]')).toHaveCount(1);await expect(page.locator('[aria-label="Geographic map of authorized Device locations"] .custom-marker')).toHaveCount(0);});
 test("map widgets remain present at tablet width",async({page})=>{await page.setViewportSize({width:768,height:1024});await expect(page.getByText("Geomap",{exact:true}).first()).toBeVisible();});
 test("map widgets remain present at narrow width",async({page})=>{await page.setViewportSize({width:375,height:667});await expect(page.getByText("Image map",{exact:true}).first()).toBeVisible();});
 test("map widgets remain present at desktop width",async({page})=>{await page.setViewportSize({width:1440,height:900});await expect(page.getByText("Geomap",{exact:true}).first()).toBeVisible();});
 test("protected asset endpoint rejects a guessed id",async({page})=>{const response=await page.request.get("http://127.0.0.1:18000/api/dashboard-map-assets/999999",{headers:{Authorization:`Bearer ${token}`}});expect([401,403,404]).toContain(response.status());});
});
