<?php

use Leafo\ScssPhp\Compiler;

class __Assets{
    private $_ds, 
            $_assets, 
            $_base, 
            $_root, 
            $error, 
            $type,
            $env;

    public function __construct() 
    {
        
        // Don't compile on production
        $this->env = 'prod';

        if( $_SERVER["REMOTE_ADDR"] === "127.0.0.1" || $_SERVER['SERVER_NAME'] === 'localhost' )
        {
            $this->env = 'dev';
        }

        
        $this->_ds      = DIRECTORY_SEPARATOR;
        
        // get root dir
        $base           = str_replace( array('/', '\\'), $this->_ds, $_SERVER['DOCUMENT_ROOT'] );
        
        // find assets folder
        $folder         = explode( '/', $_SERVER["SCRIPT_NAME"] );
                          array_pop( $folder );

        $folder         = implode( '/', $folder );
        
        // config base(assets) & root(project) dir's
        $this->_base = $base . $folder;
        $this->_root = $base;
        
    }

    /**
     * sass compiler
     */
    private function _sass(
        $css, 
        $extended = null
    )
    {
        require_once '__sass.php';

        $scss = new Compiler();
        
        // uncompressed formatting.
        if( $extended )
        {
            $scss->setFormatter('Leafo\ScssPhp\Formatter\Expanded');
        }

        return $scss->compile($css);
        
    }

    /**
     * compress Buffer
     */
    private function compress($buffer)
    {   
        // Remove comments
        // $buffer = preg_replace('/(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:(?<!\:|\\\|\'|\")\/\/.*))/', '', $buffer);

        /**
         * Trick php to think this is php code,
         * It doesnt seem to work if it doesn't
         * worth the performace gain compared to preg_replace()
         */
        $buffer = "<?php " . $buffer . " ?>";
        $fileStr       = $buffer;
        $newStr        = '';
        $commentTokens = array(T_COMMENT,T_DOC_COMMENT);
        $tokens        = token_get_all($fileStr);

        foreach ($tokens as $token) 
        {    
            if (is_array($token)) 
            {
                if (in_array($token[0], $commentTokens))
                {
                    continue;
                }

                $token = $token[1];
            }

            $newStr .= $token;
        }

        $buffer = $newStr;
        $buffer = substr($buffer, 0, -3);
        $buffer = substr($buffer, 6);


        // Remove new lines, whitespace, etc 
        $buffer = preg_replace( '/\s+/S', ' ', $buffer );
        
        // Remove extra spacing between the following chars
        $opts = array(
            ',', ';', ':',
            
            '{', '}',

            '(', ')',
            
            '=', '==', '===',
            
            '!', '!=', '!==',
            
            '||', '|',
            
            '&&', '&',
            
            '^',

            '*=', '+=', '-=', '/=', '&=', '^=', '|=',
            
            '<<', '>>', '>>>',
            
            '<<=', '>>=', '>>>=',
            
            '<', '>', '<=', '>=',
            
            '~', '?', '+', '-', '/', '*', '%', '**',
        );

        if( $this->type === 'js' )
        {
            $opts[] = '[';
            $opts[] = ']';
        }
        
        foreach($opts as $opt)
        {
            $buffer = str_replace(array($opt." ", " ".$opt), $opt, $buffer );
        }

        return $buffer;

    }

