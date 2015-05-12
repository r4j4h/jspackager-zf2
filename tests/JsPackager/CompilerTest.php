<?php

namespace JsPackager;

use JsPackager\File;
use JsPackager\DependencyTree;
use JsPackager\Compiler;
use JsPackager\Exception\Recursion as RecursionException;
use JsPackager\Exception\MissingFile as MissingFileException;

use JsPackager\Helpers\Reflection as ReflectionHelper;

/**
 * Class CompilerTest
 * @group      JsPackager
 */
class CompilerTest extends \PHPUnit_Framework_TestCase
{
    // Tests are run from project root
    const fixturesBasePath = 'tests/JsPackager/fixtures/';


    /******************************************************************
     * getCompiledFilename
     *****************************************************************/

    public function testGetCompiledFilename()
    {
        $compiler = new Compiler();
        $filename = 'some_file.js';

        $compiledFilename = $compiler->getCompiledFilename( $filename );
        $this->assertEquals( 'some_file.compiled.js', $compiledFilename );
    }

    public function testGetCompiledFilenameDoesNotHarmPaths()
    {
        $compiler = new Compiler();
        $filename = '../some/directory/and/some_file.js';

        $compiledFilename = $compiler->getCompiledFilename( $filename );
        $this->assertEquals( '../some/directory/and/some_file.compiled.js', $compiledFilename );
    }

    public function testGetCompiledFilenameIgnoresImproperFile()
    {
        $compiler = new Compiler();
        $filename = 'some_file.css';

        $compiledFilename = $compiler->getCompiledFilename( $filename );
        $this->assertEquals( 'some_file.css', $compiledFilename );
    }

    public function testGetCompiledFilenameDoesNotHarmOddNamedFile()
    {
        $compiler = new Compiler();
        $filename = 'some.js.file.js';

        $compiledFilename = $compiler->getCompiledFilename( $filename );
        $this->assertEquals( 'some.js.file.compiled.js', $compiledFilename );
    }

    /******************************************************************
     * getManifestFilename
     *****************************************************************/

    public function testGetManifestFilename()
    {
        $compiler = new Compiler();
        $filename = 'some_file.js';

        $manifestFilename = $compiler->getManifestFilename( $filename );
        $this->assertEquals( 'some_file.js.manifest', $manifestFilename );
    }

    public function testGetManifestFilenameDoesNotHarmPaths()
    {
        $compiler = new Compiler();
        $filename = '../some/directory/and/some_file.js';

        $manifestFilename = $compiler->getManifestFilename( $filename );
        $this->assertEquals( '../some/directory/and/some_file.js.manifest', $manifestFilename );
    }

    public function testGetManifestFilenameIgnoresImproperFile()
    {
        $compiler = new Compiler();
        $filename = 'some_file.css';

        $manifestFilename = $compiler->getManifestFilename( $filename );
        $this->assertEquals( 'some_file.css', $manifestFilename );
    }

    public function testGetManifestFilenameDoesNotHarmOddNamedFile()
    {
        $compiler = new Compiler();
        $filename = 'some.js.file.js';

        $manifestFilename = $compiler->getManifestFilename( $filename );
        $this->assertEquals( 'some.js.file.js.manifest', $manifestFilename );
    }



    /******************************************************************
     * concatenateFiles
     *****************************************************************/

    public function testConcatenateFilesHandlesNoFiles()
    {
        $compiler = new Compiler();
        $dependencies = array();

        $concatenatedFile = ReflectionHelper::invoke( $compiler, 'concatenateFiles', array( $dependencies ) );

        $this->assertEquals( '', $concatenatedFile, "Concatenated file should be empty" );
    }

    public function testConcatenateFilesHandlesOneFile()
    {
        $basePath = self::fixturesBasePath . '0_deps';
        $mainJsPath = $basePath . '/main.js';

        $mainContents = file_get_contents( $mainJsPath );

        $compiler = new Compiler();
        $dependencies = array(
            $mainJsPath,
        );

        $concatenatedFile = ReflectionHelper::invoke( $compiler, 'concatenateFiles', array( $dependencies ) );

        $this->assertEquals(
            $mainContents,
            $concatenatedFile,
            "Concatenated file should contain the given file's contents"
        );
    }

