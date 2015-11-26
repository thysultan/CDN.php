<?php

use Leafo\ScssPhp\Compiler;

class __Assets{
    private $_ds, $_assets, $_base, $_root, $error, $type;

    public function __construct() {
        $base        = str_replace(array('/', '\\'), $this->_ds, $_SERVER['DOCUMENT_ROOT']);
        $folder      = explode('/', $_SERVER["PHP_SELF"]);
                       array_pop($folder);
        $folder      = implode('/', $folder);
        
        $this->_ds   = DIRECTORY_SEPARATOR;
        $this->_base = $base . $folder;
        $this->_root = $base;
    }

    /**
     * sass compiler
     */
    private function _sass($css, $extended = null)
    {
        if ( class_exists( 'scssc', false ) ) return false;

        include_once '__sass.php';

        $scss = new Compiler();
        
        if( $extended )
        {
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
        if( $this->type === 'css' )
        {
            $buffer = preg_replace( '/\s+/S', '', $buffer );
        }
        else
        {
            $buffer = preg_replace( '/\s+/S', ' ', $buffer );
        }

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
        if( $refresh['value'] = true )
        {
            foreach ( $files as $key => $value )
            {
                $ext  = explode( '.', $value );
                $ext  = end($ext);

                // once every update
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
            
            if( is_writable( $minified['path']) )
            {
                // Save minified file 'all.min.ext'
                file_put_contents( $minified['path'], $buffer['minified'] );
                
                // Save unminified file 'all.ext'
                file_put_contents( str_replace('min.', '', $minified['path']), $buffer['source'] );
            }
            else
            {   
                $this->error = "<!-- Error: could note save ^^ type: permissions error; -->";
            }

        }

    }

    /**
     * Render assets link
     */
    public function assets($dir, $args, $out, $minify)
    {
        if($args === null)
        {
            $args = 'all';
        }
        
        // not actual directory?
        if(
            $dir === ''      ||
            !is_string($dir) ||
            !is_dir( $this->_base . str_replace(array('/', '\\'), $this->_ds, $dir) )
            )
        {
            echo '<!-- Error: Not an actual directory -->';
            return;
        }

        $dir = ( substr($dir, -1) !== '/' ) ? $dir.'/' : $dir;

        // get file type
        $dir  = explode('/', $dir);
        $this->type = $dir[ count($dir)-2 ];

        if(
            strpos($this->type, 'css')   !== false ||
            strpos($this->type, 'style') !== false ||
            strpos($this->type, 'sass')  !== false ||
            strpos($this->type, 'scss')  !== false
            )
        {
            $this->type = 'css';
        }

        else
        {
            $this->type = 'js';
        }

        unset( $dir[ count($dir)-2 ] );

        $dir            = implode('/', $dir);
        $this->_assets  = $this->_base . str_replace(array('/', '\\'), $this->_ds, $dir);
        $out            = ( $out !== null ) ? $this->_base.$out : $this->_assets;

        // include all files if 'all' else specific.
        if( $args === 'all' )
        {
            $rii   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->_assets ) );
            $files = array();

            foreach ($rii as $file)
            {
                $ext = explode( '.', $file->getPathname() );
                $ext = end($ext);

                if(
                    $file->isDir() ||
                    substr($file->getFileName(), 0, 1) == '.' ||
                    strpos($file,'minified') !== false
                  )
                {
                    continue;
                }

                // if it's a file that is not of the same type, continue
                if( strpos($ext, $this->type) === false )
                {
                    continue;
                }

                $files[] = $file->getPathname();
            }

            if( $this->type === 'js' )
            {
                // Comparison function
                function cmp($a, $b) 
                {
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
                $files[] = $this->_assets . $this->type . $this->_ds . $file;
            }

            $args = implode(',', $files);
        }


        // replace '/' with native directory '/' + make array.
        $args = str_replace(array('/', '\\'), $this->_ds, $args);
        $args = explode(',', $args );

        // define source, output and, minified links
        $source = array(
            'path'  => str_replace(array('/', '\\'), $this->_ds, $out),
            'www'   => str_replace($this->_ds, '/', str_replace($this->_root ,'', $out) )
        );

        $output = array(
            'path' => $source['path'] . 'minified' . $this->_ds,
            'www'  => $source['www'] . 'minified/'
        );

        $minified = array(
            'time' => ( file_exists( $output['path'] . 'all.min.'.$this->type ) ) ? filemtime( $output['path'].'all.min.'.$this->type ) : null ,
            'path' => $output['path'] . 'all.min.' . $this->type,
            'www'  => $output['www'] . 'all.min.' . $this->type
        );

        // setup $data to be passed to ->save();
        $data = array(
            'args'     => $args,
            'minified' => $minified,
            'output'   => $output,
            'source'   => $source,
            'dir'      => $dir,
            'type'     => $this->type
        );


        // saves if updated, doesn't if not
        $this->save($data);

        // append ?v=time_last_updated for cache management
        $minified['www'] .= '?v='.filemtime( $minified['path'] );
        
        if( $minify !== true )
        {
            $minified['www'] = str_replace('min.', '', $minified['www']); 
        }

        // define html to append
        $html = array(
            'css' => '<link href="'. $minified['www'] .'" rel="stylesheet">',
            'js'  => '<script src="'. $minified['www'] .'"></script>'
        );
        
        // render
        echo $html[$this->type] . '
';

        if( $this->error ){
            $this->error = $this->error."
";
        
            echo $this->error;
        }
    }

}


/**
 * Expose assets() func
 */
function assets($dir = '', $args = 'all', $out = null, $minify = true)
{
    $helpers = new __Assets();
    $helpers->assets($dir, $args, $out, $minify);
}
