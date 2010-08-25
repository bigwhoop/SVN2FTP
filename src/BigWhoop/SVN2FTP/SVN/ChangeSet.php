<?php
namespace BigWhoop\SVN2FTP\SVN;
use BigWhoop\SVN2FTP\SVN\Connection as Connection;

class ChangeSet
{
    /**
     * @var BigWhoop\SVN2FTP\SVN\Connection
     */
    protected $_connection = null;
    
    /**
     * @var array
     */
    protected $_paths = array();
    
    
    /**
     * __construct
     * 
     * @param BigWhoop\SVN2FTP\SVN\Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->_connection = $connection;
    }
    
    
    public function addPath(Path $path)
    {
        $hash = $path->getPath();
        $changeType = $path->getChangeType();
        
        // If a path was previously added and now deleted ... just remove it and do nothing.
        // Isn't that great? I like to do nothing.
        if (Path::DELETED == $changeType && isset($this->_paths[$hash]) && Path::ADDED == $this->_paths[$hash]) {
            unset($this->_paths[$path]);
            return $this;
        }
        
        // If a path was previously added and now scheduled for deletion just ignore it.
        elseif (Path::MODIFIED == $changeType && isset($this->_paths[$hash]) && Path::ADDED == $this->_paths[$hash]) {
            return $this;
        }
        
        
        $this->_paths[$hash] = $path;
        
        return $this;
    }
    
    
    public function getAddedPaths()
    {
        return $this->_getPaths(Path::ADDED);
    }
    
    
    public function getModifiedPaths()
    {
        return $this->_getPaths(Path::MODIFIED);
    }
    
    
    public function getDeletedPaths()
    {
        return $this->_getPaths(Path::DELETED);
    }
    
    
    public function export($destinationPath)
    {
        $paths = array_merge($this->getAddedPaths(), $this->getModifiedPaths());
        
        foreach ($paths as $path) {
            $svnPath = $this->_connection->export($path, $destinationPath);
        }
    }
    
    
    protected function _getPaths($changeType)
    {
        $paths = array();
        foreach ($this->_paths as $path) {
            if ($changeType == $path->getChangeType()) {
                $paths[] = $path;
            }
        }
        
        usort($paths, function($a, $b) {
            return strcasecmp($a->getPath(), $b->getPath());
        });
        
        return $paths;
    }
}