jspackager-zf2
==============

How To Use
-----------

1. Pull in via composer

```
    "repositories": [
        {
            "type": "composer",
            "url": "https://packages.zendframework.com/"
        },
        {
            "type": "vcs",
            "url": "git@github.com:r4j4h/jspackager-test"
        },
        {
            "type": "vcs",
            "url": "git@github.com:r4j4h/jspackager-zf2"
        }
    ],
    "require": {
        "php": ">=5.3.3",
        "zendframework/zendframework": "2.3.*",
        "r4j4h/jspackager-test": "~1.0@dev",
        "r4j4h/jspackager-zf2": "~1.0@dev"
    },
```


2. Add to Zend 2 Module config

```
'view_helpers' => array(
    'factories' => array(
        'scriptFile' => function($sm) {
            $helper = new JsPackager\Zend\Mvc\View\Helper\ScriptFile();
            // do stuff with $sm or the $helper
            return $helper;
        },
        'styleFile' => function($sm) {
            $helper = new JsPackager\Zend\Mvc\View\Helper\StyleFile();
            // do stuff with $sm or the $helper
            return $helper;
        },
        'scriptVars' => function($sm) {
            $helper = new JsPackager\Zend\Mvc\View\Helper\ScriptVars();
            // do stuff with $sm or the $helper
            return $helper;
        },
    ),
),


'ScriptVars' => array(
    /**
     * Destination for the scriptVars `page` object.
     * In this example it would be found at `myGlobalNamespace.page`.
     * Set to false or do not include for "page" variable to be directly attached to the global (e.g. window.page in browserland)
     */
    'prefix' => 'myGlobalNamespace' // Set to false or do not include for "page" variable to be the global
),

```

or

```
'view_helpers' => array(
    'invokables' => array(
        'ScriptFile' => 'JsPackager\Zend\Mvc\View\Helper\ScriptFile',
        'StyleFile'  => 'JsPackager\Zend\Mvc\View\Helper\StyleFile',
        'ScriptVars'  => 'JsPackager\Zend\Mvc\View\Helper\ScriptVars',
    ),
),

'ScriptVars' => array(
    /**
     * Destination for the scriptVars `page` object.
     * In this example it would be found at `myGlobalNamespace.page`.
     * Set to false or do not include for "page" variable to be directly attached to the global (e.g. window.page in browserland)
     */
    'prefix' => 'myGlobalNamespace' // Set to false for "page" to be global
),
```

3. Add an output to your layout where script/style tags should go.

```
<?php echo $this->scriptFile() ?>
```

4. Add scripts that use @require.

```
$this->scriptFile()->appendFile('js/index.js');
```

For example, if index.js contained `// @require bar/baz.js` then the generated layout should have:

```
<script type="text/javascript" src="/js/bar/baz.js"></script>
<script type="text/javascript" src="/js/index.js"></script>
```





----

Also here are Zend 2 config options:

```

    /**
     * CDN Configuration Section
     */
    'cdn' => array(

        /**
         * CDN Protocol, Domain and URL string
         *
         * NOTE: This should not have a trailing slash.
         *
         * Prepended to a shared file's path to switch from local access to CDN access.
         */
        'url' => 'URL_CDN', // Update to relevant CDN.


        /**
         * Location from base url that shared files are located on the CDN.
         *
         * NOTE: This should not have a beginning or trailing slash, but may
         * include inner slashes to reflect subfolders.
         *
         * Locally this might be like with 'shared':
         * <cdn-url>/<cdn-shared-path>/<file-url>        - Shared JS (CDN)
         */
        'cdn_shared_path' => 'shared',
    ),


    /**
     * Compiled Scripts switch.
     *
     * If true, each raw dependency is included not compiled.
     * If false, compiled files and their compiled dependencies are loaded.
     */
    'use_compiled_scripts' => false,

    /**
     * Compiled Scripts Fallback switch.
     *
     * Occurs when use_compiled_scripts is set to true and a compiled script is not found...
     *
     * If true, the raw source script is loaded and read as if use_compiled_scripts was set to false for that file.
     * If false, an error is thrown.
     */
    'fallback_if_missing_compiled_script' => false,

    /**
     * CDN/Shared mode switch.
     *
     * If true, shared files are pulled in from CDN
     * If false, shared file are pulled in from local shared EMRJS folder
     */
    'use_cdn_for_shared' => false,



    /**
     * Cache Bust switch.
     *
     * If true, files are suffixed with a cache bust query parameter to prevent caching from preventing a user
     * from getting an updated version.
     * If false, files are not given any special treatment.
     */
    'use_cache_busting' => false,

    /**
     * Configuration related to Cache Busting
     */
    'cache_busting' => array(

        /**
         * Cache Bust Strategy
         *
         * Used to determine the value to use for cache bust.
         * E.g. given some_file.thing?_cacheBust=13, "13" is the query value
         *
         * Valid values are:
         *      'constant'   - Use a file's modified time as the query value
         *      'mtime'      - Use cache_busting.constant_value as the query value
         */
        'strategy' => 'constant',

        /**
         * Constant value to use as the cache bust value
         *
         * E.g. given some_file.thing?_cacheBust=13, "13" is the query value
         */
        'constant_value' => 1,

        /**
         * The string used as the default query key in URLs.
         *
         * E.g. given some_file.thing?_cacheBust=13, "_cacheBust" is the query key
         */
        'key_string' => '_cachebust',

    ),


```

TODO
- Provide example layout, or link to a repo that uses with example layout file
