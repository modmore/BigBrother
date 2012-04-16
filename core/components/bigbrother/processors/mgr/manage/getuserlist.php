<?php
/**
 * Get account list
 *
 * @package bigbrother
 * @subpackage processors
 */
$ga =& $modx->bigbrother;
$response['success'] = false;
$data = array();

$userTable = $modx->getTableName('modUser');
$profileTable = $modx->getTableName('modUserProfile');
$userSettingTable = $modx->getTableName('modUserSetting');

$query = new xPDOCriteria($modx,
    'SELECT 
    `modUser`.`id`,
    `Profile`.`fullname`,
    ( SELECT 
        `modUserSetting`.`value` 
        FROM
            '. $userSettingTable .' modUserSetting 
        WHERE `modUserSetting`.`key` = \'bigbrother.account_name\' 
            AND `modUserSetting`.`user` = `modUser`.`id` ) AS account 
    FROM
        '. $userTable .' AS `modUser` 
        LEFT JOIN '. $profileTable .' `Profile` 
            ON `modUser`.`id` = `Profile`.`internalKey` 
    ORDER BY id ASC 
    LIMIT 10'
);
$query->prepare();
$users = $modx->getCollection('modUser', $query);
foreach($users as $user){
    $row['id'] = $user->get('id'); 
    $row['fullname'] = $user->get('fullname'); 
    $account = $user->get('account');
    $row['account'] = empty( $account ) ? 'none' : $account; 
    $data[] = $row;
}

$response['success'] = true;
$response['total'] = count($data);
$response['data'] = $data;
return $modx->toJSON($response);