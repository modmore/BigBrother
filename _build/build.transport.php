<?php
/**
 * bigbrother build script
 *
 * @package bigbrother
 * @subpackage build
 */
$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$tstart = $mtime;
set_time_limit(0);

function getSnippetContent($path, $name, $debug = false) {
    $name = ($debug) ? 'debug.'. $name .'.php' : $name .'.php';
    $filename = $path . $name;
    $o = file_get_contents($filename);
    $o = str_replace('<?php','',$o);
    $o = str_replace('?>','',$o);
    $o = trim($o);
    return $o;
}

if (!defined('MOREPROVIDER_BUILD')) {
    /* define version */
    define('PKG_NAME', 'BigBrother');
    define('PKG_NAMESPACE', strtolower(PKG_NAME));
    define('PKG_VERSION', '3.1.0');
    define('PKG_RELEASE', 'pl');

    /* load modx */
    require_once dirname(dirname(__FILE__)) . '/config.core.php';
    require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
    $modx= new modX();
    $modx->initialize('mgr');
    $modx->setLogLevel(modX::LOG_LEVEL_INFO);
    $modx->setLogTarget('ECHO');

    echo '<pre>';
    flush();
    $targetDirectory = dirname(dirname(__FILE__)) . '/_packages/';
}
else {
    $targetDirectory = MOREPROVIDER_BUILD_TARGET;
}

$root = dirname(dirname(__FILE__)).'/';
$sources= array (
    'root' => $root,
    'build' => $root .'_build/',
    'events' => $root . '_build/events/',
    'resolvers' => $root . '_build/resolvers/',
    'validators' => $root . '_build/validators/',
    'data' => $root . '_build/data/',
    'plugins' => $root.'_build/elements/plugins/',
    'snippets' => $root.'_build/elements/snippets/',
    'source_core' => $root.'core/components/'.PKG_NAMESPACE,
    'source_assets' => $root.'assets/components/'.PKG_NAMESPACE,
    'lexicon' => $root . 'core/components/'.PKG_NAMESPACE.'/lexicon/',
    'docs' => $root.'core/components/'.PKG_NAMESPACE.'/docs/',
    'model' => $root.'core/components/'.PKG_NAMESPACE.'/model/',
);
unset($root);

if (file_exists(__DIR__ . '/build.config.php')) {
    require_once __DIR__ . '/build.config.php';
}
if (!defined('GAPI_CLIENT_ID')) {
    echo "Missing GAPI_CLIENT_ID constant, make sure to provide a _build/build.config.php file based on the provided sample.\n";
    return;
}

$modx->loadClass('transport.modPackageBuilder','',false, true);
$builder = new modPackageBuilder($modx);
$builder->directory = $targetDirectory;
$builder->createPackage(PKG_NAMESPACE,PKG_VERSION,PKG_RELEASE);
$builder->registerNamespace(PKG_NAMESPACE,false,true,'{core_path}components/'.PKG_NAMESPACE.'/', '{assets_path}components/'.PKG_NAMESPACE.'/');

$builder->package->put(
    [
        'source' => $sources['source_core'],
        'target' => "return MODX_CORE_PATH . 'components/';",
    ],
    [
        xPDOTransport::ABORT_INSTALL_ON_VEHICLE_FAIL => true,
        'vehicle_class' => 'xPDOFileVehicle',
        'validate' => [
            [
                'type' => 'php',
                'source' => $sources['validators'] . 'requirements.script.php'
            ]
        ]
    ]
);
$modx->log(modX::LOG_LEVEL_INFO,'Packaged in core and requirements validator.'); flush();

/**
 * Access Policy Templates & Access Policies
 */
$modx->log(modX::LOG_LEVEL_INFO,'Packaging in Access Policy Templates...');
$templates = include $sources['data'].'transport.policy_templates.php';
$attributes = [
    xPDOTransport::PRESERVE_KEYS => true,
    xPDOTransport::UNIQUE_KEY => ['name'],
    xPDOTransport::UPDATE_OBJECT => true,
    xPDOTransport::RELATED_OBJECTS => true,
    xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [
        'Permissions' => [
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::UNIQUE_KEY => ['template','name'],
        ],
        'Policies' => [
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::UNIQUE_KEY => ['template','name'],
        ],
    ]
];
if (is_array($templates)) {
    foreach ($templates as $template) {
        $vehicle = $builder->createVehicle($template,$attributes);
        $builder->putVehicle($vehicle);
    }
    $modx->log(\modX::LOG_LEVEL_INFO,'Packaged in '.count($templates).' Access Policy Templates.'); flush();
} else {
    $modx->log(\modX::LOG_LEVEL_ERROR,'Could not package in Access Policy Templates.');
}
unset ($templates, $template, $attributes);


