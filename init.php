<?php

/**
 * Expose assets() func
 */
function assets(
    $dir     = '', 
    $include = 'all', 
    $exclude = null, 
    $out     = null, 
    $minify  = true
)
{
    require_once 'classes/main.php';
    
    $helpers = new __Assets();
    $helpers->assets($dir, $include, $exclude, $out, $minify);
}
