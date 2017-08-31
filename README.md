# README for MaintLog

## Building

> composer install

## Packaging

  ... in src folder

> tar -cvzf ../app.tgz .env.sample .htaccess *.php templates/ vendor/

## Deploy

the admin password for setup/ and list/ is set in .env 
alongwith a few other settings as described in .env.sample

deploy the starter database "empty.sqlite" to a suitable location
and define it's location in .env

## TODO

* Some sort of PIN to view list and clear out old items
