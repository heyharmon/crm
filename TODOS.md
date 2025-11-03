# App To-Dos

**Change user on the my ratings page**
On the @/resources/js/pages/websites/MyWebsiteRatings.vue I need the ability, as an admin, to change the user that I am viewing org website rating for. This can only be done as an admin, otherwise the page loads "my" ratings, as in the authenticated user. Only admins will have the ability to open a menu of users on the page and select another user to see this data for. As for authorization, we can keep the controller a public controller but if this functionality is being used (loading other users ratings) there should be an inline authorization check in the controller to check the requester is an admin.

**Add organization count to categories listed on organization categories page**

**Add filter and sorting for organizations pages count**

**Save filter/sort presets**

**Clean up some database table naming**
