<?php
/**
 * Define the MODX path constants necessary for installation
 *
 * @package samples
 * @subpackage build
 */

// Go to https://console.developers.google.com and create a project
// Under APIs & Auth > Credentials, create a Native Application (other) Client ID.
// Put the Client ID and Client Secret here to embed those into the built package.
define('GAPI_CLIENT_ID', 'Your Google API Client ID Here');
define('GAPI_CLIENT_SECRET', 'Your Google API Client Secret Here');

// Web app proxy flow (new Aug '22)
define('GAPI_WA_CLIENT_ID', '627...');
define('GAPI_WA_CLIENT_SECRET', 'GOC...');