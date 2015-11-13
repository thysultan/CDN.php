<?php

use Leafo\ScssPhp\Compiler;

class __Assets{

    private $_DS_ = DIRECTORY_SEPARATOR;

    private $_ASSETS_;
    private $_WWW_ASSETS_;
    private $_BASE;

    public function __construct() {
        $this->_BASE_       = str_replace(array('/', '\\'), $this->_DS_, $_SERVER['DOCUMENT_ROOT']);
    }


    /**
     * sass compiler
     */
    private function _sass($css)
    {
        if ( class_exists( 'scssc', false ) ) return false;

        include_once '__sass.php';

        $scss = new Compiler();

        return $scss->compile($css);
    }


    /**
     * compress Buffer
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
     * loop through files, check minified version age > file refresh minified if true
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
     * Render assets link
     */
    public function assets($dir = '', $args = 'all', $out = null)
    {

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


        // replace '/' with native directory '/' + make array.
        $args = str_replace(array('/', '\\'), $this->_DS_, $args);
        $args = explode(',', $args );

        // define source, output and, minified links
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

        // define html to append
        $html = array(
            'css' => '<link  href="'. $minified['www'] .'" rel="stylesheet">',
            'js'  => '<script src="'. $minified['www'] .'"></script>'
        );

        // render
        echo $html[$type];
    }

}


/**
 * Expose helpers
 */
function assets($dir = '', $args = 'all', $out = null)
{
    $helpers = new __Assets();
    $helpers->assets($dir, $args, $out);
}