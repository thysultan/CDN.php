# cdn.php

A Tiny assets manager, that takes a folder and delivers minified combined assets per type, works with css &amp; js


### Usage

```javascript
<?php help()->assets('/assets/css/', 'all'); ?>
<?php help()->assets('/assets/js/', 'all'); ?>
```

The first parameter i.e '/assets/css/' being the www path to the specific asset folder, the second being a list of files i.e

'scripts.js, main.js' seperated by (,) comma or just 'all' to watch all files changes.

### What it does

given '/assets/css/' it looks for all files in the specified directory, minifies them and combines them into one all.js or all.css.

### What else

It does Sass, you can write .scss files and they will be evaluated at silent-runtime to update all.css.

### Performance?

Apart from the silent generation of all.js/css it servers the cached copy if nothing has changed, and updates the cached copy behind the scene if something has.
