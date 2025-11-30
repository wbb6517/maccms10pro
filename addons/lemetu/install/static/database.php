<?php
/*
 * @author:乐美兔
 * 官网地址：https://www.lemetu.com
 */
/*creates*/ 
if(empty($col_list[$pre.'user']['user_pid_num'])){
    $sql .= "ALTER TABLE  `mac_user` ADD `user_pid_num` INT(10) UNSIGNED NOT NULL DEFAULT '0' , ADD `user_login_today` INT(10) NOT NULL DEFAULT '0' ,ADD `is_agents` INT(11) NOT NULL DEFAULT '0', ADD `user_gold` INT(8) NOT NULL DEFAULT '0';";
    $sql .="\r";
}
if(empty($col_list[$pre.'vod']['vod_levels'])){
    $sql .= "ALTER TABLE  `mac_vod` ADD `vod_levels` INT(11) NULL DEFAULT '0' , ADD `vod_custom_tag` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ;";
    $sql .="\r";
}
// if(empty($col_list[$pre.'mac_groupchat']['img'])){
//     $sql .= "ALTER TABLE `mac_groupchat` ADD `img` VARCHAR( 255 ) NOT NULL DEFAULT '0';";
//     $sql .="\r";
// }
// if(empty($col_list[$pre.'mac_groupchat']['status'])){
//     $sql .= "ALTER TABLE `mac_groupchat` ADD `status` INT( 11 ) NOT NULL DEFAULT '0';";
//     $sql .="\r";
// }
// if(empty($col_list[$pre.'mac_groupchat']['type'])){
//     $sql .= "ALTER TABLE `mac_groupchat` ADD `type` INT( 11 ) NOT NULL DEFAULT '0';";
//     $sql .="\r";
// }
if(empty($col_list[$pre.'mac_ulog']['ulog_end_time'])){
    $sql .= "ALTER TABLE `mac_ulog` ADD `ulog_end_time` INT(10) UNSIGNED NOT NULL DEFAULT '0' ;";
    $sql .="\r";
}