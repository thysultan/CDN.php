<?php

class __Assets{
    private $_ds, 
            $_assets, 
            $_base, 
            $_root, 
            $_type,

            $refresh,
            $error, 
            $type,
            $env,
            $bin;

    public function __construct() 
    {
        
        // Don't compile on production
            $this->env = 'prod';

        if( $_SERVER["REMOTE_ADDR"] === "127.0.0.1" || $_SERVER['SERVER_NAME'] === 'localhost' )
        {
            $this->env = 'dev';
        }
        

        $this->_ds        = DIRECTORY_SEPARATOR;
        $this->bin        = $this->dir_sys_path(__DIR__.'/bin/');
        
        // get root dir
        $base             = $this->dir_sys_path($_SERVER['DOCUMENT_ROOT']);
        
        // find assets folder
        $folder           = explode( '/', $_SERVER["SCRIPT_NAME"] );
                            array_pop( $folder );

        $folder           = implode( '/', $folder );
        
        // config base(assets) & root(project) dir's
        $this->_base = $base . $folder;
        $this->_root = $base;
    }


    private function dir_sys_path($dir)
    {
        return str_replace( array('/', '\\'), $this->_ds, $dir );
    }

    private function dir_www_path($dir)
    {
        return str_replace( $this->_ds, '/', $dir );
    }


    /**
     * get OS of local machine
     */
    private function getOS() 
    {
        $os_platform    =   '';
        $os_array       =   array(
                                '/Windows/i' =>  'win.msi',
                                '/Darwin/i'  =>  'osx',
                                '/Linux/i'   =>  'linux',
                            );

        foreach ($os_array as $regex => $value) 
        { 
            if (preg_match($regex, php_uname('s'))) 
            {
                $os_platform    =   $value;
            }
        }
        return $os_platform;
    }


    /**
     * exec() with stdin
     */
    private function exec($cmd, $input)
    {
        $cmd = $this->bin . $cmd;

        // Add pipes to flow data
        $spec = array(
            0 => array('pipe','r'), // stdin
            1 => array('pipe','w'), // stdout
            2 => array('pipe','w')  // stderr
        );

        $output  = array();
        $process = proc_open($cmd, $spec, $pipes);

        if ( is_resource($process) ) 
        {
            // Send the [INPUT] on stdin
            fwrite( $pipes[0], $input );
            fclose( $pipes[0] );

            // Read the outputs
            $output['success'] = stream_get_contents( $pipes[1] ); // The return
            $output['error']   = stream_get_contents( $pipes[2] );   // Where any error will be

            // Close the process
            fclose($pipes[1]);
            proc_close($process); // 1 | 0


            if( $output['error'] )
            {
                echo "<pre style='color:red;font-weight:bold;font-size:16px;'>" 
                     . 
                     $output['error'] 
                     . 
                     "</pre>";

                exit;
            }

            // Return
            return $output['success'];
        }
    }


    /**
     * sass/css compiler/minify
     */
    private function _sass($input)
    {
        $import_path = $this->_assets . $this->_type . $this->_ds;
        $cmd         = 'sass-' . $this->getOS() . ' --stdin --style ' . $this->sass_output_style  . ' --load-path ' . $import_path;

        return $this->exec($cmd, $input); 
    }

    /**
     * js compiler compiler/minify
     */
    private function _js($input)
    {
        $cmd        = $this->bin . 'jsmin-' . str_replace('.msi', '.exe', $this->getOS()) . ' < ' . $input;
        $output     = array();
        $return_var = 255;

        exec($cmd, $output, $return_var);

        if ($return_var === 0) 
        {
            $output = implode(" ", $output);
            return $output;
        }
    }

