# Users Domain

## Purpose

Manages user accounts and invitations for the application.

## Backend

-   **UserController**: provides endpoint for listing all users in the application.
-   **InvitationController**: handles creating invitation tokens and verifying them during registration.
-   **User model**: stores user account details including name, email, password, and role ('admin' or 'guest').
-   **InvitationToken model**: stores invitation details including email, token, expiration, and the role to be assigned.

## Frontend

-   **Pages**: `resources/js/pages/users/UsersIndex.vue` displays a table of all users with their name, email, role, and creation date. Includes an "Invite User" button that opens a modal for creating invitations.
-   **Invitation Flow**: Admin users can create invitations by specifying an email and role. The system generates a unique invitation URL that can be copied and shared with the invitee.

## User Roles

-   **admin**: Full access to all features including user management, invitations, and administrative functions.
-   **guest**: Limited access to application features. Default role for new registrations.
