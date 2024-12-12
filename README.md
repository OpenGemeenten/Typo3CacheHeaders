# TYPO3 frontend caching modifications with ETag

The content management system (CMS) [TYPO3](https://typo3.org/) provides great methods to get the best performance 
server (backend) and client (frontend) side by using caches. 

This extension only focuses on the frontend caches, which means caching in the visitors browser.

## What is browser caching

Before understanding the meaning of the term ‘browser caching’, it is important to know the concept of caching. Caching 
is a process in which data is kept in a cache. A cache is simply a storage area that stores data for a short time.

Browser caching is a process that involves the temporary storage of resources in web browsers. A visitor’s web browser 
downloads various website resources and stores them in the local drive. These include images, HTML files, and JavaScript 
files. When the user visits the website subsequently, the web page will load faster and the bandwidth usage will be 
reduced.

### How browser caching works

The web server collects information from the website and passes it to the web browser. Caching is done depending on 
whether the user is a first-time visitor or has used the site before. Let’s look at these two cases to understand how 
caching works.

#### Case 1: A first-time user

When you visit a website for the first time, the web browser will collect data from the web server. This is because the 
web resources have not yet been stored in a cache. The web browser will then store the web resources in a cache to 
improve your experience in the subsequent visit to the website.

#### Case 2: The user used the website before

If a user visits a website for the second time using the same computer device, the web page will load faster than the 
first visit. This is because the web browser will retrieve the static web resources like images, CSS, and JavaScript 
from the cache. The web browser will be used to provide the HTML page.

There are two options:

- Fresh content means that it has not expired and it can be retrieved from the cache. 
- Stale content means that its cache period has expired and it can only be retrieved from the web server.

Depending on the case (fresh/stale), the browser decides what to do; retrieve HTML page from cache or from the 
webserver.

### HTTP response headers commonly used for caching

The owner of a website has control over the cache policy. This control is exercised using HTTP cache headers. These 
headers are used to define the maximum time that web resources can be cached before expiring.

The following are the HTTP response headers commonly used for caching:

#### ETag

This is an abbreviation for the term ‘Entity Tag’. It works as a cache validation token. It is used when the cached 
files have expired. The web browser uses ETag in its requests to establish whether there is a stale copy existing in the 
cache.

#### Cache-Control

This header consists of various parameters that control validation, cache behavior, and expiration.

Some of the directives of this header include:

- `no-cache`: This directive instructs the browser to validate the content in the cache to check whether it corresponds to 
  the content in the web server. If the content is fresh, then the browser can fetch it from the cache.
- `public`: This means that the browser or any intermediary party (like CDN or proxies) can cache the web resources.
- `private`: This means that only the browser can cache the web resources.
- `no-store`: This directive instructs the browser not to cache.

#### Expires

This header defines when the resources stored in the cache will expire. When the expiry date reaches, the browser will 
consider the content stale. For example, Expires: Mon, 14 June 2021 10:30:00 GMT.

#### Last modified

This header provides information regarding when the web content was modified. The main content of this information 
includes date and time of modification. For example, Last Modified: Tue, 11 February 2021 10:30:00 GMT.

## How TYPO3 handles the browser caching

Now that we know how browser caching works, lets take a look at how TYPO3 handles it (and why it is totally wrong). 

Out-of-the-box TYPO3 does not handle browser caching at all. You need to enable it with the TypoScript setting 
`config.sendCacheHeaders = 1`. The page for the top level TypoScript object `config` 
[describes the exact behaviour](https://docs.typo3.org/m/typo3/reference-typoscript/main/en-us/TopLevelObjects/Config.html#confval-config-sendcacheheaders) 
of this setting. Related to this is the setting `config.cache_period`, which 
[defines](https://docs.typo3.org/m/typo3/reference-typoscript/main/en-us/TopLevelObjects/Config.html#confval-config-cache-period) the time
a page can stay in the backend, and therefor in the browser cache.

**FUN FACT:** The provided `.htaccess` file, which comes with the TYPO3 core, is removing the `ETag` cache header
again. 
```
# ETag removal
<IfModule mod_headers.c>
    Header unset ETag
</IfModule>
```
So we are left with the cache headers `Last-Modified`, `Expires`, `Cache-Control` (with `max-age` when the page
can be cached) and the deprecated `Pragma`. They probably have done this since the workings of the `Etag` is not fully 
implemented in the TYPO3 core, like the HTTP status codes of the response. 

IMPORTANT NOTE: If you are going to use this extension on an Apache webserver, remove or comment the part in the 
`.htaccess` file where the `ETag` cache header is being removed.

### What's wrong with this?

The visitor (and also editors) see old content served from the browser cache after a change in the TYPO3 backend until 
the browser cache expires as instructed by the `Expires` and `Cache-Control:max-age=` cache headers. Most TYPO3 
installations have been instructed to set the expires time to 24 hours (!!!), so in the worst case visitors and editors 
won't see this change until 24 hours have passed. Visitors which come to the same page for the first time, and do not 
have this page in the browser cache, will see the new changed version of the page.

**FUN FACT:** There is a **Tip** at the bottom of the 
[description](https://docs.typo3.org/m/typo3/reference-typoscript/main/en-us/TopLevelObjects/Config.html#confval-config-sendcacheheaders) 
of `config.sendCacheHeaders` mentioning exactly these problems. The solution is ... hold it ... tada: Shift-Reload the 
page. This is exactly what not to instruct the editors ;-)

### How does this extension solve this

This extension is using only the `ETag` cache header and the proper response HTTP status codes to tell the browser
whether the content is fresh or stale.

The `ETag` header is a `md5` hash of the content of a page and will be calculated with each request.
1. At the first request this `ETag` is stored by the browser. Furthermore the browser is instructed to validate 
   resources on the server before use it from the cache by `Cache-Control: no-cache`
2. At the next request the browser will send the request header `if-none-match`, containing the content of the `ETag` 
   from step 1.
3. A TYPO3 middleware checks if the `if-none-match` is still equal to the current `ETag` of the content. 
   - If the content is still the same HTTP status code `304, Not Modified` will be send back to the browser, instructing
     the browser it can retrieve the page from its browser cache.
   - If the content has changed HTTP status code `200 OK` will be send to the browser, instructing the browser to
     retrieve the page from the web server.

When the HTTP status code `304, Not Modified` is send, no content will be send, keeping the response very small.
See`Symfony\Component\HttpFoundation`

## IMPORTANT NOTE

When using CSP in TYPO3 with `nonce`, browsers (and TYPO3) will disable client-side caching.

## More info

You can find a great tutorial on Medium by Alex Barashkov: 
"[Best practices for cache control settings for your website](https://medium.com/pixelpoint/best-practices-for-cache-control-settings-for-your-website-ff262b38c5a2)"
