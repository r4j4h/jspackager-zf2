<?php

namespace JsPackagerTest;

use JsPackager\File;
use JsPackager\DependencyTreeParser;
use JsPackager\FileHandler;
use JsPackager\Exception\Parsing as ParsingException;
use JsPackager\Exception\Recursion as RecursionException;
use JsPackager\Exception\MissingFile as MissingFileException;

use JsPackager\Helpers\Reflection as ReflectionHelper;

/**
 * @group      JsPackager
 */
class DependencyTreeParserTest extends \PHPUnit_Framework_TestCase
{
    // Tests are run from project root
    const fixturesBasePath = 'tests/JsPackager/fixtures/';


    /******************************************************************
     * normalizeRelativePath
     *****************************************************************/


    public function testNormalizeRelativePathDoesNotHarmBasicPaths()
    {
        $path = '/chocolate/and/strawberries/is/yummy';
        $expectedPath = '/chocolate/and/strawberries/is/yummy';

        $treeParser = new DependencyTreeParser();
        $normalizedPath = $treeParser->normalizeRelativePath( $path );

        $this->assertEquals($expectedPath, $normalizedPath);
    }

    public function testNormalizeRelativePathDoesNotTrailingSlash()
    {
        $path = '/chocolate/and/strawberries/is/yummy/';
        $expectedPath = '/chocolate/and/strawberries/is/yummy/';

        $treeParser = new DependencyTreeParser();
        $normalizedPath = $treeParser->normalizeRelativePath( $path );

        $this->assertEquals($expectedPath, $normalizedPath);
    }

    public function testNormalizeRelativePathHandlesSingleUpDirectory()
    {
        $path = '/chocolate/and/../strawberries';
        $expectedPath = '/chocolate/strawberries';

        $treeParser = new DependencyTreeParser();
        $normalizedPath = $treeParser->normalizeRelativePath( $path );

        $this->assertEquals($expectedPath, $normalizedPath);
    }

    public function testNormalizeRelativePathHandlesManyUpDirectories()
    {
        $path = '/somewhere/in/a/place/../../heaven';
        $expectedPath = '/somewhere/in/heaven';

        $treeParser = new DependencyTreeParser();
        $normalizedPath = $treeParser->normalizeRelativePath( $path );

        $this->assertEquals($expectedPath, $normalizedPath);
    }

    public function testNormalizeRelativePathEvenSeparated()
    {
        $path = '/somewhere/somehow/../in/a/place/../../heaven';
        $expectedPath = '/somewhere/in/heaven';

        $treeParser = new DependencyTreeParser();
        $normalizedPath = $treeParser->normalizeRelativePath( $path );

        $this->assertEquals($expectedPath, $normalizedPath);
    }

    public function testNormalizeRelativePathDoesNotExceedRoot()
    {
        $path = '/somewhere/../../home/';
        $expectedPath = '/../home/';

        $treeParser = new DependencyTreeParser();
        $normalizedPath = $treeParser->normalizeRelativePath( $path );

        $this->assertEquals($expectedPath, $normalizedPath);
    }

    public function testNormalizeRelativePathDoesNotExceedRootEvenSeparated()
    {
        $path = '/somewhere/../../home/ward/../../bound/../../';
        $expectedPath = '/../../';

        $treeParser = new DependencyTreeParser();
        $normalizedPath = $treeParser->normalizeRelativePath( $path );

        $this->assertEquals($expectedPath, $normalizedPath);
    }

    public function testNormalizeRelativePathHandlesDashes()
    {
        $path = '/some-where/../../home-ward/ward/../../bound/../../';
        $expectedPath = '/../../';

        $treeParser = new DependencyTreeParser();
        $normalizedPath = $treeParser->normalizeRelativePath( $path );

        $this->assertEquals($expectedPath, $normalizedPath);
    }


    /******************************************************************
     * getAnnotationsFromFile
     *****************************************************************/


