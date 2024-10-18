<?php
/* Get the core config */
if (!file_exists(dirname(__DIR__).'/config.core.php')) {
    die('ERROR: missing ' . dirname(__DIR__).'/config.core.php file defining the MODX core path.');
}
/* Get the core config */
if (!file_exists(dirname(__DIR__).'/_build/build.config.php')) {
    die('ERROR: missing ' . dirname(__DIR__).'/_build/build.config.php file defining the Google API Credentials.');
}
require_once dirname(__DIR__).'/_build/build.config.php';

echo "<pre>";
/* Boot up MODX */
echo "Loading modX...\n";
require_once dirname(dirname(__FILE__)).'/config.core.php';
require_once MODX_CORE_PATH.'model/modx/modx.class.php';
$modx = new modX();
echo "Initializing manager...\n";
$modx->initialize('mgr');
$modx->getService('error','error.modError', '', '');

$componentPath = dirname(dirname(__FILE__));

$bigbrother = $modx->getService('bigbrother','BigBrother', $componentPath.'/core/components/bigbrother/model/bigbrother/', array(
    'bigbrother.core_path' => $componentPath.'/core/components/bigbrother/',
));

/* Namespace */
if (!createObject('modNamespace',array(
    'name' => 'bigbrother',
    'path' => $componentPath.'/core/components/bigbrother/',
    'assets_path' => $componentPath.'/assets/components/bigbrother/',
),'name', false)) {
    echo "Error creating namespace bigbrother.\n";
}

/* Path settings */
if (!createObject('modSystemSetting', array(
    'key' => 'bigbrother.core_path',
    'value' => $componentPath.'/core/components/bigbrother/',
    'xtype' => 'textfield',
    'namespace' => 'bigbrother',
    'area' => 'Paths',
    'editedon' => time(),
), 'key', false)) {
    echo "Error creating bigbrother.core_path setting.\n";
}

if (!createObject('modSystemSetting', array(
    'key' => 'bigbrother.assets_path',
    'value' => $componentPath.'/assets/components/bigbrother/',
    'xtype' => 'textfield',
    'namespace' => 'bigbrother',
    'area' => 'Paths',
    'editedon' => time(),
), 'key', false)) {
    echo "Error creating bigbrother.assets_path setting.\n";
}

/* Fetch assets url */
$url = 'http';
if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on')) {
    $url .= 's';
}
$url .= '://'.$_SERVER["SERVER_NAME"];
if ($_SERVER['SERVER_PORT'] != '80') {
    $url .= ':'.$_SERVER['SERVER_PORT'];
}
$requestUri = $_SERVER['REQUEST_URI'];
$bootstrapPos = strpos($requestUri, '_bootstrap/');
$requestUri = rtrim(substr($requestUri, 0, $bootstrapPos), '/').'/';
$assetsUrl = "{$url}{$requestUri}assets/components/bigbrother/";

if (!createObject('modSystemSetting', array(
    'key' => 'bigbrother.assets_url',
    'value' => $assetsUrl,
    'xtype' => 'textfield',
    'namespace' => 'bigbrother',
    'area' => 'Paths',
    'editedon' => time(),
), 'key', false)) {
    echo "Error creating bigbrother.assets_url setting.\n";
}

if (!createObject('modMenu', array(
    'parent' => 'components',
    'text' => 'bigbrother',
    'description' => 'bigbrother.menu_desc',
    'icon' => 'images/icons/plugin.gif',
    'menuindex' => '0',
    'namespace' => 'bigbrother',
    'action' => 'authorize',
), 'text', false)) {
    echo "Error creating menu.\n";
}

if (!createObject('modDashboardWidget', [
    'name' => 'bigbrother.main.name',
    'description' => 'bigbrother.main.desc',
    'type' => 'file',
    'size' => 'full',
    'content' =>  $componentPath.'/core/components/bigbrother/elements/widgets/main.class.php',
    'namespace' => 'bigbrother',
    'lexicon' => 'bigbrother:default',
], 'name', false)) {
    echo "Error creating main_widget.\n";
}