    /**
     * check if source files have been updated: update minified.
     */
    private function save($data)
    {
        extract($data);

        // create buffer object
        $buffer             = array();
        
        // create buffer object
        $refresh            = array();
        $refresh['state']   = false;
        $refresh['value']   = false;

        $files              = array();
        
        // To minify or not?
        $minify             = ( $minify ) ? 'min.' : '';
        
        // Get current list of files in minified folder of same type
        $mask               = $output['path'].'*'.$this->type;
        $file               = array();
        $file['path']       = glob($mask);
        
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
            $this->output['name'] = 'all.' . time() . '.' . $this->type;
        }
        
        // loop: file paths in $include
        foreach( $include as $key => $value )
        {
            if( file_exists( $value ) )
            {
                $files[] = $value;

                // set state based on file difference
                if( 
                    (
                        $this->refresh === true
                        ||
                        $this->minified['time']($this->output['path'], $this->output['name']) > filemtime($value) === false
                    )
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
            $buffer['sass']     = '';
            $buffer['minified'] = '';

            /**
             * Deal with sass/css files:
             * Run sass/css files through sass compiler, altogether
             * To Preserve variable scope between files.
             */
            if( $this->type === 'css' )
            {
                foreach ($files as $key => $value) 
                {
                    if( strpos($value, '.scss') || strpos($value, 'css') )
                    {
                        $type = 'scss';

                        // Make sure file starts and ends with a newline
                        $buffer['sass'] .= '

                        '.file_get_contents( $value ).'

                         ';
                        unset( $files[$key] );
                    }
                };

                /*
                 * $buffer['sass'] will always be empty if there are no sass/css files
                 * Thus we only pass to the sass process if there are sass/css files
                 * aka if the $buffer['sass'] is populated
                 */
                if( $buffer['sass'] )
                {
                    $buffer['sass'] = $this->_sass( $buffer['sass'] );
                }
            }
            else
            {
                // Deal with every other type of file, plain css/js
                foreach ( $files as $key => $value )
                {
                    $ext       = explode( '.', $value );
                    
                    // Don't compress file if already minified
                    $this->min = $ext[ count($ext)-2 ];
                    
                    // Get contents
                    $contents  = file_get_contents( $value );

                    // If file minified don't run through compressor
                    $buffer['minified'] .= ( $this->min === 'min' ) ? $contents : $this->_js( $value );
                }
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
                    "Error: php could note create the 'minfied' folder in " . dirname(dirname( $minified['path'] )) . ",
                    
                    ". 
                    "Fix: change permissions of the Folder '". dirname(dirname( $minified['path'] )) . 
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

            // Can we write to the minified dir'?
            if ( is_writable( dirname($minified['path']) ) && !$this->error )
            {
                $oldumask = umask(0);
                
                // Remove old files of same type
                $mask = $output['path'].'*' . $this->type;
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
     * Normalize input/dir, if input does not end/start with / 
     * i.e /dirname -> /dirname/
     * i.e dirname/ -> /dirname/
     * i.e dirname  -> /dirname/
     */
    private function normalizeDir($dir){
        $dir = ( substr($dir, -1) !== '/' )     ? $dir.'/'      : $dir;
        $dir = ( $dir[0] !== '/' )              ? '/' . $dir    : $dir;

        return $dir;
    }

    /**
     * Render assets link
     */
    public function assets(
        $dir, 
        $include,
        $exclude,
        $out, 
        $minify,
        $refresh,
        $return
    )
    {
        $minify                  = ( $minify === null )             ? true         : $minify;
        $this->sass_output_style = ( is_bool($minify) || !$minify ) ? 'compressed' : $minify;
        $this->refresh           = $refresh;

        $dir = $this->normalizeDir($dir);

        // not an actual directory?
        if(
            $dir === ''      ||
            !is_string($dir) ||
            !is_dir( $this->_root . $this->dir_sys_path($dir) )
        )
        {
            echo '<!-- Error: Not an actual directory -->';
            return;
        }
        
        $dir = explode('/', $dir);
        
        // set file type
        $this->_type = $this->type = $dir[ count($dir)-2 ];

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
        $this->_assets  = $this->_root . $this->dir_sys_path($dir);
        $output         = ( $out !== null ) ? $this->_root . $this->normalizeDir($out) : $this->_assets;

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
                    strpos($file, 'minified') !== false ||
                    
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
        else // else only specified files
        {
            // remove whitespace
            $include = preg_replace('/\s+/', '', $include);
            
            // Deconstruct file list in $include to array
            $include = explode(',', $include);
            
            // Loop through file list.
            foreach ($include as $file)
            {
                // Create list of files
                $files[] = $this->_assets . $this->_type . $this->_ds . $file;
            }

            // Reconstruct args parts back together
            $include = implode(',', $files);
        }


        // replace '/' with native directory '/'
        $include = $this->dir_sys_path($include);
        
        // Convert back to array.
        $include = explode(',', $include);
        
        // Exclude specified files/folders if set
        if( $exclude )
        {
            // remove whitespace
            $exclude = preg_replace('/\s+/', '', $exclude);

            // convert to array
            $exclude = explode(',', $exclude);
            
            // Removes file from file list if is found in the exclude list
            foreach($include as $includeKey => $includeValue) 
            {
                foreach($exclude as $excludeKey => $excludeValue)
                {
                    if( strpos($includeValue, $excludeValue) !== false )
                    {
                        unset( $include[$includeKey] );
                    }
                }
            }
        }

        // define source: where the source files are
        $this->source = array(
            'path'  => $this->dir_sys_path($output),
            'www'   => $this->dir_www_path(str_replace($this->_root ,'', $output))
        );
        
        // define output: where the ouput will be, after compiling
        $this->output = array(
            'path' => $this->source['path'] . 'minified' . $this->_ds,
            'www'  => $this->source['www'] . 'minified/',
            'name' => 'all.min' . '.' . $this->type
        );

        if( $out !== null )
        {
            $this->output['path'] = $this->source['path'];
            $this->output['www']  = $this->source['www'];
        }

        
        // Where the minified files will be, system & www paths include file modified time.
        $this->minified = array(
            // Get file modified time
            'time' => function( $path, $name )
            {
                if( file_exists( $path . $name ) )
                {
                    return filemtime( $path . $name );
                }
                else
                {
                    return null;
                }
            },
            
            // Get file directory path    
            'path' => function( $path, $name )
            {
                return $path . $name;
            },
            
            // Get file www path
            'www'  => function( $www, $name )
            {
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
            'dir'      => $dir
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
        
        
        /** 
         *  If we have an error display that. 
         *  hint: erros are in html comment style
         *  <!-- error here -->
         */
        if( $this->error )
        {
            // render error.
            if( $return !== true )
            {
                echo $this->error;
            }
            
            // return error.
            return $this->error;
        }
        
        else
        {
            // render html template.
            if( $return !== true )
            {
                echo $html[$this->type];
            }
            
            // return minified url.
            return $minified['www']; 
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



/**
 * Expose assets() func
 */
function assets(
    $dir     = '', 
    $include = 'all', 
    $exclude = null, 
    $out     = null, 
    $minify  = true,
    $refresh = false,
    $return  = null
)
{    
    $helpers = new __Assets();

    if( is_array($dir) )
    {
        $args    = $dir;

        $dir     = ( isset($args['directory']) ) ? $args['directory'] : '';
        $include = ( isset($args['include']) )   ? $args['include']   : 'all';
        $exclude = ( isset($args['exclude']) )   ? $args['exclude']   : null;
        $out     = ( isset($args['output']) )    ? $args['output']    : null;
        $minify  = ( isset($args['minify']) )    ? $args['minify']    : true;
        $refresh = ( isset($args['refresh']) )   ? $args['refresh']   : false;
        $return  = ( isset($args['return']) )    ? $args['return']    : null;
    }

    return $helpers->assets($dir, $include, $exclude, $out, $minify, $refresh, $return);
}