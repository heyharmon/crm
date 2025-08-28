# Invitations Domain

## Purpose
Manages invitation tokens used to invite users to teams and allow token-based registration.

## Backend
- **InvitationController**: verifies invitation tokens before registration.
- **InvitationToken model**: stores invitation details, associated team and expiration.
- **AuthController**: processes registration requests that include a valid invitation token.
- **TeamController**: issues invitations, and provides endpoints to accept or decline them.

## Frontend
- **Registration page** (`resources/js/pages/auth/Register.vue`): accepts an invitation token via query string and submits it during signup.
- **Team pages and store**: `resources/js/pages/teams/Index.vue`, `resources/js/pages/teams/Show.vue` and `resources/js/stores/teamStore.js` display pending invitations and allow members to accept or decline.
