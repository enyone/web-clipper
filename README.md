web-clipper
===========

PHP script wich allows you to create dashboard-like page displaying parts from different web pages

Used primarily to scrape luch food data from various restaurant pages. See working demo at http://jklfood.enymind.fi/

#### Using web-clipper ####

`parse.php` handles the parsing itself creating a html-document with specified amount of columns
containing one box (div) per. every source page

`config.php` contains all configuration values needed, edit it before usage

#### Crontab ####

Add an entry to your crontab to generate a static html page periodically.

`0 6,9-10,13,18 * * * wget -q http://www.page.com/parse.php?force -O /var/www/index.html`

And for all pages.

`0 6,9-10,13,18 * * * wget -q http://www.page.com/parse.php?force&all -O /var/www/all.html`

And for json data.

`0 6,9-10,13,18 * * * wget -q http://www.page.com/parse.php?force&json -O /var/www/index.json`

#### Database schemas ####

Use `*.sql` files to create your database with proper tables.

`editor.php` file can be used to manage database content, see more at http://www.adminer.org/

#### Other files ####

Just dependecies. Edit `style.css` as you like to achieve a page layout you need.
