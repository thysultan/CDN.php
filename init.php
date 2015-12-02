<?php

/**
 * Expose assets() func
 */
function assets($dir = '', $args = 'all', $out = null, $minify = true)
{
    include_once('assets/class.php');
    $helpers = new __Assets();
    $helpers->assets($dir, $args, $out, $minify);
}