    public function testConcatenateFilesConcatenatesManyFiles()
    {
        $basePath = self::fixturesBasePath . '1_dep';
        $dep1JsPath = $basePath . '/dep_1.js';
        $mainJsPath = $basePath . '/main.js';

        $dep1Contents = file_get_contents( $dep1JsPath );
        $mainContents = file_get_contents( $mainJsPath );

        $compiler = new Compiler();
        $dependencies = array(
            $dep1JsPath,
            $mainJsPath,
        );

        $concatenatedFile = ReflectionHelper::invoke( $compiler, 'concatenateFiles', array( $dependencies ) );

        $this->assertEquals(
            $dep1Contents . $mainContents,
            $concatenatedFile,
            "Concatenated file should contain both given file's contents in order"
        );
    }

    public function testConcatenateFilesThrowsOnMissingFile()
    {
        $basePath = self::fixturesBasePath . '1_dep';
        $dep1JsPath = $basePath . '/dep_1.js';
        $mainJsPath = $basePath . '/main.js.not.real'; // Second file will be broken

        $compiler = new Compiler();
        $dependencies = array(
            $dep1JsPath,
            $mainJsPath,
        );

        try {
            $concatenatedFile = ReflectionHelper::invoke( $compiler, 'concatenateFiles', array( $dependencies ) );
            $this->fail('Set should throw a missing file exception');
        } catch (MissingFileException $e) {
            $this->assertEquals(
                'tests/JsPackager/fixtures/1_dep/main.js.not.real',
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


    /******************************************************************
     * generateClosureCompilerCommandString
     *****************************************************************/

    /**
     * @runInSeparateProcess
     */
    public function testGenerateClosureCompilerCommandStringRespectsCompilationLevel()
    {
        $basePath = self::fixturesBasePath . '1_dep';
        $mainJsPath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $mainJsPath );

        $roots = $dependencyTree->getDependencySets();

        $compiler = new Compiler();

        // Grab first dependency set
        $dependencySet = $roots[0];

        $commandString = ReflectionHelper::invoke(
            $compiler,
            'generateClosureCompilerCommandString',
            array( $dependencySet->dependencies )
        );

        $this->assertContains(
            '--compilation_level=' . $compiler::GCC_COMPILATION_LEVEL,
            $commandString,
            "Command string should set the compilation level to that of the class's constant"
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testGenerateClosureCompilerCommandStringIncludesGivenFiles()
    {
        $basePath = self::fixturesBasePath . '1_dep';
        $mainJsPath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $mainJsPath );

        $roots = $dependencyTree->getDependencySets();

        $compiler = new Compiler();

        // Grab first dependency set
        $dependencySet = $roots[0];

        $commandString = ReflectionHelper::invoke(
            $compiler,
            'generateClosureCompilerCommandString',
            array( $dependencySet->dependencies )
        );

        foreach( $dependencySet->dependencies as $dependency )
        {
            $this->assertContains(
                "--js \"{$dependency}\"",
                $commandString
            );

        }
    }

    /**
     * @runInSeparateProcess
     */
    public  function testGenerateClosureCompilerCommandStringUsesDetailLevel3()
    {
        $basePath = self::fixturesBasePath . '1_dep';
        $mainJsPath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $mainJsPath );

        $roots = $dependencyTree->getDependencySets();

        $compiler = new Compiler();

        // Grab first dependency set
        $dependencySet = $roots[0];

        $commandString = ReflectionHelper::invoke(
            $compiler,
            'generateClosureCompilerCommandString',
            array( $dependencySet->dependencies )
        );

        $this->assertContains(
            '--summary_detail_level=3',
            $commandString,
            "Command string should set summary_detail_level to 3"
        );
    }


    /******************************************************************
     * compileDependencySet
     *****************************************************************/

    /**
     * @runInSeparateProcess
     */
    public function testCompileDependencySetHandlesDependenciesWithoutPackages()
    {
        $basePath = self::fixturesBasePath . '1_dep';
        $mainJsPath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $mainJsPath );

        $roots = $dependencyTree->getDependencySets();

        $compiler = new Compiler();

        // Grab first dependency set
        $dependencySet = $roots[0];

        $result = $compiler->compileDependencySet( $dependencySet );

        $compiledFilesContents = 'window.dep_1=!0;window.root_test="the pooh!";' . PHP_EOL;

        $this->assertInstanceOf( 'JsPackager\Compiler\CompiledFile', $result );
        $this->assertEquals( $basePath, $result->path, "Compiled path should default to input path" );
        $this->assertEquals(
            'main.compiled.js',
            $result->filename,
            "Compiled file should default to be named after input filename"
        );
        $this->assertEquals(
            'main.js.manifest',
            $result->manifestFilename,
            "Manifest filename should default to be named after input filename"
        );
        $this->assertEquals( $compiledFilesContents, $result->contents, "Compiled file should contain both files" );
        $this->assertEquals( '', $result->manifestContents, "Should have an empty manifest" );
    }

    /**
     * @runInSeparateProcess
     */
    public function testCompileDependencySetHandlesDependenciesWithPackages()
    {
        $basePath = self::fixturesBasePath . '2_deps_2_package_2_deep';
        $mainJsPath = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $mainJsPath );

        $roots = $dependencyTree->getDependencySets();

        $compiler = new Compiler();

        // Grab first dependency set
        $dependencySet = $roots[0];
        $result = $compiler->compileDependencySet( $dependencySet );

        $compiledFilesContents = "window.dep_5=!0;window.dep_4=!0;" . PHP_EOL;
        $manifestContents = <<<MANIFEST
dep_4_style.css

MANIFEST;

        $this->assertEquals( $basePath . '/package/subpackage', $result->path, "Compiled path should be dep_4's path" );
        $this->assertEquals(
            'dep_4.compiled.js',
            $result->filename,
            "Compiled file should be from dep_4.js"
        );
        $this->assertEquals(
            'dep_4.js.manifest',
            $result->manifestFilename,
            "Manifest filename should be from dep_4"
        );
        $this->assertEquals( $compiledFilesContents, $result->contents, "Compiled file should contain minified files" );
        $this->assertEquals( $manifestContents, $result->manifestContents, "Manifest file should contain dependent files" );


        // Grab second dependency set
        $dependencySet = $roots[1];
        $result = $compiler->compileDependencySet( $dependencySet );

        $compiledFilesContents = "window.dep_3=!0;" . PHP_EOL;
        $manifestContents = <<<MANIFEST
dep_3_style.css
subpackage/dep_4.compiled.js

MANIFEST;

        $this->assertEquals( $basePath . '/package', $result->path, "Compiled path should be dep_3's path" );
        $this->assertEquals(
            'dep_3.compiled.js',
            $result->filename,
            "Compiled file should be from dep_3.js"
        );
        $this->assertEquals(
            'dep_3.js.manifest',
            $result->manifestFilename,
            "Manifest filename should be from dep_3"
        );
        $this->assertEquals( $compiledFilesContents, $result->contents, "Compiled file should contain minified files" );
        $this->assertEquals( $manifestContents, $result->manifestContents, "Manifest file should contain dependent files" );


        // Grab third (and last) dependency set
        $dependencySet = $roots[2];
        $result = $compiler->compileDependencySet( $dependencySet );

        $compiledFilesContents = "window.dep_1=!0;window.dep_2=!0;window.main=!0;" . PHP_EOL;
        $manifestContents = 'package/dep_3.compiled.js' . PHP_EOL;

        $this->assertEquals( $basePath, $result->path, "Compiled path should be main.js's path" );
        $this->assertEquals(
            'main.compiled.js',
            $result->filename,
            "Compiled file should be from main.js"
        );
        $this->assertEquals(
            'main.js.manifest',
            $result->manifestFilename,
            "Manifest filename should be from main"
        );
        $this->assertEquals( $compiledFilesContents, $result->contents, "Compiled file should contain minified files" );
        $this->assertEquals( $manifestContents, $result->manifestContents, "Manifest file should contain dependent files" );
    }


