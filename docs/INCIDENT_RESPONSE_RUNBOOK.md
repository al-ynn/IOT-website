# Incident Response Runbook

For every incident: assign an incident lead, record UTC timestamps, preserve relevant logs and identifiers without copying secrets, contain impact, recover through an approved path, validate tenant boundaries, and document follow-up. Legal/compliance procedures are outside this technical runbook.

| Incident | Containment and rotation | Evidence | Recovery and post-checks |
|---|---|---|---|
| User authentication compromise | Revoke affected PATs; suspend account if needed; rotate password | Auth events, IPs, affected user/resource IDs | Restore access deliberately; check Admin and cross-org actions |
| Device Credential compromise | Revoke credential immediately; issue least-scope replacement | Credential prefix/ID, Device ID, endpoint/rate logs | Deploy replacement; prove old token 401 and new scopes work |
| Webhook secret compromise | Disable Webhook; rotate signing secret; notify receiver operator | Webhook/delivery IDs and sanitized response data | Update receiver; test signing; review delivered payload scope |
| `APP_KEY` exposure | Remove traffic and restrict secret access; preserve old key for controlled decryption/recovery | Deployment/config access audit | Rotate through an explicit encrypted-data migration; verify all encrypted fields |
| Database outage | Stop unsafe writes/workers if consistency is uncertain | DB health, connection errors, queue age | Restore/fail over; run readiness, integrity, authorization, and queue checks |
| Queue outage | Stop enqueue-heavy operations if backlog threatens capacity; restart supervised worker | Failed jobs, oldest age, worker logs | Drain safely; verify Webhook/Report terminal states and duplicates |
| Scheduler outage | Restore scheduler invocation; avoid manually duplicating active runs | Heartbeat and command logs | Run overdue safe commands deliberately; verify Automation/expiry/retention |
| Storage outage | Disable upload/generation workflows; prevent misleading success | Storage errors and affected artifact IDs | Restore access/data; verify private authorization, checksums, and references |
| Telemetry flood | Revoke offending credential or restrict ingress; retain rate evidence | Device/credential IDs, request counts, DB growth | Re-enable with bounded scopes; verify rate limiting and storage health |
| Cross-org security incident | Disable affected accounts/tokens; isolate relevant endpoints | Actor, raw IDs, responses, audit/log records | Patch and attack-test; review affected Organizations before reopening |
