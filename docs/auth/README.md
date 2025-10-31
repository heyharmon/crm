# Auth Domain

## Purpose

Manages user authentication including standard registration, invitation-based signup, login, logout and fetching the current user.

## Backend

-   **AuthController**: provides endpoints for registering, logging in, logging out and retrieving the current user. Handles invitation tokens during registration. New users registering without an invitation are assigned the 'admin' role by default, while invited users receive the role specified in their invitation token.
-   **User model**: stores account details including a role field ('admin' or 'guest').
-   **InvitationToken model**: tracks pending invitations that can be redeemed during registration, including the role to be assigned.

## Frontend

-   **Pages**: `resources/js/pages/auth/Login.vue` and `resources/js/pages/auth/Register.vue` implement the login and registration forms. Registration accepts an optional invitation token from the URL.
-   **Service**: `resources/js/services/auth.js` wraps authentication API calls and persists tokens in `localStorage`.
