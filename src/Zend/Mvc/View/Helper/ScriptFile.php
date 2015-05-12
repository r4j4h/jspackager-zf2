<?php
/**
 * Helper for setting and retrieving script elements, allowing for shared / CDN paths
 */

namespace JsPackager\Zend\Mvc\View\Helper;

use JsPackager\Compiler;
use JsPackager\DependencyTree;
use JsPackager\DependencyTreeParser;
use JsPackager\Exception\MissingFile as MissingFileException;
use JsPackager\Exception\MissingFile;
use JsPackager\FileHandler;
use JsPackager\ManifestResolver;
use JsPackager\Zend2FileUrl;
use Zend\Config\Config as ZendConfig;
use Zend\Http\PhpEnvironment\Request as ZendRequest;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View;
use Zend\View\Exception;
use Zend\View\Helper\HeadScript;
use JsPackager\Exception\Parsing as ParsingException;

class ScriptFile extends HeadScript implements ServiceLocatorAwareInterface
{
    /**
     * Registry key for placeholder
     * @var string
     */
    protected $regKey = 'Zend_View_Helper_ScriptFile';

    /**
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;



    /**
     * Takes a URL and handles contextually sharing it.
     * @param string $item
     * @return string
     */
    protected function convertUrlToShared($url)
    {
        /** Grab ServiceLocator from $helperPluginManager and passing the Config to Zend2FileUrl */
        $helperPluginManager = $this->getServiceLocator();
        $config = new ZendConfig($helperPluginManager->getServiceLocator()->get('Config')); //TODO refactor out

        $fileUrl = new Zend2FileUrl();

        return $fileUrl->srcToSharedUrl($url, $config);
    }

    /**
     * Helpful method to append shared files.
     * @param $path
     */
    public function appendSharedFile($path)
    {
        $path = $this->convertUrlToShared($path);
        $this->appendFile($path, 'text/javascript');
    }

    /**
     * Method for using offsetSetFile on Shared items
     * @param $index int Placement in the stack.
     * @param $path string
     */
    public function offsetSetSharedFile($index, $path)
    {
        $path = $this->convertUrlToShared($path);
        $this->offsetSetFile($index, $path, 'text/javascript');
    }

    /**
     * Helpful method to prepend a shared file.
     * @param $path
     */
    public function prependSharedFile($path)
    {
        $path = $this->convertUrlToShared($path);
        $this->prependFile($path, 'text/javascript');
    }

    /**
     * Returns the baseUrl for building paths to stylesheet URL.
     * @return string
     */
    protected function getBaseUrl()
    {
        $baseUrl = '';
        $request = new ZendRequest();
        if ( $request ) {
            $baseUrl = $request->getBaseUrl();
        }
        return $baseUrl;
    }

    /**
     * Returns file system path to starting script (should be in the public folder's root via bootstrap)
     * for file parsing.
     *
     * @return string
     */
    protected function getFileSystemPath()
    {
        return realpath(dirname($_SERVER['SCRIPT_FILENAME'])) . '/';
    }

    /**
     * Build a "script object" from a given url, if it would not be a duplicate.
     *
     * @param string $url
     * @return bool|\stdClass Returns false if it was a duplicate, or the item object.
     */
    protected function buildDependentScriptObjectIfUnique($url) {
        if ( !$this->isDuplicate($url) )
        {
            $item = $this->createData('text/javascript', array('src' => $url));
            return $item;
        }
        else
        {
            return false;
        }
    }

