# cdn.php

A Tiny assets manager, that takes a folder and delivers minified & combined assets per type(css/js), works with css &amp; js & .scss(sass). Essentially also a full sass compiler.

See for live useage: [http://162.243.206.225/](http://162.243.206.225/), [the code base](https://github.com/sultantarimo/Framework.php)


### Usage

```php
<?php assets('/assets/css/', 'all'); ?>
<?php assets('/assets/js/', 'all'); ?>

// Same as above, (short hand)
<?php assets('/assets/css/'); ?>
<?php assets('/assets/js/'); ?>

// List of files specifically
<?php assets('/assets/css/', 'main.css, header.scss'); ?>
<?php assets('/assets/js/', 'main.js, module.js'); ?>

// Lists files to exclude
<?php assets('/assets/css/', null, 'main.css, header.scss'); ?>
<?php assets('/assets/js/', null, 'main.js, module.js'); ?>

// Specify different folder to save minified folder. default = first param + '/minified/'
<?php assets('/assets/css/', null, null, '/folder/to/save/minfied/'); ?>
<?php assets('/assets/js/', null, null, '/folder/to/save/minfied/'); ?>

// Unminified
<?php assets('/assets/css/', null, null, false); ?>
<?php assets('/assets/js/', null, null, false); ?>
```

### Parameters

1. Location to assets folder i.e for css it could be '/assets/css/' **(required)
2. 'all' or a list of files seperated by (,) comma or left blank(defaults 'all'). **(optional)
3. list of files  to exclude seperated by (,) comma or left blank(defaults to exlcude none) **(optional)
3. location to save '/minified/' assets folder to **(optional)

### Output

```html
<script src="/assets/minified/all.min.modified-time-stamp.js">
<link rel="stylesheet" href="/assets/minified/all.min.modified-time-stamp.css">
```

#### Example directory structure

```
.
├─── index.php
├─── assets
│   ├── images
│   ├── css
|   |   |── style.css
│   |   └── libs
|   |		└── library.css
│   └── js
|       |── scripts.js
│       └── libs
 			└── library.js
```



The first parameter i.e '/assets/css/' being the www path to the specific asset folder, the second being a list of files i.e

'scripts.js, main.js' seperated by (,) comma or just 'all' to watch all files changes.

### What does it do?

given '/assets/css/' it looks for all files in the specified directory, minifies them and combines them into one all.min.js/all.min.css
& all.js/all.css.

### What else?

It does Sass, you can write .scss files and they will be evaluated at dev-runtime(it does not run on production) to update all.css.

### Performance?

Apart from the generation of all.js/css files it serves the cached copy if nothing has changed(always defaults to this production), and updates the cached copy only on the first request if something has.


### and?

Files are added to all.js alphabetically, so if you name a file something like ___jquery.js it will come before _second.js or third.js in the minified all.js/css, this can helps with javascript if you want one library that another depends on to come first. Also files ending with .min.ext, i.e *main.min.js* or *main.min.css* do not get processed/minified/sass compiled.


### Good luck, and don't forget Gziping for Css and Javascript.

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
