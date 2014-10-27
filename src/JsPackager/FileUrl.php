<?php
/**
 * The FileUrl class takes a path to a Js or Css file, and depending on configuration, returns a Url.
 *
 * @package JsPackager
 */

namespace JsPackager;

use Zend\Http\PhpEnvironment\Request as ZendRequest;

class Zend2FileUrl extends FileUrl
{

    /**
     * Returns the baseUrl for building paths to stylesheet URL.
     * @return string
     */
    public static function getBaseUrl()
    {
        $request = new ZendRequest();
        return $request->getBaseUrl();
    }

}