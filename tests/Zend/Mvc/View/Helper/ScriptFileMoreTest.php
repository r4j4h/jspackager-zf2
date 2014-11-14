<?php
/** Based on HeadScriptTest, for use on WebPT EMRCore
 *
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_View
 */

namespace JsPackager\Unit\Zend\Mvc\View\Helper;

use JsPackager\Exception\MissingFile as MissingFileException;
use JsPackager\Exception\MissingFile;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\Zend\Mvc\View\Helper\ScriptFile;
use Zend\View;
use JsPackager\Helpers\Reflection as ReflectionHelper;


/**
 * Test class for ScriptFile.
 *
 * @category   Zend
 * @package    Zend_View
 * @subpackage UnitTests
 * @group      Zend_View
 * @group      Zend_View_Helper
 * @group      JsPackager
 */
class ScriptFileMoreTest extends \PHPUnit_Framework_TestCase
{
    // Tests are run from EMRCore root
    const fixturesBasePath = 'tests/EMRCoreTest/lib/EMRCoreTest/JsPackager/fixtures/';


    /**
     * @var ScriptFile
     */
    public $helper;

    /**
     * @var string
     */
    private $sharedPath;

    /**
     * @var string
     */
    public $basePath;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $pluginServiceLocatorMock;
    private $helperServiceLocatorMock;


    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     *
     * @return void
     */
    public function setUp()
    {
        $this->basePath = __DIR__ . '/_files/modules';
        $this->helper = new ScriptFile();

        $this->helper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('getFileSystemPath'));

        $this->helper->expects($this->any())
            ->method('getFileSystemPath')
            ->will($this->returnValue(''));

