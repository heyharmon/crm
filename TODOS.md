# App To-Dos

We may need to normalize the data about the html fetched from wayback because some snapshots from wayback may be malformed or return partial results or versions of a page that were broken or returning a server error. So the pages that are compared should be normalized so that pages with vastly different amounts of html content are omited from the analysis.

**Add Websites I have Rated Page**
Read the docs directory for this app and focus in on the website-ratings folder and read the README there to understand how we enable users to submit their ratings for a website. Now what I want to add to this app is a brand new page where the user can go to see details about the websites they have rated and give them the opportunity to change their ratings. So here we may need a new controller for this, we will certainly need a new page, and just keep this really simple. As far as enabling the user to get to this new page, let's add a button to the current website ratings page that will take them there, and then also over on the dashboard.vue page within the website's new rated card at the bottom put a link there. They can click to go view the websites they have rated.

**Clean up some database table naming**