    /**
     * check if source files have been updated: update minified.
     */
    private function save($data)
    {
        extract($data);

        // create buffer object
        $buffer             = array();
        $buffer['sass']     = '';
        $buffer['minified'] = '';
        
        // create buffer object
        $refresh            = array();
        $refresh['state']   = false;
        $refresh['value']   = false;

        $files               = array();
        
        // To minify or not?
        $minify              = ( $minify ) ? 'min.' : '';
        
        // Get current list of files in minified folder of same type
        $mask                = $output['path'].'*'.$type;
        $file                = array();
        $file['path']        = glob($mask);
        
        // If not empty(the ^^ folder)
        if( array_key_exists(0, $file['path']) === true )
        {
            $file['path']         = $file['path'][0];
            
            // Get only the name of the file, remove the reset
            $file['name']         = str_replace($this->output['path'], '', $file['path']);
            
            $this->output['name'] = $file['name'];
        }
        else
        {
            // We are creating this file for the first time.
            $this->output['name'] = 'all.' . time() . '.' . $type;
        }
        
        
        // loop: file paths in $include
        foreach( $include as $key => $value )
        {
            if( file_exists( $value ) )
            {
                $files[] = $value;

                // set state based on file difference
                if( 
                    $this->minified['time']($this->output['path'], $this->output['name']) > filemtime($value) === false
                    &&
                    $this->env !== 'prod'
                )
                {
                    $refresh['state'] = true;
                    $refresh['value'] = true;
                }
            }
            
        }
        
         // refresh? create/update file once every update
        if( $refresh['value'] === true )
        {   

            /**
             * Deal with sass files:
             * Run sass files through sass compiler, altogether
             * To Preserve variable scope between files.
             */
            foreach ($files as $key => $value) {
                if( strpos($value, '.scss') )
                {
                    // Make sure file starts and ends with a newline
                    $buffer['sass'] .= '

                    '.file_get_contents( $value ).'

                     ';
                    unset( $files[$key] );
                }
            };

            $buffer['sass'] = $this->_sass( $buffer['sass'] );


            // Deal with every other type of file
            foreach ( $files as $key => $value )
            {
                $ext       = explode( '.', $value );
                
                // Don't compress file if already minified
                $this->min = $ext[ count($ext)-2 ];
                
                // Get contents
                $contents  = file_get_contents( $value );

                // If file minified don't run through compressor
                $buffer['minified'] .= ( $this->min === 'min' ) ? $contents : $this->compress( $contents );
            }

            // Add compilied sass to contents
            $buffer['minified'] .= $buffer['sass'];


            // make minified output dir' if it doesn't already exist
            if( !is_dir( $output['path'] ) )
            {
                // Give dir 0777 permissions
                $oldumask = umask(0); 
                
                if( !@mkdir( $output['path'], 0777, true ) )
                {
                    $this->error .= "
                    <!-- 
                    
                    ". 
                    "Error: php could note create the 'minfied' folder in ".dirname(dirname( $minified['path'] ))."/,
                    
                    ". 
                    "Fix: change permissions of the Folder '". dirname(dirname( $minified['path'] )).
                    "'; 
                    
                    -->
                    ";  
                
                    return;
                }
                
                umask($oldumask);
            }
            
            // Construct name of minified file
            $this->output['name'] = explode('.', $this->output['name']);
            $this->output['name'] = $this->output['name'][0] . '.' . 
                                           $minify .  time() . '.' . 
                                           $this->output['name'][ count($this->output['name'])-1 ];
            
            // Update minified paths
            $minified['path'] = $this->minified['path']( $this->output['path'], $this->output['name'] );
            $minified['www']  = $this->minified['path']( $this->output['www'], $this->output['name'] );

            // Can we right to the minified dir'?
            if ( is_writable( dirname($minified['path']) ) )
            {
                $oldumask = umask(0);
                
                // Remove old files of same type
                $mask = $output['path'].'*' . $type;
                array_map('unlink', glob($mask));
                
                // Save new/updated minified file 'all.min.ext'
                file_put_contents( $minified['path'], $buffer['minified'] );
                
                //  Give 0777 permissions
                @chmod($minified['path'], 0777);
                
                umask($oldumask);
            }
            else
            {
                $this->error .= "
                    <!-- 
                    
                    ". 
                    "Error: php could note save file; type: not writable/permissions,
                    
                    ". 
                    "Fix: change permissions of: '".$minified['path']."' 
                    
                    ".
                    "or the Folder '".dirname($minified['path']).
                    "'; 
                    
                    -->
                    ";  
            }
            
        }

    }

