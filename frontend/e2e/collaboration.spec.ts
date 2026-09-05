import { expect, test, type Page } from "./fixtures/test";

const password="Browser123!";
const tokens:Record<string,string>={viewer:"10301|phase103-viewer-token",unassigned:"10302|phase103-unassigned-token",admin1:"10303|phase103-admin1-token",admin2:"10304|phase103-admin2-token",full:"10305|phase103-full-token",editor2:"10306|phase103-editor2-token",recipient:"10307|phase103-recipient-token"};

async function login(page:Page,email:string){
  await page.goto("/login");
  await page.getByLabel("Email").fill(email);
  await page.getByLabel("Password").fill(password);
  await page.getByRole("button",{name:"Login"}).click();
  await expect(page).toHaveURL(/\/app\/dashboard$/);
}

async function authenticate(page:Page,token:string){await page.addInitScript(value=>localStorage.setItem("iot_token",value),token);await page.goto("/app/collaboration");await expect(page).not.toHaveURL(/\/login$/);}

function observe(page:Page){
  const consoleErrors:string[]=[];const exceptions:string[]=[];const failed:string[]=[];const serverErrors:string[]=[];
  page.on("console",message=>{if(message.type()==="error"&&!message.text().startsWith("Failed to load resource:"))consoleErrors.push(message.text());});
  page.on("pageerror",error=>exceptions.push(error.message));
  page.on("requestfailed",request=>{if(request.url().startsWith("http://127.0.0.1:15173")||request.url().startsWith("http://127.0.0.1:18000"))failed.push(`${request.method()} ${request.url()} ${request.failure()?.errorText}`);});
  page.on("response",response=>{if(response.status()>=500)serverErrors.push(`${response.status()} ${response.url()}`);});
  return()=>{expect(consoleErrors,"console errors").toEqual([]);expect(exceptions,"uncaught exceptions").toEqual([]);expect(failed,"failed requests").toEqual([]);expect(serverErrors,"unexpected 5xx").toEqual([]);};
}

test("staff workspace, search, notifications, and Dashboard source boundary use real APIs",async({page},testInfo)=>{
  const clean=observe(page);await login(page,"viewer@phase103.test");
  await page.goto("/app/collaboration");await expect(page.getByRole("heading",{name:"Collaboration",exact:true})).toBeVisible();
  await expect(page.getByRole("link",{name:/Phase103 Shared Template/}).first()).toBeVisible();
  await page.screenshot({path:testInfo.outputPath("staff-collaboration-overview.png"),fullPage:true});

  await page.goto("/app/shared-with-me");await expect(page.getByRole("heading",{name:"Shared With Me"})).toBeVisible();
  await expect(page.getByRole("link",{name:"Phase103 Shared Template"})).toBeVisible();await expect(page.getByRole("link",{name:"Phase103 Shared Source Boundary"})).toBeVisible();
  await page.screenshot({path:testInfo.outputPath("shared-with-me.png"),fullPage:true});

  await page.goto("/app/search?q=PHASE103-D1");await expect(page.getByRole("heading",{name:"Search"})).toBeVisible();await expect(page.getByText("Phase103 Shared Temperature Device").first()).toBeVisible();
  await page.getByLabel("Search accessible resources").fill("PHASE103-D2");await page.getByRole("button",{name:"Search",exact:true}).click();await expect(page.getByText("No accessible resources found.")).toBeVisible();
  await page.screenshot({path:testInfo.outputPath("search-authorization.png"),fullPage:true});

  const dashboardId=await page.evaluate(async()=>{const response=await fetch("/api/shared-with-me?resource_type=dashboard",{headers:{Authorization:`Bearer ${localStorage.getItem("iot_token")}`,Accept:"application/json"}});const body=await response.json();return body.data[0].resourceId as string;});
  await page.goto(`/app/dashboard/${dashboardId}`);await expect(page.getByText("Phase103 Shared Source Boundary")).toBeVisible();await expect(page.getByText("Restricted pressure")).toBeVisible();await expect(page.getByText(/unavailable|not accessible/i)).toBeVisible();

  await page.goto("/app/notifications");await expect(page.getByRole("heading",{name:"Notifications",exact:true})).toBeVisible();await expect(page.getByText("Phase103 unread notice")).toBeVisible();await page.getByRole("button",{name:"Mark read"}).click();await expect(page.getByRole("button",{name:"Mark unread"})).toBeVisible();
  clean();
});

