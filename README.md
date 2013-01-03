web-clipper
===========

PHP script wich allows you to create dashboard-like page displaying parts from different web pages

#### Using web-clipper ####

`parser.php` handles the parsing itself creating a html-document with specified amount of columns
containing one box (div) per. every source page.

`config.php` contains all configuration values needed, edit it before usage

#### Crontab ####

Add an entry to your crontab to generate a static html page periodically.

`0 6,9-10,13,18 * * * wget -q http://www.page.com/parse.php -O /var/www/index.html`

And for all pages.

`0 6,9-10,13,18 * * * wget -q http://www.page.com/parse.php?all -O /var/www/all.html`

#### Database schemas ####

Use `*.sql` files to create your database with proper tables.

`editor.php` file can be used to manage database content, see more at http://www.adminer.org/

#### Other files ####

Just dependecies. Edit `style.css` as you like to achieve a page layout you need.