    /**
     * Trim out the web relative parts of a file path. Including the baseUrl if one is present.
     * @param string $filePath
     * @return string
     */
    protected function stripRelativePathFromFilePath($filePath) {
        // Get baseUrl
        $baseUrl = $this->getBaseUrl();

        // Remove baseUrl if present
        if ( $baseUrl !== '' && substr( $filePath, 0, strlen($baseUrl) ) === $baseUrl )
        {
            // If $src already starts with $baseUrl then we want to remove $baseUrl from it.
            // As if we are shared then we may want something in between baseUrl and the real src.
            $pos = strpos($filePath,$baseUrl);
            if ($pos !== false) {
                $filePath = substr_replace($filePath, '', $pos, strlen($baseUrl));
            }
        }

        // Remove any leading slashes.
        $filePath = ltrim($filePath, '/');

        return $filePath;
    }

    /**
     * Get the config for reading behavioral settings
     *
     * @return mixed
     */
    protected function getConfig() {
        $helperPluginManager = $this->getServiceLocator();
        $config = new ZendConfig($helperPluginManager->getServiceLocator()->get('Config'));
        return $config;
    }

    /**
     * Re-attach the appropriate web relative part of a file path. Including the baseUrl if one should be present.
     * @param string $filePath
     * @return string
     */
    protected function prependWebRelativeRootToFilePath($filePath) {
        // Get baseUrl
        $baseUrl = $this->getBaseUrl();

        // Get CDN path from config
        $config = $this->getConfig();
        $fileUrl = new Zend2FileUrl();
        $productionCdnPath = $fileUrl->getProductionCdnPath($config);

        // We want to work from the web relative root, so we should ensure the appropriate baseUrl is there
        if ( strpos( $filePath, $productionCdnPath ) === 0 )
        {
            // If this dependency is on production CDN
            // No-op
        }
        else if ( $baseUrl === '' || $baseUrl === '/' )
        {
            // We have no baseUrl, but we want to work from the root. Since slashes were stripped earlier
            // we should re-add them.
            $filePath = '/' . $filePath;
        }
        else
        {
            $filePath = $baseUrl . '/' . $filePath;
        }

        return $filePath;
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

        // Get CDN path from config
        $helperPluginManager = $this->getServiceLocator();
        $config = new ZendConfig($helperPluginManager->getServiceLocator()->get('Config')); //TODO refactor out
        $cdnSharedPath = $config->cdn->cdn_shared_path;

        // Prefix with public path so we can load it
        $cdnSharedPathInPublic = $this->getServerSideWebRootPath() . '/' . $cdnSharedPath;

        // Pass to dependency tree
        $muteMissingFileExceptions = true;
        $logger = null;
        $depTree = new DependencyTree( $scriptSrc, null, $muteMissingFileExceptions, $logger, $cdnSharedPathInPublic );
        $dependencies = $depTree->flattenDependencyTree(false);

        return $dependencies;
    }

    /**
     * Get reference to StyleFile for handling stylesheet dependencies
     *
     * @return mixed
     */
    protected function getStyleFileReference()
    {
        $helperPluginManager = $this->getServiceLocator();
        $serviceManager = $helperPluginManager->getServiceLocator();
        $viewHelperManager = $serviceManager->get('viewhelpermanager');
        $styleFile = $viewHelperManager->get('StyleFile');
        return $styleFile;
    }


    public function getRealPathFromRelativePath($relativePath) {
        // Strip any baseUrl and leading slashes
        $cleanedRelativePath = $this->stripRelativePathFromFilePath( $relativePath );

        // Build real path to file
        $sourceScript = $this->getFileSystemPath() . $cleanedRelativePath;

        return $sourceScript;
    }