test("logout, account switch, direct authorization, and Admin personal/global boundaries",async({page})=>{
  const clean=observe(page);await login(page,"viewer@phase103.test");
  await page.goto("/app/shared-with-me");await expect(page.getByText("Phase103 Shared Template")).toBeVisible();
  await page.getByRole("button",{name:"Open user menu"}).click();await page.getByRole("button",{name:"Logout"}).click();await expect(page).toHaveURL(/\/login$/);
  await page.goto("/app/shared-with-me");await expect(page).toHaveURL(/\/login$/);await expect(page.getByText("Phase103 Shared Template")).toHaveCount(0);

  await login(page,"unassigned@phase103.test");await page.goto("/app/search?q=PHASE103-D1");await expect(page.getByText("No accessible resources found.")).toBeVisible();
  const deviceId=await page.evaluate(async()=>{const response=await fetch("/api/search?q=PHASE103-D1",{headers:{Authorization:`Bearer ${localStorage.getItem("iot_token")}`,Accept:"application/json"}});return response.status;});expect(deviceId).toBe(200);
  await page.goto("/app/devices/1");await expect(page.getByText("Device not found or unavailable.")).toBeVisible();

  await page.getByRole("button",{name:"Open user menu"}).click();await page.getByRole("button",{name:"Logout"}).click();await login(page,"admin1@phase103.test");
  await page.goto("/app/collaboration");await expect(page.getByRole("heading",{name:"Collaboration",exact:true})).toBeVisible();await expect(page.getByRole("heading",{name:"Administration"})).toBeVisible();
  await page.goto("/admin/overview");await expect(page).toHaveURL(/\/admin\/overview$/);await page.goto("/admin/reviews");await expect(page.getByRole("heading",{name:"Admin Review Center"})).toBeVisible();
  await page.getByRole("button",{name:"Open user menu"}).click();await page.getByRole("button",{name:"Logout"}).click();await login(page,"viewer@phase103.test");await page.goto("/admin/reviews");await expect(page).toHaveURL(/\/unauthorized$/);
  clean();
});

test("deep links, refresh, back-forward, lazy routes, and empty states remain coherent",async({page})=>{
  const clean=observe(page);await authenticate(page,tokens.viewer);
  await page.goto("/app/search?q=Phase103%20Shared");await expect(page.getByRole("heading",{name:"Search"})).toBeVisible();await page.reload();await expect(page.getByText("Phase103 Shared Template").first()).toBeVisible();
  await page.goto("/app/shared-with-me");await expect(page.getByRole("heading",{name:"Shared With Me"})).toBeVisible();await page.goBack();await expect(page).toHaveURL(/\/app\/search/);await page.goForward();await expect(page).toHaveURL(/\/app\/shared-with-me/);
  await page.goto("/app/changes");await expect(page.getByText(/No updates|Changes/i).first()).toBeVisible();
  await page.goto("/app/activity");await expect(page.getByText(/Activity/i).first()).toBeVisible();
  await page.goto("/app/my-work");await expect(page.getByText(/My Work/i).first()).toBeVisible();
  clean();
});

for(const viewport of [{width:320,height:568},{width:375,height:667},{width:768,height:1024},{width:1024,height:768},{width:1440,height:900}]){
  test(`responsive core workspace at ${viewport.width}x${viewport.height}`,async({page},testInfo)=>{
    const clean=observe(page);await page.setViewportSize(viewport);await authenticate(page,tokens.viewer);
    for(const route of ["/app/collaboration","/app/shared-with-me","/app/search?q=PHASE103","/app/notifications","/app/activity"]){
      await page.goto(route);await expect(page.locator("main")).toBeVisible();
      const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth+1);expect(overflow,`${route} horizontal overflow`).toBe(false);
    }
    if(viewport.width===320)await page.screenshot({path:testInfo.outputPath("mobile-320.png"),fullPage:true});
    clean();
  });
}