$builder->package->put(
    [
        'source' => $sources['source_assets'],
        'target' => "return MODX_ASSETS_PATH . 'components/';",
    ],
    [
        'vehicle_class' => 'xPDOFileVehicle',
        'resolve' => [
            [
                'type' => 'php',
                'source' => $sources['resolvers'] . 'removeoldfiles.resolver.php',
            ],
            [
                'type' => 'php',
                'source' => $sources['resolvers'] . 'dependencies.resolver.php',
            ],
            [
                'type' => 'php',
                'source' => $sources['resolvers'] . 'policies.resolver.php',
            ]
        ],
    ]
);
$modx->log(modX::LOG_LEVEL_INFO,'Packaged in assets and removeoldfiles resolver.'); flush();

/* Load system settings */
$modx->log(modX::LOG_LEVEL_INFO,'Packaging in System Settings...');
$settings = include $sources['data'].'transport.settings.php';
if (empty($settings)) $modx->log(modX::LOG_LEVEL_ERROR,'Could not package in settings.');
$attributes= array(
    xPDOTransport::UNIQUE_KEY => 'key',
    xPDOTransport::PRESERVE_KEYS => true,
    xPDOTransport::UPDATE_OBJECT => false,
);
foreach ($settings as $setting) {
    $vehicle = $builder->createVehicle($setting,$attributes);
    $builder->putVehicle($vehicle);
}
$modx->log(modX::LOG_LEVEL_INFO,'Packaged in '.count($settings).' system settings.'); flush();
unset($settings,$setting,$attributes);


/* Load Dashboard Widgets */
$modx->log(modX::LOG_LEVEL_INFO,'Packaging in Dashboard Widgets...');
$widgets = include $sources['data'].'transport.dashboard_widgets.php';
if (empty($widgets)) $modx->log(modX::LOG_LEVEL_ERROR,'Could not package in widgets.');
$attributes = array(
    xPDOTransport::PRESERVE_KEYS => false,
    xPDOTransport::UPDATE_OBJECT => true,
    xPDOTransport::UNIQUE_KEY => array ('name'),
);

foreach ($widgets as $widget) {
    $vehicle = $builder->createVehicle($widget,$attributes);
    $builder->putVehicle($vehicle);
}
$modx->log(modX::LOG_LEVEL_INFO,'Packaged in '.count($widgets).' widgets.'); flush();

$vehicle = $builder->createVehicle([
        'source' => $sources['resolvers'] . 'upgrade.resolver.php',
    ],
    [
        'vehicle_class' => 'xPDOScriptVehicle',
    ]
);
$builder->putVehicle($vehicle);

$modx->log(modX::LOG_LEVEL_INFO,'Added upgrade resolver to last dashboard widget.'); flush();
unset($widgets,$widget,$attributes);

/* Pack in the license file, readme and setup options */
$builder->setPackageAttributes([
    'license' => file_get_contents($sources['docs'] . 'license.txt'),
    'readme' => file_get_contents($sources['docs'] . 'readme.txt'),
    'changelog' => file_get_contents($sources['docs'] . 'changelog.txt'),
    'setup-options' => [
        'source' => $sources['build'].'setup.options.php',
    ],
]);
$modx->log(modX::LOG_LEVEL_INFO,'Packaged in package attributes.'); flush();

$modx->log(modX::LOG_LEVEL_INFO,'Packing...'); flush();
$builder->pack();

$mtime= microtime();
$mtime= explode(" ", $mtime);
$mtime= $mtime[1] + $mtime[0];
$tend= $mtime;
$totalTime= ($tend - $tstart);
$totalTime= sprintf("%2.4f s", $totalTime);

$modx->log(modX::LOG_LEVEL_INFO,"\n<br />Package Built.<br />\nExecution time: {$totalTime}\n");