    protected function getScriptsToLoad($sourceScript)
    {
        $scripts = array();

        $config = $this->getConfig();

        // Analyze any query/hash parameters for preservation
        $parseUrlInfo = parse_url( $sourceScript );

        if ( isset($parseUrlInfo['query']) ) {
            $sourceScript = str_replace( '?' . $parseUrlInfo['query'], '', $sourceScript );
        }
        if ( isset($parseUrlInfo['fragment']) ) {
            $sourceScript = str_replace( '#' . $parseUrlInfo['fragment'], '', $sourceScript );
        }


        // Strip any baseUrl and leading slashes
        $sourceScript = $this->getRealPathFromRelativePath( $sourceScript );

        if ( $config->use_compiled_scripts ) {
            try {
                $scriptPaths = $this->reverseResolveFromCompiledFile( $sourceScript );
            }
            catch ( MissingFileException $e ) {
                if ( $config->fallback_if_missing_compiled_script )
                {
                    $scriptPaths = $this->passToDependencyTree( $sourceScript );
                }
                else
                {
                    throw $e;
                }
            }
        }
        else {
            $scriptPaths = $this->passToDependencyTree( $sourceScript );
        }

        foreach ($scriptPaths as $idx => $scriptPath) {


            $scriptPath = $this->replaceRemoteSymbolIfPresent($scriptPath, $this->getLocallyHostedRemotePath());

            // We want to now remove the real absolute file system path
            $scriptPath = str_replace( $this->getFileSystemPath(), '', $scriptPath );

            $scriptPath = $this->prependWebRelativeRootToFilePath( $scriptPath );

            // Convert to View Helper's Script Object
            $dependentScriptObject = $this->buildDependentScriptObjectIfUnique( $scriptPath );

            if ( $idx === (count($scriptPaths)-1) ) {

                if ( isset($parseUrlInfo['query']) ) {
                    $dependentScriptObject->attributes['src'] = $dependentScriptObject->attributes['src'] . '?' . $parseUrlInfo['query'];
                }
                if ( isset($parseUrlInfo['fragment']) ) {
                    $dependentScriptObject->attributes['src'] = $dependentScriptObject->attributes['src'] . '#' . $parseUrlInfo['fragment'];
                }

            }

            if ( $dependentScriptObject )
            {
                $scripts[] = $dependentScriptObject;
            }

            $scriptPaths[$idx] = $scriptPath;

        }

        return $scripts;
    }

    protected function addCacheBust($src) {
        /** Grab ServiceLocator from $helperPluginManager and passing the Config to Zend2FileUrl */
        $helperPluginManager = $this->getServiceLocator();
        $config = new ZendConfig($helperPluginManager->getServiceLocator()->get('Config')); //TODO refactor out
        $fileUrl = new Zend2FileUrl();

        return $fileUrl->getCacheBustString($src, $this->getRealPathFromRelativePath($src), $config);
    }

    /**
     * Override append
     *
     * @param  string $value Append script or file
     * @return void
     * @throws Exception\InvalidArgumentException
     */
    public function append($value)
    {
        // Get StyleFile in case of stylesheet dependencies
        $styleFile = $this->getStyleFileReference();

        if (!$this->isValid($value)) {
            throw new Exception\InvalidArgumentException(
                'Invalid argument passed to append(); please use one of the helper methods, appendScript() or appendFile()'
            );
        }

        if ( isset( $value->attributes['src'] ) )
        {
            $value->attributes['src'] = ltrim($value->attributes['src'], '/');

            $thisSrc = $value->attributes['src'];

            $scripts = $this->getScriptsToLoad( $thisSrc );
            foreach ($scripts as $script)
            {
                $script->attributes['src'] = $this->replaceRemoteSymbolIfPresent($script->attributes['src'], $this->getLocallyHostedRemotePath());

                $thisScriptSrc = $script->attributes['src'];

                // Handle stylesheet dependencies
                if ( preg_match( '/.css$/', $thisScriptSrc ) )
                {
                    $appendResult = $styleFile->appendStylesheet( $thisScriptSrc );
                }
                else
                {
                    $script->attributes['src'] = $this->addCacheBust( $script->attributes['src'] );
                    $appendResult = $this->getContainer()->append($script);
                }
            }

            // The return value is kinda void and useless, but let's keep the same
            // interface and return the last file, which should have been the given root
            // file, as if we were never here... :o)
            return $appendResult;
        }
        else
        {
            // Keep old behavior
            return $this->getContainer()->append($value);
        }

    }

