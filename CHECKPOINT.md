# QualiNav Admin Console Checkpoint

Checkpoint created before the inline Organization Setup change requested on 2026-06-24.

This snapshot contains the live cloud plugin state from qualinav.io at the checkpoint time:

- `qualinav-my-org`: renders `/my-org`, including the Organization Setup tile behavior as of the checkpoint.
- `qualinav-admin-console`: renders `/qualinav` and `/qualinav/admin` hospital/admin console routes.

Purpose of this version:

- Preserve the working state after the My Org tile was changed from Scout to Organization Setup.
- Preserve the role rule where the tile is intended for hospital-console users, not Super Admin Console users.
- Provide a restore point before embedding the hospital setup console inside the My Org experience.

No inline console embedding work has been started in this checkpoint.
