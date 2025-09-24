# WikidotPageNotifier

> A PHP script to monitor the latest pages on any Wikidot site and automatically send notifications to Discord. Designed for PHP 5.5+ environments, it supports multiple new pages at once, ensures no duplicate alerts, and is easy to configure with a simple JSON file.

---

## Requirements

- PHP 5.5 or higher
- `curl` extension enabled
- Access to a Discord webhook URL
- Wikidot account (for sites requiring login)

---

## Installation

1. Clone this repository:

```bash
git clone https://github.com/SupsilonBrella/WikidotPageNotifier.git
cd WikidotPageNotifier
```
2.	Upload the files to your PHP environment (shared hosting or server).
	3.	Copy config.example.json to config.json and edit it:
```json
{
  "site": "yourwikidotsitename",
  "wikidotLogin": "yourusername:yourpassword",
  "webhookURL": "https://discord.com/api/webhooks/xxxx/xxxx"
}
```
* site: Wikidot site name (e.g., scr-wiki)
* wikidotLogin: Your Wikidot username and password, separated by a colon (username:password)
* webhookURL: Your Discord webhook URL

---
## Usage
Run the php script:
```bash
php monitor.php
```
* The script will log in to Wikidot using the provided credentials.
* Fetch the latest pages from the RSS feed.
* Compare with latestPage.json to detect new pages.
* Send new pages to the configured Discord webhook.
* Update latestPage.json with the newest page URL.

**Tip:** Use cron to run the script periodically (e.g., every 5 minutes).

Example cron entry:
```cron
*/5 * * * * /usr/bin/php /path/to/monitor.php
```