    /******************************************************************
     * getDependencySets
     *****************************************************************/

    public function testGetDependencySetsReturnsArrayOfDependencySets()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_packages';
        $index = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $index );
        $roots = $dependencyTree->getDependencySets();

        $this->assertInstanceOf( 'JsPackager\Compiler\DependencySet', $roots[0], "Should be a DependencySet" );
        $this->assertInstanceOf( 'JsPackager\Compiler\DependencySet', $roots[1], "Should be a DependencySet" );
        $this->assertInstanceOf( 'JsPackager\Compiler\DependencySet', $roots[2], "Should be a DependencySet" );
    }

    public function testGetDependencySetsDoesNotIncludeRedundantDependencySets()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_packages';
        $index = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $index );
        $roots = $dependencyTree->getDependencySets();

        $this->assertEquals(3, count($roots), "Should contain 3 dependency sets");
        $this->assertEmpty(    $roots[0]->packages, "Should depend on nothing" );
        $this->assertContains( $basePath . '/dep_2.js', $roots[0]->dependencies, "Should include dep_2.js" );
        $this->assertContains( $basePath . '/dep_2.js', $roots[1]->packages, "Should depend on dep_2.js" );
        $this->assertContains( $basePath . '/dep_3.js', $roots[1]->dependencies, "Should include dep_3.js" );
        $this->assertContains( $basePath . '/dep_2.js', $roots[2]->packages, "Should depend on dep_2.js" );
        $this->assertContains( $basePath . '/dep_3.js', $roots[2]->packages, "Should depend on dep_3.js" );
        $this->assertContains( $basePath . '/dep_1.js', $roots[2]->dependencies, "Should include dep_1.js" );
        $this->assertContains( $basePath . '/main.js', $roots[2]->dependencies, "Should include main.js" );
    }

    public function testGetDependencySetsIncludesDependencySetsInOrder()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_packages';
        $index = $basePath . '/main.js';

        $dependencyTree = new DependencyTree( $index );
        $roots = $dependencyTree->getDependencySets();

        $this->assertEquals(3, count($roots), "Should contain 3 dependency sets");
        $this->assertEmpty(  $roots[0]->packages, "Should depend on nothing" );
        $this->assertEquals( $basePath . '/dep_2.js', $roots[0]->dependencies[0], "Should include dep_2.js" );
        $this->assertEquals( $basePath . '/dep_2.js', $roots[0]->dependencies[0], "Should include dep_2.js" );
        $this->assertEquals( $basePath . '/dep_2.js', $roots[1]->packages[0], "Should depend on dep_2.js" );
        $this->assertEquals( $basePath . '/dep_2.js', $roots[1]->packages[0], "Should depend on dep_2.js" );
        $this->assertEquals( $basePath . '/dep_3.js', $roots[1]->dependencies[0], "Should include dep_3.js" );
        $this->assertEquals( $basePath . '/dep_3.js', $roots[2]->packages[0], "Should depend on dep_3.js first" );
        $this->assertEquals( $basePath . '/dep_2.js', $roots[2]->packages[1], "Should depend on dep_2.js second" );
        $this->assertEquals( $basePath . '/dep_1.js', $roots[2]->dependencies[0], "Should include dep_1.js" );
        $this->assertEquals( $basePath . '/main.js', $roots[2]->dependencies[1], "Should include main.js" );
    }

    /******************************************************************
     * compileAndWriteFilesAndManifests
     *****************************************************************/


    /******************************************************************
     * compileFileListUsingClosureCompilerJar
     *****************************************************************/

    /******************************************************************
     * clearPackages
     *****************************************************************/


    /******************************************************************
     * parseFolderForSourceFiles
     *****************************************************************/

    public function testParseFolderForSourceFilesDetectsJsFilesByFullPath()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_packages';
        $absBasePath = realpath( $basePath );

        $compiler = new Compiler();
        $result = $compiler->parseFolderForSourceFiles( $basePath );

        $this->assertEquals(4, count($result), "Should contain 4 files");
        $this->assertContains( $absBasePath . '/dep_1.js', $result, "Should detect dep_1.js" );
        $this->assertContains( $absBasePath . '/dep_2.js', $result, "Should detect dep_2.js" );
        $this->assertContains( $absBasePath . '/dep_3.js', $result, "Should detect dep_3.js" );
        $this->assertContains( $absBasePath . '/main.js', $result, "Should detect main.js" );
    }

    public function testParseFolderForSourceFilesIgnoresCompiledFiles()
    {
        $basePath = self::fixturesBasePath . '3_deps_1_feedback_shared_packages';
        $absBasePath = realpath( $basePath );

        $compiler = new Compiler();
        $result = $compiler->parseFolderForSourceFiles( $basePath );

        $this->assertEquals(4, count($result), "Should contain 4 files");
        $this->assertNotContains( $absBasePath . '/dep_1.compiled.js', $result, "Should not detect dep_1.compiled.js" );
        $this->assertNotContains( $absBasePath . '/dep_3.compiled.js', $result, "Should not detect dep_3.compiled.js" );
        $this->assertNotContains( $absBasePath . '/main.compiled.js', $result, "Should not detect main.compiled.js" );
        $this->assertNotContains( $absBasePath . '/main.js.manifest', $result, "Should not detect main.js.manifest" );
    }


}
