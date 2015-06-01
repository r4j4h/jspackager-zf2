<?php
namespace JsPackager\Zend\Mvc\View\Helper;

use DateTime;
use DateTimeZone;
use JsPackager\DependencyTree;
use JsPackager\Compiler;
use JsPackager\DependencyTreeParser;
use JsPackager\Exception\MissingFile as MissingFileException;
use JsPackager\Exception\MissingFile;
use JsPackager\FileHandler;
use JsPackager\FileUrl;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\ManifestResolver;
use Zend\Config\Config;
use Zend\Http\PhpEnvironment\Request;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Helper\AbstractHelper;
use JsPackager\Tagger;

/**
 * This helper takes in root files and handles creation of appropriate tags for
 * for both compiled versions and the non-compiled w/ dependencies versions.
 */
class JsPackagerScriptHelper extends AbstractHelper implements ServiceLocatorAwareInterface {

    /**
     * Registry key for placeholder
     * @var string
     */
    protected $regKey = 'JsPackagerScriptHelper';

    /**
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * Get the service locator.
     *
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }

    /**
     * Set the service locator.
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return ScriptFile
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }



    /**
     * Root script file list
     * @var array
     */
    private $scriptFiles = array();

    /**
     * @var string Server side relative path to folder where browser sees "root"
     */
    protected $serverSideWebRootPath = 'public';

    /**
     * @var string Path to locally hosted remote set of files
     */
    protected $locallyHostedRemotePath = 'shared';

    protected $usingCompiledFiles = false;


    private $needToParseFlag = false;
    private $cachedParse = null;

    protected function markNeedToParse() {
        $this->needToParseFlag = true;
    }

    protected function markParsed($parsedValue) {
        $this->needToParseFlag = false;
        $this->cachedParse = $parsedValue;
    }

    /**
     * @var null|Tagger
     */
    private $tagger = null;

    /**
     * Return object, generally used for implicit toString for output for clients
     * @return JsPackagerScriptHelper
     */
    public function jsPackagerScriptHelper() {
        return $this;
    }

    /**
     * Append a root script for dependency inclusion
     * @param string $file Path to JS file relative to the public directory
     */
    public function add($file) {
        $this->appendFile($file);
    }

    /**
     * Append a root script for dependency inclusion
     * @param string $file Path to JS file relative to the public directory
     */
    public function appendFile($file) {
        $this->scriptFiles[] = $file;
        $this->markNeedToParse();
    }

    /**
     * Append a root script for dependency inclusion from remote
     * @param string $file Path to JS file relative to the public directory
     */
    public function appendRemoteFile($file) {
        $resolver = $this->getResolver();
        $this->scriptFiles[] = $resolver->remoteSymbol . '/' . $file;
        $this->markNeedToParse();
    }

    /**
     * Prepend a root script for dependency inclusion
     * @param string $file Path to JS file relative to the public directory
     */
    public function prependFile($file) {
        array_unshift( $this->scriptFiles, $file );
        $this->markNeedToParse();
    }

    /**
     * Append a root script for dependency inclusion from remote
     * @param string $file Path to JS file relative to the public directory
     */
    public function prependRemoteFile($file) {
        $resolver = $this->getResolver();
        array_unshift( $this->scriptFiles, $resolver->remoteSymbol . '/' . $file );
        $this->markNeedToParse();
    }


    /**
     * @var String
     */
    protected $baseUrl;

    /**
     * Returns the baseUrl for building paths to URLs.
     *
     * @return string
     */
    public function getBaseUrlFromZend2()
    {
        $baseUrl = '';
        if ( class_exists( 'Zend\Http\PhpEnvironment\Request' ) ) {
            $request = new Request();
            if ($request) {
                $baseUrl = $request->getBaseUrl();
            }
        }
        return $baseUrl;
    }

    /**
     * Returns the baseUrl for building paths to URLs.
     *
     * @return string
     */
    public function getBaseUrl()
    {
        $this->baseUrl = ( $this->baseUrl ? $this->baseUrl : $this->getBaseUrlFromZend2() );
        if ( is_null( $this->baseUrl ) ) {
            $this->baseUrl = '';
        }
        return $this->baseUrl;
    }

    /**
     * @param String $baseUrl
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }


    /**
     * @var mixed
     */
    protected $config;

