# App To-Dos

We may need to normalize the data about the html fetched from wayback because some snapshots from wayback may be malformed or return partial results or versions of a page that were broken or returning a server error. So the pages that are compared should be normalized so that pages with vastly different amounts of html content are omited from the analysis.

**Set default organization sort on the organizations index page**
On the organization's index page, we list organizations in a table or a grid. On the left side, we have an organization filters component which persists query params in the address bar, but also in the API request to the back-end in order to filter and sort organizations. There are some default filters that I want to be active any time I load this page, if there are not already any filter or sorting params already in the address bar where those would be persisted. So, what I mean is that when I visit the organization's index page at the /organizations route, if there are no query params in the route already, then I want you to automatically put in place the following parameter: Website status should be set to up. Just to be super clear here, if I visit /organizations and there are no query params in the address bar, then set website status to up. If there are any website params in the route, then do nothing because we do not want to disrupt the params the user has set or is expecting to see when visiting a route.

**Add Websites I have Rated Page**
Read the docs directory for this app and focus in on the website-ratings folder and read the README there to understand how we enable users to submit their ratings for a website. Now what I want to add to this app is a brand new page where the user can go to see details about the websites they have rated and give them the opportunity to change their ratings. So here we may need a new controller for this, we will certainly need a new page, and just keep this really simple. As far as enabling the user to get to this new page, let's add a button to the current website ratings page that will take them there, and then also over on the dashboard.vue page within the website's new rated card at the bottom put a link there. They can click to go view the websites they have rated.

**Clean up some database table naming**

**Enhance pagination**
On the organizations index page we list organizations in a table or a grid. There is also a pagination component. I want to increase the per page to 100 so that 100 organizations are loaded per page by default. Then I also want to add a dropdown to the pagination component that allows me to select the per page amount and increase it or decrease it. You choose reasonable selections for this dropdown.
