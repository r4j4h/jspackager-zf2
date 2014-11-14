<?php
/** Helper for setting and retrieving stylesheet elements, allowing for shared / CDN paths
 * Based on Zend's HeadLink
 */

namespace JsPackager\Zend\Mvc\View\Helper;

use JsPackager\Zend2FileUrl;
use Zend\Config\Config as ZendConfig;
use Zend\Http\PhpEnvironment\Request as ZendRequest;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View;
use Zend\View\Exception;
use Zend\View\Helper\HeadLink;

class StyleFile extends HeadLink implements ServiceLocatorAwareInterface
{
    /**
     * Registry key for placeholder
     * @var string
     *
     */
    protected $regKey = 'Zend_View_Helper_StyleFile';

    protected $serviceLocator;

    /**
     * Helpful method to append shared stylesheets.
     * @param $path
     */
    public function appendSharedStylesheet($path)
    {
        $path = $this->convertUrlToShared($path);

        $this->appendStylesheet($path, 'screen', null);
    }

    /**
     * Method for using offsetSetStylesheet on Shared items
     * @param $index int Placement in the stack.
     * @param $path string
     */
    public function offsetSetSharedStylesheet($index, $path)
    {
        $path = $this->convertUrlToShared($path);

        $this->offsetSetStylesheet($index, $path, 'screen', null);
    }

    /**
     * Helpful method to prepend shared stylesheets.
     * @param $path
     */
    public function prependSharedStylesheet($path)
    {
        $path = $this->convertUrlToShared($path);

        $this->prependStylesheet($path, 'screen', null);
    }

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
     * Get reference to StyleFile for handling stylesheet dependencies
     *
     * @return mixed
     */
    protected function getScriptFileReference()
    {
        $helperPluginManager = $this->getServiceLocator();
        $serviceManager = $helperPluginManager->getServiceLocator();
        $viewHelperManager = $serviceManager->get('viewhelpermanager');
        $styleFile = $viewHelperManager->get('ScriptFile');
        return $styleFile;
    }

    protected function addCacheBust($src) {
        /** Grab ServiceLocator from $helperPluginManager and passing the Config to Zend2FileUrl */
        $helperPluginManager = $this->getServiceLocator();
        $config = new ZendConfig($helperPluginManager->getServiceLocator()->get('Config')); //TODO refactor out
        $fileUrl = new Zend2FileUrl();

        $scriptFile = $this->getScriptFileReference();

        return $fileUrl->getCacheBustString($src, $scriptFile->getRealPathFromRelativePath($src), $config);
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
     * @return StyleFile
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }




    /**
     * Override append()
     *
     * @param  array $value
     * @return void
     * @throws Exception\InvalidArgumentException
     */
    public function append($value)
    {
        if (!$this->isValid($value)) {
            throw new Exception\InvalidArgumentException(
                'append() expects a data token; please use one of the custom append*() methods'
            );
        }

        if ( isset( $value->href ) ) {
            $value->href = $this->addCacheBust( $value->href );
        } else if ( isset( $value->src ) ) {
            $value->src = $this->addCacheBust( $value->src );
        }

        return $this->getContainer()->append($value);
    }

    /**
     * Override offsetSet()
     *
     * @param  string|int $index
     * @param  array $value
     * @return void
     * @throws Exception\InvalidArgumentException
     */
    public function offsetSet($index, $value)
    {
        if (!$this->isValid($value)) {
            throw new Exception\InvalidArgumentException(
                'offsetSet() expects a data token; please use one of the custom offsetSet*() methods'
            );
        }

        $value->href = $this->addCacheBust( $value->href );

        return $this->getContainer()->offsetSet($index, $value);
    }

    /**
     * Override prepend()
     *
     * @param  array $value
     * @return HeadLink
     * @throws Exception\InvalidArgumentException
     */
    public function prepend($value)
    {
        if (!$this->isValid($value)) {
            throw new Exception\InvalidArgumentException(
                'prepend() expects a data token; please use one of the custom prepend*() methods'
            );
        }

        $value->href = $this->addCacheBust( $value->href );

        return $this->getContainer()->prepend($value);
    }

    /**
     * Override set()
     *
     * @param  array $value
     * @return HeadLink
     * @throws Exception\InvalidArgumentException
     */
    public function set($value)
    {
        if (!$this->isValid($value)) {
            throw new Exception\InvalidArgumentException(
                'set() expects a data token; please use one of the custom set*() methods'
            );
        }

        $value->href = $this->addCacheBust( $value->href );

        return $this->getContainer()->set($value);
    }


}