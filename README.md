Pocket Filler
=============

Prototype Symfony app with the intention of scraping a Twitter feed or RSS feed to pull article links into Pocket

How to use
----------

You'll need to have created apps on both Twitter and Pocket.

* Run `composer install` which should pull in vendor packages and set up Symfony
* To your `app/config/parameters.yml` file add keys for "twitter_key", "twitter_secret", "pocket_key". You may also add an array under the parameter "extra_ignore_urls" which can ignore specific URLs you might see in your timeline that shouldn't be on Pocket.
* Deploy to a web server somewhere
* Navigate to the server route and visit `/pocketForm` to set up Pocket via Oauth to your account
* Navigate again to the server route and visit `/twitterList` to set up Twitter via Oauth to your account
* On the server run `php bin/console app:add-urls` which will check for recent URLs in Twitter (up to 100 tweets recently) and add them to Pocket.
* The script is resistant to being run twice as it checks if the URL is already in Pocket, and also stores the ID of the most recently checked tweet to avoid looping over the same tweets repeatedly.
* Set up the command via `crontab` if you wish.

Plan
----

This isn't meant to be a self-serve application. The next step is to design a simple front end using Bootstrap and a basic database likely on some form of NoSQL hosting so it can be offered as a simple web service for anybody to authenticate their accounts and have scheduled pushes of interesting articles from Twitter into Pocket.

To get a wider variety of content it would also be ideal to support RSS, so that feeds can be added to a personal list, and regular scraping of RSS done to push these to Pocket also.

Finally, combining these so that there is more intelligent logic would be very useful - e.g. guarantee that 5 articles are added each morning and evening from a mix of RSS and Twitter, favouring certain sources etc.

Anyone is welcome to PR for these features or suggest how the service could be hosted or architected.

Current bugs
------------

* Needs a way to tell difference between live/local and generate appropriate absolute URLs - I imagine Symfony has an inbuilt mechanism for this

About
-----

Written by [M1ke](https://twitter.com/m1ke) because I liked the idea and wanted to mess with Symfony framework for the first time. Likely it doesn't follow many Symfony best practices and for such a simple app a smaller framework like Slim or Silex (or even another language & framework such as Rails) might have been more suitable. Any feedback on the design and usage of the framework is welcome.
