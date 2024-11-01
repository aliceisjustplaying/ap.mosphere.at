# Welcome to the Apmosphere

Single-file PHP ActivityPub server bridged to Bluesky via [Bridgy Fed](https://fed.brid.gy/ap/@atproto@ap.mosphere.at).

Forked from [https://gitlab.com/edent/activity-bot/](https://gitlab.com/edent/activity-bot/)

Features added:
- Being able to follow/unfollow other users



---

Original README:

# ActivityBot - A Simple ActivityPub Bot Server in a Single PHP File

This is a single PHP file - and an `.htaccess file` - which acts as an extremely basic ActivityPub server for running automated accounts.

This bot can do the following:

* 🔍 Be discovered on the Fediverse
* 👉 Be followed by other accounts
* 🚫 Be unfollowed by accounts
* 📩 Send messages to the Fediverse
* 🖼️ Attach an image & alt text to a message
* 🕸️ Autolink URls, hashtags, and @ mentions
* 🔏 Verify cryptographic signatures

That's it! Here's what it *doesn't* do:

* ❌ Thread replies
* ❌ Delete or update a post
* ❌ Create Polls
* ❌ Attach multiple images
* ❌ Set focus point for images
* ❌ Set sensitivity for images / blur
* ❌ Set "Content Warning"
* ❌ Accurate support for converting user's text to HTML

## Getting started

This is designed to be a lightweight educational tool to show you the basics of how ActivityPub works.

There are no tests, no checks, no security features, no formal verifications, no containers, no gods, no masters.

1. Edit the `index.php` file to add a username, password, and keypair.
   * If you prefer, you can rename `.env.example` to `.env` and place the details in there.
1. Upload `index.php` and `.htaccess` to the *root* directory of your domain. For example `test.example.com/`. It will not work in a subdirectory.
    * If you are using a `.env` file for credentials, upload that as well.
1. Optionally, upload an `icon.png` to make the user look nice.
1. Visit `https://test.example.com/.well-known/webfinger` and check that it shows a JSON file with your user's details.
1. Go to Mastodon or other Fediverse site and search for your user: `@username@test.example.com`
1. Follow your user.
1. Check your `/data/logs/` directory to see if the follow request was received correctly.
1. To post a message, run this on the command line:
   * `curl -X POST -F 'password=your-password-here' -F $'content=Testing!\nVisit https://example.com/ !\n\nThis post has a #hashtage and image including alt text' -F 'image=@banner.png' -F 'alt=A picture'  https://test.example.com/action/send`
1. Check social media to see if the message appears.
1. To read the messages that your server's has sent, visit `https://test.example.com/`

## How this works

* The `.htaccess` file transforms requests from `example.com/whatever` to `example.com/index.php?path=whatever`.
* The `index.php` file performs a specific action depending on the path requested.
* Log files are saved as .txt in the `/data/logs` directory.
* Post files are saved as .json in the `/posts` directory.
* Details of accounts who follow you are saved as .json in the `/data/followers` directory.
* Details of accounts who you follow are saved as .json in the `/data/following` directory.
* Messages sent to your inbox are saved as .json in the `/data/inbox` directory.
* This has sloppy support for sending posts with linked #hashtags, https:// URls, and @ mentions.
* HTTP Message Signatures are verified.

##	Requirements

* PHP 8.3 (We live in the future now)
* The [OpenSSL Extension](https://www.php.net/manual/en/book.openssl.php) (This is usually installed by default)
* HTTPS certificate (Let's Encrypt is fine)
* 100MB free disk space (ActivityPub is a very "chatty" protocol. Expect lots of logs.)
* Docker, Node, MongoDB, Wayland, GLaDOS, React, LLM, Adobe Creative Cloud, Maven (Absolutely none of these!)

## Licence

This code is released to you under the [GNU Affero General Public License v3.0 or later](https://www.gnu.org/licenses/agpl-3.0.html).  This means, if you modify this code and let other people interact with it over a computer network, [you **must** release the source code](https://www.gnu.org/licenses/gpl-faq.html#UnreleasedModsAGPL).

I actively do *not* want you to use this code in production. It is not suitable for anything other than educational use.  The use of AGPL is designed to be an incentive for you to learn from this software and then write something better.

Please take note of [CRAPL v0](https://matt.might.net/articles/crapl/):

> Any appearance of design in the Program is purely coincidental and should not in any way be mistaken for evidence of thoughtful software construction.

## Feedback

* Please raise issues at https://gitlab.com/edent/activity-bot/-/issues
* Or contact me on Mastodon @edent@mastodon.social

## FAQs

### Why doesn't this store replies?

Moderation is hard. When someone replies to you, they store data on your server. That's fine if the data is "LOL! Cool post!" but it is bad if it is "Here is a link to buy an illegal substance http://..."

Do you want to check every message you've received to make sure you're not being a vector for spam?

What happens if someone posts something clearly illegal to your server and then calls the cops on you?

### Will this scale if I have ten thousand followers?

Dunno. Try it and find out.  If your bot is ridiculously popular, you may want to find a better piece of software to host it.

### Why doesn't it support this very important feature that I want?

Because you haven't sent a Pull Request.

I'm not magic. I'm just one person building small tools. I'm happy to help you understand the code I've written but, if you want something in this world, you have to build it yourself.

## How do I run multiple bots with this?

You don't. Every bot needs its own domain name.

This is a *deliberate* choice. Let each bot have its own space on the Internet.

I don't want you to go and buy lots of new domains - you should create free subdomains.

### Why is this built in PHP rather than...?

Because I hate you and want you to suffer.

But, more specifically, because *everything* supports PHP. You can FTP these files onto any host and be guaranteed they'll run.  People don't want to faff around with an NPM install, or setting up a Python VENV.
