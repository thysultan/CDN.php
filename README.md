# cdn.php

A Tiny assets manager, that takes a folder and delivers minified combined assets per type, works with css &amp; js


### Usage

```javascript
<?php help()->assets('/assets/css/', 'all'); ?>
<?php help()->assets('/assets/js/', 'all'); ?>
```

```javascript
---/assets/
----------/css/
----------/js/
```

The first parameter i.e '/assets/css/' being the www path to the specific asset folder, the second being a list of files i.e

'scripts.js, main.js' seperated by (,) comma or just 'all' to watch all files changes.

### What does it do?

given '/assets/css/' it looks for all files in the specified directory, minifies them and combines them into one all.js or all.css.

### What else?

It does Sass, you can write .scss files and they will be evaluated at silent-runtime to update all.css.

### Performance?

Apart from the silent generation of all.js/css it servers the cached copy if nothing has changed, and updates the cached copy behind the scene if something has.

See __helpers.php for more comments i.e "If don't want your css compressed".

### What else?

Files are add to all.js alphabetically, so if you name a file something like ___jquery.js it will come before _second.js or third.js, helps with javascript if you want one library to come first that your code depends on.

### Good luck.