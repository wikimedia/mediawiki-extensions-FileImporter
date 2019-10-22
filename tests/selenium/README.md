# Selenium tests

Please see tests/selenium/README.md file in mediawiki/core repository, usually at mediawiki/vagrant/mediawiki folder.

## Setup

Set up MediaWiki-Vagrant:

    cd mediawiki/vagrant
    vagrant up
    vagrant roles enable fileimporter
    vagrant provision
    cd mediawiki
    npm install

## Start Chromedriver

You first have to start Chromedriver in one terminal tab (or window):

    chromedriver --url-base=wd/hub --port=4444

## Run all tests

    npm run selenium-test

## Run test(s) from one file

    ./node_modules/.bin/wdio tests/selenium/wdio.conf.js --spec tests/selenium/specs/FILE-NAME.js

`wdio` is a dependency that you have installed with `npm install`.

## Run specific test(s)

To run only test(s) which name contains string TEST-NAME, run this from the project's root directory:

    ./node_modules/.bin/wdio tests/selenium/wdio.conf.js --spec tests/selenium/specs/FILE-NAME.js --mochaOpts.grep TEST-NAME

Make sure Chromedriver is running when executing the above command.
