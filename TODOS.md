# App To-Dos

**TODO_TEMPLATE**

**Organizations Index Page Grid Component image sizing**
On the organizations index page, organizations can be listed out in an organizations table or the organization grid component. And I need you to look at the organization grid component and look at how the organization's website screenshot is rendered. It just needs to be taller when you change the columns from four columns down to three down to two and down to one. The screen shots get very short, so these screen shots really need to maintain an aspect ratio so that you can see the whole screenshot no matter how many columns are being used.

**Add source column to organization model**
Take a look at the organization model and take a look at the columns in the organization migration. We need to add a new column called'source' which can be a simple string column holding the name of the source of the organization's data (such as HubSpot or Google Maps or n-c-u-a). Allow this column to be undefined, empty, or null. Decide what order to put it in using your best judgement. I think it should be earlier in the table, or more towards the left, probably somewhere after the foreign ID relationship columns and organization name but before some of the other columns. Once you've added that, we need to make sure that that column is editable via the organization controller and editable on the edit organization view component (which I think is the organization form component) that has form fields for editing properties of an organization on the front end.

**Add tooltip to assets filter label**
Take a look at the OrganizationFilters.vue component. This is listing fields and toggles that allow me to filter and sort organizations. And there's a field set for assets which has a minimum maximum field. But on the label assets I need to add a tiny little tool tip like a question mark. That I can hover over and it'll show a super basic tool tip that says source of assets from 2025 NCUA data.

**Add Websites I have Rated Page**
Read the docs directory for this app and focus in on the website-ratings folder and read the README there to understand how we enable users to submit their ratings for a website. Now what I want to add to this app is a brand new page where the user can go to see details about the websites they have rated and give them the opportunity to change their ratings. So here we may need a new controller for this, we will certainly need a new page, and just keep this really simple. As far as enabling the user to get to this new page, let's add a button to the current website ratings page that will take them there, and then also over on the dashboard.vue page within the website's new rated card at the bottom put a link there. They can click to go view the websites they have rated.

**Clean up some database table naming**
