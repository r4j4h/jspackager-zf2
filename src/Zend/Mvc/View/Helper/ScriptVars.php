<?php
/** Helper for adding Javascript variables to page
 */
namespace JsPackager\Zend\Mvc\View\Helper;

use Zend\Http\PhpEnvironment\Request as ZendRequest;
use Zend\View\Helper\AbstractHelper;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;


class ScriptVars extends AbstractHelper implements ServiceLocatorAwareInterface
{
    /**
     * Registry key for placeholder
     * @var string
     */
    protected $regKey = 'Zend_View_Helper_ScriptVars';

    protected $serviceLocator;

    protected $request;

    public function __construct()
    {
        $request = new ZendRequest();
        $this->request = $request;
    }

    public function __invoke($jsVarsObj = Array())
    {
        $globalPrefix = $this->globalPrefix;

        $helperPluginManager = $this->getServiceLocator(); // We need to pop out and get the global ServiceLocator
        $config = $helperPluginManager->getServiceLocator()->get('Config');
        $configBlock = isset( $config['ScriptVars'] ) ? $config['ScriptVars'] : array();
        $configPrefix = isset( $config['ScriptVars']['prefix'] ) ? $config['ScriptVars']['prefix'] : false;

        $prefix = ( $configPrefix !== false ? $configPrefix : false );

        $jsVarsObj = $this->applyBasePath( $jsVarsObj );
        $jsVarsObj = $this->applyControllerActionInfo( $jsVarsObj );
        $jsVarsObj = $this->applySlicesInfo( $jsVarsObj );
        $jsVarsObj = $this->applySliceSpecificInfo( $jsVarsObj );


        // If the jsVars isn't empty, json encode it, otherwise default to empty JS object (not array).
        $jsVarsObj = (!empty($jsVarsObj)) ? json_encode($jsVarsObj) : '{}';

        if ( $prefix ) {

            $pageScript = "var " . $prefix . " = " . $prefix . " || {}; " . $prefix . ".page = " . $prefix . ".page || {};";
            $pageScript .= "" . $prefix . ".page.vars = " . $jsVarsObj . ";";

        } else {

            $pageScript = "var page = page || {};";
            $pageScript .= "page.vars = " . $jsVarsObj . ";";

        }


        return $pageScript;
    }

    /**
     * Apply basPath info to jsVars output
     *
     * @param $jsVarsObj
     * @return mixed
     */
    private function applyBasePath($jsVarsObj) {

        $jsVarsObj['basePath'] = $this->request->getBaseUrl();

        return $jsVarsObj;
    }

    /**
     * Apply controller/action/namespace info to jsVars output
     *
     * @param $pageScripts
     * @return mixed
     */
    private function applyControllerActionInfo($jsVarsObj) {
        $helperPluginManager = $this->getServiceLocator(); // We need to pop out and get the global ServiceLocator
        $application = $helperPluginManager->getServiceLocator()->get('Application');

        $routeMatch = $application->getMvcEvent()->getRouteMatch();
        if ( $routeMatch ) {
            $controllerFull = $routeMatch->getParam('controller');
            $controllerArray = explode("\\", $controllerFull);
            $controller = array_pop($controllerArray);
            $controllerNamespace = implode("\\\\", $controllerArray);
            $action = $routeMatch->getParam('action');
        } else {
            $controllerArray = array();
            $controller = '';
            $controllerNamespace = '';
            $action = '';
        }


        $jsVarsObj['controller'] = $controller;
        $jsVarsObj['controllerNamespace'] = $controllerNamespace;
        $jsVarsObj['action'] = $action;


        return $jsVarsObj;
    }

    /**
     * Apply slice info to jsVars output
     *
     * @param $pageScripts
     * @return mixed
     */
    private function applySlicesInfo($jsVarsObj) {
        $helperPluginManager = $this->getServiceLocator(); // We need to pop out and get the global ServiceLocator
        $config = $helperPluginManager->getServiceLocator()->get('Config');

        if ($config && isset($config['slices'])) // If we have slices urls, let's include that so we can grab urls from config
        {
            $jsVarsObj['slices'] = $config['slices'];
        }

        return $jsVarsObj;
    }

    /**
     * Hook for custom injection to jsVars output
     *
     * @param $jsVarsObj
     * @return mixed
     */
    public function applySliceSpecificInfo($jsVarsObj) {

        return $jsVarsObj;
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
}