    /**
     * Override prepend
     *
     * @param  string $value Prepend script or file
     * @return void
     * @throws Exception\InvalidArgumentException
     */
    public function prepend($value)
    {
        // Get StyleFile in case of stylesheet dependencies
        $styleFile = $this->getStyleFileReference();

        if (!$this->isValid($value)) {
            throw new Exception\InvalidArgumentException(
                'Invalid argument passed to prepend(); please use one of the helper methods, prependScript() or prependFile()'
            );
        }

        if ( isset( $value->attributes['src'] ) )
        {
            $value->attributes['src'] = ltrim($value->attributes['src'], '/');

            $thisSrc = $value->attributes['src'];

            $scripts = $this->getScriptsToLoad( $thisSrc );

            // We are prepending, so we need to reverse the actual order
            $scripts = array_reverse( $scripts );
            foreach ($scripts as $script)
            {

                $script->attributes['src'] = $this->replaceRemoteSymbolIfPresent( $script->attributes['src'], $this->getLocallyHostedRemotePath());
                $thisScriptSrc = $script->attributes['src'];

                // Handle stylesheet dependencies
                if ( preg_match( '/.css$/', $thisScriptSrc ) )
                {
                    $prependResult = $styleFile->prependStylesheet( $thisScriptSrc );
                }
                else
                {
                    $script->attributes['src'] = $this->addCacheBust( $script->attributes['src'] );
                    $prependResult = $this->getContainer()->prepend($script);
                }
            }

            // The return value is kinda void and useless, but let's keep the same
            // interface and return the last file, which should have been the given root
            // file, as if we were never here... :o)
            return $prependResult;
        }
        else
        {
            // Keep old behavior
            return $this->getContainer()->prepend($value);
        }
    }

    /**
     * Override set
     *
     * @param  string $value Set script or file
     * @return void
     * @throws Exception\InvalidArgumentException
     */
    public function set($value)
    {
        throw new \Exception('This has not been implemented with script inclusion. Please do not use.');

        if (!$this->isValid($value)) {
            throw new Exception\InvalidArgumentException(
                'Invalid argument passed to set(); please use one of the helper methods, setScript() or setFile()'
            );
        }

        return $this->getContainer()->set($value);
    }

    /**
     * Override offsetSet
     *
     * @param  string|int $index Set script of file offset
     * @param  mixed      $value
     * @return void
     * @throws Exception\InvalidArgumentException
     */
    public function offsetSet($index, $value)
    {
        throw new \Exception('This has not been implemented with script inclusion. Please do not use.');

        if (!$this->isValid($value)) {
            throw new Exception\InvalidArgumentException(
                'Invalid argument passed to offsetSet(); please use one of the helper methods, offsetSetScript() or offsetSetFile()'
            );
        }

        return $this->getContainer()->offsetSet($index, $value);
    }




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

    protected $fileHandler;

    /**
     * Get the file handler.
     *
     * @return mixed
     */
    public function getFileHandler()
    {
//        return $this->serviceLocator->get('EMRCore\JsPackager\FileHandler');
        return ( $this->fileHandler ? $this->fileHandler : new FileHandler() );
    }

    protected $compiler;

    public function getCompiler()
    {
//        return $this->serviceLocator->get('EMRCore\JsPackager\Compiler');
        return ( $this->compiler ? $this->compiler : new Compiler() );
    }

    /**
     * Set the Compiler.
     *
     * @param $compiler
     * @return ScriptFile
     */
    public function setCompiler($compiler)
    {
        $this->compiler = $compiler;
        return $this;
    }

    /**
     * Set the file handler.
     *
     * @param $fileHandler
     * @return ScriptFile
     */
    public function setFileHandler($fileHandler)
    {
        $this->fileHandler = $fileHandler;
        return $this;
    }


