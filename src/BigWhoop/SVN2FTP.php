<?php
namespace BigWhoop;
use BigWhoop\SVN2FTP;
use BigWhoop\SVN2FTP\SVN;
use BigWhoop\SVN2FTP\FTP;

class SVN2FTP
{
    const VERSION = '1.0.0-alpha';
    
    /**
     * @var array
     */
    protected $_config = array(
        'project' => array(
            'name' => 'Untitled Project',
            'force' => false,
        ),
        'ftp' => array(),
        'svn' => array(),
    );
    
    /**
     * @var string
     */
    protected $_revision = null;
    
    
    /**
     * Register our own class loader
     */
    static public function registerAutoloader()
    {
        spl_autoload_register(array(get_called_class(), 'loadClass'));
    }
    
    
    /**
     * Try to load a class according to PSR-0
     * http://groups.google.com/group/php-standards/web/psr-0-final-proposal
     * 
     * @param string $className
     */
    static public function loadClass($className)
    {
        $className = ltrim($className, '\\');
        $fileName = $namespace = '';
        
        if (false !== ($lastNsPos = strripos($className, '\\'))) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }
        
        $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
        
        require $fileName;
    }
    
    
    static protected function _getCliOptionsDefinition()
    {
        static $opts = null;
        
        if (null === $opts) {
            $opts = new \Zend_Console_Getopt(array(
                'config|c=s'   => 'Path to the config file',
                'revision|r=s' => 'List or range of the revisions to update. '
                               .  'For example 193-204 or 192,194,195.',
            ));
        }
        
        return $opts;
    }
    
    
    static public function getUsageMessage()
    {
        return static::_getCliOptionsDefinition()->getUsageMessage();
    }
    
    
    static public function printHeader()
    {
        static::printLine('---');
        static::printLine('SVN2FTP ' . self::VERSION);
        static::printLine('---');
        static::printLine('Copyright ' . date('Y') . ' Philippe Gerber <philippe@bigwhoop.ch>');
        static::printLine('Licensed under Creative Commons Attribution-ShareAlike 3.0 Unported');
        static::printLine('http://creativecommons.org/licenses/by-sa/3.0');
        static::printLine('---');
    }
    
    
    /**
     * Echo text followed by a new line character
     * 
     * @param string $text
     */
    static public function printLine($text = null)
    {
        $args = func_get_args();
        call_user_func_array(array(get_called_class(), '_print'), $args);
        
        echo PHP_EOL;
    }
    
    
    /**
     * Output some text and then read from the console till a LINE BREAK
     * character is entered.
     * 
     * @param string $text
     * @return string
     */
    static public function readLine($text)
    {
        $args = func_get_args();
        call_user_func_array(array(get_called_class(), '_print'), $args);
        
        return trim(fgets(STDIN));
    }
    
    
    /**
     * Print some text using printf
     * 
     * @param string $text
     */
    static protected function _print($text = null)
    {
        if (empty($text)) {
            return;
        }
        
        $args = func_get_args();
        $text = array_shift($args) . ' ';
        vprintf($text, $args);
    }
    
    
    static public function runCli()
    {
        static::registerAutoloader();
        
        static::printHeader();
        
        try {
            $definition = static::_getCliOptionsDefinition();
            $definition->parse();
        } catch (\Zend_Console_Getopt_Exception $e) {
            echo 'Some of the arguments are missing or wrong.' . PHP_EOL . PHP_EOL;
            echo $e->getUsageMessage();
            exit(0);
        } 
        
        $configPath = $definition->getOption('c');
        $revisions  = $definition->getOption('r');
        
        try {
            $svn2ftp = new static($configPath, $revisions);
            $svn2ftp->run();
        } catch (SVN2FTP\Exception $e) {
            static::printLine();
            static::printLine('---');
            static::printLine('ERROR: ' . $e->getMessage());
            static::printLine('---');
            echo static::getUsageMessage();
            static::printLine('---');
            exit(0);
        }
    }
    
    
    /**
     * __constructor
     * 
     * @param array|string $config    Either an array or a string containing the path to a config .INI file
     * @param string|null $revision   List or range of revisions. Default is HEAD.
     */
    public function __construct($config, $revision)
    {
        if (is_string($config) && !empty($config)) {
            if (!is_readable($config)) {
                throw new SVN2FTP\Exception('Config file "' . $config . '" is not readable.');
            }
            
            $config = @parse_ini_file($config, true);
            if (!is_array($config)) {
                throw new SVN2FTP\Exception('Config file "' . $config . '" seems to be a corrupted .INI file.');
            }
        } elseif (!is_array($config)) {
            throw new SVN2FTP\Exception('First argument must either be an array or a string specifying the path to a .INI config file.');
        }
        
        $this->_config = SVN2FTP\Config::merge($this->_config, $config);
        
        if (empty($revision)) {
            $revision = 'HEAD';
        }
        
        $this->_revision = (string)$revision;
    }
    
    
    public function run()
    {
        $svn = $this->initSVNConnection();
        
        static::printLine('Loaded project "%s".', $this->_config['project']['name']);
        static::printLine('Building change set. This can take a few seconds.');
        
        $changeSet = $svn->createChangeSet($this->_revision);
        
        // Show added paths
        $addedPaths = $changeSet->getAddedPaths();
        static::printLine();
        static::printLine('Added path(s): %d', count($addedPaths));
        foreach ($addedPaths as $path) {
            static::printLine("   $path  (r{$path->getRevision()})");
        }
        
        // Show modified paths
        $modifiedPaths = $changeSet->getModifiedPaths();
        static::printLine();
        static::printLine('Modified path(s): %d', count($modifiedPaths));
        foreach ($modifiedPaths as $path) {
            static::printLine("   $path  (r{$path->getRevision()})");
        }
        
        // Show deleted paths
        $deletedPaths = $changeSet->getDeletedPaths();
        static::printLine();
        static::printLine('Deleted path(s): %d', count($deletedPaths));
        foreach ($deletedPaths as $path) {
            static::printLine("   $path  (r{$path->getRevision()})");
        }
        
        $pathsToUpload = array_merge($addedPaths, $modifiedPaths);
        usort($pathsToUpload, function($a, $b) {
            return strcasecmp($a->getPath(), $b->getPath());
        });
        
        // Ask the user if he wants to continue
        if (!$this->_config['project']['force']) {
            static::printLine();
            while (true) {
                switch(static::readLine('Continue with uploading/deleting the above path(s)? (y/n)'))
                {
                    case 'y':
                    case 'yes':
                        break 2;
                        
                    case 'n':
                    case 'no':
                        throw new SVN2FTP\Exception('User aborted.');
                }
            }
        }
        
        // Fetch all added/modified files
        $tmpDirPath = $this->createTempDirectory();
        static::printLine();
        static::printLine('Exporting added/modified path(s) to a temp directory.');
        foreach ($pathsToUpload as $path) {
            static::printLine('   ' . $path . ' ...');
            $svn->export($path, $tmpDirPath);
        }
        
        // Etablish FTP connection
        static::printLine();
        static::printLine('Etablishing FTP connection.');
        
        // User must provide FTP password?
        if (empty($this->_config['ftp']['password'])) {
            $pwd = static::readLine('   Please enter the password for user "' . $this->_config['ftp']['user'] . '":');
            $this->_config['ftp']['password'] = $pwd;
        }
        
        $ftp = new FTP\Connection($this->_config['ftp']);
        
        // Upload/create fetches paths
        static::printLine();
        static::printLine('Uploading added/modified path(s).');
        foreach ($pathsToUpload as $path) {
            static::printLine('   ' . $path . ' ...');
            
            $localPath  = $tmpDirPath . $path;
            $remotePath = $ftp->getDefaultPath() . $path;
            
            if (is_dir($localPath)) {
                $ftp->createDirectory($remotePath);
            } else {
                $remoteDirectory = dirname($remotePath);
                
                $ftp->createDirectory($remoteDirectory);
                $ftp->uploadFile($localPath, $remotePath);
            }
        }
        
        // Delete paths
        static::printLine();
        static::printLine('Deleting path(s).');
        foreach ($deletedPaths as $path) {
            static::printLine('   ' . $path . ' ...');
            
            $localPath  = $tmpDirPath . $path;
            $remotePath = $ftp->getDefaultPath() . $path;
            
            try {
                if (is_dir($localPath)) {
                    $ftp->deleteDirectory($remotePath);
                } else {
                    $ftp->deleteFile($remotePath);
                }
            } catch (FTP\Exception $e) {
                // Ignore...
                continue;
            }
        }
        
        static::printLine();
        static::printLine('---');
        static::printLine('Done. :]');
        static::printLine('---');
    }
    
    
    /**
     * Create a temp directory and return the path
     * 
     * @return string
     */
    public function createTempDirectory()
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR
              . '.svn2ftp' . DIRECTORY_SEPARATOR
              . uniqid($this->_config['project']['name'] . '-');
        
        if (!is_dir($path)) {
            mkdir($path, null, true);
        }
        
        return realpath($path);
    }
    
    
    public function initSVNConnection()
    {
        return new SVN\Connection($this->_config['svn']);
    }
}