    /**
     * Get the config for reading behavioral settings
     *
     * @return mixed|null
     */
    protected function getConfig() {
        $this->config = ( $this->config ? $this->config : $this->loadConfiguration());
        return $this->config;
    }

    /**
     * @return mixed|null
     */
    protected function loadConfiguration()
    {
        return $this->getConfigFromZend2();
    }

    /**
     * @param String $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * Attempt to load config through Zend 2 framework's ServiceLocator
     * @return mixed|null
     */
    protected function getConfigFromZend2() {

        $helperPluginManager = $this->getServiceLocator();
        if ( !$helperPluginManager ) {
            return null;
        }

        $config = $helperPluginManager->getServiceLocator()->get('Config');
        if ( !$config ) {
            return null;
        }

        $config = new Config( $config );
        return $config;
    }


    /**
     * Get the config for reading behavioral settings
     *
     * @param bool|array $configArray If array, detects from config array. Otherwise uses `Use Compiled Files` flag.
     * @return bool
     */
    protected function isUsingCompiledFilesFlagEnabled($configArray = null) {

        if ( $this->detectArrayKeyExistsInZend1Config( $configArray ) ) {
            // If a config entry is present, prefer it

            $usingCompiled = $this->detectUsingCompiledFilesFromZend1Config($configArray);

        } else {
            // If no config entry is present, fall back to flag

            $usingCompiled = $this->isUsingCompiledFiles();

        }

        return $usingCompiled;
    }

    protected function getCurrentTime() {
        $dateTime = new DateTime('now', new DateTimeZone('Africa/Nairobi') );
        $curTime = $dateTime->getTimestamp();
        return $curTime;
    }



    /**
     * Transform dependency tree to link tags in development and production.
     * @return string
     */
    public function printStylesheets() {
        $output = '';
        $baseUrl = $this->getBaseUrl();
        $config = $this->getConfig();
        $resolver = $this->getResolver();
        $usingCompiled = $this->isUsingCompiledFilesFlagEnabled($config);
        $dependentStylesheetsPaths = $this->getStylesheetDependencies($usingCompiled);

        if ( $usingCompiled ) {
            // Using compiled files

            foreach ($dependentStylesheetsPaths as $filePath) {

                $mtime = $this->getCacheBustValueForFile($filePath);
                // Cache bust by modified time or present time to ensure users use the latest.

                $output = $this->generateStylesheetTagsForCompiledFiles($filePath, $output, $baseUrl, $mtime);
                // Include the file

            }

        } else {
            // Not using compiled files

            foreach ($dependentStylesheetsPaths as $filePath) {

                $filePath = $resolver->replaceRemoteSymbolIfPresent($filePath, $this->getLocallyHostedRemotePath());
                // Find any @remote references and replace the @remote and anything before it with the real base path to remote

                $output = $this->generateStylesheetTagsForUncompiledFiles($filePath, $output, $baseUrl);
                // Include the file

            }
        }

        return $output;
    }

    protected function getPathToLocallyHostedRemoteFilesFromWebRoot() {
        $locallyHostedRemotePath = $this->getLocallyHostedRemotePath();
        return $this->getServerSideWebRootPath() . '/' . $locallyHostedRemotePath;
    }


    protected function createDependencyTreeParser() {
        $deptreeParser = new DependencyTreeParser();
        $deptreeParser->remoteFolderPath = $this->getPathToLocallyHostedRemoteFilesFromWebRoot();
        return $deptreeParser;
    }

    /**
     * Transform dependency tree to script tags in development, or a script tag to compiled version in production.
     * @return string
     */
    public function __toString() {
        $output = '';
        $baseUrl = $this->getBaseUrl();
        $config = $this->getConfig();
        $resolver = $this->getResolver();
        $usingCompiled = $this->isUsingCompiledFilesFlagEnabled($config);
        $dependentScriptsPaths = $this->getScriptsPathsByConfig($config);


        if ( $usingCompiled ) {
            // Using compiled files

            foreach ( $dependentScriptsPaths as $filePath ) {

                $mtime = $this->getCacheBustValueForFile($filePath);
                // Cache bust by modified time or present time to ensure users use the latest.

                $output = $this->generateScriptTagsForCompiledFiles($filePath, $output, $baseUrl, $mtime);
                // Include the file
            }

        } else {
            // Not using compiled files

            foreach ($dependentScriptsPaths as $filePath) {

                $filePath = $resolver->replaceRemoteSymbolIfPresent($filePath, $this->getLocallyHostedRemotePath() );
                // Find any @remote references and replace the @remote and anything before it with the real base path to remote

                $output = $this->generateScriptTagForUncompiledFiles($filePath, $output, $baseUrl);
                // Include the file
            }

        }

        return $output;
    }