test("two Admin browser contexts claim, take over, and release one exact submitted revision",async({browser})=>{
  const first=await browser.newContext();const second=await browser.newContext();
  await first.addInitScript(value=>localStorage.setItem("iot_token",value),tokens.admin1);await second.addInitScript(value=>localStorage.setItem("iot_token",value),tokens.admin2);
  const admin1=await first.newPage();const admin2=await second.newPage();
  await admin1.goto("http://127.0.0.1:15173/admin/reviews");await expect(admin1.getByRole("heading",{name:"Admin Review Center"})).toBeVisible();await expect(admin1.getByText("Submitted Revision 1")).toBeVisible();
  await admin1.getByRole("button",{name:"Start Review"}).click();await expect(admin1.getByText(/^My Review .*Submitted by/)).toBeVisible();
  await admin2.goto("http://127.0.0.1:15173/admin/reviews");await expect(admin2.getByText("In Review by Phase103 Admin One")).toBeVisible();
  admin2.once("dialog",dialog=>dialog.accept());await admin2.getByRole("button",{name:"Take Over"}).click();await expect(admin2.getByText(/^My Review .*Submitted by/)).toBeVisible();
  await admin1.reload();await expect(admin1.getByText("In Review by Phase103 Admin Two")).toBeVisible();
  admin2.once("dialog",dialog=>dialog.accept());await admin2.getByRole("button",{name:"Release Review"}).click();await expect(admin2.getByText(/^Available for Review .*Submitted by/)).toBeVisible();
  await first.close();await second.close();
});

test("Viewer reviews, schedules, ignores, and Pulls an actual behind Dashboard revision",async({page})=>{
  const clean=observe(page);await authenticate(page,tokens.viewer);await page.goto("/app/changes");
  await expect(page.getByText("Phase103 Shared Temperature Device")).toBeVisible();await expect(page.getByText(/Revision 1.*2/)).toBeVisible();
  await page.getByRole("button",{name:"Review Changes"}).click();await expect(page.getByRole("dialog",{name:"Revision comparison"})).toBeVisible();await page.getByRole("button",{name:"Close"}).click();
  await page.getByRole("button",{name:"Remind me later"}).click();await expect(page.getByRole("dialog",{name:"Remind me later"})).toBeVisible();await page.getByText("In 1 hour",{exact:true}).click();await expect(page.getByRole("button",{name:"Remind me later"})).toBeFocused();
  await page.getByRole("button",{name:"Ignore"}).click();await expect(page.getByText("Phase103 Shared Temperature Device")).toBeVisible();
  await page.getByRole("button",{name:"Pull Update"}).click();await expect(page.getByText("You are up to date.")).toBeVisible();clean();
});

test("two real editors receive a stale-save conflict and can preserve work as a private Draft",async({browser})=>{
  test.setTimeout(60_000);
  const first=await browser.newContext();const second=await browser.newContext();await first.addInitScript(v=>localStorage.setItem("iot_token",v),tokens.full);await second.addInitScript(v=>localStorage.setItem("iot_token",v),tokens.editor2);
  const a=await first.newPage();await a.goto("http://127.0.0.1:15173/app/devices/1?tab=dashboard");await Promise.all([a.waitForResponse(r=>r.url().includes("/editing-sessions")&&r.request().method()==="POST"),a.getByRole("button",{name:"Customize Dashboard"}).click()]);
  const b=await second.newPage();await b.goto("http://127.0.0.1:15173/app/devices/1?tab=dashboard");await Promise.all([b.waitForResponse(r=>r.url().includes("/editing-sessions")&&r.request().method()==="POST"),b.getByRole("button",{name:"Customize Dashboard"}).click()]);
  await a.getByLabel("Dashboard name").fill("Phase103 Editor A Dashboard");const firstSave=a.waitForResponse(r=>r.url().includes("/dashboard")&&r.request().method()==="PATCH");await a.getByRole("button",{name:"Save Dashboard"}).click();const firstResponse=await firstSave;expect(firstResponse.status(),`${await firstResponse.text()}\n${firstResponse.request().postData()}`).toBe(200);await expect(a.getByRole("heading",{name:"Phase103 Editor A Dashboard"})).toBeVisible();
  await b.getByLabel("Dashboard name").fill("Phase103 Editor B Stale Dashboard");const staleSave=b.waitForResponse(r=>r.url().includes("/dashboard")&&r.request().method()==="PATCH");await b.getByRole("button",{name:"Save Dashboard"}).click();expect((await staleSave).status()).toBe(409);await expect(b.getByRole("dialog",{name:"Newer changes are available"})).toBeVisible();await expect(b.getByRole("button",{name:"Save My Draft"})).toBeVisible();await b.getByRole("button",{name:"Save My Draft"}).click();await expect(b.getByText("My Draft",{exact:true})).toBeVisible();
  b.once("dialog",dialog=>dialog.accept());await b.getByRole("button",{name:"Discard Draft"}).click();await expect(b.getByText("My Draft",{exact:true})).toHaveCount(0);await first.close();await second.close();
});

