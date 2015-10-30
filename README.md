# cdn.php

A Tiny assets manager, that takes a folder and delivers minified & combined assets per type(css/js), works with css &amp; js & .scss(sass). Essentially also a sass php compiler.


### Usage

```php
<?php help()->assets('/assets/css/', 'all'); ?>
<?php help()->assets('/assets/js/', 'all'); ?>

// Same as above, but a short hand
<?php help()->assets('/assets/css/'); ?>
<?php help()->assets('/assets/js/'); ?>

// List of files specifically
<?php help()->assets('/assets/css/', 'main.css, header.scss'); ?>
<?php help()->assets('/assets/js/', 'main.js, module.js'); ?>

// Specify different folder to save minified folder. default = first param + '/minified/'
<?php help()->assets('/assets/css/', 'all', '/folder/to/save/minfied/'); ?>
<?php help()->assets('/assets/js/', 'all', '/folder/to/save/minfied/'); ?>
```

### Parameters 

1. Location to assets folder i.e for css it could be '/assets/css/' **(required)
2. 'all' or a list of files seperated by (,) comma or left blank(defaults 'all'). **(optional)
3. location to save '/minified/' assets folder to **(optional)

### Output

```html
//Adds versions to the rendered link i.e

<script src="/assets/js/all.js?v=$last-modified-time-stamp">
<link href="/assets/css/all.css?v=$last-modified-time-stamp" rel="stylesheet">
```

###### Example directory structure

```

---/assets/
----------/css/
--------------/libs/
--------------/style.scss
--------------/plugins.scss

----------/js/
--------------/libs/
-------------------/___jquery.js
--------------/main.js
```

The first parameter i.e '/assets/css/' being the www path to the specific asset folder, the second being a list of files i.e

'scripts.js, main.js' seperated by (,) comma or just 'all' to watch all files changes.

### What does it do?

given '/assets/css/' it looks for all files in the specified directory, minifies them and combines them into one all.js or all.css.

### What else?

It does Sass, you can write .scss files and they will be evaluated at silent-runtime to update all.css.

### Performance?

Apart from the generation of all.js/css it servers the cached copy if nothing has changed, and updates the cached copy only on the first request if something has.

See __helpers.php for more comments i.e "If you don't want your css compressed".

### and?

Files are added to all.js alphabetically, so if you name a file something like ___jquery.js it will come before _second.js or third.js in the minified all.js/css, helps with javascript if you want one library to come first that your code depends on.


### Good luck, and don't forget to add Gziping for Css and Javascript to your .htaccess.

```
//.htaccess

# GZIP COMPRESSION

SetOutputFilter DEFLATE
AddOutputFilterByType DEFLATE text/html text/css text/plain text/xml application/x-javascript application/x-httpd-php
BrowserMatch ^Mozilla/4 gzip-only-text/html
BrowserMatch ^Mozilla/4\.0[678] no-gzip
BrowserMatch \bMSIE !no-gzip !gzip-only-text/html
BrowserMatch \bMSI[E] !no-gzip !gzip-only-text/html
SetEnvIfNoCase Request_URI \.(?:gif|jpe?g|png)$ no-gzip
Header append Vary User-Agent env=!dont-vary
```