        /**
         * Mock config.
         */
        $testCdn = 'https://cdn.tests.com';
        $this->sharedPath = 'shared';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => false,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
            'use_cache_busting' => false
        );
        $this->setUpMockConfig($config);
    }

    /**
     * Sets up a Config Service from $config, in a manner used by Helper Plugins.
     *
     * @param array $config
     */
    private function setUpMockConfig($config = array())
    {
        $this->helperServiceLocatorMock = $this->getMockBuilder('Zend\View\HelperPluginManager')
            ->disableOriginalConstructor()
            ->getMock();
        $this->pluginServiceLocatorMock = $this->getMock('Zend\ServiceManager\ServiceLocatorInterface');

        $this->helperServiceLocatorMock->expects($this->any())
            ->method('getServiceLocator')
            ->will($this->returnValue($this->pluginServiceLocatorMock));

        $styleFile = $this->getMock('stdClass', array('appendStylesheet', 'prependStylesheet'));
        $styleFile->expects($this->any())->method('appendStylesheet')
            ->will($this->returnValue(null));
        $styleFile->expects($this->any())->method('prependStylesheet')
            ->will($this->returnValue(null));

        $viewHelperManager = $this->getMock('stdClass', array('get'));
        $viewHelperManager->expects($this->any())->method('get')
            ->with($this->equalTo('StyleFile'))->will($this->returnValue($styleFile));

//        $valueMap = array(
//            array('Config',null,$config),
//            array('viewhelpermanager',null,$viewHelperManager),
//        );

        $this->pluginServiceLocatorMock->expects($this->any())
            ->method('get')
            ->will($this->returnCallback(function($name) use ($config, $viewHelperManager) {
                if ( $name ==='Config') {
                    return $config;
                }
                if ( $name === 'viewhelpermanager') {
                    return $viewHelperManager;
                }
                return null;
            }));

        $this->helper->setServiceLocator($this->helperServiceLocatorMock);
    }

    /**
     * Tears down the fixture, for example, close a network connection.
     * This method is called after a test is executed.
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->helper);
    }



    public function testAppendSharedCallsAppendWithLocalSharedDirectoryPrependedWhenUseCdnForSharedIsFalse()
    {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => false,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);

        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('appendFile'));
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);
        $mockedHelper->expects($this->once())
            ->method('appendFile')
            ->with('/' . $this->sharedPath . '/' . 'js/test.js');

        $mockedHelper->appendSharedFile('js/test.js');
    }
    public function testAppendSharedCallsAppendWithCdnSharedDirectoryPrependedWhenUseCdnForSharedIsTrue()
    {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => false,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => true,
        );
        $this->setUpMockConfig($config);

        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('appendFile'));
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);
        $mockedHelper->expects($this->once())
            ->method('appendFile')
            ->with($testCdn . '/' . $this->sharedPath . '/' . 'js/test.js');

        $mockedHelper->appendSharedFile('js/test.js');
    }

    public function testPrependSharedCallsPrependWithLocalSharedDirectoryPrependedWhenUseCdnForSharedIsFalse()
    {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => false,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);

        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('prependFile'));
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);
        $mockedHelper->expects($this->once())
            ->method('prependFile')
            ->with('/' . $this->sharedPath . '/' . 'js/test.js');

        $mockedHelper->prependSharedFile('js/test.js');
    }
    public function testPrependSharedCallsPrependWithCdnSharedDirectoryPrependedWhenUseCdnForSharedIsTrue()
    {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => false,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => true,
        );
        $this->setUpMockConfig($config);

        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('prependFile'));
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);
        $mockedHelper->expects($this->once())
            ->method('prependFile')
            ->with($testCdn . '/' . $this->sharedPath . '/' . 'js/test.js');

        $mockedHelper->prependSharedFile('js/test.js');
    }

    // stripRelativePathFromFilePath tests

    public function testStripRelativePathFromFilePathRemovesLeadingSlash()
    {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => false,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);


        $strippedPath = ReflectionHelper::invoke(
            $this->helper,
            'stripRelativePathFromFilePath',
            array( '/shared/js/test.js' )
        );


        $this->assertEquals('shared/js/test.js', $strippedPath);
    }

    public function testStripRelativePathFromFilePathRemovesAnyLeadingSlashes()
    {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => false,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);


        $strippedPath = ReflectionHelper::invoke(
            $this->helper,
            'stripRelativePathFromFilePath',
            array( '///shared/js/test.js' )
        );


        $this->assertEquals('shared/js/test.js', $strippedPath);
    }

    public function testStripRelativePathFromFilePathDoesNotHarmIfNoLeadingSlash()
    {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => false,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);


        $strippedPath = ReflectionHelper::invoke(
            $this->helper,
            'stripRelativePathFromFilePath',
            array( 'shared/js/test.js' )
        );


        $this->assertEquals('shared/js/test.js', $strippedPath);
    }

    public function testStripRelativePathFromFilePathRemovesBaseUrl()
    {
        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('getBaseUrl'));
        $mockedHelper->expects($this->once())
            ->method('getBaseUrl')
            ->will($this->returnValue('iAmABaseURL'));
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $strippedPath = ReflectionHelper::invoke(
            $mockedHelper,
            'stripRelativePathFromFilePath',
            array( 'iAmABaseURL/shared/js/test.js' )
        );

        $this->assertEquals('shared/js/test.js', $strippedPath);

    }

    public function testStripRelativePathFromFilePathRemovesBaseUrlAndSlashes()
    {
        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('getBaseUrl'));
        $mockedHelper->expects($this->once())
            ->method('getBaseUrl')
            ->will($this->returnValue('iAmABaseURL'));
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $strippedPath = ReflectionHelper::invoke(
            $mockedHelper,
            'stripRelativePathFromFilePath',
            array( 'iAmABaseURL//shared/js/test.js' )
        );

        $this->assertEquals('shared/js/test.js', $strippedPath);
    }

    public function testStripRelativePathFromFilePathHandlesEmptyBaseUrl()
    {
        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('getBaseUrl'));
        $mockedHelper->expects($this->once())
            ->method('getBaseUrl')
            ->will($this->returnValue(''));
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $strippedPath = ReflectionHelper::invoke(
            $mockedHelper,
            'stripRelativePathFromFilePath',
            array( '/shared/js/test.js' )
        );

        $this->assertEquals('shared/js/test.js', $strippedPath);

    }

    public function testStripRelativePathFromFilePathHandlesOneSlashBaseUrl()
    {
        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('getBaseUrl'));
        $mockedHelper->expects($this->once())
            ->method('getBaseUrl')
            ->will($this->returnValue('/'));
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $strippedPath = ReflectionHelper::invoke(
            $mockedHelper,
            'stripRelativePathFromFilePath',
            array( '/shared/js/test.js' )
        );

        $this->assertEquals('shared/js/test.js', $strippedPath);
    }

    public function testStripRelativePathFromFilePathHandlesSliceBasedBaseUrlWithTrailingSlash()
    {
        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('getBaseUrl'));
        $mockedHelper->expects($this->once())
            ->method('getBaseUrl')
            ->will($this->returnValue('/s/rawr/'));
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $strippedPath = ReflectionHelper::invoke(
            $mockedHelper,
            'stripRelativePathFromFilePath',
            array( '/s/rawr///shared/js/test.js' )
        );

        $this->assertEquals('shared/js/test.js', $strippedPath);
    }

    public function testStripRelativePathFromFilePathHandlesSliceBasedBaseUrlWithoutTrailingSlash()
    {
        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('getBaseUrl'));

        $mockedHelper->expects($this->once())
            ->method('getBaseUrl')
            ->will($this->returnValue('/s/rawr'));

        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $strippedPath = ReflectionHelper::invoke(
            $mockedHelper,
            'stripRelativePathFromFilePath',
            array( '/s/rawr/shared/js/test.js' )
        );

        $this->assertEquals('shared/js/test.js', $strippedPath);
    }

    public function testStripRelativePathFromFilePathPreservesCleanPath()
    {
        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('getBaseUrl'));

        $mockedHelper->expects($this->once())
            ->method('getBaseUrl')
            ->will($this->returnValue('/s/rawr'));

        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $strippedPath = ReflectionHelper::invoke(
            $mockedHelper,
            'stripRelativePathFromFilePath',
            array( 'shared/js/test.js' )
        );

        $this->assertEquals('shared/js/test.js', $strippedPath);
    }


    // prependWebRelativeRootToFilePath tests

    public function testPrependWebRelativeRootToFilePathSkipsIfAlreadyUsingLocalCdnPath()
    {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => false,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => true,
        );
        $this->setUpMockConfig($config);

        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('getBaseUrl'));

        $mockedHelper->expects($this->once())
            ->method('getBaseUrl')
            ->will($this->returnValue('/s/rawr'));

        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $strippedPath = ReflectionHelper::invoke(
            $mockedHelper,
            'prependWebRelativeRootToFilePath',
            array( 'shared/js/test.js' )
        );

        $this->assertEquals('/s/rawr/shared/js/test.js', $strippedPath);
    }

    public function testPrependWebRelativeRootToFilePathSkipsIfAlreadyUsingProductionCdnPath()
    {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => false,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => true,
        );
        $this->setUpMockConfig($config);

        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('getBaseUrl'));

        $mockedHelper->expects($this->once())
            ->method('getBaseUrl')
            ->will($this->returnValue('/s/rawr'));

        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $strippedPath = ReflectionHelper::invoke(
            $mockedHelper,
            'prependWebRelativeRootToFilePath',
            array( $testCdn . '/' . $this->sharedPath . '/js/test.js' )
        );

        $this->assertEquals($testCdn . '/' . $this->sharedPath . '/js/test.js', $strippedPath);
    }

    public function testPrependWebRelativeRootToFilePathPrependsSlashWithEmptyBaseUrl()
    {
        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('getBaseUrl'));

        $mockedHelper->expects($this->once())
            ->method('getBaseUrl')
            ->will($this->returnValue(''));

        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $strippedPath = ReflectionHelper::invoke(
            $mockedHelper,
            'prependWebRelativeRootToFilePath',
            array( 'js/test.js' )
        );

        $this->assertEquals('/js/test.js', $strippedPath);

    }

    public function testPrependWebRelativeRootToFilePathPrependsOneSlashWithOneSlashBaseUrl()
    {
        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('getBaseUrl'));

        $mockedHelper->expects($this->once())
            ->method('getBaseUrl')
            ->will($this->returnValue('/'));

        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $strippedPath = ReflectionHelper::invoke(
            $mockedHelper,
            'prependWebRelativeRootToFilePath',
            array( 'js/test.js' )
        );

        $this->assertEquals('/js/test.js', $strippedPath);

    }

    public function testPrependWebRelativeRootToFilePathPrependsBaseUrl()
    {
        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('getBaseUrl'));

        $mockedHelper->expects($this->once())
            ->method('getBaseUrl')
            ->will($this->returnValue('/s/rawr'));

        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $strippedPath = ReflectionHelper::invoke(
            $mockedHelper,
            'prependWebRelativeRootToFilePath',
            array( 'js/test.js' )
        );

        $this->assertEquals('/s/rawr/js/test.js', $strippedPath);
    }



    // getScriptsToLoad tests

    public function testGetScriptsToLoadCallsStripRelativePathFromFilePath()
    {
        $mockedHelper = $this->getMock(
            'EMRCore\Zend\Mvc\View\Helper\ScriptFile',
            array(
                'stripRelativePathFromFilePath',
            )
        );

        $mockedHelper->expects($this->once())
            ->method('stripRelativePathFromFilePath')
            ->with($this->equalTo('js/test.js'));

        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

//        $mockedFileHandler = $this->getMock('EMRCore\JsPackager\FileHandler', array('is_file', 'fclose'));
//        $mockedFileHandler->expects($this->any())
//            ->method('is_file')
//            ->will($this->returnValue(true));
//        $mockedFileHandler->expects($this->any())
//            ->method('fclose')
//            ->will($this->returnValue(true));
//        $mockedHelper->setFileHandler( $mockedFileHandler );

        ReflectionHelper::invoke(
            $mockedHelper,
            'getScriptsToLoad',
            array( 'js/test.js' )
        );
    }

    public function testGetScriptsToLoadCallsPrependWebRelativeRootToFilePath()
    {
        $mockedHelper = $this->getMock(
            'EMRCore\Zend\Mvc\View\Helper\ScriptFile',
            array(
                'prependWebRelativeRootToFilePath',
            )
        );

        $mockedHelper->expects($this->once())
            ->method('prependWebRelativeRootToFilePath')
            ->with($this->equalTo('js/test.js'));

        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $mockedFileHandler = $this->getMock('EMRCore\JsPackager\FileHandler', array('is_file', 'fclose'));
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));
        $mockedHelper->setFileHandler( $mockedFileHandler );

        ReflectionHelper::invoke(
            $mockedHelper,
            'getScriptsToLoad',
            array( 'js/test.js' )
        );
    }


    public function testGetScriptsToLoadCallsBuildDependentScriptObjectIfUnique()
    {
        $mockedHelper = $this->getMock(
            'EMRCore\Zend\Mvc\View\Helper\ScriptFile',
            array(
                'buildDependentScriptObjectIfUnique',
            )
        );

        $mockedHelper->expects($this->once())
            ->method('buildDependentScriptObjectIfUnique')
            ->with($this->equalTo('/js/test.js'));

        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $mockedFileHandler = $this->getMock('EMRCore\JsPackager\FileHandler', array('is_file', 'fclose'));
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));
        $mockedHelper->setFileHandler( $mockedFileHandler );

        ReflectionHelper::invoke(
            $mockedHelper,
            'getScriptsToLoad',
            array( 'js/test.js' )
        );
    }

    /**
     * parseManifestFile tests
     */

    public function testParseManifestReturnsFalseIfFileDoesNotExist()
    {
        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('mockPlaceholder'));
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