test("Device share requires recipient acceptance and Admin approval before original-resource access",async({browser})=>{
  test.setTimeout(60_000);
  const senderContext=await browser.newContext();const recipientContext=await browser.newContext();const adminContext=await browser.newContext();await senderContext.addInitScript(v=>localStorage.setItem("iot_token",v),tokens.full);await recipientContext.addInitScript(v=>localStorage.setItem("iot_token",v),tokens.recipient);await adminContext.addInitScript(v=>localStorage.setItem("iot_token",v),tokens.admin1);
  const sender=await senderContext.newPage();await sender.goto("http://127.0.0.1:15173/app/search?q=PHASE103-D1");const href=await sender.getByRole("link",{name:/Open Phase103 Shared Temperature Device/}).getAttribute("href");const deviceId=href!.split("/").pop()!;await sender.goto(`http://127.0.0.1:15173/app/devices/${deviceId}/share`);const recipientValue=await sender.getByLabel("Recipient").locator("option",{hasText:"Phase103 Share Recipient"}).getAttribute("value");await sender.getByLabel("Recipient").selectOption(recipientValue!);await sender.getByLabel("Requested access").selectOption("full_access");await sender.getByRole("button",{name:"Send request"}).click();await expect(sender).toHaveURL(/\/app\/shares\/\d+$/);const shareUrl=sender.url();
  const recipient=await recipientContext.newPage();await recipient.goto(shareUrl);await recipient.getByRole("button",{name:"Accept request"}).click();await expect(recipient.getByText("awaiting admin approval",{exact:true})).toBeVisible();await recipient.goto(`http://127.0.0.1:15173/app/devices/${deviceId}`);await expect(recipient.getByText("Device not found or unavailable.")).toBeVisible();await sender.close();await recipient.close();
  const admin=await adminContext.newPage();await admin.goto("http://127.0.0.1:15173/admin/access-requests");await expect(admin.getByRole("heading",{name:"Device Access Requests"})).toBeVisible();await admin.getByLabel("Final access").selectOption("viewer");await admin.getByRole("button",{name:"Approve"}).click();await expect(admin.getByText("No Device requests await approval.")).toBeVisible();await admin.close();const recipientAfter=await recipientContext.newPage();await recipientAfter.goto(`http://127.0.0.1:15173/app/devices/${deviceId}`);await expect(recipientAfter.getByRole("heading",{name:"Phase103 Shared Temperature Device"})).toBeVisible();
  await senderContext.close();await recipientContext.close();await adminContext.close();
});

test("Admin approves the exact submitted Template revision and the published payload stays pinned",async({page})=>{
  await authenticate(page,tokens.admin1);await page.goto("/admin/reviews");await page.getByRole("button",{name:"Start Review"}).click();await page.getByRole("link",{name:"View Review"}).click();await expect(page.getByText(/pinned to submitted Revision 1/)).toBeVisible();page.once("dialog",dialog=>dialog.accept());await page.getByRole("button",{name:"Approve Submitted Revision"}).click();await expect(page).toHaveURL(/\/admin\/reviews$/);
  const published=await page.evaluate(async()=>{const r=await fetch("/api/device-templates/published",{headers:{Authorization:`Bearer ${localStorage.getItem("iot_token")}`,Accept:"application/json"}});return r.json()});expect(published.data[0].approvedRevision.number).toBe(1);
});