    /**
     * @return \JsPackager\FileHandler
     */
    private function getMockedFileHandler()
    {
        return $this->getMock('JsPackager\FileHandler', array('is_file', 'fopen', 'fgets', 'fclose'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "// JS code";
        $treeParser = new DependencyTreeParser();

        $mockedFileHandler = $this->getMockedFileHandler();
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls($lineA, $lineB, false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        $annotationResponse = ReflectionHelper::invoke( $treeParser, 'getAnnotationsFromFile', array( 'mocked' ) );
        $this->assertArrayHasKey('annotations', $annotationResponse);
        $this->assertArrayHasKey('orderingMap', $annotationResponse);
        $this->assertEmpty( $annotationResponse['orderingMap'] );


        $annotations = $annotationResponse['annotations'];
        $this->assertArrayHasKey('require', $annotations);
        $this->assertArrayHasKey('requireRemote', $annotations);
        $this->assertArrayHasKey('requireStyle', $annotations);
        $this->assertArrayHasKey('root', $annotations);
        $this->assertArrayHasKey('tests', $annotations);
        $this->assertEmpty( $annotations['require'] );
        $this->assertEmpty( $annotations['requireRemote'] );
        $this->assertEmpty( $annotations['requireStyle'] );
        $this->assertEmpty( $annotations['root'] );

        return $annotationResponse;
    }

    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     * @runInSeparateProcess
     */
    public function testGetAnnotationsReturnsOrderingMap($annotationResponse)
    {
        $this->assertArrayHasKey('orderingMap', $annotationResponse);
    }


    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     * @runInSeparateProcess
     */
    public function testGetAnnotationsFromFileGetsRequireAnnotations()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "@require bla.js";
        $lineC = "// Some comment";
        $lineD = "@require other/bla.js";
        $treeParser = new DependencyTreeParser();

        $mockedFileHandler = $this->getMockedFileHandler();
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

        $treeParser->setFileHandler( $mockedFileHandler );

        $annotationResponse = ReflectionHelper::invoke( $treeParser, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertContains( 'bla.js', $annotations['require'] );
        $this->assertContains( 'other/bla.js', $annotations['require'] );
        $this->assertEmpty( $annotations['requireRemote'] );
        $this->assertEmpty( $annotations['requireStyle'] );
        $this->assertEmpty( $annotations['root'] );


        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'require', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'require', $annotationOrderingMap[1]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 1, $annotationOrderingMap[1]['annotationIndex'], "Should reflect appropriate order" );
    }

    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     * @runInSeparateProcess
     */
    public function testGetAnnotationsFromFileGetsRemoteRequires()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "@requireRemote bla.js";
        $lineC = "// Some comment";
        $lineD = "@requireRemote other/bla.js";
        $treeParser = new DependencyTreeParser();

        $mockedFileHandler = $this->getMockedFileHandler();
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

        $treeParser->setFileHandler( $mockedFileHandler );

        $annotationResponse = ReflectionHelper::invoke( $treeParser, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertEmpty( $annotations['require'] );
        $this->assertContains( 'bla.js', $annotations['requireRemote'] );
        $this->assertContains( 'other/bla.js', $annotations['requireRemote'] );
        $this->assertEmpty( $annotations['requireStyle'] );
        $this->assertEmpty( $annotations['root'] );


        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'requireRemote', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'requireRemote', $annotationOrderingMap[1]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 1, $annotationOrderingMap[1]['annotationIndex'], "Should reflect appropriate order" );

    }

    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     * @runInSeparateProcess
     */
    public function testGetAnnotationsFromFileGetsStylesheetRequires()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "@requireStyle bla.css";
        $lineC = "// Some comment";
        $lineD = "@requireStyle other/bla.css";
        $treeParser = new DependencyTreeParser();

        $mockedFileHandler = $this->getMockedFileHandler();
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

        $treeParser->setFileHandler( $mockedFileHandler );

        $annotationResponse = ReflectionHelper::invoke( $treeParser, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertEmpty( $annotations['require'] );
        $this->assertEmpty( $annotations['requireRemote'] );
        $this->assertContains( 'bla.css', $annotations['requireStyle'] );
        $this->assertContains( 'other/bla.css', $annotations['requireStyle'] );
        $this->assertEmpty( $annotations['root'] );

        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'requireStyle', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'requireStyle', $annotationOrderingMap[1]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 1, $annotationOrderingMap[1]['annotationIndex'], "Should reflect appropriate order" );
    }

    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     * @runInSeparateProcess
     */
    public function testGetAnnotationsFromFileGetsRootAnnotation()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "@root";
        $lineC = "// Some comment";
        $treeParser = new DependencyTreeParser();

        $mockedFileHandler = $this->getMockedFileHandler();
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

        $treeParser->setFileHandler( $mockedFileHandler );

