# Invitations Domain

## Purpose

Manages invitation tokens used to invite users to the application with a specified role.

## Backend

-   **InvitationController**: verifies invitation tokens before registration.
-   **InvitationToken model**: stores invitation details including email, token, expiration, and the role to be assigned ('admin' or 'guest').
-   **AuthController**: processes registration requests that include a valid invitation token and assigns the specified role to the new user.

## Frontend

-   **Registration page** (`resources/js/pages/auth/Register.vue`): accepts an invitation token via query string and submits it during signup.