    /**
     * Get the base path to a file.
     *
     * Give it something like '/my/cool/file.jpg' and get '/my/cool/' back.
     * @param $sourceFilePath
     * @return string
     */
    protected function getBasePathFromSourceFile($sourceFilePath) {
        return ltrim( ( substr( $sourceFilePath, 0, strrpos($sourceFilePath, '/' )+1 ) ), '/' );
    }


    /**
     * Take a file and attempt to open its compiled version and manifest, returning an ordered array of the
     * necessary files to load.
     *
     * @param $sourceFilePath
     * @return array
     * @throws MissingFile
     */
    protected function reverseResolveFromCompiledFile($sourceFilePath, $deeper = false)
    {
        $resolver = $this->getResolver();
        $filePaths = $resolver->resolveFile( $sourceFilePath );

        // Resolver uses current working directory, which in this case is outside of the web root
        // so we need to trim that substring out!
        foreach( $filePaths as $index => $filePath ) {
            $filePaths[ $index ] = ltrim( $filePath, 'public/' );
        }

        return $filePaths;
    }


    protected $remoteSymbol = '@remote';

    protected function replaceRemoteSymbolIfPresent($filePath, $browserRelativePathToRemote = '') {

        $resolver = $this->getResolver();

        return $resolver->replaceRemoteSymbolIfPresent( $filePath, $browserRelativePathToRemote );

    }

    /**
     * @var string Server side relative path to folder where browser sees "root"
     */
    protected $serverSideWebRootPath = 'public';

    /**
     * @var string Path to locally hosted remote set of files
     */
    protected $locallyHostedRemotePath = 'shared';

    protected $usingCompiledFiles = false;



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



    protected function createDependencyTreeParser() {
        $deptreeParser = new DependencyTreeParser();
        $deptreeParser->remoteFolderPath = $this->getServerSideWebRootPath() . '/' . $this->getLocallyHostedRemotePath();
        return $deptreeParser;
    }

    /**
     * Extracts packages and stylesheets from a given a manifest file.
     *
     * Item in manifest are expected to be separated by newlines, with NO other characters or spaces.
     *
     * @param $filePath string File's path
     */
    protected function parseManifestFile($filePath) {
        $stylesheets = array();
        $packages = array();
        $resolver = $this->getResolver();
        $deptreeParser = $this->createDependencyTreeParser();

        $filesFromManifest = $resolver->resolveFile( $filePath );

        foreach($filesFromManifest as $idx => $filePath) {
            $filesFromManifest[$idx] = $resolver->replaceRemoteSymbolIfPresent($filePath, $this->getLocallyHostedRemotePath());
            // Find any @remote references and replace the @remote and anything before it with the real base path to remote
            $filesFromManifest[$idx] = $deptreeParser->normalizeRelativePath( $filesFromManifest[$idx] );
            // Normalize paths - e.g. remove any `../../` type stuff
        }

        $filesFromManifest = array_merge(array_keys(array_flip($filesFromManifest)));
        // This is faster than array_unique and doesn't cause gaps in array keys

        foreach ($filesFromManifest as $file)
        {
            if ( preg_match('/.js$/i', $file ) ) {
                $packages[] = $file;
            }
            else if ( preg_match('/.css$/i', $file ) ) {
                $stylesheets[] = $file;
            }
        }

        return array(
            'stylesheets' => $stylesheets,
            'packages' => $packages
        );
    }



    /**
     * @var ManifestResolver
     */
    protected $resolver;

    /**
     * @return ManifestResolver
     */
    public function getResolver()
    {
        $this->resolver = ( $this->resolver ? $this->resolver : new ManifestResolver() );

        $this->resolver->remoteFolderPath = $this->getLocallyHostedRemotePath();
        $this->resolver->baseFolderPath = $this->getBaseUrl();

        return $this->resolver;
    }

    /**
     * @param ManifestResolver $resolver
     */
    public function setResolver($resolver)
    {
        $this->resolver = $resolver;
    }


}




