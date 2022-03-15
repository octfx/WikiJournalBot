# Wikiversity Bot
This bot can automatically list the articles of a given WikiJournal Volume/Issue.

The bot retrieves all pages that transclude the `{{WikiJournalBotList}}` template.  
Based on this list, the content of each page is retrieved and the volume and issue is extracted.

Then a SPARQL query retrieves all 'scholarly_article' (P31) that are:
- published in 'WikiJournal of Medicine' (P1433 = Q24657325)
- and match the given volume (P478) and issue (P433)

Now the page content between `{{WikiJournalBotList}}` and `{{ListEnd}}` is replaced with the articles found in the query.

The actual rendering of the item is done by a separate template. Which template is used can be configured through the template.

Note: Currently only one list per page is supported.

The bot will honor the [{{bots}}](https://en.wikipedia.org/wiki/Template:Bots) template, with the username set in `.env`.  
The bot can be disabled by placing `{{nobots}}` on its user page.

## Usage
Pages that should be edited by the bot need to contain two templates.  
The beginning of a list is denoted by the `{{WikiJournalBotList}}` template, it takes the following arguments:
- `journal` (required when journal is not set in the page title)
  - The journal name to use to find the journal QID to use in the SPARQL query
- `volume` (required when volume is not set in the page title)
  - The volume number to use in the SPARQL query
- `issue` (required when issue is not set in the page title)
    - The issue number to use in the SPARQL query
- `row_template`
  - The template to use for all found articles
  - The WikiData ID is set as the `item` argument

The SPARQL query automatically fetches an available image set in P18 and sets it as `image=File.ending` in the template
defined in `row_template`.

## Installation
Head over to https://meta.wikimedia.org/wiki/Special:OAuthConsumerRegistration/propose and register a consumer only OAuth 1.0 application.
Check: `This consumer is for use only by XXX.`  
Applicable project: `enwikiversity`  
Required grants: `Edit existing pages`  

Copy `.env.example` to `.env` and set the four keys you've got after the consumer proposition.  
Set `ARTICLE_VOLUME_LIST_TEMPLATE` in `.env` to the template that is used on pages that should have automatic bot updates.  
Set `LIST_END_TEMPLATE` in `.env` to the template that denotes the end of the list.

Run `composer install --no-dev` in the folder where `.env` resides.  
Run `php bot.php` to start the update process.  
