MarnetAnalyzer
=========

Developed to analyze the usage of the macedonian top-level internet domain ".mk" in the period from 2003 to 2013. The results (in macedonian language) are presented [here](http://angelovdejan.wordpress.com/2014/01/03/analiza-na-mk-domenite-dekemvri-2013/).

**Important:** This tool fetches the information from [reg.marnet.net.mk](http://reg.marnet.net.mk). In spring 2014, MarNET introduced a new service for previewing domain information - [whois.marnet.mk](http://whois.marnet.mk), which has a different HTML structure. Because of that, I'm not sure if the service used by this tool is up to date anymore. (For example, I think that the web site [domejn.ot.mk](http://domejn.ot.mk) stopped generating new daily reports when the MarNET's new service was started.)

Analyze the domains available in the Macedonian Academic Research Network's registrar.

Before using the application, you need to install the dependencies via Composer.

The main reason why this was developed is having fun. I know that it would have been better to use database for storing the results... i just felt like using some arrays. Also, it was little late when i found out that Guzzle can send parralel http requests.

No further development is guaranteed.

![demo](http://i.imgur.com/p2tLsbl.png)
