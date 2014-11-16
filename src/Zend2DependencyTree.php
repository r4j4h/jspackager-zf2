<?php
/**
 * The FileUrl class takes a path to a Js or Css file, and depending on configuration, returns a Url.
 *
 * @package JsPackager
 */

namespace JsPackager;

use Psr\Log\LoggerInterface;

class Zend2DependencyTree extends DependencyTree
{

    /**
     * @var String
     */
    private $nonCdnSharedFolder;

    /**
     * Constructor for DependencyTree
     * @param string $filePath
     * @param string $testsSourcePath Optional. For @tests annotations, the source scripts root path with no trailing
     * slash.
     * @param bool $muteMissingFileExceptions Optional. If true, missing file exceptions will not be thrown and
     * will be carried through as if they were there. Note: Obviously they will not be parsed for children.
     * @param LoggerInterface $logger
     * @throws Exception\Recursion If the dependent files have a circular dependency
     * @throws Exception\MissingFile Through internal File object if $filePath does not point to a valid file
     */
    public function __construct( $filePath, $testsSourcePath = null, $muteMissingFileExceptions = false, LoggerInterface $logger = null, $nonCdnSharedFolder ) {

        $this->nonCdnSharedFolder = $nonCdnSharedFolder;

        parent::__construct($filePath, $testsSourcePath, $muteMissingFileExceptions, $logger);
    }

    /**
     * Get a DependencyTreeParser
     *
     * @return DependencyTreeParser
     */
    protected function getDependencyTreeParser()
    {
        $treeParser = parent::getDependencyTreeParser();
        $treeParser->sharedFolderPath = $this->nonCdnSharedFolder;
        return $treeParser;
    }

}