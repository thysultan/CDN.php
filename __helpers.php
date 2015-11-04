<?php

/**
 * If you expect to write 20k+ lines of sass code per file
 * the complier could timeout, uncomment the below to insure
 * php has enough time and memory to parse 20k+ lines
 */

// ini_set('memory_limit', '512M');      // 512 mb
// ini_set('max_execution_time', '300'); // 5 mins

use Leafo\ScssPhp\Compiler;

class __Helpers{

    private $_DS_ = DIRECTORY_SEPARATOR;

    private $_ASSETS_;
    private $_WWW_ASSETS_;
    private $_BASE;

    public function __construct() {
        $this->_BASE_       = str_replace(array('/', '\\'), $this->_DS_, $_SERVER['DOCUMENT_ROOT']);
    }


    /**
     * Sass/Scss compiler
     */
    private function _sass($css)
    {
        if ( class_exists( 'scssc', false ) ) return false;

        include_once '__sass.php';

        $scss = new Compiler();


        /**
         * Want uncompressed css? uncomment the below
         */
        // $scss->setFormatter('Leafo\ScssPhp\Formatter\Expanded');


        return $scss->compile($css);
    }


    /**
     * Pretty Print
     */
    public function printP( $array, $type = 0 )
    {
        echo '<pre>';
        ( $type === 1 ) ? var_dump( $array ) : print_r( $array );
        echo '</pre>';
    }


    /**
     * Do A Quick Benchmark Tests
     */
    public function benchmark( $funcName, $value )
    {
        $numCycles  = 10000;
        $time_start = microtime( true );

        for ( $i = 0; $i < $numCycles; $i++ )
        {
            clearstatcache();
            $funcName( $value );
        }

        $time_end   = microtime(true);
        $time       = $time_end - $time_start;

        echo "<pre> $funcName x $numCycles = $time seconds </pre>\n";
    }


    /**
     * Compress Buffer
     */
    private function compress( $buffer )
    {
        // Remove comments
        $buffer = preg_replace('@//.*|/\\*[\\s\\S]*?\\*/|(\"(\\\\.|[^\"])*\")@', '', $buffer);
        // Remove space after colons
        $buffer = str_replace( ': ', ':', $buffer );
        // Remove space before equal signs
        $buffer = str_replace( ' =', '=', $buffer );
        // Remove space after equal signs
        $buffer = str_replace( '= ', '=', $buffer );
        // Remove whitespace
        $buffer = str_replace( array("\r\n\r\n", "\n\n", "\r\r", '\t', '  ', '    ', '    '), '', $buffer );
        // Remove new lines
        $buffer = preg_replace( '/\s+/S', '', $buffer );

        return $buffer;
    }


    /**
     * Loop through files,
     * check if minified version is older than file
     * refresh minified version if true
     */

    private function save($data)
    {
        extract($data);

        $buffer      = '';
        $files       = array();

        // If this changes then we refresh the minified copy.
        $refresh     = array(
            'state' => false,
            'value' => false
        );

        foreach ($args as $key => $value)
        {
            if( file_exists($value) )
            {
                $files[] = $value;

                // if minified file older than real file, refresh
                if( $minified['time'] > filemtime($value) === false && $refresh['state'] === false )
                {
                    $refresh['state'] = true;
                    $refresh['value'] = true;
                }
            }
        }




        // Refresh? create/update file
        if( $refresh['value'] === true )
        {
            foreach ($files as $key => $value)
            {
                $ext  = explode( '.', $value );
                $ext  = end($ext);

                // Only runs once every update
                if( $ext === 'scss' )
                {
                    $buffer .= $this->_sass( file_get_contents($value) );
                }

                else
                {
                    $buffer .= $this->compress( file_get_contents($value) );
                }
            }

            // make dir if doesn't return
            if( is_dir( $output['path'] ) === false )
            {
                mkdir( $output['path'] );
            }

            file_put_contents( $minified['path'], $buffer );
        }

    }

    /**
     * Render Assets Link
     */
    public function assets($dir, $args = 'all', $out = null)
    {

        $dir = ( substr($dir, -1) !== '/' ) ? $dir.'/' : $dir;

        // If directory not specified or empty list, return;

        if( !isset($dir) || $args === '' ) return false;


        /**
         * get type from last file name
         * if 3rd last character is j then js file else css file
         */

        $type = ( substr($dir, -3, 1) === 'j' ) ? 'js' : 'css';

        $dir = explode('/', $dir);
               unset( $dir[ count($dir)-2 ] );

        $dir = implode('/', $dir);
        $Dir = str_replace(array('/', '\\'), $this->_DS_, $dir);

        $this->_ASSETS_     = $this->_BASE_ . str_replace(array('/', '\\'), $this->_DS_, $dir);
        $this->_WWW_ASSETS_ = $dir ;

        $out                = ( $out !== null ) ? $this->_BASE_.$out : $this->_ASSETS_;

        // Include all files
        if( $args === 'all' )
        {
            $rii       = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->_ASSETS_));
            $files     = array();

            foreach ($rii as $file)
            {
                $ext  = explode( '.', $file->getPathname() );
                $ext  = end($ext);

                if (
                    $file->isDir() ||
                    substr($file->getFileName(), 0, 1) == '.' ||
                    strpos($file,'minified') !== false
                    )
                {
                    continue;
                }

                // If it's a file that is not of the same type, continue
                if( strpos($ext, $type) === false )
                {
                    continue;
                }

                $files[] = $file->getPathname();
            }

            sort($files);

            $args = implode(',', $files);
        }

        else
        {
            $args = preg_replace('/\s+/', '', $args);
            $args = explode(',', $args);

            foreach ($args as $file)
            {
                $files[] = $this->_ASSETS_ . $type . $this->_DS_ . $file;
            }

            $args = implode(',', $files);
        }


        // replace '/' with native directory sep, make array.
        $args = str_replace(array('/', '\\'), $this->_DS_, $args);
        $args = explode(',', $args );

        // Define source, output and minified links
        $source = array(
            'path'  => str_replace(array('/', '\\'), $this->_DS_, $out),
            'www'   => str_replace($this->_DS_, '/', str_replace($this->_BASE_ ,'', $out) )
        );

        $output = array(
            'path' => $source['path'] . 'minified' . $this->_DS_,
            'www'  => $source['www'] . 'minified/'
        );

        $minified = array(
            'time' => ( file_exists( $output['path'] . 'all.'.$type ) ) ? filemtime( $output['path'].'all.'.$type ) : null ,
            'path' => $output['path'] . 'all.' . $type,
            'www'  => $output['www'] . 'all.' . $type
        );

        $data = array(
            'args'     => $args,
            'minified' => $minified,
            'output'   => $output,
            'source'   => $source,
            'dir'      => $dir,
            'type'     => $type
        );


        // Saves if updated, doesn't if not
        $this->save($data);

        // Append ?v=time_last_updated for cache management
        $minified['www'] .= '?v='.filemtime( $minified['path'] );

        // Define html to append
        $html = array(
            'css' => '<link  href="'. $minified['www'] .'" rel="stylesheet">',
            'js'  => '<script src="'. $minified['www'] .'"></script>'
        );

        // Render
        echo $html[$type];
    }
}

/**
 * Exposes helpers to all
 */

function help(){
    $helpers = new __Helpers();
    return $helpers;
}
