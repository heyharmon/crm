# Teams Domain

## Purpose
Handles creation of teams, membership management and role assignments.

## Backend
- **TeamController**: provides endpoints for listing teams, creating and updating teams, inviting users, accepting or declining invitations, removing members and changing member roles.
- **Team model**: represents a team owned by a user and linked to members through a pivot table.
- **InvitationToken model** and **Mailable classes** (`NewUserTeamInvitation`, `TeamInvitation`): support sending and tracking invitations.

## Frontend
- **Pages**: `resources/js/pages/teams/Index.vue` lists teams and allows creation; `resources/js/pages/teams/Show.vue` displays team details, members and pending invitations.
- **Store**: `resources/js/stores/teamStore.js` fetches teams, manages invitations and membership actions.