    /**
     * Take a given relative script path and pass it to DependencyTree to gather its dependencies into an ordered
     * array.
     *
     * @param string $scriptSrc
     * @return array
     * @throws \JsPackager\Exception\Recursion If the dependent files have a circular dependency
     * @throws \JsPackager\Exception\MissingFile Through internal File object if $filePath does not point to a valid file
     */
    protected function passToDependencyTree($scriptSrc) {

        // Pass to dependency tree
        $dependencyTree = new DependencyTree( $scriptSrc, null, true, null, $this->getLocallyHostedRemotePath() );
        $dependencies = $dependencyTree->flattenDependencyTreeIntoAssocArrays(false);

        return $dependencies;
    }

    /**
     * Collect stylesheet dependencies into
     * @return array
     */
    private function getStylesheetDependenciesThroughUncompiledFiles() {
        if ( $this->needToParseFlag === true ) {
            $scriptFiles = $this->getScriptFiles(true, true);
            $this->markParsed($scriptFiles);
        } else {
            $scriptFiles = $this->cachedParse;
        }
        return $scriptFiles['stylesheets'];
    }

    /**
     * Collect stylesheet dependencies into
     * @return array
     */
    private function getStylesheetDependenciesThroughCompiledFiles() {
        if ( $this->needToParseFlag === true ) {
            $scriptFiles = $this->getScriptFilesByCompiledAndManifestVersions();
            $this->markParsed($scriptFiles);
        } else {
            $scriptFiles = $this->cachedParse;
        }
        return $scriptFiles['stylesheets'];
    }

    /**
     * Collect stylesheet dependencies into
     * @return array
     */
    private function getStylesheetDependencies($usingCompiled) {

        $dependentStylesheetsPaths = array();

        if ( $usingCompiled ) {
            // Using compiled files, so include each child in order by parsing manifest for stylesheets

            $dependentStylesheetsPaths = $this->getStylesheetDependenciesThroughCompiledFiles();

        } else {
            // Not using compiled files, so include each child in order by parsing dependency tree for stylesheets

            $dependentStylesheetsPaths = $this->getStylesheetDependenciesThroughUncompiledFiles();

        }

        return $dependentStylesheetsPaths;
    }

    private function getScriptsPathsThroughUncompiledFiles() {
        if ( $this->needToParseFlag === true ) {
            $scriptFiles = $this->getScriptFiles(true, true);
            $this->markParsed($scriptFiles);
        } else {
            $scriptFiles = $this->cachedParse;
        }
        return $scriptFiles['scripts'];
    }

    private function getScriptsPathsThroughCompiledFiles() {
        if ( $this->needToParseFlag === true ) {
            $scriptFiles = $this->getScriptFilesByCompiledAndManifestVersions();
            $this->markParsed($scriptFiles);
        } else {
            $scriptFiles = $this->cachedParse;
        }
        return $scriptFiles['scripts'];
    }

    private function getScriptsPathsByConfig($config)
    {

        $usingCompiled = $this->isUsingCompiledFilesFlagEnabled($config);
        $dependentScriptsPaths = array();

        if ($usingCompiled) {
            // Using compiled files

            $dependentScriptsPaths = $this->getScriptsPathsThroughCompiledFiles();


        } else {
            // Not using compiled files

            $dependentScriptsPaths = $this->getScriptsPathsThroughUncompiledFiles();

        }

        return $dependentScriptsPaths;

    }

