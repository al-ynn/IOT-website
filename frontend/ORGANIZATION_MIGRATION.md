# Organization and team migration

The previous organization experience consisted of a single legacy organization card and a members page that mixed invitation forms, member cards, and invitation cards without complete loading, error, empty, filtering, billing-limit, or permission states.

Phase 21 retains the existing organization context, organization service, invitation service, permission API, billing context, and Laravel authorization. Organization profile mutation is limited to the backend-supported name field. Settings use the existing settings endpoints.

Member records are read-only because no member update/removal/detail endpoint exists. Member status is shown as unavailable because the members response does not include account status. Invitations support backend creation and cancellation; resend is not displayed because no endpoint exists.

The roles page displays the authenticated user’s real role and permissions returned by `/permissions`. A full role-to-permission matrix and role mutation are not shown because the backend exposes neither capability. The backend defines `members.manage` and `organization.manage`; it does not define `organization.view`.
