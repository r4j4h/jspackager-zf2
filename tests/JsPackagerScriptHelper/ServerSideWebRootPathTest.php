<?php

namespace JsPackagerTest;

require_once __DIR__ . '/../../vendor' . '/autoload.php';

use PHPUnit_Framework_TestCase;
use JsPackager\Zend\Mvc\View\Helper\JsPackagerScriptHelper;

class ServerSideWebRootPathTest extends PHPUnit_Framework_TestCase
{

    public function testUsesConfiguredServerSideWebRootPath()
    {
        $helperInstance = new JsPackagerScriptHelper();
        $helperInstance->setUsingCompiledFiles(false);

        $constants = new Constants();
        $fixturesPath = $constants->getJsPackagerFixtures();

        $helperInstance->appendFile( $fixturesPath . 'remote_annotation/main.js');

        $helperInstance->setServerSideWebRootPath('www-files');

        $styles = $helperInstance->printStylesheets();
        $scripts = $helperInstance->__toString();

        $expectedStyles = <<<WH
<link href="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css" rel="stylesheet" type="text/css" />
<link href="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css" rel="stylesheet" type="text/css" />
<link href="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css" rel="stylesheet" type="text/css" />
<link href="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css" rel="stylesheet" type="text/css" />

WH;
        $this->assertEquals($expectedStyles, $styles);

        $expectedScripts = <<<ES
<script type="text/javascript" src="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/local_file_before.js"></script>
<script type="text/javascript" src="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.js"></script>
<script type="text/javascript" src="/shared/remotescript/script.js"></script>
<script type="text/javascript" src="/shared/remotepackage/script.js"></script>
<script type="text/javascript" src="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.js"></script>
<script type="text/javascript" src="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/local_file_after.js"></script>
<script type="text/javascript" src="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/main.js"></script>

ES;
        $this->assertEquals($expectedScripts, $scripts);

    }

    public function testHandlesEmptyServerSideWebRootPath()
    {
        $helperInstance = new JsPackagerScriptHelper();
        $helperInstance->setUsingCompiledFiles(false);

        $constants = new Constants();
        $fixturesPath = $constants->getJsPackagerFixtures();

        $helperInstance->appendFile( $fixturesPath . 'remote_annotation/main.js');

        $helperInstance->setServerSideWebRootPath('');

        $styles = $helperInstance->printStylesheets();
        $scripts = $helperInstance->__toString();

        $expectedStyles = <<<WH
<link href="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css" rel="stylesheet" type="text/css" />
<link href="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css" rel="stylesheet" type="text/css" />
<link href="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css" rel="stylesheet" type="text/css" />
<link href="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css" rel="stylesheet" type="text/css" />

WH;
        $this->assertEquals($expectedStyles, $styles);

        $expectedScripts = <<<ES
<script type="text/javascript" src="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/local_file_before.js"></script>
<script type="text/javascript" src="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.js"></script>
<script type="text/javascript" src="/shared/remotescript/script.js"></script>
<script type="text/javascript" src="/shared/remotepackage/script.js"></script>
<script type="text/javascript" src="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.js"></script>
<script type="text/javascript" src="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/local_file_after.js"></script>
<script type="text/javascript" src="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/main.js"></script>

ES;
        $this->assertEquals($expectedScripts, $scripts);

    }

}