    /**
     * Utilize Dependency Tree to build a non-redundant dependent scripts list
     * @param  boolean $include_scripts If true, scripts array will be populated
     * @param  boolean $include_styles  If true, stylesheets array will be populated
     * @return array Arrays of script and style strings relative to public directory
     */
    private function getScriptFiles($include_scripts = true, $include_styles = true) {
        $scripts = array();
        $stylesheets = array();
        $resolver = $this->getResolver();

        foreach ($this->scriptFiles as $file) {

            $file = ltrim($file, '/');
            $file = $resolver->replaceRemoteSymbolIfPresent($file, $this->getLocallyHostedRemotePath());

            $files = $this->passToDependencyTree( $file );

            $scriptFiles = $files['scripts'];
            $stylesheetFiles = $files['stylesheets'];

            foreach ($scriptFiles as $innerFile) {
                array_push( $scripts, $innerFile );
            }

            foreach ($stylesheetFiles as $innerFile) {
                array_push( $stylesheets, $innerFile );
            }

        }

//            if ( $include_scripts )
        $scripts = array_unique( $scripts );
//			if ( $include_styles )
        $stylesheets = array_unique( $stylesheets );

        return array(
            'scripts' => $scripts,
            'stylesheets' => $stylesheets
        );
    }

    private function getScriptFilesByCompiledAndManifestVersions() {
        $scripts = array();
        $stylesheets = array();
        $resolver = $this->getResolver();
        $deptreeParser = $this->createDependencyTreeParser();

        $lines = array();

        foreach ($this->scriptFiles as $file) {

            $compiledResponse = $resolver->resolveFile( $file );
            // Link to compiled scripts
            $lines = array_merge( $lines, $compiledResponse );

        }

        foreach($lines as $idx => $filePath) {

            $lines[$idx] = $deptreeParser->normalizeRelativePath( $lines[$idx] );
            // Normalize paths - e.g. remove any `../../` type stuff
        }

        $lines = array_merge(array_keys(array_flip($lines)));
        // This is faster than array_unique and doesn't cause gaps in array keys

        foreach($lines as $line) {
            // Split lines by file type extension

            if (preg_match('/.js$/i', $line)) {
                $scripts[] = $line;

            } else if (preg_match('/.css$/i', $line)) {
                $stylesheets[] = $line;

            } else {
                continue;
            }
        }

        return array(
            'scripts' => $scripts,
            'stylesheets' => $stylesheets
        );

    }


    ////// Adapted from our ZF2 ScriptFile



    /**
     * @var ManifestResolver
     */
    protected $resolver;

    /**
     * @return ManifestResolver
     */
    public function getDefaultResolver()
    {
        $resolver = new ManifestResolver();

        $resolver->remoteFolderPath = $this->getLocallyHostedRemotePath();
        $resolver->baseFolderPath = $this->getBaseUrl(); //'.';

        return $resolver;
    }

    /**
     * @return ManifestResolver
     */
    public function getResolver()
    {
        $this->resolver = ( $this->resolver ? $this->resolver : $this->getDefaultResolver() );

        return $this->resolver;
    }

    /**
     * @param ManifestResolver $resolver
     */
    public function setResolver($resolver)
    {
        $this->resolver = $resolver;
    }


    /**
     * @var FileHandler
     */
    protected $fileHandler;

    /**
     * @return FileHandler
     */
    protected function getDefaultFileHandler()
    {
        return new FileHandler();
    }

    /**
     * Get the file handler.
     *
     * @var FileHandler
     */
    public function getFileHandler()
    {
//        return $this->serviceLocator->get('EMRCore\JsPackager\FileHandler');
        return ( $this->fileHandler ? $this->fileHandler : $this->getDefaultFileHandler());
    }

    /**
     * Set the file handler.
     *
     * @param $fileHandler
     * @return JsPackagerScriptHelper
     */
    public function setFileHandler($fileHandler)
    {
        $this->fileHandler = $fileHandler;
        return $this;
    }


    /**
     * @var Compiler
     */
    protected $compiler;

    /**
     * @return Compiler
     */
    protected function getDefaultCompiler()
    {
        return new Compiler();
    }

    public function getCompiler()
    {
//        return $this->serviceLocator->get('EMRCore\JsPackager\Compiler');
        return ( $this->compiler ? $this->compiler : $this->getDefaultCompiler());
    }

    /**
     * Set the Compiler.
     *
     * @param $compiler
     * @return JsPackagerScriptHelper
     */
    public function setCompiler($compiler)
    {
        $this->compiler = $compiler;
        return $this;
    }


    /**
     * @return Tagger
     */
    protected function getDefaultTagger()
    {
        $tagger = new Tagger();
        $tagger->includingEndOfLine = true;
        $tagger->cacheBustKey = 'mtime';

        return $tagger;
    }

