<?php
use BigWhoop\SVN2FTP as SVN2FTP;

set_include_path(realpath(__DIR__ . '/../src') . PATH_SEPARATOR . get_include_path());
require 'BigWhoop/SVN2FTP.php';

SVN2FTP::runCli();