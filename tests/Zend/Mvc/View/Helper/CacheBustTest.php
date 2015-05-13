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
use JsPackager\Zend\Mvc\View\Helper\StyleFile;
use Zend\View;
use JsPackager\Helpers\Reflection as ReflectionHelper;


/**
 * Test class for Cache Bust features across ScriptFile and StyleFile.
 *
 * @category   Zend
 * @package    Zend_View
 * @subpackage UnitTests
 * @group      Zend_View
 * @group      Zend_View_Helper
 * @group      JsPackager
 */
class CacheBustTest extends \PHPUnit_Framework_TestCase
{
    // Tests are run from EMRCore root
    const fixturesBasePath = 'vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/';

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
    private $testCdn;

    /**
     * @var string
     */
    public $basePath;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $pluginServiceLocatorMock;
    private $helperServiceLocatorMock;

    public function setUp()
    {
        $this->basePath = __DIR__ . '/_files/modules';
        $this->helper = new ScriptFile();

        $this->helper = $this->getMock('JsPackager\Zend\Mvc\View\Helper\ScriptFile', array('getFileSystemPath'));

        $this->helper->expects($this->any())
            ->method('getFileSystemPath')
            ->will($this->returnValue(''));

        /**
         * Mock config.
         */
        $testCdn = 'https://cdn.tests.com';
        $this->testCdn = 'https://cdn.tests.com';
        $this->sharedPath = 'shared';
        $config = array(
            'cdn' => array(
                'url' => $testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => false,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,

            'use_cache_busting' => true,
            'cache_busting' => array(
                /**
                 * Cache Bust Strategy
                 *
                 * Valid values are:
                 *      'constant'   - Use a file's modified time as the query value
                 *      'mtime'      - Use cache_busting.constant_value as the query value
                 */
                'strategy' => 'constant',
                /**
                 * Constant value to use as the cache bust value
                 */
                'constant_value' => 1,
                /**
                 * The string used as the default query key in URLs.
                 */
                'key_string' => '_cachebust',
            ),

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

    public function testWorksFineOff()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_package';
        $filePath = $basePath . '/main.js';

        $config = array(
            'cdn' => array(
                'url' => $this->testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => true,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,

            'use_cache_busting' => false,
        );
        $this->setUpMockConfig($config);
        $this->helper->appendFile( $filePath );

        $expectedContents = <<<STUFF
<script type="text/javascript" src="/$basePath/dep_3.compiled.js"></script>
<script type="text/javascript" src="/$basePath/main.compiled.js"></script>
STUFF;
        $this->assertEquals($expectedContents, $this->helper->toString());
    }

    public function testWorksFineOnWithDefaults()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_package';
        $filePath = $basePath . '/main.js';

        $config = array(
            'cdn' => array(
                'url' => $this->testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => true,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,

            'use_cache_busting' => true,
        );
        $this->setUpMockConfig($config);
        $this->helper->appendFile( $filePath );

        $expectedContents = <<<STUFF
<script type="text/javascript" src="/$basePath/dep_3.compiled.js?_cachebust=123"></script>
<script type="text/javascript" src="/$basePath/main.compiled.js?_cachebust=123"></script>
STUFF;
        $this->assertEquals($expectedContents, $this->helper->toString());
    }


    public function testModifiedTimeReadsModifiedTime()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_package';
        $filePath = $basePath . '/main.js';

        $config = array(
            'cdn' => array(
                'url' => $this->testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => true,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,

            'use_cache_busting' => true,
            'cache_busting' => array(
                /**
                 * Cache Bust Strategy
                 *
                 * Valid values are:
                 *      'constant'   - Use a file's modified time as the query value
                 *      'mtime'      - Use cache_busting.constant_value as the query value
                 */
                'strategy' => 'mtime',
                /**
                 * Constant value to use as the cache bust value
                 */
                'constant_value' => 1,
                /**
                 * The string used as the default query key in URLs.
                 */
                'key_string' => '_cachebust_keytest',
            ),
        );
        $this->setUpMockConfig($config);
        $this->helper->appendFile( $filePath );

        $mainMTime = filemtime( $basePath . '/dep_3.compiled.js' );
        $dep3MTime = filemtime( $basePath . '/main.compiled.js' );

        $expectedContents = <<<STUFF
<script type="text/javascript" src="/$basePath/dep_3.compiled.js?_cachebust_keytest=$dep3MTime"></script>
<script type="text/javascript" src="/$basePath/main.compiled.js?_cachebust_keytest=$mainMTime"></script>
STUFF;
        $this->assertEquals($expectedContents, $this->helper->toString());
    }

    public function testConstantStrategy()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_package';
        $filePath = $basePath . '/main.js';

        $config = array(
            'cdn' => array(
                'url' => $this->testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => true,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,

            'use_cache_busting' => true,
            'cache_busting' => array(
                /**
                 * Cache Bust Strategy
                 *
                 * Valid values are:
                 *      'constant'   - Use a file's modified time as the query value
                 *      'mtime'      - Use cache_busting.constant_value as the query value
                 */
                'strategy' => 'constant',
                /**
                 * Constant value to use as the cache bust value
                 */
                'constant_value' => 668899,
                /**
                 * The string used as the default query key in URLs.
                 */
                'key_string' => '_astronomy',
            ),
        );
        $this->setUpMockConfig($config);
        $this->helper->appendFile( $filePath );

        $expectedContents = <<<STUFF
<script type="text/javascript" src="/$basePath/dep_3.compiled.js?_astronomy=668899"></script>
<script type="text/javascript" src="/$basePath/main.compiled.js?_astronomy=668899"></script>
STUFF;
        $this->assertEquals($expectedContents, $this->helper->toString());
    }

    public function testDoesNotStompQueryStringOrHash()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_package';
        $filePath = $basePath . '/main.js?_test=value&and=another#and=ahash&and=another';

        $config = array(
            'cdn' => array(
                'url' => $this->testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => true,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,

            'use_cache_busting' => true,
            'cache_busting' => array(
                /**
                 * Cache Bust Strategy
                 *
                 * Valid values are:
                 *      'constant'   - Use a file's modified time as the query value
                 *      'mtime'      - Use cache_busting.constant_value as the query value
                 */
                'strategy' => 'constant',
                /**
                 * Constant value to use as the cache bust value
                 */
                'constant_value' => 668899,
                /**
                 * The string used as the default query key in URLs.
                 */
                'key_string' => '_astronomy',
            ),
        );
        $this->setUpMockConfig($config);
        $this->helper->appendFile( $filePath );

        $expectedContents = <<<STUFF
<script type="text/javascript" src="/$basePath/dep_3.compiled.js?_astronomy=668899"></script>
<script type="text/javascript" src="/$basePath/main.compiled.js?_test=value&amp;and=another&amp;_astronomy=668899#and=ahash&amp;and=another"></script>
STUFF;
        $this->assertEquals($expectedContents, $this->helper->toString());

    }

    public function testDoesNotStompQueryStringVariable()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_package';
        $filePath = $basePath . '/main.js?_test=value&_astronomy=sun';

        $config = array(
            'cdn' => array(
                'url' => $this->testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => true,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,

            'use_cache_busting' => true,
            'cache_busting' => array(
                /**
                 * Cache Bust Strategy
                 *
                 * Valid values are:
                 *      'constant'   - Use a file's modified time as the query value
                 *      'mtime'      - Use cache_busting.constant_value as the query value
                 */
                'strategy' => 'constant',
                /**
                 * Constant value to use as the cache bust value
                 */
                'constant_value' => 668899,
                /**
                 * The string used as the default query key in URLs.
                 */
                'key_string' => '_astronomy',
            ),
        );
        $this->setUpMockConfig($config);
        $this->helper->appendFile( $filePath );

        $expectedContents = <<<STUFF
<script type="text/javascript" src="/$basePath/dep_3.compiled.js?_astronomy=668899"></script>
<script type="text/javascript" src="/$basePath/main.compiled.js?_test=value&amp;_astronomy=sun&amp;_astronomyz=668899"></script>
STUFF;
        $this->assertEquals($expectedContents, $this->helper->toString());

    }
    public function testDoesNotStompQueryStringVariableEdgeCase()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_package';
        $filePath = $basePath . '/main.js?_astronomy=sun&_astronomyz=moon&_astronomyzz=stars';

        $config = array(
            'cdn' => array(
                'url' => $this->testCdn,
                'cdn_shared_path' => $this->sharedPath,
            ),
            'use_compiled_scripts' => true,
            'fallback_if_missing_compiled_script' => false,
            'use_cdn_for_shared' => false,

            'use_cache_busting' => true,
            'cache_busting' => array(
                /**
                 * Cache Bust Strategy
                 *
                 * Valid values are:
                 *      'constant'   - Use a file's modified time as the query value
                 *      'mtime'      - Use cache_busting.constant_value as the query value
                 */
                'strategy' => 'constant',
                /**
                 * Constant value to use as the cache bust value
                 */
                'constant_value' => 668899,
                /**
                 * The string used as the default query key in URLs.
                 */
                'key_string' => '_astronomy',
            ),
        );
        $this->setUpMockConfig($config);
        $this->helper->appendFile( $filePath );

        $expectedContents = <<<STUFF
<script type="text/javascript" src="/$basePath/dep_3.compiled.js?_astronomy=668899"></script>
<script type="text/javascript" src="/$basePath/main.compiled.js?_astronomy=sun&amp;_astronomyz=moon&amp;_astronomyzz=stars&amp;_astronomyzzz=668899"></script>
STUFF;
        $this->assertEquals($expectedContents, $this->helper->toString());

    }



}
