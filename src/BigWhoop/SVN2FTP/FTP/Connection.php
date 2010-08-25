<?php
namespace BigWhoop\SVN2FTP\FTP;
use BigWhoop\SVN2FTP\Config;

class Connection
{
    /**
     * @var array
     */
    protected $_config = array(
        'uri'      => null,
        'user'     => null,
        'password' => null,
        'timeout'  => 10,
    );
    
    /**
     * @var resource
     */
    protected $_handle = null;
    
    
    /**
     * __construct
     * 
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->_config = Config::merge($this->_config, $config);
        $this->_connect();
    }
    
    
    /**
     * Connect to the server
     * 
     * @return BigWhoop\SVN2FTP\FTP\Connection
     */
    protected function _connect()
    {
        $host = $this->getHost();
        $port = $this->getPort();
        $this->_handle = @ftp_connect($host, $port, $this->_config['timeout']);
        
        if (!$this->_handle) {
            throw new ConnectionFailedException("Could not connect to $host:$port.");
        }
        
        if (!@ftp_login($this->_handle, $this->_config['user'], $this->_config['password'])) {
            throw new LoginFailedException("Could not login on $host:$port using user {$this->_config['user']}.");
        }
        
        return $this;
    }
    
    
    /**
     * Change directory on the server
     * 
     * @param string $path
     * @return BigWhoop\SVN2FTP\FTP\Connection
     */
    public function changeDirectory($path)
    {
        if (!@ftp_chdir($this->_handle, $path)) {
            throw new Exception('Failed changing directory to "' . $path . '".');
        }
        
        return $this;
    }
    
    
    /**
     * Check whether a specific directory exists
     * 
     * @param string $path
     * @return bool
     */
    public function hasDirectory($path)
    {
        $currentDirectory = ftp_pwd($this->_handle);
        
        try {
            $this->changeDirectory($path);
        } catch (Exception $e) {
            return false;
        }
        
        $this->changeDirectory($currentDirectory);
        
        return true;
    }
    
    
    /**
     * Create a directory on the server. Works recursively, too.
     * 
     * @param string $path
     * @return BigWhoop\SVN2FTP\FTP\Connection
     */
    public function createDirectory($path, $absolute = true)
    {
        $directories = explode('/', trim($path, '/'));
        
        $path = $absolute ? '' : '.';
        foreach ($directories as $directory) {
            $path .= '/' . $directory;
            
            if (!$this->hasDirectory($path)) {
                if (!@ftp_mkdir($this->_handle, $path)) {
                    throw new Exception('Failed creating directory at "' . $path . '".');
                }
            }
            
            $this->changeDirectory($path);
        }
        
        return $this;
    }
    
    
    /**
     * Upload a locale file to a remote path. The remote path's directory
     * must exists, otherwise an exception is thrown. All files are uploaded
     * in BINARY MODE.
     * 
     * @param string $localPath
     * @param string $remotePath
     * @return BigWhoop\SVN2FTP\FTP\Connection
     */
    public function uploadFile($localPath, $remotePath)
    {
        if (!file_exists($localPath)) {
            throw new Exception('Local file "' . $localPath . '" does not exist.');
        }
        
        $remoteDirectory = dirname($remotePath);
        $remoteFileName  = basename($remotePath);
        
        $this->changeDirectory($remoteDirectory);
        
        if (!@ftp_put($this->_handle, $remoteFileName, $localPath, FTP_BINARY)) {
            throw new Exception('Failed upload file "' . $localPath . '".');
        }
        
        return $this;
    }
    
    
    /**
     * Delete a file on the remote host.
     * 
     * @param string $path
     * @return BigWhoop\SVN2FTP\FTP\Connection
     */
    public function deleteFile($path)
    {
        $directory = dirname($path);
        $fileName  = basename($path);
        
        $this->changeDirectory($directory);
        
        if (!@ftp_delete($this->_handle, $fileName)) {
            throw new Exception('Failed deleting file "' . $path . '".');
        }
        
        return $this;
    }
    
    
    /**
     * Delete a directory on the remote host.
     * 
     * @param string $path
     * @return BigWhoop\SVN2FTP\FTP\Connection
     */
    public function deleteDirectory($path)
    {
        if (!$this->hasDirectory($path)) {
            throw new Exception('Can\'t delete non-existing directory at "' . $path . '".');
        }
        
        if (!@ftp_rmdir($this->_handle, $path)) {
            throw new Exception('Failed deleting directory "' . $path . '".');
        }
        
        return $this;
    }
    
    
    /**
     * Return the host name
     * 
     * @return string
     */
    public function getHost()
    {
        return parse_url($this->_config['uri'], PHP_URL_HOST);
    }
    
    
    /**
     * Return the server port
     * 
     * @return int
     */
    public function getPort()
    {
        $port = (int)parse_url($this->_config['uri'], PHP_URL_PORT);
        return empty($port) ? 21 : $port;
    }
    
    
    /**
     * Return the default remote path
     * 
     * @return string
     */
    public function getDefaultPath()
    {
        $path = parse_url($this->_config['uri'], PHP_URL_PATH);
        return empty($path) ? '/' : $path;
    }
}