if (!createObject('modDashboardWidget', [
    'name' => 'bigbrother.visits.name',
    'description' => 'bigbrother.visits.desc',
    'type' => 'file',
    'size' => 'half',
    'content' =>  $componentPath.'/core/components/bigbrother/elements/widgets/visitsline.class.php',
    'namespace' => 'bigbrother',
    'lexicon' => 'bigbrother:default',
], 'name', false)) {
    echo "Error creating visits widget.\n";
}

if (!createObject('modDashboardWidget', [
    'name' => 'bigbrother.metrics.name',
    'description' => 'bigbrother.metrics.desc',
    'type' => 'file',
    'size' => 'half',
    'content' =>  $componentPath.'/core/components/bigbrother/elements/widgets/metrics.class.php',
    'namespace' => 'bigbrother',
    'lexicon' => 'bigbrother:default',
], 'name', false)) {
    echo "Error creating metrics widget.\n";
}

if (!createObject('modDashboardWidget', [
    'name' => 'bigbrother.acquisition.name',
    'description' => 'bigbrother.acquisition.desc',
    'type' => 'file',
    'size' => 'half',
    'content' =>  $componentPath.'/core/components/bigbrother/elements/widgets/acquisition.class.php',
    'namespace' => 'bigbrother',
    'lexicon' => 'bigbrother:default',
], 'name', false)) {
    echo "Error creating acquisition widget.\n";
}

if (!createObject('modDashboardWidget', [
    'name' => 'bigbrother.popular_pages.name',
    'description' => 'bigbrother.popular_pages.desc',
    'type' => 'file',
    'size' => 'half',
    'content' =>  $componentPath.'/core/components/bigbrother/elements/widgets/popularpages.class.php',
    'namespace' => 'bigbrother',
    'lexicon' => 'bigbrother:default',
], 'name', false)) {
    echo "Error creating popular pages widget.\n";
}

if (!createObject('modDashboardWidget', [
    'name' => 'bigbrother.top_countries.name',
    'description' => 'bigbrother.top_countries.desc',
    'type' => 'file',
    'size' => 'half',
    'content' =>  $componentPath.'/core/components/bigbrother/elements/widgets/topcountries.class.php',
    'namespace' => 'bigbrother',
    'lexicon' => 'bigbrother:default',
], 'name', false)) {
    echo "Error creating top countries widget.\n";
}

if (!createObject('modDashboardWidget', [
    'name' => 'bigbrother.top_referrers.name',
    'description' => 'bigbrother.top_referrers.desc',
    'type' => 'file',
    'size' => 'half',
    'content' =>  $componentPath.'/core/components/bigbrother/elements/widgets/topreferrers.class.php',
    'namespace' => 'bigbrother',
    'lexicon' => 'bigbrother:default',
], 'name', false)) {
    echo "Error creating top referrers widget.\n";
}

$settings = include dirname(dirname(__FILE__)).'/_build/data/transport.settings.php';
foreach ($settings as $key => $obj) {
    /** @var modSystemSetting $obj */
    if (!createObject('modSystemSetting', $obj->toArray(), 'key', false)) {
        echo "Error creating bigbrother.".$obj->get('key')." setting.\n";
    }
}

echo "Done.";


/**
 * Creates an object.
 *
 * @param string $className
 * @param array $data
 * @param string $primaryField
 * @param bool $update
 * @return bool
 */
function createObject ($className = '', array $data = array(), $primaryField = '', $update = true) {
    global $modx;
    /* @var xPDOObject $object */
    $object = null;

    /* Attempt to get the existing object */
    if (!empty($primaryField)) {
        if (is_array($primaryField)) {
            $condition = array();
            foreach ($primaryField as $key) {
                $condition[$key] = $data[$key];
            }
        }
        else {
            $condition = array($primaryField => $data[$primaryField]);
        }
        $object = $modx->getObject($className, $condition);
        if ($object instanceof $className) {
            if ($update) {
                $object->fromArray($data);
                return $object->save();
            } else {
                $condition = $modx->toJSON($condition);
                echo "Skipping {$className} {$condition}: already exists.\n";
                return true;
            }
        }
    }

    /* Create new object if it doesn't exist */
    if (!$object) {
        $object = $modx->newObject($className);
        $object->fromArray($data, '', true);
        return $object->save();
    }

    return false;
}
