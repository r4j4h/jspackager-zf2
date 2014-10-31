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
        "r4j4h/jspackager-test": "1.0.x-dev",
        "r4j4h/jspackager-zf2": "1.0.x-dev"
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
```

3. Add an output to your layout where script/style tags should go.

```
<?php echo $this->scriptFile() ?>
```

4. Add scripts that use @require.

```
$this->scriptFile()->appendFile('js/index.js');
```

For example, if index.js contained `// @require bar/baz.js` then the layout should have:

<script type="text/javascript" src="/js/bar/baz.js"></script>
<script type="text/javascript" src="/js/index.js"></script>