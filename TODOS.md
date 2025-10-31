# App To-Dos

**Add filter for last redesign**

**Save filter/sort presets**

**Add Websites I have Rated Page**
Read the docs directory for this app and focus in on the website-ratings folder and read the README there to understand how we enable users to submit their ratings for a website. Now what I want to add to this app is a brand new page where the user can go to see a list of the websites they have rated and give them the opportunity to change their ratings. The reason for adding this page is that when rating websites one by one, you might decide that you may have misrated some of those websites. And so you want to go back and be able to see the timeline of the ratings you submitted so that you can scroll through those ratings and see the websites you might want to change your rating for. And on this page, it would be really helpful if you could filter your organization website ratings by rating. So if you wanted to see all of the organization websites you rated as bad or poor, for example, you could just see those. Or if you wanted to see the websites you rated as both excellent and good, you could see websites by those filters at the same time. It probably makes sense to add a method to the WebsiteRatingController for this but I don't know, if we need a new controller that's fine. We will certainly need a new page, and just keep this page simple. As far as enabling the user to get to this new page, let's add a button to the current website ratings page that will take them there, and then also over on the dashboard.vue page within the "Websites you rated" card at the bottom put a link there. They can click that link to go view the websites they have rated.

**Clean up some database table naming**
