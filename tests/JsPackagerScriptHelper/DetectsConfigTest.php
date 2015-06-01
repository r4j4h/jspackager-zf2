<?php

namespace JsPackagerTest;

require_once __DIR__ . '/../../vendor' . '/autoload.php';

use DateTime;
use DateTimeZone;
use PHPUnit_Framework_TestCase;
use JsPackager\Zend\Mvc\View\Helper\JsPackagerScriptHelper;

class DetectsConfigTest extends PHPUnit_Framework_TestCase
{

    protected function getConfigMissing() {
        return array();
    }

    protected function getConfigOn() {
        return array(
            'compiled' => array(
                'javascript' => 'true'
            )
        );
    }

    protected function getConfigOff() {
        return array(
            'compiled' => array(
                'javascript' => 'false'
            )
        );
    }

    public function testUsesFlagWhenMissing()
    {
        $helperInstance = new JsPackagerScriptHelper();
        $helperInstance->setConfig($this->getConfigMissing());
        $helperInstance->setUsingCompiledFiles(false);

        $constants = new Constants();
        $fixturesPath = $constants->getJsPackagerFixtures();

        $helperInstance->appendFile( $fixturesPath . 'remote_annotation/main.js');

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


        $helperInstance = new JsPackagerScriptHelper();
        $helperInstance->setUsingCompiledFiles(true);

        $constants = new Constants();
        $fixturesPath = $constants->getJsPackagerFixtures();

        $helperInstance->appendFile( $fixturesPath . 'remote_annotation/main.js');

        $styles = $helperInstance->printStylesheets();
        $scripts = $helperInstance->__toString();

        $dateTimeForNonExistentFiles =  new DateTime('now', new DateTimeZone('Africa/Nairobi') );
        $nowTimeForNonExistentFiles = $dateTimeForNonExistentFiles->getTimestamp();

        $expectedStyles = <<<WH
<link href="/shared/remotepackage/package_subfolder/local_on_remote.css?mtime={$nowTimeForNonExistentFiles}" rel="stylesheet" type="text/css" />
<link href="/shared/remotepackage/package_subfolder/remote_on_remote.css?mtime={$nowTimeForNonExistentFiles}" rel="stylesheet" type="text/css" />
<link href="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css?mtime=1433286069" rel="stylesheet" type="text/css" />
<link href="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css?mtime=1433286069" rel="stylesheet" type="text/css" />
<link href="/shared/remotescript/script_subfolder/local_on_remote.css?mtime={$nowTimeForNonExistentFiles}" rel="stylesheet" type="text/css" />
<link href="/shared/remotescript/script_subfolder/remote_on_remote.css?mtime={$nowTimeForNonExistentFiles}" rel="stylesheet" type="text/css" />
<link href="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css?mtime=1433286069" rel="stylesheet" type="text/css" />
<link href="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css?mtime=1433286069" rel="stylesheet" type="text/css" />

WH;
        $this->assertEquals($expectedStyles, $styles);

        $expectedScripts = <<<ES
<script type="text/javascript" src="/shared/remotepackage/script.compiled.js?mtime={$nowTimeForNonExistentFiles}"></script>
<script type="text/javascript" src="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/main.compiled.js?mtime=1433286069"></script>

ES;
        $this->assertEquals($expectedScripts, $scripts);

    }

    public function testWhenOff()
    {
        $helperInstance = new JsPackagerScriptHelper();
        $helperInstance->setConfig($this->getConfigOff());
        $constants = new Constants();
        $fixturesPath = $constants->getJsPackagerFixtures();

        $helperInstance->appendFile( $fixturesPath . 'remote_annotation/main.js');

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

    public function testWhenOn()
    {
        $helperInstance = new JsPackagerScriptHelper();
        $helperInstance->setConfig($this->getConfigOn());

        $constants = new Constants();
        $fixturesPath = $constants->getJsPackagerFixtures();

        $helperInstance->appendFile( $fixturesPath . 'remote_annotation/main.js');

        $styles = $helperInstance->printStylesheets();
        $scripts = $helperInstance->__toString();

        $dateTimeForNonExistentFiles =  new DateTime('now', new DateTimeZone('Africa/Nairobi') );
        $nowTimeForNonExistentFiles = $dateTimeForNonExistentFiles->getTimestamp();

        $expectedStyles = <<<WH
<link href="/shared/remotepackage/package_subfolder/local_on_remote.css?mtime={$nowTimeForNonExistentFiles}" rel="stylesheet" type="text/css" />
<link href="/shared/remotepackage/package_subfolder/remote_on_remote.css?mtime={$nowTimeForNonExistentFiles}" rel="stylesheet" type="text/css" />
<link href="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/stylesheet_before.css?mtime=1433286069" rel="stylesheet" type="text/css" />
<link href="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_before.css?mtime=1433286069" rel="stylesheet" type="text/css" />
<link href="/shared/remotescript/script_subfolder/local_on_remote.css?mtime={$nowTimeForNonExistentFiles}" rel="stylesheet" type="text/css" />
<link href="/shared/remotescript/script_subfolder/remote_on_remote.css?mtime={$nowTimeForNonExistentFiles}" rel="stylesheet" type="text/css" />
<link href="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/local_subfolder/local_subfolder_after.css?mtime=1433286069" rel="stylesheet" type="text/css" />
<link href="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/stylesheet_after.css?mtime=1433286069" rel="stylesheet" type="text/css" />

WH;
        $this->assertEquals($expectedStyles, $styles);

        $expectedScripts = <<<ES
<script type="text/javascript" src="/shared/remotepackage/script.compiled.js?mtime={$nowTimeForNonExistentFiles}"></script>
<script type="text/javascript" src="/vendor/r4j4h/jspackager-test/tests/JsPackager/fixtures/remote_annotation/main.compiled.js?mtime=1433286069"></script>

ES;
        $this->assertEquals($expectedScripts, $scripts);

    }
}