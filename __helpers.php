<?php

use Leafo\ScssPhp\Compiler;

class __Assets{

    private $_DS_ = DIRECTORY_SEPARATOR;

    private $_ASSETS_;
    private $_WWW_ASSETS_;
    private $_BASE_;
    private $_ROOT_;

    public function __construct() {
        $base = str_replace(array('/', '\\'), $this->_DS_, $_SERVER['DOCUMENT_ROOT']);

        $folder = explode('/', $_SERVER["PHP_SELF"]);
                  array_pop($folder);
        $folder = implode('/', $folder);
        
        $this->_BASE_ = $base . $folder;
        $this->_ROOT_ = $base;
    }


    /**
     * sass compiler
     */
    private function _sass($css, $extended = null)
    {
        if ( class_exists( 'scssc', false ) ) return false;

        include_once '__sass.php';

        $scss = new Compiler();
        
        if($extended){
            $scss->setFormatter('Leafo\ScssPhp\Formatter\Expanded');
        }

        return $scss->compile($css);
    }


    /**
     * compress Buffer
     */
    private function compress( $buffer )
    {
        // Remove comments
        $buffer = preg_replace('/(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:(?<!\:|\\\|\'|\")\/\/.*))/', '', $buffer);
        // Remove space after colons
        $buffer = str_replace( ': ', ':', $buffer );
        // Remove space before equal signs
        $buffer = str_replace( ' =', '=', $buffer );
        // Remove space after equal signs
        $buffer = str_replace( '= ', '=', $buffer );
        // Remove whitespace
        $buffer = str_replace( array("\r\n\r\n", "\n\n", "\r\r", '\t', '  ', '    ', '    '), '', $buffer );
        // Remove new lines
        $buffer = preg_replace( '/\s+/S', ' ', $buffer );

        return $buffer;
    }


    /**
     * loop through files, check minified version age > file refresh minified if true
     */

    private function save($data)
    {
        extract($data);

        $buffer      = array(
            'source'   => '',
            'minified' => ''
        );
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


        // refresh? create/update file
        if( $refresh['value'] === true )
        {
            foreach ($files as $key => $value)
            {
                $ext  = explode( '.', $value );
                $ext  = end($ext);

                // only runs once every update
                if( $ext === 'scss' )
                {
                    $buffer['source']   .= $this->_sass( file_get_contents( $value ), true );
                    $buffer['minified'] .= $this->_sass( file_get_contents( $value ) );
                }

                else
                {
                    $buffer['source']   .= file_get_contents( $value );
                    $buffer['minified'] .= $this->compress( file_get_contents( $value ) );
                }
            }

            // make dir if doesn't return
            if( is_dir( $output['path'] ) === false )
            {
                mkdir( $output['path'] );
            }
            
            // Save minified file 'all.min.ext'
            file_put_contents( $minified['path'], $buffer['minified'] );
            
            // Save unminified file 'all.ext'
            file_put_contents( str_replace('min.', '', $minified['path']), $buffer['source'] );
        }

    }

    /**
     * Render assets link
     */
    public function assets($dir, $args, $out, $minify)
    {
        if($args === null) $args = 'all';
        /**
         * If not actual directory? return;
         */
        if(
            $dir === ''      ||
            !is_string($dir) ||
            !is_dir( $this->_BASE_ . str_replace(array('/', '\\'), $this->_DS_, $dir) )
            )
        {
            echo  '<!-- Error: Not an actual directory -->';
            return;
        }

        $dir = ( substr($dir, -1) !== '/' ) ? $dir.'/' : $dir;


        // Get file type
        $dir  = explode('/', $dir);
        $type = $dir[ count($dir)-2 ];

        if(
            strpos($type, 'css')   !== false ||
            strpos($type, 'style') !== false ||
            strpos($type, 'sass')  !== false ||
            strpos($type, 'scss')  !== false
            )
        {
            $type = 'css';
        }

        else
        {
            $type = 'js';
        }

                              unset( $dir[ count($dir)-2 ] );

        $dir                = implode('/', $dir);
        $Dir                = str_replace(array('/', '\\'), $this->_DS_, $dir);

        $this->_ASSETS_     = $this->_BASE_ . str_replace(array('/', '\\'), $this->_DS_, $dir);
        $this->_WWW_ASSETS_ = $dir ;

        $out                = ( $out !== null ) ? $this->_BASE_.$out : $this->_ASSETS_;


        /**
         * Include all files if 'all' else specified files.
         */
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

                // if it's a file that is not of the same type, continue
                if( strpos($ext, $type) === false )
                {
                    continue;
                }

                $files[] = $file->getPathname();
            }

            if($type === 'js')
            {
                // Comparison function
                function cmp($a, $b) {
                    $a = strtolower($a);
                    $b = strtolower($b);

                    if ($a == $b) return 0;
                    return ($a < $b) ? -1 : 1;
                }

                uasort($files, 'cmp');
            }

            else
            {
                sort($files);
            }

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


        // replace '/' with native directory '/' + make array.
        $args = str_replace(array('/', '\\'), $this->_DS_, $args);
        $args = explode(',', $args );

        // define source, output and, minified links
        $source = array(
            'path'  => str_replace(array('/', '\\'), $this->_DS_, $out),
            'www'   => str_replace($this->_DS_, '/', str_replace($this->_ROOT_ ,'', $out) )
        );

        $output = array(
            'path' => $source['path'] . 'minified' . $this->_DS_,
            'www'  => $source['www'] . 'minified/'
        );

        $minified = array(
            'time' => ( file_exists( $output['path'] . 'all.min.'.$type ) ) ? filemtime( $output['path'].'all.min.'.$type ) : null ,
            'path' => $output['path'] . 'all.min.' . $type,
            'www'  => $output['www'] . 'all.min.' . $type
        );

        // setup $data to be passed to ->save();
        $data = array(
            'args'     => $args,
            'minified' => $minified,
            'output'   => $output,
            'source'   => $source,
            'dir'      => $dir,
            'type'     => $type
        );


        // saves if updated, doesn't if not
        $this->save($data);

        // append ?v=time_last_updated for cache management
        $minified['www'] .= '?v='.filemtime( $minified['path'] );
        
        if( $minify !== true ){
            $minified['www'] = str_replace('min.', '', $minified['www']); 
        }

        // define html to append
        $html = array(
            'css' => '<link href="'. $minified['www'] .'" rel="stylesheet">',
            'js'  => '<script src="'. $minified['www'] .'"></script>'
        );
        
        // render
        echo $html[$type];
    }

}


/**
 * Expose helpers
 */
function assets($dir = '', $args = 'all', $out = null, $minify = true)
{
    $helpers = new __Assets();
<<<<<<< HEAD
    $helpers->assets($dir, $args, $out, $minify);
}