//        $helper = new ScriptFile();
        $serviceLocatorMock = $this->getMock('Zend\ServiceManager\ServiceLocatorInterface');
//        $helper->setServiceLocator( $serviceLocatorMock );

        $mockedFileHandler = $this->getMock('EMRCore\JsPackager\FileHandler', array('is_file'));

        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(false));


//        $serviceLocatorMock->expects($this->once())
//            ->method('get')
//            ->with('EMRCore\JsPackager\FileHandler')
//            ->will($this->returnValue( $mockedFileHandler ));

        $mockedHelper->setFileHandler( $mockedFileHandler );

        $manifestContents = ReflectionHelper::invoke( $mockedHelper, 'parseManifestFile', array( 'mocked' ) );

        $this->assertFalse( $manifestContents );
    }

    public function testParseManifestFileThrowsUponUnexpectedSyntax()
    {
        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('mockPlaceholder'));
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $lineA = 'some/path/to/a/stylesheet.css';
        $lineB = 'eggbert';
        $lineC = '/i/love/receiving/packages.js';

        $mockedFileHandler = $this->getMock('EMRCore\JsPackager\FileHandler', array('is_file', 'fopen', 'fgets', 'fclose'));

        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls($lineA, $lineB, $lineC, false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        $mockedHelper->setFileHandler( $mockedFileHandler );


        try {
            $manifestContents = ReflectionHelper::invoke( $mockedHelper, 'parseManifestFile', array( 'mocked' ) );
            $this->fail('Parse manifest should throw a malformed manifest parsing exception');
        } catch (ParsingException $e) {
            $this->assertEquals(
                'Malformed manifest entry encountered',
                $e->getMessage(),
                'Exception should contain a message describing a malformed manifest entry'
            );

            $this->assertEquals(
                'eggbert',
                $e->getErrors(),
                'Exception should contain the malformed line'
            );

            $this->assertEquals(
                ParsingException::ERROR_CODE,
                $e->getCode(),
                'Exception should contain proper error code'
            );
        }

    }

    public function testParseManifestFileReturnsArrayContainingStylesheetsAndPackages()
    {
        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('mockPlaceholder'));
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $lineA = 'some/path/to/a/stylesheet.css';

        $mockedFileHandler = $this->getMock('EMRCore\JsPackager\FileHandler', array('is_file', 'fopen', 'fgets', 'fclose'));

        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls($lineA, false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        $mockedHelper->setFileHandler( $mockedFileHandler );

        $manifestContents = ReflectionHelper::invoke( $mockedHelper, 'parseManifestFile', array( 'mocked' ) );

        $this->assertArrayHasKey('stylesheets', $manifestContents);
        $this->assertArrayHasKey('packages', $manifestContents);
    }

    /**
     * @depends testParseManifestFileReturnsArrayContainingStylesheetsAndPackages
     */
    public function testParseManifestFileParsesFilepaths()
    {
        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('mockPlaceholder'));
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $lineA = 'some/path/to/a/stylesheet.css';
        $lineB = 'some/path/to/a/package.js';
        $lineC = 'some/where/over/a/stylesheet.rainbow.css';
        $lineD = '/i/love/receiving/packages.js';

        $mockedFileHandler = $this->getMock('EMRCore\JsPackager\FileHandler', array('is_file', 'fopen', 'fgets', 'fclose'));

        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls($lineA, $lineB, $lineC, $lineD, false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        $mockedHelper->setFileHandler( $mockedFileHandler );


        $manifestContents = ReflectionHelper::invoke( $mockedHelper, 'parseManifestFile', array( 'mocked' ) );

        $stylesheets = $manifestContents['stylesheets'];
        $packages = $manifestContents['packages'];

        $this->assertContains( $lineA, $stylesheets );
        $this->assertContains( $lineC, $stylesheets );
        $this->assertContains( $lineB, $packages );
        $this->assertContains( $lineD, $packages );
    }




    /**
     * lookForCompiledFile tests
     */

    /**
     * Get a simple mocked compiler for (any) number of calls to 'getCompiledFilename' and 'getManifestFilename'
     * for the given value with the given responses.
     *
     * @param $sourceFilename
     * @param $compiledFilename
     * @param $manifestFilename
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getSimpleMockedCompiler($sourceFilename, $compiledFilename, $manifestFilename)
    {
        $mockedCompiler = $this->getMock('EMRCore\JsPackager\Compiler', array('getCompiledFilename', 'getManifestFilename'));
        $mockedCompiler->expects($this->any())
            ->method('getCompiledFilename')
            ->with($this->equalTo($sourceFilename))
            ->will($this->returnValue($compiledFilename));
        $mockedCompiler->expects($this->any())
            ->method('getManifestFilename')
            ->with($this->equalTo($sourceFilename))
            ->will($this->returnValue($manifestFilename));

        return $mockedCompiler;
    }

    public function testLookForCompiledFileCallsGetCompiledFilenameAndGetManifestFilename()
    {
        $mockedHelper = $this->getMock(
            'EMRCore\Zend\Mvc\View\Helper\ScriptFile',
            array(
                'parseManifestFile'
            )
        );
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);
        $mockedHelper->expects($this->once())
            ->method('parseManifestFile')
            ->will($this->returnValue(array()));

        $mockedCompiler = $this->getSimpleMockedCompiler('js/test.js', 'js/test.compiled.js', 'js/test.js.manifest' );
        $mockedHelper->setCompiler( $mockedCompiler );

        $mockedFileHandler = $this->getMock('EMRCore\JsPackager\FileHandler', array('is_file', 'fclose'));
        $mockedHelper->setFileHandler( $mockedFileHandler );
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));

        ReflectionHelper::invoke(
            $mockedHelper,
            'lookForCompiledFile',
            array( 'js/test.js' )
        );
    }

    /**
     * @depends testLookForCompiledFileCallsGetCompiledFilenameAndGetManifestFilename
     */
    public function testLookForCompiledFileThrowsIfMissingCompiledScript()
    {
        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('mockPlaceholder'));
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $mockedFileHandler = $this->getMock('EMRCore\JsPackager\FileHandler', array('is_file', 'fopen', 'fgets', 'fclose'));
        $mockedHelper->setFileHandler( $mockedFileHandler );
        $mockedFileHandler->expects($this->at(0))
            ->method('is_file')
            ->will($this->returnValue(false));

        try
        {
            ReflectionHelper::invoke( $mockedHelper, 'lookForCompiledFile', array( 'some.file.js' ) );
            $this->fail('Should throw a missing file exception');
        } catch ( MissingFileException $e )
        {
            $this->assertEquals(
                'some.file.compiled.js',
                $e->getMissingFilePath(),
                'Exception should contain failed file\'s path information'
            );

            $this->assertEquals(
                MissingFileException::ERROR_CODE,
                $e->getCode(),
                'Exception should contain proper error code'
            );
        }

    }

    /**
     * @depends testLookForCompiledFileCallsGetCompiledFilenameAndGetManifestFilename
     */
    public function testLookForCompiledFileLooksForConventionalFilename()
    {
        $mockedHelper = $this->getMock(
            'EMRCore\Zend\Mvc\View\Helper\ScriptFile',
            array(
                'parseManifestFile'
            )
        );
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);
        $mockedHelper->expects($this->once())
            ->method('parseManifestFile')
            ->will($this->returnValue(array()));

        $mockedCompiler = $this->getSimpleMockedCompiler('some.file.js', 'some.file.compiled.js', 'some.file.js.manifest' );
        $mockedHelper->setCompiler( $mockedCompiler );

        $mockedFileHandler = $this->getMock('EMRCore\JsPackager\FileHandler', array('is_file', 'fopen', 'fgets', 'fclose'));
        $mockedHelper->setFileHandler( $mockedFileHandler );
        $mockedFileHandler->expects($this->at(0))
            ->method('is_file')
            ->with('some.file.compiled.js')
            ->will($this->returnValue(true));

        ReflectionHelper::invoke( $mockedHelper, 'lookForCompiledFile', array( 'some.file.js' ) );
    }

    /**
     * @depends testLookForCompiledFileCallsGetCompiledFilenameAndGetManifestFilename
     */
    public function testLookForCompiledFileCallsParseManifestFile()
    {
        $mockedHelper = $this->getMock(
            'EMRCore\Zend\Mvc\View\Helper\ScriptFile',
            array(
                'parseManifestFile',
            )
        );

        $mockedHelper->expects($this->once())
            ->method('parseManifestFile')
            ->with($this->equalTo('some.file.js.manifest'))
            ->will($this->returnValue(array()));

        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $mockedFileHandler = $this->getMock('EMRCore\JsPackager\FileHandler', array('is_file', 'fopen', 'fgets', 'fclose'));
        $mockedHelper->setFileHandler( $mockedFileHandler );
        $mockedFileHandler->expects($this->at(0))
            ->method('is_file')
            ->with('some.file.compiled.js')
            ->will($this->returnValue(true));

        ReflectionHelper::invoke( $mockedHelper, 'lookForCompiledFile', array( 'some.file.js' ) );
    }


    public function testLookForCompiledFileReturnsArrayContainingCompiledFile()
    {
        $mockedHelper = $this->getMock(
            'EMRCore\Zend\Mvc\View\Helper\ScriptFile',
            array(
                'rawr',
            )
        );
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $mockedFileHandler = $this->getMock('EMRCore\JsPackager\FileHandler', array('is_file', 'fopen', 'fgets', 'fclose'));
        $mockedHelper->setFileHandler( $mockedFileHandler );
        $mockedFileHandler->expects($this->at(0))
            ->method('is_file')
            ->with('some.file.compiled.js')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->at(1))
            ->method('is_file')
            ->with('some.file.js.manifest')
            ->will($this->returnValue(false));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls('something.in.a.package.js', false));

        $files = ReflectionHelper::invoke( $mockedHelper, 'lookForCompiledFile', array( 'some.file.js' ) );

        $this->assertCount(1, $files);
        $this->assertContains('some.file.compiled.js', $files);
    }

    /**
     * @depends testLookForCompiledFileCallsParseManifestFile
     */
    public function testLookForCompiledFileReturnsArrayContainingAllFilesFromManifest()
    {
        $mockedHelper = $this->getMock(
            'EMRCore\Zend\Mvc\View\Helper\ScriptFile',
            array(
                'rawr',
            )
        );
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $mockedFileHandler = $this->getMock('EMRCore\JsPackager\FileHandler', array('is_file', 'fopen', 'fgets', 'fclose'));
        $mockedHelper->setFileHandler( $mockedFileHandler );
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls('some.package.js', 'some.style.css', 'another.package.js', false));

        $files = ReflectionHelper::invoke( $mockedHelper, 'lookForCompiledFile', array( 'some.file.js' ) );

        $this->assertCount(4, $files);
        $this->assertContains('some.package.js', $files);
        $this->assertContains('some.style.css', $files);
        $this->assertContains('another.package.js', $files);
        $this->assertEquals('some.file.compiled.js', $files[3]);
    }

    /**
     * @depends testLookForCompiledFileCallsParseManifestFile
     */
    public function testLookForCompiledFileAssumesEmptyManifestIfMissingManifest()
    {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => true,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);

        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('mockPlaceholder'));
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);


        $mockedFileHandler = $this->getMock('EMRCore\JsPackager\FileHandler', array('is_file', 'fopen', 'fgets', 'fclose'));

        $mockedFileHandler->expects($this->at(0))
            ->method('is_file')
            ->with('some.file.compiled.js')
            ->will($this->returnValue(true));

        $mockedFileHandler->expects($this->at(1))
            ->method('is_file')
            ->with('some.file.js.manifest')
            ->will($this->returnValue(false));

        $mockedHelper->setFileHandler( $mockedFileHandler );

        $results = ReflectionHelper::invoke( $mockedHelper, 'lookForCompiledFile', array( 'some.file.js' ) );

        $this->assertCount( 1, $results );
    }



    // getScriptsToLoad tests

    public function testGetScriptsToLoadCallsPassToDependencyTreeIfUseCompiledFilesIsFalse()
    {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => false,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);

        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('passToDependencyTree', 'getFileSystemPath'));

        $mockedHelper->expects($this->any())
            ->method('getFileSystemPath')
            ->will($this->returnValue(''));

        $mockedHelper->expects($this->once())
            ->method('passToDependencyTree')
            ->with($this->equalTo('shared/js/test.js'))
            ->will($this->returnValue(array('shared/js/test.js')));


        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $scriptObjects = ReflectionHelper::invoke( $mockedHelper, 'getScriptsToLoad', array( '/shared/js/test.js' ) );

        return $scriptObjects;
    }


    /**
     * @depends testGetScriptsToLoadCallsPassToDependencyTreeIfUseCompiledFilesIsFalse
     */
    public function testGetScriptsToLoadReturnsArrayOfScriptObjectsRepresentingDepTreeFiles($scriptObjects)
    {
        $this->assertCount(1, $scriptObjects);
        $this->assertObjectHasAttribute('type', $scriptObjects[0] );
        $this->assertEquals('/shared/js/test.js', $scriptObjects[0]->attributes['src']);
    }

    public function testGetScriptsToLoadCallsLookForCompiledFileIfUseCompiledFilesIsTrue() {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => true,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);

        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('lookForCompiledFile', 'getFileSystemPath'));

        $mockedHelper->expects($this->any())
            ->method('getFileSystemPath')
            ->will($this->returnValue(''));

        $mockedHelper->expects($this->once())
            ->method('lookForCompiledFile')
            ->with($this->equalTo('shared/js/test.js'))
            ->will($this->returnValue(array( 'shared/js/test.js' ) ));

        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $scriptObjects = ReflectionHelper::invoke( $mockedHelper, 'getScriptsToLoad', array( '/shared/js/test.js' ) );

        return $scriptObjects;
    }

    /**
     * @depends testGetScriptsToLoadCallsLookForCompiledFileIfUseCompiledFilesIsTrue
     */
    public function testGetScriptsToLoadReturnsArrayOfScriptObjectsRepresentingCompiledAndManifestFiles($scriptObjects)
    {
        $this->assertCount(1, $scriptObjects);
        $this->assertObjectHasAttribute('type', $scriptObjects[0] );
        $this->assertEquals('/shared/js/test.js', $scriptObjects[0]->attributes['src']);
    }


    public function testGetScriptsToLoadCallsPassToDependencyTreeIfLookingForCompiledFileFailedAndFallingBack() {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => true,
            'fallback_if_missing_compiled_script' => true,
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);

        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('passToDependencyTree', 'getFileSystemPath'));

        $mockedHelper->expects($this->any())
            ->method('getFileSystemPath')
            ->will($this->returnValue(''));

        $mockedHelper->expects($this->once())
            ->method('passToDependencyTree')
            ->with($this->equalTo($this->sharedPath . '/' . 'js/test.js'))
            ->will($this->returnValue( array( 'shared/js/test.js' ) ) );
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $mockedFileHandler = $this->getMock('EMRCore\JsPackager\FileHandler', array('is_file', 'fopen', 'fgets', 'fclose'));
        $mockedFileHandler->expects($this->at(0))
            ->method('is_file')
            ->with( $this->sharedPath . '/' . 'js/test.compiled.js')
            ->will($this->returnValue(false));
        $mockedHelper->setFileHandler( $mockedFileHandler );

        ReflectionHelper::invoke( $mockedHelper, 'getScriptsToLoad', array( $this->sharedPath . '/' . 'js/test.js' ) );
    }


    public function testGetScriptsToLoadThrowsExceptionIfLookingForCompiledFileFailedAndNotFallingBack() {

        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => true,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);

        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('getFileSystemPath'));
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $mockedHelper->expects($this->any())
            ->method('getFileSystemPath')
            ->will($this->returnValue(''));

        $mockedFileHandler = $this->getMock('EMRCore\JsPackager\FileHandler', array('is_file', 'fopen', 'fgets', 'fclose'));

        $mockedFileHandler->expects($this->at(0))
            ->method('is_file')
            ->with( $this->sharedPath . '/' . 'js/test.compiled.js')
            ->will($this->returnValue(false));

        $mockedHelper->setFileHandler( $mockedFileHandler );

        try {
            $mockedHelper->appendSharedFile('js/test.js');
            $this->fail('An exception should have been thrown');
        } catch ( MissingFileException $e ) {

            $this->assertEquals(
                'shared/js/test.compiled.js is not a valid file!',
                $e->getMessage(),
                'Exception should contain failed file\'s path information'
            );

            $this->assertEquals(
                MissingFileException::ERROR_CODE,
                $e->getCode(),
                'Exception should contain proper error code'
            );

        }
    }

    public function testAppendPassesFileToGetScriptsToLoad() {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => false,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);

        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('getScriptsToLoad'));

        $mockedHelper->expects($this->once())
            ->method('getScriptsToLoad')
            ->with($this->equalTo('/shared/js/test.js'))
            ->will($this->returnValue(
                array(
                    ReflectionHelper::invoke( $this->helper, 'buildDependentScriptObjectIfUnique', array( '/shared/js/test.js' ) )
                )
            ));

        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $mockedHelper->appendSharedFile('js/test.js');
        $this->assertEquals('<script type="text/javascript" src="/shared/js/test.js"></script>',
            $mockedHelper->toString());
    }

    public function testPrependPassesFileToGetScriptsToLoad() {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => false,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);

        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('getScriptsToLoad'));

        $mockedHelper->expects($this->once())
            ->method('getScriptsToLoad')
            ->with($this->equalTo('/shared/js/test.js'))
            ->will($this->returnValue(
                array(
                    ReflectionHelper::invoke( $this->helper, 'buildDependentScriptObjectIfUnique', array( '/shared/js/test.js' ) )
                )
            ));

        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $mockedHelper->prependSharedFile('js/test.js');
        $this->assertEquals('<script type="text/javascript" src="/shared/js/test.js"></script>',
            $mockedHelper->toString());
    }




    /**
     * Tests for use_cdn_for_shared config flag
     */

    public function testAppendLoadsSharedFileViaLocalSharedWhenUseCdnForSharedIsFalse() {
        /**
         * Mock config.
         */
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => false,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );

        $this->setUpMockConfig($config);


        $this->helper->appendSharedFile('js/test.js');
        $this->assertEquals('<script type="text/javascript" src="/shared/js/test.js"></script>',
            $this->helper->toString());
    }

    public function testAppendLoadsSharedFileViaCdnWhenUseCdnForSharedIsTrue()
    {
        /**
         * Mock config.
         */
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => false,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => true,
        );

        $this->setUpMockConfig($config);


        $this->helper->appendSharedFile('js/test.js');
        $this->assertEquals('<script type="text/javascript" src="' . $testCdn . '/' . $this->sharedPath . '/'
        . 'js/test.js"></script>' , $this->helper->toString());
    }

    public function testPrependLoadsSharedFileViaLocalSharedWhenUseCdnForSharedIsFalse() {
        /**
         * Mock config.
         */
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => false,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );

        $this->setUpMockConfig($config);


        $this->helper->prependSharedFile('js/test.js');
        $this->assertEquals('<script type="text/javascript" src="/shared/js/test.js"></script>',
            $this->helper->toString());
    }

    public function testPrependLoadsSharedFileViaCdnWhenUseCdnForSharedIsTrue()
    {
        /**
         * Mock config.
         */
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => false,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => true,
        );

        $this->setUpMockConfig($config);


        $this->helper->prependSharedFile('js/test.js');
        $this->assertEquals('<script type="text/javascript" src="' . $testCdn . '/' . $this->sharedPath . '/'
        . 'js/test.js"></script>' , $this->helper->toString());
    }


    /**
     * use_compiled_scripts config flag
     */

    public function testAppendLoadsNormalFileWhenUseCompiledScriptsIsFalse()
    {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => false,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);

        $this->helper->appendSharedFile('js/test.js');
        $this->assertEquals('<script type="text/javascript" src="/' . $this->sharedPath . '/'
        . 'js/test.js"></script>' , $this->helper->toString());
    }

    public function testAppendSharedFileCallsLookForCompiledFileWhenUseCompiledScriptsIsTrue()
    {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => true,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);

        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('lookForCompiledFile', 'getFileSystemPath'));

        $mockedHelper->expects($this->any())
            ->method('getFileSystemPath')
            ->will($this->returnValue(''));

        $mockedHelper->expects($this->once())
            ->method('lookForCompiledFile')
            ->with($this->equalTo( $this->sharedPath . '/' . 'js/test.js') )
            ->will($this->returnValue(array( '/' . $this->sharedPath . '/' . 'js/test.js' )));

        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $mockedHelper->appendSharedFile('js/test.js');

    }

    public function testPrependLoadsNormalFileWhenUseCompiledScriptsIsFalse()
    {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => false,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);

        $this->helper->prependSharedFile('js/test.js');
        $this->assertEquals('<script type="text/javascript" src="/' . $this->sharedPath . '/'
        . 'js/test.js"></script>' , $this->helper->toString());
    }

    public function testPrependSharedFileCallsLookForCompiledFileWhenUseCompiledScriptsIsTrue()
    {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => true,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);

        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('lookForCompiledFile', 'getFileSystemPath'));

        $mockedHelper->expects($this->any())
            ->method('getFileSystemPath')
            ->will($this->returnValue(''));

        $mockedHelper->expects($this->once())
            ->method('lookForCompiledFile')
            ->with($this->equalTo( $this->sharedPath . '/' . 'js/test.js') )
            ->will($this->returnValue(array( '/' . $this->sharedPath . '/' . 'js/test.js' )));

        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $mockedHelper->prependSharedFile('js/test.js');

    }

    public function testAppendLoadsCompiledFileAndManifestWhenUseCompiledScriptsIsTrue()
    {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => true,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);

        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('getStyleFileReference'));
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $styleFile = $this->getMock('stdClass', array('appendStylesheet'));
        $styleFile->expects($this->once())->method('appendStylesheet')
            ->with('/some.stylesheet.css')
            ->will($this->returnValue(null));
        $mockedHelper->expects($this->any())
            ->method('getStyleFileReference')
            ->will($this->returnValue($styleFile));

        $mockedFileHandler = $this->getMock('EMRCore\JsPackager\FileHandler', array('is_file', 'fopen', 'fgets', 'fclose'));
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls('some.package.js', 'some.stylesheet.css', 'package.two.js', false));
        $mockedHelper->setFileHandler( $mockedFileHandler );

        $mockedHelper->appendSharedFile('js/test.js');

        $this->assertEquals('<script type="text/javascript" src="/some.package.js"></script>
<script type="text/javascript" src="/package.two.js"></script>
<script type="text/javascript" src="/shared/js/test.compiled.js"></script>' , $mockedHelper->toString());

    }

    public function testPrependLoadsCompiledFileAndManifestWhenUseCompiledScriptsIsTrue()
    {
        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => true,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);

        $mockedHelper = $this->getMock('EMRCore\Zend\Mvc\View\Helper\ScriptFile', array('getStyleFileReference'));
        $mockedHelper->setServiceLocator($this->helperServiceLocatorMock);

        $styleFile = $this->getMock('stdClass', array('prependStylesheet'));
        $styleFile->expects($this->once())->method('prependStylesheet')
            ->with('/some.stylesheet.css')
            ->will($this->returnValue(null));
        $mockedHelper->expects($this->any())
            ->method('getStyleFileReference')
            ->will($this->returnValue($styleFile));

        $mockedFileHandler = $this->getMock('EMRCore\JsPackager\FileHandler', array('is_file', 'fopen', 'fgets', 'fclose'));
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls('some.package.js', 'some.stylesheet.css', 'package.two.js', false));
        $mockedHelper->setFileHandler( $mockedFileHandler );

        $mockedHelper->prependSharedFile('js/test.js');
        $this->assertEquals('<script type="text/javascript" src="/some.package.js"></script>
<script type="text/javascript" src="/package.two.js"></script>
<script type="text/javascript" src="/shared/js/test.compiled.js"></script>' , $mockedHelper->toString());
    }


    // Test with fixtures
    public function testAppendLoadsRealCompiledFileAndManifestWhenUseCompiledFilesIsTrue()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_package';
        $filePath = $basePath . '/main.js';

        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => true,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);

        $this->helper->appendFile( $filePath );


        $scripts = ReflectionHelper::invoke(
            $this->helper,
            'getScriptsToLoad',
            array( $filePath )
        );

        $expectedContents = <<<STUFF
<script type="text/javascript" src="/$basePath/dep_3.compiled.js"></script>
<script type="text/javascript" src="/$basePath/main.compiled.js"></script>
STUFF;
        $this->assertEquals($expectedContents, $this->helper->toString());
    }

    public function testAppendLoadsRealComplicatedCompiledFileAndManifestWhenUseCompiledFilesIsTrue()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_packages';
        $filePath = $basePath . '/main.js';

        $testCdn = 'https://cdn.test.com';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => true,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);

        $this->helper->appendFile( $filePath );


        $scripts = ReflectionHelper::invoke(
            $this->helper,
            'getScriptsToLoad',
            array( $filePath )
        );

        $expectedContents = <<<STUFF
<script type="text/javascript" src="/$basePath/dep_3.compiled.js"></script>
<script type="text/javascript" src="/$basePath/dep_2.compiled.js"></script>
<script type="text/javascript" src="/$basePath/main.compiled.js"></script>
STUFF;
        $this->assertEquals($expectedContents, $this->helper->toString());
    }
}
