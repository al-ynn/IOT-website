# Device Credential Rotation Runbook

1. Confirm the target Device and required scopes. Never reuse a User PAT.
2. Create a replacement Device Credential through the authorized Device context. Copy the plaintext once into the approved secret-delivery channel; do not store it in tickets, URLs, logs, localStorage, or Operational Events.
3. Deploy the replacement to the Device through the real device-management channel. The platform does not remotely install credentials.
4. Verify successful authenticated requests and the replacement credential's `last_used_at`.
5. Keep the old credential active until the replacement is proven unless incident response requires immediate revocation.
6. Revoke only the old credential. Verify it returns 401 while the replacement remains active.
7. If rollout fails, restore Device configuration to the still-active old credential, diagnose transport/configuration, and create another replacement if the plaintext was exposed.

Rotation behavior, independent revocation, scope separation, expiry, and immediate rejection were feature-tested. Physical Device rollout was not tested.