    /**
     * @return Tagger|null
     */
    public function getTagger()
    {
        return ( $this->tagger ? $this->tagger : $this->getDefaultTagger());
    }

    /**
     * @param Tagger|null $tagger
     */
    public function setTagger($tagger)
    {
        $this->tagger = $tagger;
    }



    /**
     * @return boolean
     */
    public function isUsingCompiledFiles()
    {
        return $this->usingCompiledFiles;
    }

    /**
     * @param boolean $usingCompiledFiles
     */
    public function setUsingCompiledFiles($usingCompiledFiles)
    {
        $this->usingCompiledFiles = $usingCompiledFiles;
    }

    /**
     * Determine if the the `Use Compiled Files` flag  is present in a Zend 1 style config object
     *
     * @param Array $configArray
     * @return bool
     */
    public function detectArrayKeyExistsInZend1Config($configArray)
    {
        if ( !$configArray ) {
            return false;
        }

        $keyExists = ( array_key_exists( 'compiled', $configArray )
            && array_key_exists( 'javascript', $configArray['compiled'] ) );

        return $keyExists;
    }

    /**
     * Grab the value of the `Use Compiled Files` flag from a Zend 1 style config object
     *
     * @param Array $configArray
     * @return bool
     */
    public function detectUsingCompiledFilesFromZend1Config(Array $configArray)
    {
        if ( !$configArray ) {
            return false;
        }

        if ( !$this->detectArrayKeyExistsInZend1Config($configArray) ) {
            return false;
        }

        $usingCompiled = $configArray['compiled']['javascript'] == "true";

        return $usingCompiled;
    }


    /**
     * @return string
     */
    public function getServerSideWebRootPath()
    {
        return $this->serverSideWebRootPath;
    }

    /**
     * @param string $serverSideWebRootPath
     */
    public function setServerSideWebRootPath($serverSideWebRootPath)
    {
        $this->serverSideWebRootPath = $serverSideWebRootPath;
    }

    /**
     * @return string
     */
    public function getLocallyHostedRemotePath()
    {
        return $this->locallyHostedRemotePath;
    }

    /**
     * @param string $locallyHostedRemotePath
     */
    public function setLocallyHostedRemotePath($locallyHostedRemotePath)
    {
        $this->locallyHostedRemotePath = $locallyHostedRemotePath;
    }

    /**
     * @param $file
     * @return int
     */
    protected function getCacheBustValueForFile($file)
    {
        $mtime = @filemtime($file);
        if ($mtime === false) {
            // If the file does not exist, use the current time to force cache bust
            // Or do nothing? TODO make configurable

            $mtime = $this->getCurrentTime();
            return $mtime;
        }
        return $mtime;
    }


    /**
     * @param $file
     * @param $output
     * @param $baseUrl
     * @return string
     */
    protected function generateStylesheetTagsForUncompiledFiles($file, $output, $baseUrl)
    {
        return $this->generateStylesheetTagsForCompiledFiles($file, $output, $baseUrl, null);
    }

    /**
     * @param $filePath
     * @param $output
     * @param $baseUrl
     * @return string
     */
    protected function generateScriptTagForUncompiledFiles($filePath, $output, $baseUrl)
    {
        return $this->generateScriptTagsForCompiledFiles($filePath, $output, $baseUrl, null);
    }




    /**
     * @param $file
     * @param $output
     * @param $baseUrl
     * @param null|string $mtime
     * @return string
     */
    protected function generateStylesheetTagsForCompiledFiles($file, $output, $baseUrl, $mtime = null)
    {
        $webAccessiblePath = $baseUrl . '/' . $file;
        $tagger = $this->getTagger();
        $output .= $tagger->getStylesheetTag($webAccessiblePath, $mtime);
        return $output;
    }
    /**
     * @param $filePath
     * @param $output
     * @param $baseUrl
     * @param null|string $mtime
     * @return string
     */
    protected function generateScriptTagsForCompiledFiles($filePath, $output, $baseUrl, $mtime = null)
    {
        $webAccessiblePath = $baseUrl . '/' . $filePath;
        $tagger = $this->getTagger();
        $output .= $tagger->getScriptTag($webAccessiblePath, $mtime);
        return $output;
    }



}
