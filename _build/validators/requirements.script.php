<?php
/** @var modX $modx */
$modx =& $transport->xpdo;
$success = false;
switch($options[xPDOTransport::PACKAGE_ACTION]) {
    case xPDOTransport::ACTION_INSTALL:
    case xPDOTransport::ACTION_UPGRADE:
        $success = true;
        $modx->log(xPDO::LOG_LEVEL_INFO, 'Checking if server meets the minimum requirements...');

        $level = xPDO::LOG_LEVEL_INFO;
        $modxVersion = $modx->getVersionData();
        if (version_compare($modxVersion['full_version'], '2.8.0') <= 0) {
            $level = xPDO::LOG_LEVEL_ERROR;
            $success = false;
        }
        $modx->log($level, '- MODX Revolution 2.8+: ' . $modxVersion['full_version']);

        $level = xPDO::LOG_LEVEL_INFO;
        if (version_compare(PHP_VERSION, '8.2.0') <= 0) {
            $level = xPDO::LOG_LEVEL_ERROR;
            $success = false;
        }
        $modx->log($level, '- PHP version 8.2.0+: ' . PHP_VERSION);

        $level = xPDO::LOG_LEVEL_INFO;
        $bcMath = extension_loaded('bcmath');
        if (!$bcMath) {
            $level = xPDO::LOG_LEVEL_ERROR;
            $success = false;
        }
        $modx->log($level, '- bcmath extension loaded: ' . ($bcMath ? 'yes' : 'no'));

        if ($success) {
            $modx->log(xPDO::LOG_LEVEL_INFO, 'Requirements look good!');
        }
        else {
            $modx->log(xPDO::LOG_LEVEL_ERROR, 'Unfortunately, your server does not meet the minimum requirements for the installation to continue.');
        }

        break;
    case xPDOTransport::ACTION_UNINSTALL:
        $success = true;
        break;
}
return $success;
