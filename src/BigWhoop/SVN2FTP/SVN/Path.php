<?php
namespace BigWhoop\SVN2FTP\SVN;
use BigWhoop\SVN2FTP\SVN\Connection as Connection;

class Path
{
    const ADDED = 'added';
    const MODIFIED = 'modified';
    const DELETED = 'deleted';
    
    /**
     * @var BigWhoop\SVN2FTP\SVN\Connection
     */
    protected $_connection = null;
    
    /**
     * @var string
     */
    protected $_path = null;
    
    /**
     * @var int
     */
    protected $_revision = null;
    
    /**
     * @var string
     */
    protected $_changeType = null;
    
    
    /**
     * __construct
     * 
     * @param BigWhoop\SVN2FTP\SVN\Connection $connection
     * @param string $path
     * @param int $revision
     * @param string $changeType
     */
    public function __construct(Connection $connection, $path, $revision, $changeType)
    {
        if (!in_array($changeType, array(self::ADDED, self::MODIFIED, self::DELETED))) {
            throw new \InvalidArgumentException($changeType);
        }
        
        $this->_connection = $connection;
        $this->_path       = $path;
        $this->_revision   = (int)$revision;
        $this->_changeType = $changeType;
    }
    
    
    /**
     * Return the relative path
     * 
     * @return string
     */
    public function getPath()
    {
        return $this->_path;
    }
    
    
    /**
     * Return the revision
     * 
     * @return int
     */
    public function getRevision()
    {
        return $this->_revision;
    }
    
    
    /**
     * Return the change type
     * 
     * @return string
     */
    public function getChangeType()
    {
        return $this->_changeType;
    }
    
    
    /**
     * __toString
     * 
     * @return string
     */
    public function __toString()
    {
        return $this->getPath();
    }
}