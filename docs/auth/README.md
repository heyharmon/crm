# Auth Domain

## Purpose
Manages user authentication including standard registration, invitation-based signup, login, logout and fetching the current user.

## Backend
- **AuthController**: provides endpoints for registering, logging in, logging out and retrieving the current user. Handles invitation tokens during registration and creates a default team for new users.
- **User model**: stores account details and relationships to teams.
- **InvitationToken model**: tracks pending invitations that can be redeemed during registration.

## Frontend
- **Pages**: `resources/js/pages/auth/Login.vue` and `resources/js/pages/auth/Register.vue` implement the login and registration forms. Registration accepts an optional invitation token from the URL.
- **Service**: `resources/js/services/auth.js` wraps authentication API calls and persists tokens in `localStorage`.