    /**
     * Render assets link
     */
    public function assets(
        $dir, 
        $include,
        $exclude,
        $out, 
        $minify
    )
    {
        // default to 'all' files option
        if($include === null)
        {
            $include = 'all';
        }
        
        /**
         * Normalize input, if input does not end/start with / 
         * i.e /dirname -> /dirname/
         * i.e dirname/ -> /dirname/
         * i.e dirname  -> /dirname/
         */
        $dir = ( substr($dir, -1) !== '/' ) ? $dir.'/' : $dir;
        $dir = ( $dir[0] !== '/' ) ? '/'.$dir : $dir;
        
        // not an actual directory?
        if(
            $dir === ''      ||
            !is_string($dir) ||
            !is_dir( $this->_root . str_replace(array('/', '\\'), $this->_ds, $dir) )
            )
        {
            echo '<!-- Error: Not an actual directory -->';
            return;
        }
        
        $dir = explode('/', $dir);
        
        // set file type
        $this->type = $dir[ count($dir)-2 ];

        if(
            strpos($this->type, 'css')   !== false ||
            strpos($this->type, 'style') !== false
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
        $this->_assets  = $this->_root . str_replace( array( '/', '\\' ), $this->_ds, $dir );
        $out            = ( $out !== null ) ? $this->_root.$out : $this->_assets;

        // include all files if 'all' or 'null': not set
        if( $include === 'all' || $include === null )
        {
            // Get all files in directory/sub directories recursive operation
            $rii   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->_assets ) );
            $files = array();
            
            // Loop through listed files
            foreach ($rii as $file)
            {
                // Get file extension.
                $ext = explode( '.', $file->getPathname() );
                $ext = end($ext);

                if(
                    // File should not be directory
                    $file->isDir() ||
                    
                    // File should not start with '.'
                    substr($file->getFileName(), 0, 1) == '.' ||
                    
                    // File should not be in minified folder
                    strpos($file,'minified') !== false ||
                    
                    // If it's a file that is not of the same type, continue
                    strpos($ext, $this->type) === false
                )
                {
                    continue;
                }
                
                // Build safe list of needed files
                $files[] = $file->getPathname();
            }
            
            // Run custom sort function on file list
            uasort($files, array($this, 'cmp'));
            
            $include = implode(',', $files);
        }
        // else only specified files
        else
        {
            // remove whitespace
            $include = preg_replace('/\s+/', '', $include);
            
            // Deconstruct file list in $include to array
            $include = explode(',', $include);
            
            // Loop through file list.
            foreach ($include as $file)
            {
                // Create list of files
                $files[] = $this->_assets . $this->type . $this->_ds . $file;
            }
            
            // Reconstruct args parts back together
            $include = implode(',', $files);
        }


        // replace '/' with native directory '/' + make array
        $include = str_replace(array('/', '\\'), $this->_ds, $include);
        
        // Convert args back to array.
        $include = explode(',', $include);
        
        // Exclude specified files/folders if set
        if($exclude)
        {
            $exclude = preg_replace('/\s+/', '', $exclude);
            $exclude = explode(',', $exclude);
            
            foreach($include as $includeKey => $includeValue) 
            {
                foreach($exclude as $excludeKey => $excludeValue)
                {
                    if(strpos($includeValue, $excludeValue) !== false)
                    {
                        unset($include[$includeKey]);
                    }
                }
            }
        }

        // define source: where the source files are
        $this->source = array(
            'path'  => str_replace(array('/', '\\'), $this->_ds, $out),
            'www'   => str_replace($this->_ds, '/', str_replace($this->_root ,'', $out) )
        );
        
        // define output: where the ouput will be, after compiling
        $this->output = array(
            'path' => $this->source['path'] . 'minified' . $this->_ds,
            'www'  => $this->source['www'] . 'minified/',
            'name' => 'all.min' . '.' . $this->type
        );
        
        // Where the minified files will be, system & www paths
        $this->minified = array(
            'time' => function($path, $name){
                if( file_exists( $path . $name ) ){
                    return filemtime( $path . $name );
                }else{
                    return null;
                }
            },
                        
            'path' => function($path, $name){
                return $path . $name;
            },
            
            'www'  => function($www, $name){
                return $www . $name;
            }
        );

        // Set current $minified paths
        $minified = $this->reset();
        
        // setup $data to be passed to ->save()
        $data = array(
            'include'  => $include,
            
            'minified' => $minified,
            'output'   => $this->output,
            'source'   => $this->source,
            'minify'   => $minify,
            'dir'      => $dir,
            'type'     => $this->type
        );


        // saves if updated, doesn't if not
        $this->save($data);
        
        /** 
         *  Update current $minified paths, 
         *  hint: they where changed in $this->save()
         */
        $minified = $this->reset();

        // define html template to append.
        $html = array(
            'css' => '<link rel="stylesheet" href="' . $minified['www'] . '">',
            'js'  => '<script src="' .                 $minified['www'] . '"></script>'
        );
        
        // render html template.
        echo $html[$this->type];
        
        /** 
         *  If we have an error display that. 
         *  hint: erros are in html comment style
         *  <!-- error here -->
         */
        if( $this->error )
        {
            echo $this->error;
        }
    }
    
    /**
     * returns the minified paths
     */
    public function reset()
    {
        $minified['path'] = $this->minified['path']( $this->output['path'], $this->output['name'] );
        $minified['www']  = $this->minified['www']( $this->output['www'], $this->output['name'] );
        
        return $minified;
    }
    
    /**
     * Comparison function: treat uppercase/lowercase the same
     */
    public function cmp($a, $b) 
    {
        $a = strtolower($a);
        $b = strtolower($b);

        if ($a == $b) return 0;
        
        return ($a < $b) ? -1 : 1;
    }

}