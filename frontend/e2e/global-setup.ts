import { prepareBrowserTestDatabase } from "./prepare-browser-db.mjs";

export default function globalSetup(){
  console.log("PW_DIAG GLOBAL SETUP START");
  if(process.env.PLAYWRIGHT_EXTERNAL_SERVERS==="1") { console.log("PW_DIAG GLOBAL SETUP COMPLETE"); return; }
  prepareBrowserTestDatabase();
  console.log("PW_DIAG GLOBAL SETUP COMPLETE");
}