test("Notification dismiss/read state and fake-secret DOM/network regression remain isolated",async({page})=>{
  const responses:string[]=[];page.on("response",async response=>{if(response.url().includes("/api/")&&response.request().method()==="GET")responses.push(await response.text().catch(()=>""))});await authenticate(page,tokens.viewer);for(const route of["/app/collaboration","/app/shared-with-me","/app/search?q=PHASE103","/app/notifications","/app/activity"]){await page.goto(route);await expect(page.locator("main")).toBeVisible();}
  await page.goto("/app/notifications");const notice=page.getByText("Phase103 unread notice").locator("xpath=ancestor::li");await notice.getByRole("button",{name:"Dismiss"}).click();await page.getByLabel("State").selectOption("dismissed");await expect(page.getByText("Phase103 unread notice")).toBeVisible();
  const forbidden=["PHASE103_WEBHOOK_SECRET","PHASE103_API_KEY","PHASE103_PRIVATE_KEY","phase103-password-secret"];const corpus=(await page.locator("body").innerText())+responses.join("");for(const value of forbidden)expect(corpus).not.toContain(value);
});

test("open Device access is revoked by Admin and disappears on the next protected browser request",async({browser})=>{
  const viewerContext=await browser.newContext();const adminContext=await browser.newContext();
  await viewerContext.addInitScript(value=>localStorage.setItem("iot_token",value),tokens.viewer);await adminContext.addInitScript(value=>localStorage.setItem("iot_token",value),tokens.admin1);
  const viewer=await viewerContext.newPage();const admin=await adminContext.newPage();
  await admin.goto("http://127.0.0.1:15173/admin/device-access");await expect(admin).toHaveURL(/\/admin\/device-access/);
  await viewer.goto("http://127.0.0.1:15173/app/search?q=PHASE103-D1");await viewer.getByRole("link",{name:/Open Phase103 Shared Temperature Device/}).click();await expect(viewer.getByRole("heading",{name:"Phase103 Shared Temperature Device"})).toBeVisible();
  const removed=await admin.evaluate(async()=>{const headers={Authorization:`Bearer ${localStorage.getItem("iot_token")}`,Accept:"application/json","Content-Type":"application/json"};const list=await fetch("/api/admin/device-access?search=Phase103%20Device%20Viewer",{headers});const body=await list.json();const assignment=body.data.find((item:{user:{email:string}})=>item.user.email==="viewer@phase103.test");const response=await fetch(`/api/admin/device-access/${assignment.id}`,{method:"DELETE",headers});return response.status;});expect(removed).toBe(204);
  await viewer.reload();await expect(viewer.getByText("Device not found or unavailable.")).toBeVisible();await viewer.goto("http://127.0.0.1:15173/app/search?q=PHASE103-D1");await expect(viewer.getByText("No accessible resources found.")).toBeVisible();
  await viewerContext.close();await adminContext.close();
});

test("Dashboard comments create and reply safely without executing HTML-like text",async({page})=>{
  const clean=observe(page);let browserDialog=false;page.on("dialog",()=>{browserDialog=true});await authenticate(page,tokens.viewer);
  const dashboardId=await page.evaluate(async()=>{const response=await fetch("/api/shared-with-me?resource_type=dashboard",{headers:{Authorization:`Bearer ${localStorage.getItem("iot_token")}`,Accept:"application/json"}});const body=await response.json();return body.data[0].resourceId as string;});
  await page.goto(`/app/dashboard/${dashboardId}`);await page.getByRole("button",{name:"Dashboard discussion"}).click();
  const fixture='<script>alert("phase103")</script>';
  await page.getByLabel("Start a discussion about Dashboard").fill(fixture);await page.getByRole("button",{name:"Start thread"}).click();await expect(page.getByText(fixture,{exact:true})).toBeVisible();expect(browserDialog).toBe(false);
  const reply=page.getByLabel(/Reply to thread/).first();await reply.fill("Phase103 safe reply");await page.getByRole("button",{name:"Reply"}).first().click();await expect(page.getByText("Phase103 safe reply",{exact:true})).toBeVisible();
  clean();
});

test("Admin disables and restores a Template through its lifecycle UI",async({page})=>{
  const clean=observe(page);await authenticate(page,tokens.admin1);
  const templateId="1";
  await page.goto(`/admin/templates/${templateId}`);await expect(page.getByText("Resource lifecycle")).toBeVisible();page.once("dialog",dialog=>dialog.accept());await page.getByRole("button",{name:"Disable",exact:true}).click();await expect(page.getByText("Disabled",{exact:true})).toBeVisible();
  page.once("dialog",dialog=>dialog.accept());await page.getByRole("button",{name:"Restore",exact:true}).click();await expect(page.getByText("Active",{exact:true})).toBeVisible();
  clean();
});
