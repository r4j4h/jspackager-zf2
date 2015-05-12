<?php
/** Based on HeadLinkTest, for use on WebPT EMRCore with added tests for 'shared' (cdn) css files
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_View
 */


use Zend\View\Helper\Placeholder\Registry as PlaceholderRegistry;
use JsPackager\Zend\Mvc\View\Helper\StyleFile;
use Zend\View\Helper;
use Zend\View\Renderer\PhpRenderer as View;
use Zend\View\Exception\ExceptionInterface as ViewException;

/**
 * Test class for Zend_View_Helper_StyleFile.
 *
 * @category   Zend
 * @package    Zend_View
 * @subpackage UnitTests
 * @group      Zend_View
 * @group      Zend_View_Helper
 * @group      JsPackager
 */
class StyleFileTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var StyleFile
     */
    public $helper;

    /**
     * @var string
     */
    public $basePath;

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     *
     * @return void
     */
    public function setUp()
    {
        $this->basePath = __DIR__ . '/_files/modules';
        $this->view     = new View();
        $this->helper   = new StyleFile();
        $this->helper->setView($this->view);

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


        $scriptFile = $this->getMock('stdClass', array('getRealPathFromRelativePath'));
        $scriptFile->expects($this->any())->method('getRealPathFromRelativePath')
            ->will($this->returnCallback(function($src) {
                return 'rawr/' . $src;
            }));

        $viewHelperManager = $this->getMock('stdClass', array('get'));
        $viewHelperManager->expects($this->any())->method('get')
            ->with($this->equalTo('ScriptFile'))->will($this->returnValue($scriptFile));


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

    public function testNamespaceRegisteredInPlaceholderRegistryAfterInstantiation()
    {
        $this->markTestSkipped('PHPUnit_Framework_Error_Deprecated : Placeholder view helpers should no longer use a singleton registry');
        $registry = PlaceholderRegistry::getRegistry();
        if ($registry->containerExists('Zend_View_Helper_StyleFile')) {
            $registry->deleteContainer('Zend_View_Helper_StyleFile');
        }
        $this->assertFalse($registry->containerExists('Zend_View_Helper_StyleFile'));
        $helper = new StyleFile();
        $this->assertTrue($registry->containerExists('Zend_View_Helper_StyleFile'));
    }

    public function testStyleFileReturnsObjectInstance()
    {
        $placeholder = $this->helper->__invoke();
        $this->assertTrue($placeholder instanceof StyleFile);
    }

    public function testPrependThrowsExceptionWithoutArrayArgument()
    {
        $this->setExpectedException('Zend\View\Exception\ExceptionInterface');
        $this->helper->prepend('foo');
    }

    public function testAppendThrowsExceptionWithoutArrayArgument()
    {
        $this->setExpectedException('Zend\View\Exception\ExceptionInterface');
        $this->helper->append('foo');
    }

    public function testSetThrowsExceptionWithoutArrayArgument()
    {
        $this->setExpectedException('Zend\View\Exception\ExceptionInterface');
        $this->helper->set('foo');
    }

    public function testOffsetSetThrowsExceptionWithoutArrayArgument()
    {
        $this->setExpectedException('Zend\View\Exception\ExceptionInterface');
        $this->helper->offsetSet(1, 'foo');
    }

    public function testCreatingLinkStackViaHeadScriptCreatesAppropriateOutput()
    {
        $links = array(
            'link1' => array('rel' => 'stylesheet', 'type' => 'text/css', 'href' => '/foo'),
            'link2' => array('rel' => 'stylesheet', 'type' => 'text/css', 'href' => '/bar'),
            'link3' => array('rel' => 'stylesheet', 'type' => 'text/css', 'href' => '/baz'),
        );
        $this->helper->__invoke($links['link1'])
            ->__invoke($links['link2'], 'PREPEND')
            ->__invoke($links['link3']);

        $string = $this->helper->toString();
        $lines  = substr_count($string, PHP_EOL);
        $this->assertEquals(2, $lines);
        $lines  = substr_count($string, '<link ');
        $this->assertEquals(3, $lines, $string);

        foreach ($links as $link) {
            $substr = ' href="' . $link['href'] . '"';
            $this->assertContains($substr, $string);
            $substr = ' rel="' . $link['rel'] . '"';
            $this->assertContains($substr, $string);
            $substr = ' type="' . $link['type'] . '"';
            $this->assertContains($substr, $string);
        }

        $order = array();
        foreach ($this->helper as $key => $value) {
            if (isset($value->href)) {
                $order[$key] = $value->href;
            }
        }
        $expected = array('/bar', '/foo', '/baz');
        $this->assertSame($expected, $order);
    }

    public function testCreatingLinkStackViaStyleSheetMethodsCreatesAppropriateOutput()
    {
        $links = array(
            'link1' => array('rel' => 'stylesheet', 'type' => 'text/css', 'href' => '/foo'),
            'link2' => array('rel' => 'stylesheet', 'type' => 'text/css', 'href' => '/bar'),
            'link3' => array('rel' => 'stylesheet', 'type' => 'text/css', 'href' => '/baz'),
        );
        $this->helper->appendStylesheet($links['link1']['href'])
            ->prependStylesheet($links['link2']['href'])
            ->appendStylesheet($links['link3']['href']);

        $string = $this->helper->toString();
        $lines  = substr_count($string, PHP_EOL);
        $this->assertEquals(2, $lines);
        $lines  = substr_count($string, '<link ');
        $this->assertEquals(3, $lines, $string);

        foreach ($links as $link) {
            $substr = ' href="' . $link['href'] . '"';
            $this->assertContains($substr, $string);
            $substr = ' rel="' . $link['rel'] . '"';
            $this->assertContains($substr, $string);
            $substr = ' type="' . $link['type'] . '"';
            $this->assertContains($substr, $string);
        }

        $order = array();
        foreach ($this->helper as $key => $value) {
            if (isset($value->href)) {
                $order[$key] = $value->href;
            }
        }
        $expected = array('/bar', '/foo', '/baz');
        $this->assertSame($expected, $order);
    }

    public function testCreatingLinkStackViaAlternateMethodsCreatesAppropriateOutput()
    {
        $links = array(
            'link1' => array('title' => 'stylesheet', 'type' => 'text/css', 'href' => '/foo'),
            'link2' => array('title' => 'stylesheet', 'type' => 'text/css', 'href' => '/bar'),
            'link3' => array('title' => 'stylesheet', 'type' => 'text/css', 'href' => '/baz'),
        );
        $where = 'append';
        foreach ($links as $link) {
            $method = $where . 'Alternate';
            $this->helper->$method($link['href'], $link['type'], $link['title']);
            $where = ('append' == $where) ? 'prepend' : 'append';
        }

        $string = $this->helper->toString();
        $lines  = substr_count($string, PHP_EOL);
        $this->assertEquals(2, $lines);
        $lines  = substr_count($string, '<link ');
        $this->assertEquals(3, $lines, $string);
        $lines  = substr_count($string, ' rel="alternate"');
        $this->assertEquals(3, $lines, $string);

        foreach ($links as $link) {
            $substr = ' href="' . $link['href'] . '"';
            $this->assertContains($substr, $string);
            $substr = ' title="' . $link['title'] . '"';
            $this->assertContains($substr, $string);
            $substr = ' type="' . $link['type'] . '"';
            $this->assertContains($substr, $string);
        }

        $order = array();
        foreach ($this->helper as $key => $value) {
            if (isset($value->href)) {
                $order[$key] = $value->href;
            }
        }
        $expected = array('/bar', '/foo', '/baz');
        $this->assertSame($expected, $order);
    }

    public function testOverloadingThrowsExceptionWithNoArguments()
    {
        $this->setExpectedException('Zend\View\Exception\ExceptionInterface');
        $this->helper->appendStylesheet();
    }

    public function testOverloadingShouldAllowSingleArrayArgument()
    {
        $this->helper->setStylesheet(array('href' => '/styles.css'));
        $link = $this->helper->getValue();
        $this->assertEquals('/styles.css', $link->href);
    }

    public function testOverloadingUsingSingleArrayArgumentWithInvalidValuesThrowsException()
    {
        $this->setExpectedException('Zend\View\Exception\ExceptionInterface');
        $this->helper->setStylesheet(array('bogus' => 'unused'));
    }

    public function testOverloadingOffsetSetWorks()
    {
        $this->helper->offsetSetStylesheet(100, '/styles.css');
        $items = $this->helper->getArrayCopy();
        $this->assertTrue(isset($items[100]));
        $link = $items[100];
        $this->assertEquals('/styles.css', $link->href);
    }

    public function testOverloadingThrowsExceptionWithInvalidMethod()
    {
        $this->setExpectedException('Zend\View\Exception\ExceptionInterface');
        $this->helper->bogusMethod();
    }

    public function testStylesheetAttributesGetSet()
    {
        $this->helper->setStylesheet('/styles.css', 'projection', 'ie6');
        $item = $this->helper->getValue();
        $this->assertObjectHasAttribute('media', $item);
        $this->assertObjectHasAttribute('conditionalStylesheet', $item);

        $this->assertEquals('projection', $item->media);
        $this->assertEquals('ie6', $item->conditionalStylesheet);
    }

    public function testConditionalStylesheetNotCreatedByDefault()
    {
        $this->helper->setStylesheet('/styles.css');
        $item = $this->helper->getValue();
        $this->assertObjectHasAttribute('conditionalStylesheet', $item);
        $this->assertFalse($item->conditionalStylesheet);

        $string = $this->helper->toString();
        $this->assertContains('/styles.css', $string);
        $this->assertNotContains('<!--[if', $string);
        $this->assertNotContains(']>', $string);
        $this->assertNotContains('<![endif]-->', $string);
    }

    public function testConditionalStylesheetCreationOccursWhenRequested()
    {
        $this->helper->setStylesheet('/styles.css', 'screen', 'ie6');
        $item = $this->helper->getValue();
        $this->assertObjectHasAttribute('conditionalStylesheet', $item);
        $this->assertEquals('ie6', $item->conditionalStylesheet);

        $string = $this->helper->toString();
        $this->assertContains('/styles.css', $string);
        $this->assertContains('<!--[if ie6]>', $string);
        $this->assertContains('<![endif]-->', $string);
    }

    public function testSettingAlternateWithTooFewArgsRaisesException()
    {
        try {
            $this->helper->setAlternate('foo');
            $this->fail('Setting alternate with fewer than 3 args should raise exception');
        } catch (ViewException $e) { }
        try {
            $this->helper->setAlternate('foo', 'bar');
            $this->fail('Setting alternate with fewer than 3 args should raise exception');
        } catch (ViewException $e) { }
    }

    public function testIndentationIsHonored()
    {
        $this->helper->setIndent(4);
        $this->helper->appendStylesheet('/css/screen.css');
        $this->helper->appendStylesheet('/css/rules.css');
        $string = $this->helper->toString();

        $scripts = substr_count($string, '    <link ');
        $this->assertEquals(2, $scripts);
    }

    public function testLinkRendersAsPlainHtmlIfDoctypeNotXhtml()
    {
        $this->view->plugin('doctype')->__invoke('HTML4_STRICT');
        $this->helper->__invoke(array('rel' => 'icon', 'src' => '/foo/bar'))
            ->__invoke(array('rel' => 'foo', 'href' => '/bar/baz'));
        $test = $this->helper->toString();
        $this->assertNotContains(' />', $test);
    }

    public function testDoesNotAllowDuplicateStylesheets()
    {
        $this->helper->appendStylesheet('foo');
        $this->helper->appendStylesheet('foo');
        $this->assertEquals(1, count($this->helper), var_export($this->helper->getContainer()->getArrayCopy(), 1));
    }

    /**
     * test for ZF-2889
     */
    public function testBooleanStylesheet()
    {
        $this->helper->appendStylesheet(array('href' => '/bar/baz', 'conditionalStylesheet' => false));
        $test = $this->helper->toString();
        $this->assertNotContains('[if false]', $test);
    }

    /**
     * test for ZF-3271
     *
     */
    public function testBooleanTrueConditionalStylesheet()
    {
        $this->helper->appendStylesheet(array('href' => '/bar/baz', 'conditionalStylesheet' => true));
        $test = $this->helper->toString();
        $this->assertNotContains('[if 1]', $test);
        $this->assertNotContains('[if true]', $test);
    }

    /**
     * @issue ZF-3928
     * @link http://framework.zend.com/issues/browse/ZF-3928
     */
    public function testTurnOffAutoEscapeDoesNotEncodeAmpersand()
    {
        $this->helper->setAutoEscape(false)->appendStylesheet('/css/rules.css?id=123&foo=bar');
        $this->assertContains('id=123&foo=bar', $this->helper->toString());
    }

    public function testSetAlternateWithExtras()
    {
        $this->helper->setAlternate('/mydocument.pdf', 'application/pdf', 'foo', array('media' => array('print','screen')));
        $test = $this->helper->toString();
        $this->assertContains('media="print,screen"', $test);
    }

    public function testAppendStylesheetWithExtras()
    {
        $this->helper->appendStylesheet(array('href' => '/bar/baz', 'conditionalStylesheet' => false, 'extras' => array('id' => 'my_link_tag')));
        $test = $this->helper->toString();
        $this->assertContains('id="my_link_tag"', $test);
    }

    public function testSetStylesheetWithMediaAsArray()
    {
        $this->helper->appendStylesheet('/bar/baz', array('screen','print'));
        $test = $this->helper->toString();
        $this->assertContains(' media="screen,print"', $test);
    }

    /**
     * @issue ZF-5435
     */
    public function testContainerMaintainsCorrectOrderOfItems()
    {
        $this->helper->__invoke()->offsetSetStylesheet(1,'/test1.css');
        $this->helper->__invoke()->offsetSetStylesheet(10,'/test2.css');
        $this->helper->__invoke()->offsetSetStylesheet(20,'/test3.css');
        $this->helper->__invoke()->offsetSetStylesheet(5,'/test4.css');

        $test = $this->helper->toString();

        $expected = '<link href="/test1.css" media="screen" rel="stylesheet" type="text/css">' . PHP_EOL
            . '<link href="/test4.css" media="screen" rel="stylesheet" type="text/css">' . PHP_EOL
            . '<link href="/test2.css" media="screen" rel="stylesheet" type="text/css">' . PHP_EOL
            . '<link href="/test3.css" media="screen" rel="stylesheet" type="text/css">';

        $this->assertEquals($expected, $test);
    }

    /**
     * @issue ZF-10345
     */
    public function testIdAttributeIsSupported()
    {
        $this->helper->appendStylesheet(array('href' => 'bar/baz', 'id' => 'foo'));
        $this->assertContains('id="foo"', $this->helper->toString());
    }

    public function testAppendSharedStylesheet()
    {
        $this->helper->appendSharedStylesheet('bar/baz');
        $test = $this->helper->toString();
        $this->assertContains($this->sharedPath, $test);
    }

    public function testPrependSharedStylesheet()
    {
        $this->helper->prependSharedStylesheet('bar/baz');
        $this->helper->prependSharedStylesheet('foo/bar');
        $test = $this->helper->toString();
        $this->assertContains('foo/bar', strtok($test, "\n")); // Make sure foo/bar is the first line
    }

    public function testAppendStylesheetWithSharedExtra()
    {
        $this->helper->appendStylesheet('bar/baz', 'screen', null, array('data-shared' => true));
        $test = $this->helper->toString();
        $this->assertContains($this->sharedPath, $test);
    }

    public function testContainerMaintainsCorrectOrderOfSharedItems()
    {
        $this->helper->__invoke()->offsetSetSharedStylesheet(1,'test1.css');
        $this->helper->__invoke()->offsetSetSharedStylesheet(10,'test2.css');
        $this->helper->__invoke()->offsetSetSharedStylesheet(20,'test3.css');
        $this->helper->__invoke()->offsetSetSharedStylesheet(5,'test4.css');

        $test = $this->helper->toString();

        $expected = '<link href="/shared/test1.css" media="screen" rel="stylesheet" type="text/css">' . PHP_EOL
            . '<link href="/shared/test4.css" media="screen" rel="stylesheet" type="text/css">' . PHP_EOL
            . '<link href="/shared/test2.css" media="screen" rel="stylesheet" type="text/css">' . PHP_EOL
            . '<link href="/shared/test3.css" media="screen" rel="stylesheet" type="text/css">';

        $this->assertEquals($expected, $test);
    }





    public function testSharedFileInDevelopmentMode()
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
            'use_cdn_for_shared' => false,
        );
        $this->setUpMockConfig($config);


        $this->helper->appendSharedStylesheet('css/test.css');
        $this->assertEquals('<link href="/' . 'shared/' . 'css/test.css" '
            . 'media="screen" rel="stylesheet" type="text/css">'
            , $this->helper->toString());
    }

    public function testSharedFileWithCdn()
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


        $this->helper->appendSharedStylesheet('css/test.css');
        $this->assertEquals('<link href="' . $testCdn . '/' . $this->sharedPath . '/' . 'css/test.css" '
            . 'media="screen" rel="stylesheet" type="text/css">'
            , $this->helper->toString());
    }

}
