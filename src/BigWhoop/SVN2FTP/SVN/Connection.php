<?php
namespace BigWhoop\SVN2FTP\SVN;
use BigWhoop\SVN2FTP\Config;

class Connection
{
    /**
     * @var array
     */
    protected $_config = array(
        'bin'      => '.',
        'uri'      => null,
        'user'     => null,
        'password' => null,
    );
    
    
    /**
     * __construct
     * 
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->_config = Config::merge($this->_config, $config);
    }
    
    
    /**
     * Return a change set of paths according to the SVN log
     * 
     * @param string $revisions   List or range of revisions
     * @return array
     */
    public function createChangeSet($revisions)
    {
        $arguments = array(
            $this->getUri(),
            'revision' => $revisions,
            '--verbose',
        );
        
        $log = $this->execute('log', $arguments);
        $lines = explode("\n", $log);
        
        $changeSet = new ChangeSet($this);
        
        $revision = null;
        
        foreach ($lines as $line) {
            $matches = array();
            if (preg_match('/r([0-9]+) /iu', $line, $matches)) {
                $revision = $matches[1];
            } elseif (0 === strpos($line, '   A ')) {
                $type = Path::ADDED;
            } elseif (0 === strpos($line, '   M ')) {
                $type = Path::MODIFIED;
            } elseif (0 === strpos($line, '   D ')) {
                $type = Path::DELETED;  
            } else {
                $type = null;
            }
            
            if (null !== $type && null !== $revision) {
                $path = substr($line, 5);
                if (false !== ($pos = strpos($path, ' ('))) {
                    $path = substr($path, 0, $pos);
                }
                
                $path = new Path($this, $path, $revision, $type);
                $changeSet->addPath($path);
            }
        }
        
        return $changeSet;
    }
    
    
    /**
     * SVN EXPORT a remote file to a local path
     * 
     * @param BigWhoop\SVN2FTP\SVN\Path $remotePath
     * @param string $destinationPath
     */
    public function export(Path $remotePath, $destinationPath)
    {
        if (!is_writable($destinationPath)) {
            throw new \InvalidArgumentException('Destination directory "' . $destinationPath . '" is not writable.');
        }
        
        $destinationPath = $destinationPath . DIRECTORY_SEPARATOR . dirname($remotePath);
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, null, true);
        }
        
        $arguments = array(
            //$this->getUri() . '/!svn/bc/' . $remotePath->getRevision() . $remotePath->getPath(),
            $this->getUri() . $remotePath . '@' . $remotePath->getRevision(),
            $destinationPath . DIRECTORY_SEPARATOR . basename($remotePath),
            'depth' => 'files',
            '--quiet',
            'revision' => $remotePath->getRevision(),
        );
        
        $result = $this->execute('export', $arguments);
        
        echo $result;
    }
    
    
    /**
     * Execute the SVN command
     * 
     * @param string $subCmd     SVN subcommands like 'log' or 'export'
     * @param array $arguments   An array of additional SVN options
     * @return string
     */
    public function execute($subCmd, array $arguments = array())
    {
        // Add the username if available
        if (!isset($arguments['username']) && !empty($this->_config['user'])) {
            $arguments['username'] = $this->_config['user'];
        }
        
        // Add the password if available
        if (!isset($arguments['password']) && !empty($this->_config['password'])) {
            $arguments['password'] = $this->_config['password'];
        }
        
        $params = array();
        foreach ($arguments as $key => $value) {
            if (is_string($key)) {
                $params[] = sprintf('--%s %s', $key, escapeshellarg($value));
            } else {
                $params[] = $value;
            }
        }
        
        $cmd = sprintf('%s %s %s',
            $this->_getSvnExecutablePath(),
            $subCmd,
            join(' ', $params)
        );
        
        //echo PHP_EOL . 'Executing: ' . $cmd . PHP_EOL;
        
        return shell_exec($cmd);
    }
    
    
    /**
     * Prepare and return the repository's URI
     * 
     * @return string
     */
    public function getUri()
    {
        $parts = @parse_url($this->_config['uri']);
        if (!is_array($parts)) {
            throw new InvalidArgumentException($this->_config['uri']);
        }
        
        $uri = '';
        
        if (isset($parts['scheme'])) {
            $uri .= $parts['scheme'] . '://';
        }
        
        if (isset($parts['user'])) {
            $uri .= urlencode($parts['user']);
            
            if (isset($parts['pass'])) {
                $uri .= ':' . urlencode($parts['pass']);
            }
            
            $uri .= '@';
        }
        
        if (isset($parts['host'])) {
            $uri .= $parts['host'];
        }
        
        if (isset($parts['path'])) {
            $uri .= $parts['path'];
        }
        
        return $uri;
    }
    
    
    protected function _getSvnExecutablePath()
    {
        $path = rtrim($this->_config['bin'], '\\/');
        $path .= DIRECTORY_SEPARATOR . 'svn';
        
        return '"' . $path . '"';
    }
}