        $annotationResponse = ReflectionHelper::invoke( $treeParser, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertEmpty( $annotations['require'] );
        $this->assertEmpty( $annotations['requireRemote'] );
        $this->assertEmpty( $annotations['requireStyle'] );
        $this->assertTrue( $annotations['root'] );

        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'root', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );
    }

    /**
     * @depends testGetAnnotationsFromFileGetsRootAnnotation
     * @runInSeparateProcess
     */
    public function testGetAnnotationsFromFileGetsRootAnnotationWithWhitespace()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "@root     ";
        $lineC = "// Some comment";
        $treeParser = new DependencyTreeParser();

        $mockedFileHandler = $this->getMockedFileHandler();
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

        $treeParser->setFileHandler( $mockedFileHandler );

        $annotationResponse = ReflectionHelper::invoke( $treeParser, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertTrue( $annotations['root'] );


        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'root', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );
    }

    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     * @runInSeparateProcess
     */
    public function testGetAnnotationsFromFileGetsAllAnnotations()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "// @root ";
        $lineC = "// @require bob.js";
        $lineD = "// @requireRemote common.js ";
        $lineE = "// @requireStyle beautiful.css rawr.css ";
        $lineF = "// Some comment";
        $treeParser = new DependencyTreeParser();

        $mockedFileHandler = $this->getMockedFileHandler();
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls($lineA, $lineB, $lineC, $lineD, $lineE, $lineF, false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        $treeParser->setFileHandler( $mockedFileHandler );

        $annotationResponse = ReflectionHelper::invoke( $treeParser, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertContains( 'bob.js', $annotations['require'] );
        $this->assertContains( 'common.js', $annotations['requireRemote'] );
        $this->assertContains( 'beautiful.css', $annotations['requireStyle'] );
        $this->assertContains( 'rawr.css', $annotations['requireStyle'] );
        $this->assertTrue( $annotations['root'] );

        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'root', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'require', $annotationOrderingMap[1]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[1]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'requireRemote', $annotationOrderingMap[2]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[2]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'requireStyle', $annotationOrderingMap[3]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[3]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'requireStyle', $annotationOrderingMap[4]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 1, $annotationOrderingMap[4]['annotationIndex'], "Should reflect appropriate order" );
    }

    /**
     * @depends testGetAnnotationsFromFileReturnsEmptyArrayWhenNoAnnotations
     * @runInSeparateProcess
     */
    public function testGetAnnotationsFromFileWithMultipleArguments()
    {
        $lineA = "window.wpt = window.wpt || {};";
        $lineB = "// @root ";
        $lineC = "// /!#5/ttgeag   @require   bob.js billy.js  ";
        $lineD = "// @requireRemote common.js   rare.js";
        $lineE = "// @root "; // For testing ordering map
        $lineF = "// @requireStyle beautiful.css rawr.css ";
        $lineG = "// Some comment";
        $treeParser = new DependencyTreeParser();

        $mockedFileHandler = $this->getMockedFileHandler();
        $mockedFileHandler->expects($this->any())
            ->method('is_file')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fopen')
            ->will($this->returnValue(true));
        $mockedFileHandler->expects($this->any())
            ->method('fgets')
            ->will($this->onConsecutiveCalls($lineA, $lineB, $lineC, $lineD, $lineE, $lineF, $lineG, false));
        $mockedFileHandler->expects($this->any())
            ->method('fclose')
            ->will($this->returnValue(true));

        $treeParser->setFileHandler( $mockedFileHandler );

        $annotationResponse = ReflectionHelper::invoke( $treeParser, 'getAnnotationsFromFile', array( 'mocked' ) );
        $annotations = $annotationResponse['annotations'];

        $this->assertContains( 'bob.js', $annotations['require'] );
        $this->assertContains( 'billy.js', $annotations['require'] );

        $this->assertContains( 'common.js', $annotations['requireRemote'] );
        $this->assertContains( 'rare.js', $annotations['requireRemote'] );

        $this->assertContains( 'beautiful.css', $annotations['requireStyle'] );
        $this->assertContains( 'rawr.css', $annotations['requireStyle'] );
        $this->assertTrue( $annotations['root'] );


        $annotationOrderingMap = $annotationResponse['orderingMap'];

        $this->assertEquals( 'root', $annotationOrderingMap[0]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[0]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'require', $annotationOrderingMap[1]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[1]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'require', $annotationOrderingMap[2]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 1, $annotationOrderingMap[2]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'requireRemote', $annotationOrderingMap[3]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[3]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'requireRemote', $annotationOrderingMap[4]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 1, $annotationOrderingMap[4]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'root', $annotationOrderingMap[5]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[5]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'requireStyle', $annotationOrderingMap[6]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $annotationOrderingMap[6]['annotationIndex'], "Should reflect appropriate order" );
        $this->assertEquals( 'requireStyle', $annotationOrderingMap[7]['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 1, $annotationOrderingMap[7]['annotationIndex'], "Should reflect appropriate order" );
    }

    /******************************************************************
     * parseFile > Fundamentals
     *****************************************************************/


    public function testParseFileReturnsFileObject()
    {
        $basePath = self::fixturesBasePath . '0_deps';
        $filePath = $basePath . '/main.js';

        $treeParser = new DependencyTreeParser();
        $dependencyTree = $treeParser->parseFile( $filePath );

        $this->assertInstanceOf(
            'JsPackager\File',
            $dependencyTree,
            "parseFile's return value should be a File object"
        );
    }

    /**
     * @depends testParseFileReturnsFileObject
     */
    public function testParseFileReturnsGivenFile()
    {
        $basePath = self::fixturesBasePath . '0_deps';
        $filePath = $basePath . '/main.js';

        $treeParser = new DependencyTreeParser();
        $dependencyTree = $treeParser->parseFile( $filePath );

        $this->assertEquals( 'main', $dependencyTree->filename, "Main file should be named main" );
        $this->assertEquals( 'js', $dependencyTree->filetype, "Main file should be of js filetype" );
        $this->assertEquals( $basePath, $dependencyTree->path, "Main file should have base path" );
        $this->assertFalse( $dependencyTree->isRoot, 'Main file should not be marked isRoot' );
        $this->assertEmpty( $dependencyTree->scripts, "Main.js should have no dependent scripts" );
        $this->assertEmpty( $dependencyTree->stylesheets, "Main.js should have no dependent stylesheets" );
        $this->assertEmpty( $dependencyTree->packages, "Main.js should have no dependent packages" );
        $this->assertEmpty(
            $dependencyTree->annotationOrderMap,
            "Main.js should have no annotations in its ordering map"
        );
    }

    /**
     * @depends testParseFileReturnsGivenFile
     */
    public function testParseFileReturnsGivenRootFile()
    {
        $basePath = self::fixturesBasePath . '0_deps_root';
        $filePath = $basePath . '/main.js';

        $treeParser = new DependencyTreeParser();
        $dependencyTree = $treeParser->parseFile( $filePath );

        $this->assertEquals( 'main', $dependencyTree->filename );
        $this->assertEquals( 'js', $dependencyTree->filetype );
        $this->assertEquals( $basePath, $dependencyTree->path );
        $this->assertTrue( $dependencyTree->isRoot, 'Main.js should be marked isRoot' );
        $this->assertEmpty( $dependencyTree->scripts );
        $this->assertEmpty( $dependencyTree->stylesheets );
        $this->assertEmpty( $dependencyTree->packages );
        $this->assertEmpty(
            $dependencyTree->annotationOrderMap,
            "Main.js should have no annotations in its ordering map"
        );
    }

    /**
     * @depends testParseFileReturnsGivenFile
     */
    public function testParseFileLoadsDependentFile()
    {
        $basePath = self::fixturesBasePath . '1_dep';
        $filePath = $basePath . '/main.js';

        $treeParser = new DependencyTreeParser();
        $dependencyTree = $treeParser->parseFile( $filePath );

        $this->assertEquals( 'main', $dependencyTree->filename );
        $this->assertEquals( 'js', $dependencyTree->filetype );
        $this->assertEquals( $basePath, $dependencyTree->path );
        $this->assertFalse( $dependencyTree->isRoot, 'File should not be marked isRoot' );
        $this->assertNotEmpty( $dependencyTree->scripts );
        $this->assertEmpty( $dependencyTree->stylesheets );
        $this->assertEmpty( $dependencyTree->packages );

        $this->assertCount(1, $dependencyTree->scripts, 'Should have a dependent script' );
        $this->assertInstanceOf( 'JsPackager\File', $dependencyTree->scripts[0] );

        $this->assertEquals( 'dep_1', $dependencyTree->scripts[0]->filename );
        $this->assertFalse( $dependencyTree->scripts[0]->isRoot, 'File should not be marked isRoot' );

        $this->assertEquals(
            'require',
            $dependencyTree->annotationOrderMap[0]['action'],
            "Should reflect appropriate bucket"
        );
        $this->assertEquals(
            0,
            $dependencyTree->annotationOrderMap[0]['annotationIndex'],
            "Should reflect appropriate order"
        );
    }

    /**
     * @depends testParseFileReturnsGivenRootFile
     */
    public function testParseFileLoadsDependentRootFile()
    {
        $basePath = self::fixturesBasePath . '1_dep_root';
        $filePath = $basePath . '/main.js';

        $treeParser = new DependencyTreeParser();
        $dependencyTree = $treeParser->parseFile( $filePath );

        $this->assertEquals( 'main', $dependencyTree->filename );
        $this->assertEquals( 'js', $dependencyTree->filetype );
        $this->assertEquals( $basePath, $dependencyTree->path );
        $this->assertFalse( $dependencyTree->isRoot, 'File should not be marked isRoot' );
        $this->assertNotEmpty( $dependencyTree->scripts );
        $this->assertEmpty( $dependencyTree->stylesheets );
        $this->assertNotEmpty( $dependencyTree->packages );

        $this->assertCount(1, $dependencyTree->scripts, 'Should have a dependent script' );
        $this->assertCount(1, $dependencyTree->packages, 'Should have a dependent script package entry' );

        $this->assertInstanceOf( 'JsPackager\File', $dependencyTree->scripts[0] );
        $this->assertEquals( 'dep_1', $dependencyTree->scripts[0]->filename );
        $this->assertTrue( $dependencyTree->scripts[0]->isRoot, 'Dependent script should be marked isRoot');

        $this->assertEquals( $basePath . '/somePackage/dep_1.js', $dependencyTree->packages[0] );

        $this->assertEquals(
            'require',
            $dependencyTree->annotationOrderMap[0]['action'],
            "Should reflect appropriate bucket"
        );
        $this->assertEquals(
            0,
            $dependencyTree->annotationOrderMap[0]['annotationIndex'],
            "Should reflect appropriate order"
        );
    }

    /**
     * Leaving this test for clarity, stylesheets used to be File objects instead of behaving
     * like packages with full paths.
     *
     * Now this test verifies it by just seeing that the dependency tree in total does not have any
     * of the things that the stylesheet is annotated with.
     */
    public function testParseFileIgnoresAnnotationsInStylesheets()
    {
        $basePath = self::fixturesBasePath . 'css_with_annotations';
        $filePath = $basePath . '/main.js';

        $treeParser = new DependencyTreeParser();
        $dependencyTree = $treeParser->parseFile( $filePath );

        $this->assertEquals( 'main', $dependencyTree->filename, "main should be base file's name" );
        $this->assertEquals( 'js', $dependencyTree->filetype, "main.js should be a javascript file" );
        $this->assertEquals( $basePath, $dependencyTree->path, "main.js should be in the css_with_annotations fixture" );
        $this->assertFalse( $dependencyTree->isRoot, 'main.js should not be marked isRoot' );
        $this->assertEmpty( $dependencyTree->scripts, "main.js should have no script dependencies" );
        $this->assertNotEmpty( $dependencyTree->stylesheets, "main.js should have 1 dependent stylesheet" );
        $this->assertEmpty( $dependencyTree->packages, "main.js should have no packaged dependencies" );

        $this->assertCount(1, $dependencyTree->stylesheets, 'Should have one dependent stylesheet' );
        $this->assertEquals( $basePath . '/' . 'main.css', $dependencyTree->stylesheets[0] );
    }

    public function testParseFileThrowsMissingFileExceptionOnBrokenReferencesIfNotMuted()
    {
        // Test JavaScript files

        $basePath = self::fixturesBasePath . '1_broken_js_reference';
        $filePath = $basePath . '/main.js';

        $treeParser = new DependencyTreeParser();

        try {
            $dependencyTree = $treeParser->parseFile( $filePath );
            $this->fail('Set should throw a missing file exception');
        } catch (ParsingException $e) {
            $this->assertEquals(
                'tests/JsPackager/fixtures/1_broken_js_reference/heeper.js',
                $e->getErrors(),
                'Exception should contain failed file\'s path information'
            );

            $this->assertEquals(
                ParsingException::ERROR_CODE,
                $e->getCode(),
                'Exception should contain proper error code'
            );
        }


        // Test Stylesheet files

        $basePath = self::fixturesBasePath . '1_broken_css_reference';
        $filePath = $basePath . '/main.js';

        $treeParser = new DependencyTreeParser();

        try {
            $dependencyTree = $treeParser->parseFile( $filePath );
            $this->fail('Set should throw a missing file exception');
        } catch (MissingFileException $e) {
            $this->assertEquals(
                'tests/JsPackager/fixtures/1_broken_css_reference/heeper.css',
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

    public function testParseFileDoesNotThrowMissingFileExceptionOnBrokenReferencesIfMuted()
    {
        // Test JavaScript files

        $basePath = self::fixturesBasePath . '1_broken_js_reference';
        $filePath = $basePath . '/main.js';

        $treeParser = new DependencyTreeParser();
        $treeParser->muteMissingFileExceptions();

        $dependencyTree = $treeParser->parseFile( $filePath );
        $this->assertEquals(
            $dependencyTree->filename,
            "main",
            "Dependency Tree should have completed with main as the filename"
        );


        // Test Stylesheet files

        $basePath = self::fixturesBasePath . '1_broken_css_reference';
        $filePath = $basePath . '/main.js';

        $treeParser = new DependencyTreeParser();
        $treeParser->muteMissingFileExceptions();

        $dependencyTree = $treeParser->parseFile( $filePath );
        $this->assertEquals(
            $dependencyTree->filename,
            "main",
            "Dependency Tree should have completed with main as the filename"
        );
    }

    public function testParseFileThrowsParsingExceptionOnMissingFileDuringRecursionIntoFile()
    {
        // Test JavaScript files

        $basePath = self::fixturesBasePath . '1_broken_js_reference_recursive';
        $filePath = $basePath . '/main.js';

        $treeParser = new DependencyTreeParser();

        try {
            $dependencyTree = $treeParser->parseFile( $filePath );
            $this->fail('Set should throw a missing file exception');
        } catch (ParsingException $e) {
            $this->assertEquals(
                'Failed to include missing file ' .
                '"tests/JsPackager/fixtures/1_broken_js_reference_recursive/heeper.js"'.
                ' while trying to parse ' .
                '"tests/JsPackager/fixtures/1_broken_js_reference_recursive/helper.js"',
                $e->getMessage(),
                'Exception should contain failed file\'s path information'
            );

            $this->assertEquals(
                'tests/JsPackager/fixtures/1_broken_js_reference_recursive/heeper.js',
                $e->getErrors(),
                'Exception should contain failed file\'s path information'
            );

            $this->assertEquals(
                ParsingException::ERROR_CODE,
                $e->getCode(),
                'Exception should contain proper error code'
            );
        }

    }


    /******************************************************************
     * parseFile > 2 Dependencies, #1 and #2
     * Fixture folder: 2_indep_deps
     *****************************************************************/
    public function testParseFile_2IndepDeps()
    {
        $basePath = self::fixturesBasePath . '2_indep_deps';
        $filePath = $basePath . '/main.js';

        $treeParser = new DependencyTreeParser();
        $dependencyTree = $treeParser->parseFile( $filePath );

        $this->assertEquals( 'main', $dependencyTree->filename );
        $this->assertEquals( 'js', $dependencyTree->filetype );
        $this->assertEquals( $basePath, $dependencyTree->path );
        $this->assertFalse( $dependencyTree->isRoot, 'File should not be marked isRoot' );
        $this->assertNotEmpty( $dependencyTree->scripts );
        $this->assertEmpty( $dependencyTree->stylesheets );
        $this->assertEmpty( $dependencyTree->packages );

        $this->assertCount(2, $dependencyTree->scripts, 'Should have two dependent scripts' );
        $this->assertInstanceOf( 'JsPackager\File', $dependencyTree->scripts[0] );
        $this->assertInstanceOf( 'JsPackager\File', $dependencyTree->scripts[1] );

        $this->assertEquals( 'comp_a', $dependencyTree->scripts[0]->filename );
        $this->assertEquals( 'comp_b', $dependencyTree->scripts[1]->filename );
        $this->assertFalse( $dependencyTree->scripts[0]->isRoot, 'File should not be marked isRoot' );
        $this->assertFalse( $dependencyTree->scripts[1]->isRoot, 'File should not be marked isRoot' );
    }

    /******************************************************************
     * parseFile > 2 Dependencies, #1 and #2, #2 is root
     * Fixture folder: 2_indep_deps_1_root
     *****************************************************************/
    public function testParseFile_2IndepDeps1Root()
    {
        $basePath = self::fixturesBasePath . '2_indep_deps_1_root';
        $filePath = $basePath . '/main.js';

        $treeParser = new DependencyTreeParser();
        $dependencyTree = $treeParser->parseFile( $filePath );

        $this->assertEquals( 'main', $dependencyTree->filename );
        $this->assertEquals( 'js', $dependencyTree->filetype );
        $this->assertEquals( $basePath, $dependencyTree->path );
        $this->assertFalse( $dependencyTree->isRoot, 'File should not be marked isRoot' );
        $this->assertNotEmpty( $dependencyTree->scripts );
        $this->assertEmpty( $dependencyTree->stylesheets );
        $this->assertNotEmpty( $dependencyTree->packages );

        $this->assertCount(2, $dependencyTree->scripts, 'Should have two dependent scripts' );
        $this->assertInstanceOf( 'JsPackager\File', $dependencyTree->scripts[0] );
        $this->assertInstanceOf( 'JsPackager\File', $dependencyTree->scripts[1] );

        $this->assertEquals( 'comp_a', $dependencyTree->scripts[0]->filename );
        $this->assertEquals( 'comp_b', $dependencyTree->scripts[1]->filename );
        $this->assertFalse( $dependencyTree->scripts[0]->isRoot, 'File should not be marked isRoot' );
        $this->assertTrue(  $dependencyTree->scripts[1]->isRoot, 'File should be marked isRoot' );

        $this->assertEquals( $basePath . '/ComponentB/comp_b.js', $dependencyTree->packages[0], "Should have comp_b package" );
    }


    /******************************************************************
     * parseFile > 2 Dependencies, #1 and #2
     *      #1 with its own dependency (#3)
     *      #2 with its own dependency (#4)
     * Fixture folder: 2_indep_deps_individ_deps
     *****************************************************************/

    public function testParseFile_2IndepDepsIndividDeps()
    {
        $basePath = self::fixturesBasePath . '2_indep_deps_individ_deps';
        $filePath = $basePath . '/main.js';

        $treeParser = new DependencyTreeParser();
        $dependencyTree = $treeParser->parseFile( $filePath );

        $this->assertEquals( 'main', $dependencyTree->filename );
        $this->assertEquals( 'js', $dependencyTree->filetype );
        $this->assertEquals( $basePath, $dependencyTree->path );
        $this->assertFalse( $dependencyTree->isRoot, 'File should not be marked isRoot' );
        $this->assertNotEmpty( $dependencyTree->scripts );
        $this->assertEmpty( $dependencyTree->stylesheets );
        $this->assertEmpty( $dependencyTree->packages );

        $this->assertCount(2, $dependencyTree->scripts, 'Should have two dependent scripts' );
        $this->assertInstanceOf( 'JsPackager\File', $dependencyTree->scripts[0] );
        $this->assertInstanceOf( 'JsPackager\File', $dependencyTree->scripts[1] );

        $this->assertEquals( 'dep_1', $dependencyTree->scripts[0]->filename );
        $this->assertEquals( 'dep_2', $dependencyTree->scripts[1]->filename );
        $this->assertFalse( $dependencyTree->scripts[0]->isRoot, 'File should not be marked isRoot' );
        $this->assertFalse( $dependencyTree->scripts[1]->isRoot, 'File should not be marked isRoot' );

        $dep1 = $dependencyTree->scripts[0];
        $dep2 = $dependencyTree->scripts[1];

        $this->assertCount(1, $dep1->scripts, 'Should have one dependent script');
        $this->assertEquals( 'dep_3', $dep1->scripts[0]->filename );
        $this->assertFalse( $dep1->scripts[0]->isRoot, 'File should not be marked isRoot' );
        $this->assertEmpty( $dep1->scripts[0]->packages );

        $this->assertCount(1, $dep2->scripts, 'Should have one dependent script');
        $this->assertEquals( 'dep_4', $dep2->scripts[0]->filename );
        $this->assertFalse( $dep2->scripts[0]->isRoot, 'File should not be marked isRoot' );
        $this->assertEmpty( $dep2->scripts[0]->packages );
    }

    /******************************************************************
     * parseFile > 2 Dependencies, #1 and #2
     *      #1 with a dependency (#3)
     *      #2 with same dependency (#3)
     * Fixture folder: 2_indep_deps_shared_deps
     *****************************************************************/

    public function testParseFile_2IndepDepsSharedDeps()
    {
        $basePath = self::fixturesBasePath . '2_indep_deps_shared_deps';
        $filePath = $basePath . '/main.js';

        $treeParser = new DependencyTreeParser();
        $dependencyTree = $treeParser->parseFile( $filePath );

        $this->assertEquals( 'main', $dependencyTree->filename );
        $this->assertEquals( 'js', $dependencyTree->filetype );
        $this->assertEquals( $basePath, $dependencyTree->path );
        $this->assertFalse( $dependencyTree->isRoot, 'File should not be marked isRoot' );
        $this->assertNotEmpty( $dependencyTree->scripts );
        $this->assertEmpty( $dependencyTree->stylesheets );
        $this->assertEmpty( $dependencyTree->packages );

        $this->assertCount(2, $dependencyTree->scripts, 'Should have two dependent scripts' );
        $this->assertInstanceOf( 'JsPackager\File', $dependencyTree->scripts[0] );
        $this->assertInstanceOf( 'JsPackager\File', $dependencyTree->scripts[1] );

        $this->assertEquals( 'dep_1', $dependencyTree->scripts[0]->filename );
        $this->assertEquals( 'dep_2', $dependencyTree->scripts[1]->filename );
        $this->assertFalse( $dependencyTree->scripts[0]->isRoot, 'File should not be marked isRoot' );
        $this->assertFalse( $dependencyTree->scripts[1]->isRoot, 'File should not be marked isRoot' );

        $dep1 = $dependencyTree->scripts[0];
        $dep2 = $dependencyTree->scripts[1];

        $this->assertCount(1, $dep1->scripts, 'Should have one dependent script');
        $this->assertEquals( 'dep_3', $dep1->scripts[0]->filename );
        $this->assertFalse( $dep1->scripts[0]->isRoot, 'File should not be marked isRoot' );
        $this->assertEmpty( $dep1->scripts[0]->packages );

        $this->assertCount(1, $dep2->scripts, 'Should have one dependent script');
        $this->assertEquals( 'dep_3', $dep2->scripts[0]->filename );
        $this->assertFalse( $dep2->scripts[0]->isRoot, 'File should not be marked isRoot' );
        $this->assertEmpty( $dep2->scripts[0]->packages );

        $this->assertEquals( $dep1->scripts[0]->filename, $dep2->scripts[0]->filename );
    }

    /******************************************************************
     * parseFile > 2 Dependencies, #1 and #2
     *      #1 with a dependency (#3)
     *      #2 with same dependency (#3)
     *      #3 is a root package
     * Fixture folder: 2_indep_deps_shared_package
     *****************************************************************/

    public function testParseFile_2IndepDepsSharedPackage()
    {
        $basePath = self::fixturesBasePath . '2_indep_deps_shared_package';
        $filePath = $basePath . '/main.js';

        $treeParser = new DependencyTreeParser();
        $dependencyTree = $treeParser->parseFile( $filePath );

        // Ensure root file was scanned properly
        $this->assertEquals( 'main', $dependencyTree->filename );
        $this->assertEquals( 'js', $dependencyTree->filetype );
        $this->assertEquals( $basePath, $dependencyTree->path );

        // Ensure root file (main.js) has #1 and #2 scripts but no packages (should it have a package?)
        $this->assertFalse( $dependencyTree->isRoot, 'Main file should not be marked isRoot' );
        $this->assertNotEmpty( $dependencyTree->scripts );
        $this->assertEmpty( $dependencyTree->stylesheets );
        $this->assertEmpty( $dependencyTree->packages );

        // Ensure it has #1 and #2
        $this->assertCount(2, $dependencyTree->scripts, 'Main file should have two dependent scripts' );
        $this->assertInstanceOf( 'JsPackager\File', $dependencyTree->scripts[0] );
        $this->assertInstanceOf( 'JsPackager\File', $dependencyTree->scripts[1] );
        $this->assertEquals( 'dep_1', $dependencyTree->scripts[0]->filename );
        $this->assertEquals( 'dep_2', $dependencyTree->scripts[1]->filename );

        // Shortcut for easier access and readability
        $dep1 = $dependencyTree->scripts[0];
        $dep2 = $dependencyTree->scripts[1];

        // Ensure #1 and #2 are not root packages themselves
        $this->assertFalse( $dep1->isRoot, 'Dep #1 should not be marked isRoot' );
        $this->assertFalse( $dep2->isRoot, 'Dep #2 should not be marked isRoot' );


        // Ensure dep #1 has #3 as a package
        $this->assertCount(1, $dep1->scripts, 'Dep #1 should have one dependent script');
        $this->assertEquals( 'dep_3', $dep1->scripts[0]->filename );
        $this->assertTrue( $dep1->scripts[0]->isRoot, 'Dep #3 through Dep #1 should be marked isRoot' );
        $this->assertEmpty( $dep1->scripts[0]->packages );

        // Ensure dep #2 has #3 as a package
        $this->assertCount(1, $dep2->scripts, 'Dep #2 should have one dependent script');
        $this->assertEquals( 'dep_3', $dep2->scripts[0]->filename );
        $this->assertTrue( $dep2->scripts[0]->isRoot, 'Dep #3 through Dep #2 should be marked isRoot' );
        $this->assertEmpty( $dep2->scripts[0]->packages );

        // Ensure dep #1's dependency and dep #2's dependency is the same
        $this->assertEquals( $dep1->scripts[0]->filename, $dep2->scripts[0]->filename );
    }

    /******************************************************************
     * parseFile > 3 Dependencies, #1, #2, and #3
     *      #1 with no dependency
     *      #3 with a dependency on #2
     *      #2 with no dependency
     * Fixture folder: 3_deps_1_feedback
     *****************************************************************/

    public function testParseFile_3Deps1Feedback()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback';
        $filePath = $basePath . '/main.js';

        $treeParser = new DependencyTreeParser();
        $dependencyTree = $treeParser->parseFile( $filePath );

        // Ensure root file was scanned properly
        $this->assertEquals( 'main', $dependencyTree->filename );
        $this->assertEquals( 'js', $dependencyTree->filetype );
        $this->assertEquals( $basePath, $dependencyTree->path );

        // Ensure root file (main.js) has #1 and #2 scripts but no packages
        $this->assertFalse( $dependencyTree->isRoot, 'Root file should not be marked isRoot' );
        $this->assertNotEmpty( $dependencyTree->scripts );
        $this->assertEmpty( $dependencyTree->stylesheets );
        $this->assertEmpty( $dependencyTree->packages );

        // Ensure it has #1 and #2 and #3
        $this->assertCount(3, $dependencyTree->scripts, 'Root file should have three dependent scripts' );
        $this->assertInstanceOf( 'JsPackager\File', $dependencyTree->scripts[0] );
        $this->assertInstanceOf( 'JsPackager\File', $dependencyTree->scripts[1] );
        $this->assertInstanceOf( 'JsPackager\File', $dependencyTree->scripts[2] );
        $this->assertEquals( 'dep_1', $dependencyTree->scripts[0]->filename );
        $this->assertEquals( 'dep_3', $dependencyTree->scripts[1]->filename );
        $this->assertEquals( 'dep_2', $dependencyTree->scripts[2]->filename );

        // Shortcut for easier access and readability
        $dep1 = $dependencyTree->scripts[0];
        $dep3 = $dependencyTree->scripts[1];
        $dep2 = $dependencyTree->scripts[2];

        // Ensure dependencies are not root packages themselves
        $this->assertFalse( $dep1->isRoot, 'Dep #1 should not be marked isRoot' );
        $this->assertFalse( $dep2->isRoot, 'Dep #2 should not be marked isRoot' );
        $this->assertFalse( $dep3->isRoot, 'Dep #3 should not be marked isRoot' );


        // Ensure dep #1 and #2 have no dependencies
        $this->assertCount(0, $dep1->scripts, 'Dep #1 should have no dependent script');
        $this->assertCount(0, $dep2->scripts, 'Dep #2 should have no dependent script');

        // Ensure dep #3 has #2 as a dependency
        $this->assertCount(1, $dep3->scripts, 'Dep #3 should have one dependent script');
        $this->assertEquals( 'dep_2', $dep3->scripts[0]->filename );
        $this->assertFalse( $dep3->scripts[0]->isRoot, 'File should not be marked isRoot' );
        $this->assertEmpty( $dep3->scripts[0]->packages );

        // Ensure dep #3's dependency is dependency #2
        $this->assertEquals( $dep3->scripts[0]->filename, $dep2->filename );
    }

    /******************************************************************
     * parseFile > 3 Dependencies, #1, #2, and #3
     *      #1 with own dependency (#4)
     *      #2 & #3 both share a third dependency (#5)
     *      The shared third dependency (#5) has a dependency on #1's dependency (#4)
     * Fixture folder: 3_deps_all_on_one
     *****************************************************************/

    public function testParseFile_3DepsAllOnOne()
    {
        $basePath = self::fixturesBasePath . '3_deps_all_on_one';
        $filePath = $basePath . '/main.js';

        $treeParser = new DependencyTreeParser();
        $dependencyTree = $treeParser->parseFile( $filePath );

        // Ensure root file was scanned properly
        $this->assertEquals( 'main', $dependencyTree->filename );
        $this->assertEquals( 'js', $dependencyTree->filetype );
        $this->assertEquals( $basePath, $dependencyTree->path );

        $this->assertCount( 3, $dependencyTree->scripts );
        $this->assertCount( 0, $dependencyTree->stylesheets );
        $this->assertCount( 3, $dependencyTree->scripts );

        $this->assertFalse( $dependencyTree->isRoot );

        // Shortcut for easier access and readability
        $dep1 = $dependencyTree->scripts[0];
        $dep2 = $dependencyTree->scripts[1];
        $dep3 = $dependencyTree->scripts[2];

        // Ensure dependencies are not root packages themselves
        $this->assertFalse( $dep1->isRoot, 'Dep #1 should not be marked isRoot' );
        $this->assertFalse( $dep2->isRoot, 'Dep #2 should not be marked isRoot' );
        $this->assertFalse( $dep3->isRoot, 'Dep #3 should not be marked isRoot' );

        // #1 & #5 depends on #4
        $this->assertEquals( 'dep_4', $dep1->scripts[0]->filename );

        $dep4 = $dep1->scripts[0];
        $this->assertFalse( $dep4->isRoot, 'Dep #4 should not be marked isRoot' );

        // #2 & #3 depend on #5
        $this->assertEquals( 'dep_5', $dep2->scripts[0]->filename, "Dep #2 should depend on Dep #5" );
        $this->assertEquals( 'dep_5', $dep3->scripts[0]->filename, "Dep #3 should depend on Dep #5" );

        $dep5 = $dep2->scripts[0];
        $this->assertFalse( $dep5->isRoot, 'Dep #5 should not be marked isRoot' );

        // #5 depends on #4
        $this->assertEquals( 'dep_4', $dep5->scripts[0]->filename );

        $orderMapEntry = $dependencyTree->annotationOrderMap[0];
        $this->assertEquals( 'require', $orderMapEntry['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $orderMapEntry['annotationIndex'], "Should reflect appropriate order" );
        $orderMapEntry = $dependencyTree->annotationOrderMap[1];
        $this->assertEquals( 'require', $orderMapEntry['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 1, $orderMapEntry['annotationIndex'], "Should reflect appropriate order" );
        $orderMapEntry = $dependencyTree->annotationOrderMap[2];
        $this->assertEquals( 'require', $orderMapEntry['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 2, $orderMapEntry['annotationIndex'], "Should reflect appropriate order" );

        $orderMapEntry = $dep1->annotationOrderMap[0];
        $this->assertEquals( 'require', $orderMapEntry['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $orderMapEntry['annotationIndex'], "Should reflect appropriate order" );

        $orderMapEntry = $dep2->annotationOrderMap[0];
        $this->assertEquals( 'require', $orderMapEntry['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $orderMapEntry['annotationIndex'], "Should reflect appropriate order" );

        $orderMapEntry = $dep3->annotationOrderMap[0];
        $this->assertEquals( 'require', $orderMapEntry['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $orderMapEntry['annotationIndex'], "Should reflect appropriate order" );

        $this->assertEmpty( $dep4->annotationOrderMap, "Dep #4 has no dependencies" );

        $orderMapEntry = $dep5->annotationOrderMap[0];
        $this->assertEquals( 'require', $orderMapEntry['action'], "Should reflect appropriate bucket" );
        $this->assertEquals( 0, $orderMapEntry['annotationIndex'], "Should reflect appropriate order" );

    }

    /******************************************************************
     * parseFile > 3 Dependencies, #1, #2, and #3
     *      #1 with own dependency (#4)
     *      #2 & #3 both share a third dependency (#5)
     *      The shared third dependency (#5) has a dependency on #1's dependency (#4)
     * Fixture folder: 3_deps_all_on_one_package
     *****************************************************************/

    public function testParseFile_3DepsAllOnOnePackage()
    {
        $basePath = self::fixturesBasePath . '3_deps_all_on_one_package';
        $filePath = $basePath . '/main.js';

        $treeParser = new DependencyTreeParser();
        $dependencyTree = $treeParser->parseFile( $filePath );

        // Ensure root file was scanned properly
        $this->assertEquals( 'main', $dependencyTree->filename, "Root file should be named main" );
        $this->assertEquals( 'js', $dependencyTree->filetype, "Root file should be of js filetype" );
        $this->assertEquals( $basePath, $dependencyTree->path, "Root file should be in the base path" );

        $this->assertCount( 3, $dependencyTree->scripts, "main.js should contain 3 scripts" );
        $this->assertCount( 0, $dependencyTree->packages, "main.js should contain no package" );
        $this->assertCount( 0, $dependencyTree->stylesheets, "main.js should contain no stylesheets" );

        $this->assertFalse( $dependencyTree->isRoot, "main.js should not be marked isRoot" );

        // Shortcut for easier access and readability
        $dep1 = $dependencyTree->scripts[0];
        $dep2 = $dependencyTree->scripts[1];
        $dep3 = $dependencyTree->scripts[2];

        // Ensure dependencies are not root packages themselves
        $this->assertFalse( $dep1->isRoot, 'Dep #1 should not be marked isRoot' );
        $this->assertFalse( $dep2->isRoot, 'Dep #2 should not be marked isRoot' );
        $this->assertFalse( $dep3->isRoot, 'Dep #3 should not be marked isRoot' );

        // #1 & #5 depends on #4
        $this->assertEquals( 'dep_4', $dep1->scripts[0]->filename, "Dep #1 should depend on Dep #4" );

        $dep4 = $dep1->scripts[0];
        $this->assertTrue( $dep4->isRoot, 'Dep #4 should be marked isRoot' );

        // #2 & #3 depend on #5
        $this->assertEquals( 'dep_5', $dep2->scripts[0]->filename, "Dep #2 should depend on Dep #5" );
        $this->assertEquals( 'dep_5', $dep3->scripts[0]->filename, "Dep #3 should depend on Dep #5" );

        $dep5 = $dep2->scripts[0];
        $this->assertFalse( $dep5->isRoot, 'File should not be marked isRoot' );
        $this->assertEquals( $basePath . '/dep_4.js', $dep5->packages[0], 'Dep #5 should contain package entry for Dep #4');

        // #5 depends on #4
        $this->assertEquals( 'dep_4', $dep5->scripts[0]->filename, "Dep #5 should depend on Dep #4" );
    }

    /******************************************************************
     * parseFile > Recursion (2 Dependencies, #1, #2)
     *      #1 with own dependency (#2)
     *      #2 with own dependency (#1)
     * Fixture folder: recursion
     *****************************************************************/

    public function testParseFileThrowsOnRecursion()
    {
        $basePath = self::fixturesBasePath . 'recursion';
        $filePath = $basePath . '/main.js';

        $treeParser = new DependencyTreeParser();

        try {
            $dependencyTree = $treeParser->parseFile( $filePath );
            $this->fail('Set should throw a recursion exception');
        } catch (RecursionException $e) {
            $this->assertEquals(
                RecursionException::ERROR_CODE,
                $e->getCode(),
                'Exception should contain proper error code'
            );
        }
    }

}
