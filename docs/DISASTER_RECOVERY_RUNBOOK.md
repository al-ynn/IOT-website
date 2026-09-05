# Disaster Recovery Runbook

## Backup scope

- PostgreSQL database, including users, Device Credentials hashes, encrypted Webhook secrets, definitions, run/deployment history, and queue state.
- Private Firmware and Report artifact storage with versioning where supported.
- Deployment environment and configuration from the approved secret/configuration manager, especially the original `APP_KEY`.
- Reverse-proxy, worker, scheduler, DNS, TLS, mail, and monitoring configuration maintained by the deployment platform.

Do not back up generated frontend assets as the sole source; they are reproducible from the release artifact. Crash binary artifacts do not currently exist.

## Frequency and retention

Operations must define RPO/RTO before launch. Until defined, no compliant backup frequency can be claimed. Use database-native consistent backups and coordinate storage snapshots so database artifact references and private objects can be restored to a compatible point.

## Restore order

1. Isolate the failed environment and preserve forensic evidence where relevant.
2. Provision the same application release and dependencies.
3. Restore the original secret/configuration set, especially `APP_KEY`; changing it makes encrypted Webhook secrets unreadable.
4. Restore PostgreSQL to the selected recovery point.
5. Restore private artifact storage to a consistent recovery point.
6. Run `php artisan migrate --force` only after confirming backup integrity and release compatibility.
7. Rebuild configuration/routes/views caches for the deployed release.
8. Start one queue worker, then the worker pool; start scheduler invocation.
9. Verify readiness, Admin login, Device lists, credential metadata, Webhook secret decryption through a controlled test delivery, Firmware/Report artifact downloads, queue processing, scheduler heartbeat, and no unexpected pending migrations.
10. Re-enable traffic and monitor errors, queue age, telemetry arrival, and storage failures.

## Verification status

A byte-for-byte disposable SQLite copy/restore was exercised locally: source, backup, and restored database SHA-256 values matched (`390BD46EE81CAE0F9D28EEEFD511E049A7137ACB0873FB20960C20249BFADEC1`), and the restored schema was readable. This is tooling confidence only, not a PostgreSQL backup/restore rehearsal. Production database, object storage, encrypted-field, and full deployed restore are **NOT VERIFIED**.
