# App To-Dos

**TODO_TEMPLATE**

**Organizations Index Page Grid Component image sizing**
On the organizations index page, organizations can be listed out in an organizations table or the organization grid component. And I need you to look at the organization grid component and look at how the organization's website screenshot is rendered. It just needs to be taller when you change the columns from four columns down to three down to two and down to one. The screen shots get very short, so these screen shots really need to maintain an aspect ratio so that you can see the whole screenshot no matter how many columns are being used.

**Add source column to organization model**
Take a look at the organization model and take a look at the columns in the organization migration. We need to add a new column called'source' which can be a simple string column holding the name of the source of the organization's data (such as HubSpot or Google Maps or n-c-u-a). Allow this column to be undefined, empty, or null. Decide what order to put it in using your best judgement. I think it should be earlier in the table, or more towards the left, probably somewhere after the foreign ID relationship columns and organization name but before some of the other columns. Once you've added that, we need to make sure that that column is editable via the organization controller and editable on the edit organization view component (which I think is the organization form component) that has form fields for editing properties of an organization on the front end.

**Add assets filters to the website ratings page**
Take a look at the WebsiteRatings.vue page on the front end. On this page, a user is shown one organization's website at a time so that they can rate it based on its design. The organization website that is shown to the user is loaded at random and must have the website status equal to up. This is a filter that is being used in the organization controller to randomly load organizations with a website status of up. Now what I need you to do is add another filter to this query so that the organizations that are randomly loaded also have at least $400M in their assets column. Take a look at the assets column of the organization migration to understand how this column works and what kind of number it contains, which is a plain number. Then on the website ratings page add that filter of minimum $400M for assets in the query for the organizations that are loaded. And then the next thing I want you to do on that website ratings page is just show the user in small text somewhere on the page that's not going to be in the user's way and subtly just show the filters that are active so that the user understands that:

1. The organization website they are seeing is for a random organization whose website status is up and whose assets are at least $400 million.
2. They have not yet rated this website.
   Just so that the user knows as a reference what organizations are being loaded for them to rate.

**Add tooltip to assets filter label**
Take a look at the OrganizationFilters.vue component. This is listing fields and toggles that allow me to filter and sort organizations. And there's a field set for assets which has a minimum maximum field. But on the label assets I need to add a tiny little tool tip like a question mark. That I can hover over and it'll show a super basic tool tip that says source of assets from 2025 NCUA data.

**Add Websites I have Rated Page**
Read the docs directory for this app and focus in on the website-ratings folder and read the README there to understand how we enable users to submit their ratings for a website. Now what I want to add to this app is a brand new page where the user can go to see details about the websites they have rated and give them the opportunity to change their ratings. So here we may need a new controller for this, we will certainly need a new page, and just keep this really simple. As far as enabling the user to get to this new page, let's add a button to the current website ratings page that will take them there, and then also over on the dashboard.vue page within the website's new rated card at the bottom put a link there. They can click to go view the websites they have rated.

**Clean up some database table naming**
