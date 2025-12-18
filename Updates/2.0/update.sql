ALTER TABLE `users` ADD `country_name` VARCHAR(255) NULL DEFAULT NULL AFTER `password`, ADD `dial_code` INT NOT NULL DEFAULT '0' AFTER `country_name`, ADD `city` VARCHAR(255) NULL DEFAULT NULL AFTER `dial_code`, ADD `state` VARCHAR(255) NULL DEFAULT NULL AFTER `city`, ADD `zip` VARCHAR(255) NULL DEFAULT NULL AFTER `state`;

-- ====================== frontends ======================

ALTER TABLE `frontends` ADD `seo_content` LONGTEXT AFTER `data_values`;
ALTER TABLE `frontends` ADD `tempname` VARCHAR(40) NULL DEFAULT NULL AFTER `seo_content`;
ALTER TABLE `frontends` ADD `slug` VARCHAR(255) NULL DEFAULT NULL AFTER `tempname`;
UPDATE `frontends` SET `tempname` = 'basic' WHERE `frontends`.`id` != 0;

UPDATE frontends
SET slug = LOWER(REPLACE(REPLACE(REPLACE(
    JSON_UNQUOTE(JSON_EXTRACT(data_values, '$.title')), ' ', '-'), ',', ''), '.', ''))
WHERE data_keys = 'blog.element';

UPDATE frontends
SET slug = LOWER(REPLACE(REPLACE(REPLACE(
    JSON_UNQUOTE(JSON_EXTRACT(data_values, '$.title')), ' ', '-'), ',', ''), '.', ''))
WHERE data_keys = 'policy_pages.element';

-- ====================== frontends ======================

-- ====================== general_settings ======================

ALTER TABLE `general_settings` ADD `system_customized` TINYINT(1) NOT NULL DEFAULT '0' AFTER `bal_trans_fixed_charge`;
ALTER TABLE `general_settings` CHANGE `last_cron` `last_cron` TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `general_settings` ADD `paginate_number` INT NOT NULL DEFAULT '0' AFTER `system_customized`;
ALTER TABLE `general_settings` ADD `socialite_credentials` LONGTEXT NULL DEFAULT NULL AFTER `paginate_number`;
ALTER TABLE `general_settings` ADD `currency_format` INT NOT NULL DEFAULT '0' COMMENT '1=>Both\r\n2=>Text Only\r\n3=>Symbol Only' AFTER `paginate_number`;
ALTER TABLE `general_settings` ADD `email_from_name` VARCHAR(255) NULL DEFAULT NULL AFTER `email_from`;
ALTER TABLE `general_settings` ADD `available_version` VARCHAR(40) NULL DEFAULT NULL AFTER `last_cron`;
ALTER TABLE `general_settings` CHANGE `currency_format` `currency_format` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '1=>Both\r\n2=>Text Only\r\n3=>Symbol Only';

ALTER TABLE `general_settings` ADD `country_list` LONGTEXT NULL DEFAULT NULL AFTER `socialite_credentials`;
UPDATE `general_settings` SET `country_list` = '{\n  \"AF\": {\n    \"country\": \"Afghanistan\",\n    \"dial_code\": \"93\"\n  },\n  \"AX\": {\n    \"country\": \"Aland Islands\",\n    \"dial_code\": \"358\"\n  },\n  \"AL\": {\n    \"country\": \"Albania\",\n    \"dial_code\": \"355\"\n  },\n  \"DZ\": {\n    \"country\": \"Algeria\",\n    \"dial_code\": \"213\"\n  },\n  \"AS\": {\n    \"country\": \"AmericanSamoa\",\n    \"dial_code\": \"1684\"\n  },\n  \"AD\": {\n    \"country\": \"Andorra\",\n    \"dial_code\": \"376\"\n  },\n  \"AO\": {\n    \"country\": \"Angola\",\n    \"dial_code\": \"244\"\n  },\n  \"AI\": {\n    \"country\": \"Anguilla\",\n    \"dial_code\": \"1264\"\n  },\n  \"AQ\": {\n    \"country\": \"Antarctica\",\n    \"dial_code\": \"672\"\n  },\n  \"AG\": {\n    \"country\": \"Antigua and Barbuda\",\n    \"dial_code\": \"1268\"\n  },\n  \"AR\": {\n    \"country\": \"Argentina\",\n    \"dial_code\": \"54\"\n  },\n  \"AM\": {\n    \"country\": \"Armenia\",\n    \"dial_code\": \"374\"\n  },\n  \"AW\": {\n    \"country\": \"Aruba\",\n    \"dial_code\": \"297\"\n  },\n  \"AU\": {\n    \"country\": \"Australia\",\n    \"dial_code\": \"61\"\n  },\n  \"AT\": {\n    \"country\": \"Austria\",\n    \"dial_code\": \"43\"\n  },\n  \"AZ\": {\n    \"country\": \"Azerbaijan\",\n    \"dial_code\": \"994\"\n  },\n  \"BS\": {\n    \"country\": \"Bahamas\",\n    \"dial_code\": \"1242\"\n  },\n  \"BH\": {\n    \"country\": \"Bahrain\",\n    \"dial_code\": \"973\"\n  },\n  \"BD\": {\n    \"country\": \"Bangladesh\",\n    \"dial_code\": \"880\"\n  },\n  \"BB\": {\n    \"country\": \"Barbados\",\n    \"dial_code\": \"1246\"\n  },\n  \"BY\": {\n    \"country\": \"Belarus\",\n    \"dial_code\": \"375\"\n  },\n  \"BE\": {\n    \"country\": \"Belgium\",\n    \"dial_code\": \"32\"\n  },\n  \"BZ\": {\n    \"country\": \"Belize\",\n    \"dial_code\": \"501\"\n  },\n  \"BJ\": {\n    \"country\": \"Benin\",\n    \"dial_code\": \"229\"\n  },\n  \"BM\": {\n    \"country\": \"Bermuda\",\n    \"dial_code\": \"1441\"\n  },\n  \"BT\": {\n    \"country\": \"Bhutan\",\n    \"dial_code\": \"975\"\n  },\n  \"BO\": {\n    \"country\": \"Plurinational State of Bolivia\",\n    \"dial_code\": \"591\"\n  },\n  \"BA\": {\n    \"country\": \"Bosnia and Herzegovina\",\n    \"dial_code\": \"387\"\n  },\n  \"BW\": {\n    \"country\": \"Botswana\",\n    \"dial_code\": \"267\"\n  },\n  \"BR\": {\n    \"country\": \"Brazil\",\n    \"dial_code\": \"55\"\n  },\n  \"IO\": {\n    \"country\": \"British Indian Ocean Territory\",\n    \"dial_code\": \"246\"\n  },\n  \"BN\": {\n    \"country\": \"Brunei Darussalam\",\n    \"dial_code\": \"673\"\n  },\n  \"BG\": {\n    \"country\": \"Bulgaria\",\n    \"dial_code\": \"359\"\n  },\n  \"BF\": {\n    \"country\": \"Burkina Faso\",\n    \"dial_code\": \"226\"\n  },\n  \"BI\": {\n    \"country\": \"Burundi\",\n    \"dial_code\": \"257\"\n  },\n  \"KH\": {\n    \"country\": \"Cambodia\",\n    \"dial_code\": \"855\"\n  },\n  \"CM\": {\n    \"country\": \"Cameroon\",\n    \"dial_code\": \"237\"\n  },\n  \"CA\": {\n    \"country\": \"Canada\",\n    \"dial_code\": \"1\"\n  },\n  \"CV\": {\n    \"country\": \"Cape Verde\",\n    \"dial_code\": \"238\"\n  },\n  \"KY\": {\n    \"country\": \"Cayman Islands\",\n    \"dial_code\": \" 345\"\n  },\n  \"CF\": {\n    \"country\": \"Central African Republic\",\n    \"dial_code\": \"236\"\n  },\n  \"TD\": {\n    \"country\": \"Chad\",\n    \"dial_code\": \"235\"\n  },\n  \"CL\": {\n    \"country\": \"Chile\",\n    \"dial_code\": \"56\"\n  },\n  \"CN\": {\n    \"country\": \"China\",\n    \"dial_code\": \"86\"\n  },\n  \"CX\": {\n    \"country\": \"Christmas Island\",\n    \"dial_code\": \"61\"\n  },\n  \"CC\": {\n    \"country\": \"Cocos (Keeling) Islands\",\n    \"dial_code\": \"61\"\n  },\n  \"CO\": {\n    \"country\": \"Colombia\",\n    \"dial_code\": \"57\"\n  },\n  \"KM\": {\n    \"country\": \"Comoros\",\n    \"dial_code\": \"269\"\n  },\n  \"CG\": {\n    \"country\": \"Congo\",\n    \"dial_code\": \"242\"\n  },\n  \"CD\": {\n    \"country\": \"The Democratic Republic of the Congo\",\n    \"dial_code\": \"243\"\n  },\n  \"CK\": {\n    \"country\": \"Cook Islands\",\n    \"dial_code\": \"682\"\n  },\n  \"CR\": {\n    \"country\": \"Costa Rica\",\n    \"dial_code\": \"506\"\n  },\n  \"CI\": {\n    \"country\": \"Cote d\'Ivoire\",\n    \"dial_code\": \"225\"\n  },\n  \"HR\": {\n    \"country\": \"Croatia\",\n    \"dial_code\": \"385\"\n  },\n  \"CU\": {\n    \"country\": \"Cuba\",\n    \"dial_code\": \"53\"\n  },\n  \"CY\": {\n    \"country\": \"Cyprus\",\n    \"dial_code\": \"357\"\n  },\n  \"CZ\": {\n    \"country\": \"Czech Republic\",\n    \"dial_code\": \"420\"\n  },\n  \"DK\": {\n    \"country\": \"Denmark\",\n    \"dial_code\": \"45\"\n  },\n  \"DJ\": {\n    \"country\": \"Djibouti\",\n    \"dial_code\": \"253\"\n  },\n  \"DM\": {\n    \"country\": \"Dominica\",\n    \"dial_code\": \"1767\"\n  },\n  \"DO\": {\n    \"country\": \"Dominican Republic\",\n    \"dial_code\": \"1849\"\n  },\n  \"EC\": {\n    \"country\": \"Ecuador\",\n    \"dial_code\": \"593\"\n  },\n  \"EG\": {\n    \"country\": \"Egypt\",\n    \"dial_code\": \"20\"\n  },\n  \"SV\": {\n    \"country\": \"El Salvador\",\n    \"dial_code\": \"503\"\n  },\n  \"GQ\": {\n    \"country\": \"Equatorial Guinea\",\n    \"dial_code\": \"240\"\n  },\n  \"ER\": {\n    \"country\": \"Eritrea\",\n    \"dial_code\": \"291\"\n  },\n  \"EE\": {\n    \"country\": \"Estonia\",\n    \"dial_code\": \"372\"\n  },\n  \"ET\": {\n    \"country\": \"Ethiopia\",\n    \"dial_code\": \"251\"\n  },\n  \"FK\": {\n    \"country\": \"Falkland Islands (Malvinas)\",\n    \"dial_code\": \"500\"\n  },\n  \"FO\": {\n    \"country\": \"Faroe Islands\",\n    \"dial_code\": \"298\"\n  },\n  \"FJ\": {\n    \"country\": \"Fiji\",\n    \"dial_code\": \"679\"\n  },\n  \"FI\": {\n    \"country\": \"Finland\",\n    \"dial_code\": \"358\"\n  },\n  \"FR\": {\n    \"country\": \"France\",\n    \"dial_code\": \"33\"\n  },\n  \"GF\": {\n    \"country\": \"French Guiana\",\n    \"dial_code\": \"594\"\n  },\n  \"PF\": {\n    \"country\": \"French Polynesia\",\n    \"dial_code\": \"689\"\n  },\n  \"GA\": {\n    \"country\": \"Gabon\",\n    \"dial_code\": \"241\"\n  },\n  \"GM\": {\n    \"country\": \"Gambia\",\n    \"dial_code\": \"220\"\n  },\n  \"GE\": {\n    \"country\": \"Georgia\",\n    \"dial_code\": \"995\"\n  },\n  \"DE\": {\n    \"country\": \"Germany\",\n    \"dial_code\": \"49\"\n  },\n  \"GH\": {\n    \"country\": \"Ghana\",\n    \"dial_code\": \"233\"\n  },\n  \"GI\": {\n    \"country\": \"Gibraltar\",\n    \"dial_code\": \"350\"\n  },\n  \"GR\": {\n    \"country\": \"Greece\",\n    \"dial_code\": \"30\"\n  },\n  \"GL\": {\n    \"country\": \"Greenland\",\n    \"dial_code\": \"299\"\n  },\n  \"GD\": {\n    \"country\": \"Grenada\",\n    \"dial_code\": \"1473\"\n  },\n  \"GP\": {\n    \"country\": \"Guadeloupe\",\n    \"dial_code\": \"590\"\n  },\n  \"GU\": {\n    \"country\": \"Guam\",\n    \"dial_code\": \"1671\"\n  },\n  \"GT\": {\n    \"country\": \"Guatemala\",\n    \"dial_code\": \"502\"\n  },\n  \"GG\": {\n    \"country\": \"Guernsey\",\n    \"dial_code\": \"44\"\n  },\n  \"GN\": {\n    \"country\": \"Guinea\",\n    \"dial_code\": \"224\"\n  },\n  \"GW\": {\n    \"country\": \"Guinea-Bissau\",\n    \"dial_code\": \"245\"\n  },\n  \"GY\": {\n    \"country\": \"Guyana\",\n    \"dial_code\": \"595\"\n  },\n  \"HT\": {\n    \"country\": \"Haiti\",\n    \"dial_code\": \"509\"\n  },\n  \"VA\": {\n    \"country\": \"Holy See (Vatican City State)\",\n    \"dial_code\": \"379\"\n  },\n  \"HN\": {\n    \"country\": \"Honduras\",\n    \"dial_code\": \"504\"\n  },\n  \"HK\": {\n    \"country\": \"Hong Kong\",\n    \"dial_code\": \"852\"\n  },\n  \"HU\": {\n    \"country\": \"Hungary\",\n    \"dial_code\": \"36\"\n  },\n  \"IS\": {\n    \"country\": \"Iceland\",\n    \"dial_code\": \"354\"\n  },\n  \"IN\": {\n    \"country\": \"India\",\n    \"dial_code\": \"91\"\n  },\n  \"ID\": {\n    \"country\": \"Indonesia\",\n    \"dial_code\": \"62\"\n  },\n  \"IR\": {\n    \"country\": \"Iran - Islamic Republic of Persian Gulf\",\n    \"dial_code\": \"98\"\n  },\n  \"IQ\": {\n    \"country\": \"Iraq\",\n    \"dial_code\": \"964\"\n  },\n  \"IE\": {\n    \"country\": \"Ireland\",\n    \"dial_code\": \"353\"\n  },\n  \"IM\": {\n    \"country\": \"Isle of Man\",\n    \"dial_code\": \"44\"\n  },\n  \"IL\": {\n    \"country\": \"Israel\",\n    \"dial_code\": \"972\"\n  },\n  \"IT\": {\n    \"country\": \"Italy\",\n    \"dial_code\": \"39\"\n  },\n  \"JM\": {\n    \"country\": \"Jamaica\",\n    \"dial_code\": \"1876\"\n  },\n  \"JP\": {\n    \"country\": \"Japan\",\n    \"dial_code\": \"81\"\n  },\n  \"JE\": {\n    \"country\": \"Jersey\",\n    \"dial_code\": \"44\"\n  },\n  \"JO\": {\n    \"country\": \"Jordan\",\n    \"dial_code\": \"962\"\n  },\n  \"KZ\": {\n    \"country\": \"Kazakhstan\",\n    \"dial_code\": \"77\"\n  },\n  \"KE\": {\n    \"country\": \"Kenya\",\n    \"dial_code\": \"254\"\n  },\n  \"KI\": {\n    \"country\": \"Kiribati\",\n    \"dial_code\": \"686\"\n  },\n  \"KP\": {\n    \"country\": \"Democratic People\'s Republic of Korea\",\n    \"dial_code\": \"850\"\n  },\n  \"KR\": {\n    \"country\": \"Republic of South Korea\",\n    \"dial_code\": \"82\"\n  },\n  \"KW\": {\n    \"country\": \"Kuwait\",\n    \"dial_code\": \"965\"\n  },\n  \"KG\": {\n    \"country\": \"Kyrgyzstan\",\n    \"dial_code\": \"996\"\n  },\n  \"LA\": {\n    \"country\": \"Laos\",\n    \"dial_code\": \"856\"\n  },\n  \"LV\": {\n    \"country\": \"Latvia\",\n    \"dial_code\": \"371\"\n  },\n  \"LB\": {\n    \"country\": \"Lebanon\",\n    \"dial_code\": \"961\"\n  },\n  \"LS\": {\n    \"country\": \"Lesotho\",\n    \"dial_code\": \"266\"\n  },\n  \"LR\": {\n    \"country\": \"Liberia\",\n    \"dial_code\": \"231\"\n  },\n  \"LY\": {\n    \"country\": \"Libyan Arab Jamahiriya\",\n    \"dial_code\": \"218\"\n  },\n  \"LI\": {\n    \"country\": \"Liechtenstein\",\n    \"dial_code\": \"423\"\n  },\n  \"LT\": {\n    \"country\": \"Lithuania\",\n    \"dial_code\": \"370\"\n  },\n  \"LU\": {\n    \"country\": \"Luxembourg\",\n    \"dial_code\": \"352\"\n  },\n  \"MO\": {\n    \"country\": \"Macao\",\n    \"dial_code\": \"853\"\n  },\n  \"MK\": {\n    \"country\": \"Macedonia\",\n    \"dial_code\": \"389\"\n  },\n  \"MG\": {\n    \"country\": \"Madagascar\",\n    \"dial_code\": \"261\"\n  },\n  \"MW\": {\n    \"country\": \"Malawi\",\n    \"dial_code\": \"265\"\n  },\n  \"MY\": {\n    \"country\": \"Malaysia\",\n    \"dial_code\": \"60\"\n  },\n  \"MV\": {\n    \"country\": \"Maldives\",\n    \"dial_code\": \"960\"\n  },\n  \"ML\": {\n    \"country\": \"Mali\",\n    \"dial_code\": \"223\"\n  },\n  \"MT\": {\n    \"country\": \"Malta\",\n    \"dial_code\": \"356\"\n  },\n  \"MH\": {\n    \"country\": \"Marshall Islands\",\n    \"dial_code\": \"692\"\n  },\n  \"MQ\": {\n    \"country\": \"Martinique\",\n    \"dial_code\": \"596\"\n  },\n  \"MR\": {\n    \"country\": \"Mauritania\",\n    \"dial_code\": \"222\"\n  },\n  \"MU\": {\n    \"country\": \"Mauritius\",\n    \"dial_code\": \"230\"\n  },\n  \"YT\": {\n    \"country\": \"Mayotte\",\n    \"dial_code\": \"262\"\n  },\n  \"MX\": {\n    \"country\": \"Mexico\",\n    \"dial_code\": \"52\"\n  },\n  \"FM\": {\n    \"country\": \"Federated States of Micronesia\",\n    \"dial_code\": \"691\"\n  },\n  \"MD\": {\n    \"country\": \"Moldova\",\n    \"dial_code\": \"373\"\n  },\n  \"MC\": {\n    \"country\": \"Monaco\",\n    \"dial_code\": \"377\"\n  },\n  \"MN\": {\n    \"country\": \"Mongolia\",\n    \"dial_code\": \"976\"\n  },\n  \"ME\": {\n    \"country\": \"Montenegro\",\n    \"dial_code\": \"382\"\n  },\n  \"MS\": {\n    \"country\": \"Montserrat\",\n    \"dial_code\": \"1664\"\n  },\n  \"MA\": {\n    \"country\": \"Morocco\",\n    \"dial_code\": \"212\"\n  },\n  \"MZ\": {\n    \"country\": \"Mozambique\",\n    \"dial_code\": \"258\"\n  },\n  \"MM\": {\n    \"country\": \"Myanmar\",\n    \"dial_code\": \"95\"\n  },\n  \"NA\": {\n    \"country\": \"Namibia\",\n    \"dial_code\": \"264\"\n  },\n  \"NR\": {\n    \"country\": \"Nauru\",\n    \"dial_code\": \"674\"\n  },\n  \"NP\": {\n    \"country\": \"Nepal\",\n    \"dial_code\": \"977\"\n  },\n  \"NL\": {\n    \"country\": \"Netherlands\",\n    \"dial_code\": \"31\"\n  },\n  \"AN\": {\n    \"country\": \"Netherlands Antilles\",\n    \"dial_code\": \"599\"\n  },\n  \"NC\": {\n    \"country\": \"New Caledonia\",\n    \"dial_code\": \"687\"\n  },\n  \"NZ\": {\n    \"country\": \"New Zealand\",\n    \"dial_code\": \"64\"\n  },\n  \"NI\": {\n    \"country\": \"Nicaragua\",\n    \"dial_code\": \"505\"\n  },\n  \"NE\": {\n    \"country\": \"Niger\",\n    \"dial_code\": \"227\"\n  },\n  \"NG\": {\n    \"country\": \"Nigeria\",\n    \"dial_code\": \"234\"\n  },\n  \"NU\": {\n    \"country\": \"Niue\",\n    \"dial_code\": \"683\"\n  },\n  \"NF\": {\n    \"country\": \"Norfolk Island\",\n    \"dial_code\": \"672\"\n  },\n  \"MP\": {\n    \"country\": \"Northern Mariana Islands\",\n    \"dial_code\": \"1670\"\n  },\n  \"NO\": {\n    \"country\": \"Norway\",\n    \"dial_code\": \"47\"\n  },\n  \"OM\": {\n    \"country\": \"Oman\",\n    \"dial_code\": \"968\"\n  },\n  \"PK\": {\n    \"country\": \"Pakistan\",\n    \"dial_code\": \"92\"\n  },\n  \"PW\": {\n    \"country\": \"Palau\",\n    \"dial_code\": \"680\"\n  },\n  \"PS\": {\n    \"country\": \"Palestinian Territory\",\n    \"dial_code\": \"970\"\n  },\n  \"PA\": {\n    \"country\": \"Panama\",\n    \"dial_code\": \"507\"\n  },\n  \"PG\": {\n    \"country\": \"Papua New Guinea\",\n    \"dial_code\": \"675\"\n  },\n  \"PY\": {\n    \"country\": \"Paraguay\",\n    \"dial_code\": \"595\"\n  },\n  \"PE\": {\n    \"country\": \"Peru\",\n    \"dial_code\": \"51\"\n  },\n  \"PH\": {\n    \"country\": \"Philippines\",\n    \"dial_code\": \"63\"\n  },\n  \"PN\": {\n    \"country\": \"Pitcairn\",\n    \"dial_code\": \"872\"\n  },\n  \"PL\": {\n    \"country\": \"Poland\",\n    \"dial_code\": \"48\"\n  },\n  \"PT\": {\n    \"country\": \"Portugal\",\n    \"dial_code\": \"351\"\n  },\n  \"PR\": {\n    \"country\": \"Puerto Rico\",\n    \"dial_code\": \"1939\"\n  },\n  \"QA\": {\n    \"country\": \"Qatar\",\n    \"dial_code\": \"974\"\n  },\n  \"RO\": {\n    \"country\": \"Romania\",\n    \"dial_code\": \"40\"\n  },\n  \"RU\": {\n    \"country\": \"Russia\",\n    \"dial_code\": \"7\"\n  },\n  \"RW\": {\n    \"country\": \"Rwanda\",\n    \"dial_code\": \"250\"\n  },\n  \"RE\": {\n    \"country\": \"Reunion\",\n    \"dial_code\": \"262\"\n  },\n  \"BL\": {\n    \"country\": \"Saint Barthelemy\",\n    \"dial_code\": \"590\"\n  },\n  \"SH\": {\n    \"country\": \"Saint Helena\",\n    \"dial_code\": \"290\"\n  },\n  \"KN\": {\n    \"country\": \"Saint Kitts and Nevis\",\n    \"dial_code\": \"1869\"\n  },\n  \"LC\": {\n    \"country\": \"Saint Lucia\",\n    \"dial_code\": \"1758\"\n  },\n  \"MF\": {\n    \"country\": \"Saint Martin\",\n    \"dial_code\": \"590\"\n  },\n  \"PM\": {\n    \"country\": \"Saint Pierre and Miquelon\",\n    \"dial_code\": \"508\"\n  },\n  \"VC\": {\n    \"country\": \"Saint Vincent and the Grenadines\",\n    \"dial_code\": \"1784\"\n  },\n  \"WS\": {\n    \"country\": \"Samoa\",\n    \"dial_code\": \"685\"\n  },\n  \"SM\": {\n    \"country\": \"San Marino\",\n    \"dial_code\": \"378\"\n  },\n  \"ST\": {\n    \"country\": \"Sao Tome and Principe\",\n    \"dial_code\": \"239\"\n  },\n  \"SA\": {\n    \"country\": \"Saudi Arabia\",\n    \"dial_code\": \"966\"\n  },\n  \"SN\": {\n    \"country\": \"Senegal\",\n    \"dial_code\": \"221\"\n  },\n  \"RS\": {\n    \"country\": \"Serbia\",\n    \"dial_code\": \"381\"\n  },\n  \"SC\": {\n    \"country\": \"Seychelles\",\n    \"dial_code\": \"248\"\n  },\n  \"SL\": {\n    \"country\": \"Sierra Leone\",\n    \"dial_code\": \"232\"\n  },\n  \"SG\": {\n    \"country\": \"Singapore\",\n    \"dial_code\": \"65\"\n  },\n  \"SK\": {\n    \"country\": \"Slovakia\",\n    \"dial_code\": \"421\"\n  },\n  \"SI\": {\n    \"country\": \"Slovenia\",\n    \"dial_code\": \"386\"\n  },\n  \"SB\": {\n    \"country\": \"Solomon Islands\",\n    \"dial_code\": \"677\"\n  },\n  \"SO\": {\n    \"country\": \"Somalia\",\n    \"dial_code\": \"252\"\n  },\n  \"ZA\": {\n    \"country\": \"South Africa\",\n    \"dial_code\": \"27\"\n  },\n  \"SS\": {\n    \"country\": \"South Sudan\",\n    \"dial_code\": \"211\"\n  },\n  \"GS\": {\n    \"country\": \"South Georgia and the South Sandwich Islands\",\n    \"dial_code\": \"500\"\n  },\n  \"ES\": {\n    \"country\": \"Spain\",\n    \"dial_code\": \"34\"\n  },\n  \"LK\": {\n    \"country\": \"Sri Lanka\",\n    \"dial_code\": \"94\"\n  },\n  \"SD\": {\n    \"country\": \"Sudan\",\n    \"dial_code\": \"249\"\n  },\n  \"SR\": {\n    \"country\": \"Suricountry\",\n    \"dial_code\": \"597\"\n  },\n  \"SJ\": {\n    \"country\": \"Svalbard and Jan Mayen\",\n    \"dial_code\": \"47\"\n  },\n  \"SZ\": {\n    \"country\": \"Swaziland\",\n    \"dial_code\": \"268\"\n  },\n  \"SE\": {\n    \"country\": \"Sweden\",\n    \"dial_code\": \"46\"\n  },\n  \"CH\": {\n    \"country\": \"Switzerland\",\n    \"dial_code\": \"41\"\n  },\n  \"SY\": {\n    \"country\": \"Syrian Arab Republic\",\n    \"dial_code\": \"963\"\n  },\n  \"TW\": {\n    \"country\": \"Taiwan\",\n    \"dial_code\": \"886\"\n  },\n  \"TJ\": {\n    \"country\": \"Tajikistan\",\n    \"dial_code\": \"992\"\n  },\n  \"TZ\": {\n    \"country\": \"Tanzania\",\n    \"dial_code\": \"255\"\n  },\n  \"TH\": {\n    \"country\": \"Thailand\",\n    \"dial_code\": \"66\"\n  },\n  \"TL\": {\n    \"country\": \"Timor-Leste\",\n    \"dial_code\": \"670\"\n  },\n  \"TG\": {\n    \"country\": \"Togo\",\n    \"dial_code\": \"228\"\n  },\n  \"TK\": {\n    \"country\": \"Tokelau\",\n    \"dial_code\": \"690\"\n  },\n  \"TO\": {\n    \"country\": \"Tonga\",\n    \"dial_code\": \"676\"\n  },\n  \"TT\": {\n    \"country\": \"Trinidad and Tobago\",\n    \"dial_code\": \"1868\"\n  },\n  \"TN\": {\n    \"country\": \"Tunisia\",\n    \"dial_code\": \"216\"\n  },\n  \"TR\": {\n    \"country\": \"Turkey\",\n    \"dial_code\": \"90\"\n  },\n  \"TM\": {\n    \"country\": \"Turkmenistan\",\n    \"dial_code\": \"993\"\n  },\n  \"TC\": {\n    \"country\": \"Turks and Caicos Islands\",\n    \"dial_code\": \"1649\"\n  },\n  \"TV\": {\n    \"country\": \"Tuvalu\",\n    \"dial_code\": \"688\"\n  },\n  \"UG\": {\n    \"country\": \"Uganda\",\n    \"dial_code\": \"256\"\n  },\n  \"UA\": {\n    \"country\": \"Ukraine\",\n    \"dial_code\": \"380\"\n  },\n  \"AE\": {\n    \"country\": \"United Arab Emirates\",\n    \"dial_code\": \"971\"\n  },\n  \"GB\": {\n    \"country\": \"United Kingdom\",\n    \"dial_code\": \"44\"\n  },\n  \"US\": {\n    \"country\": \"United States\",\n    \"dial_code\": \"1\"\n  },\n  \"UY\": {\n    \"country\": \"Uruguay\",\n    \"dial_code\": \"598\"\n  },\n  \"UZ\": {\n    \"country\": \"Uzbekistan\",\n    \"dial_code\": \"998\"\n  },\n  \"VU\": {\n    \"country\": \"Vanuatu\",\n    \"dial_code\": \"678\"\n  },\n  \"VE\": {\n    \"country\": \"Venezuela\",\n    \"dial_code\": \"58\"\n  },\n  \"VN\": {\n    \"country\": \"Vietnam\",\n    \"dial_code\": \"84\"\n  },\n  \"VG\": {\n    \"country\": \"British Virgin Islands\",\n    \"dial_code\": \"1284\"\n  },\n  \"VI\": {\n    \"country\": \"U.S. Virgin Islands\",\n    \"dial_code\": \"1340\"\n  },\n  \"WF\": {\n    \"country\": \"Wallis and Futuna\",\n    \"dial_code\": \"681\"\n  },\n  \"YE\": {\n    \"country\": \"Yemen\",\n    \"dial_code\": \"967\"\n  },\n  \"ZM\": {\n    \"country\": \"Zambia\",\n    \"dial_code\": \"260\"\n  },\n  \"ZW\": {\n    \"country\": \"Zimbabwe\",\n    \"dial_code\": \"263\"\n  }\n}' WHERE `general_settings`.`id` = 1;

UPDATE users SET dial_code = ( SELECT JSON_UNQUOTE(JSON_EXTRACT(country_list, CONCAT('$."', users.country_code, '".dial_code'))) FROM general_settings WHERE JSON_CONTAINS_PATH(country_list, 'one', CONCAT('$."', users.country_code, '"')) );

UPDATE users SET mobile = SUBSTRING(mobile, CHAR_LENGTH(dial_code) + 1);
ALTER TABLE `general_settings` DROP `country_list`;
ALTER TABLE `general_settings` ADD `sms_template` VARCHAR(255) NULL DEFAULT NULL AFTER `email_template`, ADD `sms_from` VARCHAR(255) NULL DEFAULT NULL AFTER `sms_template`, ADD `push_title` VARCHAR(255) NULL DEFAULT NULL AFTER `sms_from`, ADD `push_template` VARCHAR(255) NULL DEFAULT NULL AFTER `push_title`;
ALTER TABLE `general_settings` ADD `firebase_config` TEXT NULL DEFAULT NULL AFTER `sms_config`, ADD `kv` TINYINT(1) NOT NULL DEFAULT '0' AFTER `firebase_config`;
ALTER TABLE `general_settings` CHANGE `sitename` `site_name` VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;
ALTER TABLE `general_settings` ADD `multi_language` TINYINT(1) NOT NULL DEFAULT '1' AFTER `currency_format`;
ALTER TABLE `general_settings` ADD `global_shortcodes` TEXT NULL DEFAULT NULL AFTER `multi_language`;
UPDATE `general_settings` SET `global_shortcodes` = '{\n \"site_name\":\"Name of your site\",\n \"site_currency\":\"Currency of your site\",\n \"currency_symbol\":\"Symbol of currency\"\n}' WHERE `general_settings`.`id` = 1;
ALTER TABLE `general_settings` ADD `maintenance_mode` TINYINT(1) NOT NULL DEFAULT '0' AFTER `global_shortcodes`;
UPDATE `general_settings` SET `available_version` = '2.0' WHERE `general_settings`.`id` = 1;

-- ====================== general_settings ======================


-- ====================== pages ======================
ALTER TABLE `pages` ADD `seo_content` TEXT NULL DEFAULT NULL AFTER `secs`;
UPDATE `pages` SET `slug` = '/' WHERE `pages`.`id` = 7;
-- ====================== pages ======================

-- ====================== admins ======================
ALTER TABLE `admins` ADD `remember_token` VARCHAR(255) NULL DEFAULT NULL AFTER `password`;
-- ====================== admins ======================


-- ====================== users ======================
ALTER TABLE `users` ADD `provider` TEXT NULL DEFAULT NULL AFTER `tsc`;
ALTER TABLE `users` ADD `kyc_data` TEXT NULL DEFAULT NULL AFTER `remember_token`;
ALTER TABLE `users` ADD `kyc_rejection_reason` VARCHAR(255) NULL DEFAULT NULL AFTER `kyc_data`;
ALTER TABLE `users` CHANGE `username` `username` VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;

ALTER TABLE `users` CHANGE `dial_code` `dial_code` VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `email`;
ALTER TABLE `users` CHANGE `address` `addressss` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;
ALTER TABLE `users` ADD `address` TEXT NULL DEFAULT NULL AFTER `zip`;
UPDATE `users`
SET
    `country_name` = JSON_UNQUOTE(JSON_EXTRACT(`addressss`, '$.country')),
    `zip` = JSON_UNQUOTE(JSON_EXTRACT(`addressss`, '$.zip')),
    `city` = JSON_UNQUOTE(JSON_EXTRACT(`addressss`, '$.city')),
    `state` = JSON_UNQUOTE(JSON_EXTRACT(`addressss`, '$.state')),
    `address` = JSON_UNQUOTE(JSON_EXTRACT(`addressss`, '$.address'));
ALTER TABLE `users` DROP `addressss`;

-- ====================== users ======================

-- ====================== deposits ======================

ALTER TABLE `deposits` CHANGE `user_id` `user_id` INT UNSIGNED NOT NULL DEFAULT '0', CHANGE `method_code` `method_code` INT UNSIGNED NOT NULL DEFAULT '0', CHANGE `method_currency` `method_currency` VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL, CHANGE `final_amo` `final_amount` DECIMAL(28,8) NOT NULL DEFAULT '0.00000000', CHANGE `btc_amo` `btc_amount` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL, CHANGE `try` `payment_try` INT NOT NULL DEFAULT '0';
ALTER TABLE `deposits` ADD `success_url` VARCHAR(255) NULL DEFAULT NULL AFTER `admin_feedback`, ADD `failed_url` VARCHAR(255) NULL DEFAULT NULL AFTER `success_url`, ADD `last_cron` INT NOT NULL DEFAULT '0' AFTER `failed_url`;

-- ====================== deposits ======================

-- ====================== notification_logs ======================
CREATE TABLE `notification_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL DEFAULT '0',
  `sender` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_from` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_to` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notification_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `notification_logs`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `notification_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
  
ALTER TABLE `notification_logs` ADD `user_read` TINYINT(1) NOT NULL DEFAULT '0' AFTER `image`;

-- ====================== notification_logs ======================


-- ====================== other ======================

ALTER TABLE `extensions` DROP `deleted_at`;
ALTER TABLE `gateways` DROP `input_form`;
ALTER TABLE `languages` DROP `text_align`;
ALTER TABLE `languages` CHANGE `icon` `flag` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;
UPDATE `extensions` SET `support` = 'fb_com.png' WHERE `extensions`.`act` = 'fb-comment';
ALTER TABLE `admin_notifications` CHANGE `read_status` `is_read` TINYINT(1) NOT NULL DEFAULT '0';

ALTER TABLE `support_messages` CHANGE `supportticket_id` `support_ticket_id` INT UNSIGNED NOT NULL DEFAULT '0';

-- ====================== form ======================
CREATE TABLE `forms` (
  `id` bigint UNSIGNED NOT NULL,
  `act` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `form_data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `forms`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `forms` MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

INSERT INTO `forms` (`act`, `form_data`, `created_at`, `updated_at`) VALUES
('kyc', '{\"full_name\":{\"name\":\"Full Name\",\"label\":\"full_name\",\"is_required\":\"required\",\"instruction\":null,\"extensions\":\"\",\"options\":[],\"type\":\"text\",\"width\":\"6\"},\"email\":{\"name\":\"Email\",\"label\":\"email\",\"is_required\":\"required\",\"instruction\":null,\"extensions\":\"\",\"options\":[],\"type\":\"email\",\"width\":\"6\"},\"gender\":{\"name\":\"Gender\",\"label\":\"gender\",\"is_required\":\"required\",\"instruction\":null,\"extensions\":null,\"options\":[\"Male\",\"Female\",\"Others\"],\"type\":\"select\",\"width\":\"6\"},\"nid_number\":{\"name\":\"NID Number\",\"label\":\"nid_number\",\"is_required\":\"required\",\"instruction\":null,\"extensions\":null,\"options\":[],\"type\":\"number\",\"width\":\"6\"},\"nid_photo_front\":{\"name\":\"NID Photo Front\",\"label\":\"nid_photo_front\",\"is_required\":\"required\",\"instruction\":\"Upload the front side of your NID\",\"extensions\":\"jpg,png\",\"options\":[],\"type\":\"file\",\"width\":\"6\"},\"nid_photo_back\":{\"name\":\"NID Photo Back\",\"label\":\"nid_photo_back\",\"is_required\":\"required\",\"instruction\":\"Upload the back side of your NID\",\"extensions\":\"jpg,jpeg,png\",\"options\":[],\"type\":\"file\",\"width\":\"6\"},\"you_hobby\":{\"name\":\"You Hobby\",\"label\":\"you_hobby\",\"is_required\":\"required\",\"instruction\":null,\"extensions\":null,\"options\":[\"Programming\",\"Gardening\",\"Traveling\",\"Others\"],\"type\":\"checkbox\",\"width\":\"12\"}}', '2022-03-17 02:56:14', '2024-05-08 08:27:07');
-- ====================== form ======================


-- ====================== gateways ======================
ALTER TABLE `gateways` CHANGE `name` `name` VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL, CHANGE `alias` `alias` VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;
ALTER TABLE `gateway_currencies` DROP `image`;
ALTER TABLE `gateways` ADD `form_id` INT NOT NULL DEFAULT '0' AFTER `code`;

-- ====================== gateways ======================

-- ====================== cron_jobs ======================

CREATE TABLE `cron_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alias` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cron_schedule_id` int NOT NULL DEFAULT '0',
  `next_run` datetime DEFAULT NULL,
  `last_run` datetime DEFAULT NULL,
  `is_running` tinyint(1) NOT NULL DEFAULT '1',
  `is_default` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `cron_job_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `cron_job_id` int UNSIGNED NOT NULL DEFAULT '0',
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `duration` int UNSIGNED NOT NULL DEFAULT '0',
  `error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `cron_schedules` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `interval` int UNSIGNED NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `cron_jobs`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `cron_job_logs`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `cron_schedules`
  ADD PRIMARY KEY (`id`);


ALTER TABLE `cron_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `cron_job_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `cron_schedules`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

-- ====================== cron_jobs ======================

-- ====================== update_logs ======================
CREATE TABLE `update_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `version` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `update_log` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `update_logs`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `update_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

-- ====================== update_logs ======================

-- ====================== notification_logs ======================

CREATE TABLE `notification_templates` (
  `id` bigint UNSIGNED NOT NULL,
  `act` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `push_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sms_body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `push_body` text COLLATE utf8mb4_unicode_ci,
  `shortcodes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `email_status` tinyint(1) NOT NULL DEFAULT '1',
  `email_sent_from_name` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_sent_from_address` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sms_status` tinyint(1) NOT NULL DEFAULT '1',
  `sms_sent_from` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `push_status` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `notification_templates`
  ADD PRIMARY KEY (`id`);
  


DROP TABLE `email_logs`, `email_sms_templates`;


INSERT INTO `notification_templates` (`id`, `act`, `name`, `subject`, `push_title`, `email_body`, `sms_body`, `push_body`, `shortcodes`, `email_status`, `email_sent_from_name`, `email_sent_from_address`, `sms_status`, `sms_sent_from`, `push_status`, `created_at`, `updated_at`) VALUES
(1, 'BAL_ADD', 'Balance - Added', 'Your Account has been Credited', '{{site_name}} - Balance Added', '<div>We\'re writing to inform you that an amount of {{amount}} {{site_currency}} has been successfully added to your account.</div><div><br></div><div>Here are the details of the transaction:</div><div><br></div><div><b>Transaction Number: </b>{{trx}}</div><div><b>Current Balance:</b> {{post_balance}} {{site_currency}}</div><div><b>Admin Note:</b> {{remark}}</div><div><br></div><div>If you have any questions or require further assistance, please don\'t hesitate to contact us. We\'re here to assist you.</div>', 'We\'re writing to inform you that an amount of {{amount}} {{site_currency}} has been successfully added to your account.', '{{amount}} {{site_currency}} has been successfully added to your account.', '{\"trx\":\"Transaction number for the action\",\"amount\":\"Amount inserted by the admin\",\"remark\":\"Remark inserted by the admin\",\"post_balance\":\"Balance of the user after this transaction\"}', 1, '{{site_name}} Finance', NULL, 0, NULL, 1, '2021-11-03 12:00:00', '2024-05-25 00:49:44'),
(2, 'BAL_SUB', 'Balance - Subtracted', 'Your Account has been Debited', '{{site_name}} - Balance Subtracted', '<div>We wish to inform you that an amount of {{amount}} {{site_currency}} has been successfully deducted from your account.</div><div><br></div><div>Below are the details of the transaction:</div><div><br></div><div><b>Transaction Number:</b> {{trx}}</div><div><b>Current Balance: </b>{{post_balance}} {{site_currency}}</div><div><b>Admin Note:</b> {{remark}}</div><div><br></div><div>Should you require any further clarification or assistance, please do not hesitate to reach out to us. We are here to assist you in any way we can.</div><div><br></div><div>Thank you for your continued trust in {{site_name}}.</div>', 'We wish to inform you that an amount of {{amount}} {{site_currency}} has been successfully deducted from your account.', '{{amount}} {{site_currency}} debited from your account.', '{\"trx\":\"Transaction number for the action\",\"amount\":\"Amount inserted by the admin\",\"remark\":\"Remark inserted by the admin\",\"post_balance\":\"Balance of the user after this transaction\"}', 1, '{{site_name}} Finance', NULL, 1, NULL, 0, '2021-11-03 12:00:00', '2024-05-08 07:17:48'),
(3, 'DEPOSIT_COMPLETE', 'Deposit - Automated - Successful', 'Deposit Completed Successfully', '{{site_name}} - Deposit successful', '<div>We\'re delighted to inform you that your deposit of {{amount}} {{site_currency}} via {{method_name}} has been completed.</div><div><br></div><div>Below, you\'ll find the details of your deposit:</div><div><br></div><div><b>Amount:</b> {{amount}} {{site_currency}}</div><div><b>Charge: </b>{{charge}} {{site_currency}}</div><div><b>Conversion Rate:</b> 1 {{site_currency}} = {{rate}} {{method_currency}}</div><div><b>Received:</b> {{method_amount}} {{method_currency}}</div><div><b>Paid via:</b> {{method_name}}</div><div><b>Transaction Number:</b> {{trx}}</div><div><br></div><div>Your current balance stands at {{post_balance}} {{site_currency}}.</div><div><br></div><div>If you have any questions or need further assistance, feel free to reach out to our support team. We\'re here to assist you in any way we can.</div>', 'We\'re delighted to inform you that your deposit of {{amount}} {{site_currency}} via {{method_name}} has been completed.', 'Deposit Completed Successfully', '{\"trx\":\"Transaction number for the deposit\",\"amount\":\"Amount inserted by the user\",\"charge\":\"Gateway charge set by the admin\",\"rate\":\"Conversion rate between base currency and method currency\",\"method_name\":\"Name of the deposit method\",\"method_currency\":\"Currency of the deposit method\",\"method_amount\":\"Amount after conversion between base currency and method currency\",\"post_balance\":\"Balance of the user after this transaction\"}', 1, '{{site_name}} Billing', NULL, 1, NULL, 1, '2021-11-03 12:00:00', '2024-05-08 07:20:34'),
(4, 'DEPOSIT_APPROVE', 'Deposit - Manual - Approved', 'Deposit Request Approved', '{{site_name}} - Deposit Request Approved', '<div>We are pleased to inform you that your deposit request of {{amount}} {{site_currency}} via {{method_name}} has been approved.</div><div><br></div><div>Here are the details of your deposit:</div><div><br></div><div><b>Amount:</b> {{amount}} {{site_currency}}</div><div><b>Charge: </b>{{charge}} {{site_currency}}</div><div><b>Conversion Rate:</b> 1 {{site_currency}} = {{rate}} {{method_currency}}</div><div><b>Received: </b>{{method_amount}} {{method_currency}}</div><div><b>Paid via: </b>{{method_name}}</div><div><b>Transaction Number: </b>{{trx}}</div><div><br></div><div>Your current balance now stands at {{post_balance}} {{site_currency}}.</div><div><br></div><div>Should you have any questions or require further assistance, please feel free to contact our support team. We\'re here to help.</div>', 'We are pleased to inform you that your deposit request of {{amount}} {{site_currency}} via {{method_name}} has been approved.', 'Deposit of {{amount}} {{site_currency}} via {{method_name}} has been approved.', '{\"trx\":\"Transaction number for the deposit\",\"amount\":\"Amount inserted by the user\",\"charge\":\"Gateway charge set by the admin\",\"rate\":\"Conversion rate between base currency and method currency\",\"method_name\":\"Name of the deposit method\",\"method_currency\":\"Currency of the deposit method\",\"method_amount\":\"Amount after conversion between base currency and method currency\",\"post_balance\":\"Balance of the user after this transaction\"}', 1, '{{site_name}} Billing', NULL, 1, NULL, 0, '2021-11-03 12:00:00', '2024-05-08 07:19:49'),
(5, 'DEPOSIT_REJECT', 'Deposit - Manual - Rejected', 'Deposit Request Rejected', '{{site_name}} - Deposit Request Rejected', '<div>We regret to inform you that your deposit request of {{amount}} {{site_currency}} via {{method_name}} has been rejected.</div><div><br></div><div>Here are the details of the rejected deposit:</div><div><br></div><div><b>Conversion Rate:</b> 1 {{site_currency}} = {{rate}} {{method_currency}}</div><div><b>Received:</b> {{method_amount}} {{method_currency}}</div><div><b>Paid via:</b> {{method_name}}</div><div><b>Charge:</b> {{charge}}</div><div><b>Transaction Number:</b> {{trx}}</div><div><br></div><div>If you have any questions or need further clarification, please don\'t hesitate to contact us. We\'re here to assist you.</div><div><br></div><div>Rejection Reason:</div><div>{{rejection_message}}</div><div><br></div><div>Thank you for your understanding.</div>', 'We regret to inform you that your deposit request of {{amount}} {{site_currency}} via {{method_name}} has been rejected.', 'Your deposit request of {{amount}} {{site_currency}} via {{method_name}} has been rejected.', '{\"trx\":\"Transaction number for the deposit\",\"amount\":\"Amount inserted by the user\",\"charge\":\"Gateway charge set by the admin\",\"rate\":\"Conversion rate between base currency and method currency\",\"method_name\":\"Name of the deposit method\",\"method_currency\":\"Currency of the deposit method\",\"method_amount\":\"Amount after conversion between base currency and method currency\",\"rejection_message\":\"Rejection message by the admin\"}', 1, '{{site_name}} Billing', NULL, 1, NULL, 0, '2021-11-03 12:00:00', '2024-05-08 07:20:13'),
(6, 'DEPOSIT_REQUEST', 'Deposit - Manual - Requested', 'Deposit Request Submitted Successfully', NULL, '<div>We are pleased to confirm that your deposit request of {{amount}} {{site_currency}} via {{method_name}} has been submitted successfully.</div><div><br></div><div>Below are the details of your deposit:</div><div><br></div><div><b>Amount:</b> {{amount}} {{site_currency}}</div><div><b>Charge:</b> {{charge}} {{site_currency}}</div><div><b>Conversion Rate:</b> 1 {{site_currency}} = {{rate}} {{method_currency}}</div><div><b>Payable:</b> {{method_amount}} {{method_currency}}</div><div><b>Pay via: </b>{{method_name}}</div><div><b>Transaction Number:</b> {{trx}}</div><div><br></div><div>Should you have any questions or require further assistance, please feel free to reach out to our support team. We\'re here to assist you.</div>', 'We are pleased to confirm that your deposit request of {{amount}} {{site_currency}} via {{method_name}} has been submitted successfully.', 'Your deposit request of {{amount}} {{site_currency}} via {{method_name}} submitted successfully.', '{\"trx\":\"Transaction number for the deposit\",\"amount\":\"Amount inserted by the user\",\"charge\":\"Gateway charge set by the admin\",\"rate\":\"Conversion rate between base currency and method currency\",\"method_name\":\"Name of the deposit method\",\"method_currency\":\"Currency of the deposit method\",\"method_amount\":\"Amount after conversion between base currency and method currency\"}', 1, '{{site_name}} Billing', NULL, 1, NULL, 0, '2021-11-03 12:00:00', '2024-04-25 03:27:42'),
(7, 'PASS_RESET_CODE', 'Password - Reset - Code', 'Password Reset', '{{site_name}} Password Reset Code', '<div>We\'ve received a request to reset the password for your account on <b>{{time}}</b>. The request originated from\r\n            the following IP address: <b>{{ip}}</b>, using <b>{{browser}}</b> on <b>{{operating_system}}</b>.\r\n    </div><br>\r\n    <div><span>To proceed with the password reset, please use the following account recovery code</span>: <span><b><font size=\"6\">{{code}}</font></b></span></div><br>\r\n    <div><span>If you did not initiate this password reset request, please disregard this message. Your account security\r\n            remains our top priority, and we advise you to take appropriate action if you suspect any unauthorized\r\n            access to your account.</span></div>', 'To proceed with the password reset, please use the following account recovery code: {{code}}', 'To proceed with the password reset, please use the following account recovery code: {{code}}', '{\"code\":\"Verification code for password reset\",\"ip\":\"IP address of the user\",\"browser\":\"Browser of the user\",\"operating_system\":\"Operating system of the user\",\"time\":\"Time of the request\"}', 1, '{{site_name}} Authentication Center', NULL, 0, NULL, 0, '2021-11-03 12:00:00', '2024-05-08 07:24:57'),
(8, 'PASS_RESET_DONE', 'Password - Reset - Confirmation', 'Password Reset Successful', NULL, '<div><div><span>We are writing to inform you that the password reset for your account was successful. This action was completed at {{time}} from the following browser</span>: <span>{{browser}}</span><span>on {{operating_system}}, with the IP address</span>: <span>{{ip}}</span>.</div><br><div><span>Your account security is our utmost priority, and we are committed to ensuring the safety of your information. If you did not initiate this password reset or notice any suspicious activity on your account, please contact our support team immediately for further assistance.</span></div></div>', 'We are writing to inform you that the password reset for your account was successful.', 'We are writing to inform you that the password reset for your account was successful.', '{\"ip\":\"IP address of the user\",\"browser\":\"Browser of the user\",\"operating_system\":\"Operating system of the user\",\"time\":\"Time of the request\"}', 1, '{{site_name}} Authentication Center', NULL, 1, NULL, 0, '2021-11-03 12:00:00', '2024-04-25 03:27:24'),
(9, 'ADMIN_SUPPORT_REPLY', 'Support - Reply', 'Re: {{ticket_subject}} - Ticket #{{ticket_id}}', '{{site_name}} - Support Ticket Replied', '<div>\r\n    <div><span>Thank you for reaching out to us regarding your support ticket with the subject</span>:\r\n        <span>\"{{ticket_subject}}\"&nbsp;</span><span>and ticket ID</span>: {{ticket_id}}.</div><br>\r\n    <div><span>We have carefully reviewed your inquiry, and we are pleased to provide you with the following\r\n            response</span><span>:</span></div><br>\r\n    <div>{{reply}}</div><br>\r\n    <div><span>If you have any further questions or need additional assistance, please feel free to reply by clicking on\r\n            the following link</span>: <a href=\"{{link}}\" title=\"\" target=\"_blank\">{{link}}</a><span>. This link will take you to\r\n            the ticket thread where you can provide further information or ask for clarification.</span></div><br>\r\n    <div><span>Thank you for your patience and cooperation as we worked to address your concerns.</span></div>\r\n</div>', 'Thank you for reaching out to us regarding your support ticket with the subject: \"{{ticket_subject}}\" and ticket ID: {{ticket_id}}. We have carefully reviewed your inquiry. To check the response, please go to the following link: {{link}}', 'Re: {{ticket_subject}} - Ticket #{{ticket_id}}', '{\"ticket_id\":\"ID of the support ticket\",\"ticket_subject\":\"Subject  of the support ticket\",\"reply\":\"Reply made by the admin\",\"link\":\"URL to view the support ticket\"}', 1, '{{site_name}} Support Team', NULL, 1, NULL, 0, '2021-11-03 12:00:00', '2024-05-08 07:26:06'),
(10, 'EVER_CODE', 'Verification - Email', 'Email Verification Code', NULL, '<div>\r\n    <div><span>Thank you for taking the time to verify your email address with us. Your email verification code\r\n            is</span>: <b><font size=\"6\">{{code}}</font></b></div><br>\r\n    <div><span>Please enter this code in the designated field on our platform to complete the verification\r\n            process.</span></div><br>\r\n    <div><span>If you did not request this verification code, please disregard this email. Your account security is our\r\n            top priority, and we advise you to take appropriate measures if you suspect any unauthorized access.</span>\r\n    </div><br>\r\n    <div><span>If you have any questions or encounter any issues during the verification process, please don\'t hesitate\r\n            to contact our support team for assistance.</span></div><br>\r\n    <div><span>Thank you for choosing us.</span></div>\r\n</div>', '---', '---', '{\"code\":\"Email verification code\"}', 1, '{{site_name}} Verification Center', NULL, 0, NULL, 0, '2021-11-03 12:00:00', '2024-04-25 03:27:12'),
(11, 'SVER_CODE', 'Verification - SMS', 'Verify Your Mobile Number', NULL, '---', 'Your mobile verification code is {{code}}. Please enter this code in the appropriate field to verify your mobile number. If you did not request this code, please ignore this message.', '---', '{\"code\":\"SMS Verification Code\"}', 0, '{{site_name}} Verification Center', NULL, 1, NULL, 0, '2021-11-03 12:00:00', '2024-04-25 03:27:03'),
(12, 'WITHDRAW_APPROVE', 'Withdraw - Approved', 'Withdrawal Confirmation: Your Request Processed Successfully', '{{site_name}} - Withdrawal Request Approved', '<div>We are writing to inform you that your withdrawal request of {{amount}} {{site_currency}} via {{method_name}} has been processed successfully.</div><div><br></div><div>Below are the details of your withdrawal:</div><div><br></div><div><b>Amount:</b> {{amount}} {{site_currency}}</div><div><b>Charge:</b> {{charge}} {{site_currency}}</div><div><b>Conversion Rate:</b> 1 {{site_currency}} = {{rate}} {{method_currency}}</div><div><b>You will receive:</b> {{method_amount}} {{method_currency}}</div><div><b>Via:</b> {{method_name}}</div><div><b>Transaction Number:</b> {{trx}}</div><div><br></div><hr><div><br></div><div><b>Details of Processed Payment:</b></div><div>{{admin_details}}</div><div><br></div><div>Should you have any questions or require further assistance, feel free to reach out to our support team. We\'re here to help.</div>', 'We are writing to inform you that your withdrawal request of {{amount}} {{site_currency}} via {{method_name}} has been processed successfully.', 'Withdrawal Confirmation: Your Request Processed Successfully', '{\"trx\":\"Transaction number for the withdraw\",\"amount\":\"Amount requested by the user\",\"charge\":\"Gateway charge set by the admin\",\"rate\":\"Conversion rate between base currency and method currency\",\"method_name\":\"Name of the withdraw method\",\"method_currency\":\"Currency of the withdraw method\",\"method_amount\":\"Amount after conversion between base currency and method currency\",\"admin_details\":\"Details provided by the admin\"}', 1, '{{site_name}} Finance', NULL, 1, NULL, 0, '2021-11-03 12:00:00', '2024-05-08 07:26:37'),
(13, 'WITHDRAW_REJECT', 'Withdraw - Rejected', 'Withdrawal Request Rejected', '{{site_name}} - Withdrawal Request Rejected', '<div>We regret to inform you that your withdrawal request of {{amount}} {{site_currency}} via {{method_name}} has been rejected.</div><div><br></div><div>Here are the details of your withdrawal:</div><div><br></div><div><b>Amount:</b> {{amount}} {{site_currency}}</div><div><b>Charge:</b> {{charge}} {{site_currency}}</div><div><b>Conversion Rate:</b> 1 {{site_currency}} = {{rate}} {{method_currency}}</div><div><b>Expected Amount:</b> {{method_amount}} {{method_currency}}</div><div><b>Via:</b> {{method_name}}</div><div><b>Transaction Number:</b> {{trx}}</div><div><br></div><hr><div><br></div><div><b>Refund Details:</b></div><div>{{amount}} {{site_currency}} has been refunded to your account, and your current balance is {{post_balance}} {{site_currency}}.</div><div><br></div><hr><div><br></div><div><b>Reason for Rejection:</b></div><div>{{admin_details}}</div><div><br></div><div>If you have any questions or concerns regarding this rejection or need further assistance, please do not hesitate to contact our support team. We apologize for any inconvenience this may have caused.</div>', 'We regret to inform you that your withdrawal request of {{amount}} {{site_currency}} via {{method_name}} has been rejected.', 'Withdrawal Request Rejected', '{\"trx\":\"Transaction number for the withdraw\",\"amount\":\"Amount requested by the user\",\"charge\":\"Gateway charge set by the admin\",\"rate\":\"Conversion rate between base currency and method currency\",\"method_name\":\"Name of the withdraw method\",\"method_currency\":\"Currency of the withdraw method\",\"method_amount\":\"Amount after conversion between base currency and method currency\",\"post_balance\":\"Balance of the user after fter this action\",\"admin_details\":\"Rejection message by the admin\"}', 1, '{{site_name}} Finance', NULL, 1, NULL, 0, '2021-11-03 12:00:00', '2024-05-08 07:26:55'),
(14, 'WITHDRAW_REQUEST', 'Withdraw - Requested', 'Withdrawal Request Confirmation', '{{site_name}} - Requested for withdrawal', '<div>We are pleased to inform you that your withdrawal request of {{amount}} {{site_currency}} via {{method_name}} has been submitted successfully.</div><div><br></div><div>Here are the details of your withdrawal:</div><div><br></div><div><b>Amount:</b> {{amount}} {{site_currency}}</div><div><b>Charge:</b> {{charge}} {{site_currency}}</div><div><b>Conversion Rate:</b> 1 {{site_currency}} = {{rate}} {{method_currency}}</div><div><b>Expected Amount:</b> {{method_amount}} {{method_currency}}</div><div><b>Via:</b> {{method_name}}</div><div><b>Transaction Number:</b> {{trx}}</div><div><br></div><div>Your current balance is {{post_balance}} {{site_currency}}.</div><div><br></div><div>Should you have any questions or require further assistance, feel free to reach out to our support team. We\'re here to help.</div>', 'We are pleased to inform you that your withdrawal request of {{amount}} {{site_currency}} via {{method_name}} has been submitted successfully.', 'Withdrawal request submitted successfully', '{\"trx\":\"Transaction number for the withdraw\",\"amount\":\"Amount requested by the user\",\"charge\":\"Gateway charge set by the admin\",\"rate\":\"Conversion rate between base currency and method currency\",\"method_name\":\"Name of the withdraw method\",\"method_currency\":\"Currency of the withdraw method\",\"method_amount\":\"Amount after conversion between base currency and method currency\",\"post_balance\":\"Balance of the user after fter this transaction\"}', 1, '{{site_name}} Finance', NULL, 1, NULL, 0, '2021-11-03 12:00:00', '2024-05-08 07:27:20'),
(15, 'DEFAULT', 'Default Template', '{{subject}}', '{{subject}}', '{{message}}', '{{message}}', '{{message}}', '{\"subject\":\"Subject\",\"message\":\"Message\"}', 1, NULL, NULL, 1, NULL, 1, '2019-09-14 13:14:22', '2024-05-16 01:32:53'),
(16, 'KYC_APPROVE', 'KYC Approved', 'KYC Details has been approved', '{{site_name}} - KYC Approved', '<div><div><span>We are pleased to inform you that your Know Your Customer (KYC) information has been successfully reviewed and approved. This means that you are now eligible to conduct any payout operations within our system.</span></div><br><div><span>Your commitment to completing the KYC process promptly is greatly appreciated, as it helps us ensure the security and integrity of our platform for all users.</span></div><br><div><span>With your KYC verification now complete, you can proceed with confidence to carry out any payout transactions you require. Should you encounter any issues or have any questions along the way, please don\'t hesitate to reach out to our support team. We\'re here to assist you every step of the way.</span></div><br><div><span>Thank you once again for choosing {{site_name}} and for your cooperation in this matter.</span></div></div>', 'We are pleased to inform you that your Know Your Customer (KYC) information has been successfully reviewed and approved. This means that you are now eligible to conduct any payout operations within our system.', 'Your  Know Your Customer (KYC) information has been approved successfully', '[]', 1, '{{site_name}} Verification Center', NULL, 1, NULL, 0, NULL, '2024-05-08 07:23:57'),
(17, 'KYC_REJECT', 'KYC Rejected', 'KYC has been rejected', '{{site_name}} - KYC Rejected', '<div><div><span>We regret to inform you that the Know Your Customer (KYC) information provided has been reviewed and unfortunately, it has not met our verification standards. As a result, we are unable to approve your KYC submission at this time.</span></div><br><div><span>We understand that this news may be disappointing, and we want to assure you that we take these matters seriously to maintain the security and integrity of our platform.</span></div><br><div><span>Reasons for rejection may include discrepancies or incomplete information in the documentation provided. If you believe there has been a misunderstanding or if you would like further clarification on why your KYC was rejected, please don\'t hesitate to contact our support team.</span></div><br><div><span>We encourage you to review your submitted information and ensure that all details are accurate and up-to-date. Once any necessary adjustments have been made, you are welcome to resubmit your KYC information for review.</span></div><br><div><span>We apologize for any inconvenience this may cause and appreciate your understanding and cooperation in this matter.</span></div><br><div>Rejection Reason:</div><div>{{reason}}</div><div><br></div><div><span>Thank you for your continued support and patience.</span></div></div>', 'We regret to inform you that the Know Your Customer (KYC) information provided has been reviewed and unfortunately, it has not met our verification standards. As a result, we are unable to approve your KYC submission at this time. We encourage you to review your submitted information and ensure that all details are accurate and up-to-date. Once any necessary adjustments have been made, you are welcome to resubmit your KYC information for review.', 'Your  Know Your Customer (KYC) information has been rejected', '{\"reason\":\"Rejection Reason\"}', 1, '{{site_name}} Verification Center', NULL, 1, NULL, 0, NULL, '2024-05-08 07:24:13'),
(18, 'ORDER_SHIPPED', 'Order Shipped', 'Order shipped successfully', NULL, 'Product Name :&nbsp;{{product_name}}<div>Quentity :&nbsp;{{quantity}}</div><div>Per Price :&nbsp;{{currency_symbol}}{{price}}</div><div><span style=\"color: rgb(33, 37, 41);\">Total Price :&nbsp;{{currency_symbol}}</span><span style=\"background-color: var(--bs-card-bg); text-align: var(--bs-body-text-align);\"><font color=\"#212529\">{{total_price}}</font></span><br></div><div>Transition :&nbsp;{{trx}}</div>', 'Product Name : {{product_name}}\r\nQuentity : {{quantity}}\r\nPer Price : {{currency_symbol}}{{price}}\r\nTotal Price : {{currency_symbol}}{{total_price}}\r\nTransition : {{trx}}', 'Product Name : {{product_name}}\r\nQuentity : {{quantity}}\r\nPer Price : {{currency_symbol}}{{price}}\r\nTotal Price : {{currency_symbol}}{{total_price}}\r\nTransition : {{trx}}', '{\r\n    \"product_name\": \"Product Name\",\r\n    \"quantity\": \"Order Quantity\",\r\n    \"price\": \"Product Price\",\r\n    \"total_price\": \"Total Price\",\r\n    \"trx\": \"Transaction Number\"\r\n}', 1, NULL, NULL, 1, NULL, 0, NULL, '2024-06-11 23:39:59'),
(19, 'BAL_SEND', 'Balance Send', 'Balance Transfer Successfully', 'Balance Transfer Successfully', '<div>Balance transferred successfully complete. You send  {{amount}} {{site_currency}}  to  {{username}}  And charge to transfer  {{charge}} {{site_currency}} .</div><div><br></div><div>Transaction number {{trx}} .<br></div><div><br></div><div> Your Current Balance is {{balance_now}}  {{site_currency}}.</div>', 'Balance transferred successfully complete. You send {{amount}} {{site_currency}} to {{username}} And charge to transfer {{charge}} {{site_currency}} .\r\n\r\nTransaction number {{trx}} .\r\n\r\nYour Current Balance is {{balance_now}} {{site_currency}}.', 'Balance transferred successfully complete. You send {{amount}} {{site_currency}} to {{username}} And charge to transfer {{charge}} {{site_currency}} .\r\n\r\nTransaction number {{trx}} .\r\n\r\nYour Current Balance is {{balance_now}} {{site_currency}}.', '{\r\n    \"amount\": \"Send Amount\",\r\n    \"username\": \"Receiver Username\",\r\n    \"charge\": \"Transfer charge\",\r\n    \"balance_now\": \"After Balance\",\r\n    \"trx\": \"Transaction number\"\r\n}', 1, NULL, NULL, 1, NULL, 0, NULL, NULL),
(20, 'BAL_RECEIVE', 'Balance Received', 'Balance Received Successfully', 'Balance Received Successfully', 'Balance received successfully. You got {{amount}} \r\n{{site_currency}} from&nbsp; {{username}}  And charge to transfer  {{charge}}{{site_currency}} .<div><div><br></div><div>Transaction number {{trx}} .<br></div><div><br></div><div> Your Current Balance is {{balance_now}}{{site_currency}}.</div></div>', 'Balance received successfully. You got {{amount}} {{site_currency}} from  {{username}} And charge to transfer {{charge}}{{site_currency}} .\r\n\r\nTransaction number {{trx}} .\r\n\r\nYour Current Balance is {{balance_now}}{{site_currency}}.', 'Balance received successfully. You got {{amount}} {{site_currency}} from  {{username}} And charge to transfer {{charge}}{{site_currency}} .\r\n\r\nTransaction number {{trx}} .\r\n\r\nYour Current Balance is {{balance_now}}{{site_currency}}.', '{\r\n    \"amount\": \"Received Amount\",\r\n    \"username\": \"Sender Username\",\r\n    \"charge\": \"Transfer charge\",\r\n    \"balance_now\": \" After Balance\",\r\n    \"trx\": \"Transaction number\"\r\n}', 1, NULL, NULL, 1, NULL, 0, NULL, NULL),
(21, 'PLAN_PURCHASED', 'Plan Purchased', 'Plan Purchased successfully', 'Congratulation, you successfully&nbsp;Purchased {{plan}},&nbsp; {{amount}} {{currency}}&nbsp; And your current balance is {{post_balance}}&nbsp;<span style=\\\"color: rgb(33, 37, 41);\\\">&nbsp;{{currency}}</span>. Transaction number {{trx}}', '<span style=\"color: rgb(33, 37, 41);\">Congratulation, you successfully Purchased {{plan}}, {{amount}} {{site_currency}} And your current balance is {{post_balance}} {{site_currency}}. Transaction number {{trx}}</span>', 'Congratulation, you successfully Purchased {{plan}}, {{amount}} {{site_currency}} And your current balance is {{post_balance}} {{site_currency}}. Transaction number {{trx}}', 'Congratulation, you successfully Purchased {{plan}}, {{amount}} {{site_currency}} And your current balance is {{post_balance}} {{site_currency}}. Transaction number {{trx}}', '{\r\n    \"plan\": \"Plan name\",\r\n    \"amount\": \"Plan price\",\r\n    \"post_balance\": \" After Balance\",\r\n    \"trx\": \"Transaction number\"\r\n}', 1, NULL, NULL, 1, NULL, 0, NULL, '2024-06-23 06:57:41'),
(22, 'REFERRAL_COMMISSION', 'Referral Commission', 'Referral Commission', 'Referral Commission', '<font color=\"\\&quot;#212529\\&quot;\">Congratulation, you get&nbsp;</font><span style=\"color: rgb(33, 37, 41);\">Referral&nbsp;</span><span style=\"color: rgb(33, 37, 41);\">Commission from user {{username}} ,&nbsp; &nbsp;{{amount}}&nbsp;</span><font color=\"#001290\">{{site_currency}}</font><span style=\"color: rgb(33, 37, 41);\">&nbsp;&nbsp;And your current balance is {{post_balance}}&nbsp;</span><span rgb(33,=\"\" 37,=\"\" 41);\\\"=\"\">&nbsp;{{site_currency}}</span><span rgb(33,=\"\" 37,=\"\" 41);\\\"=\"\">. Transaction number {{trx}}</span><br>', 'Congratulation, you get Referral Commission from user {{username}} ,   {{amount}} {{site_currency}}  And your current balance is {{post_balance}}  {{site_currency}}. Transaction number {{trx}}', 'Congratulation, you get Referral Commission from user {{username}} ,   {{amount}} {{site_currency}}  And your current balance is {{post_balance}}  {{site_currency}}. Transaction number {{trx}}', '{\r\n    \"username\": \"commission From user\",\r\n    \"amount\": \"Plan price\",\r\n    \"post_balance\": \" After Balance\",\r\n    \"trx\": \"Transaction number\"\r\n}', 1, NULL, NULL, 1, NULL, 0, NULL, '2024-06-15 03:30:35'),
(23, 'ORDER_PLACED', 'Order Placed', 'Order placed successfully', NULL, 'Product Name :&nbsp;{{product_name}}<div>Quantity :&nbsp;{{quantity}}</div><div>Price :&nbsp;{{price}}{{site_currency}}</div><div>Total Price :&nbsp;{{total_price}}<span style=\"color: rgb(33, 37, 41); background-color: var(--bs-card-bg); font-size: 1rem; text-align: var(--bs-body-text-align);\">{{site_currency}}</span></div><div><span style=\"color: rgb(33, 37, 41); background-color: var(--bs-card-bg); font-size: 1rem; text-align: var(--bs-body-text-align);\">Transaction No :&nbsp;</span><span style=\"background-color: var(--bs-card-bg); text-align: var(--bs-body-text-align);\"><font color=\"#212529\">{{trx}}</font></span></div>', 'Product Name : {{product_name}}\r\nQuantity : {{quantity}}\r\nPrice : {{price}}{{site_currency}}\r\nTotal Price : {{total_price}}{{site_currency}}\r\nTransaction No : {{trx}}', 'Product Name : {{product_name}}\r\nQuantity : {{quantity}}\r\nPrice : {{price}}{{site_currency}}\r\nTotal Price : {{total_price}}{{site_currency}}\r\nTransaction No : {{trx}}', '{\r\n    \"product_name\": \"Product Name\",\r\n    \"quantity\": \"Order Quantity\",\r\n    \"price\": \"Product Price\",\r\n    \"total_price\": \"Total Price\",\r\n    \"trx\": \"Transaction Number\"\r\n}', 1, NULL, NULL, 1, NULL, 0, NULL, '2024-06-23 07:09:14'),
(24, 'ORDER_CANCELED', 'Order Cancelled', 'Order canceled successfully', NULL, '<span style=\"color: rgb(33, 37, 41);\">Product Name :&nbsp;{{product_name}}</span><div>Quentity :&nbsp;{{quantity}}</div><div>Per Price :&nbsp;{{currency_symbol}}{{price}}</div><div><span style=\"color: rgb(33, 37, 41);\">Total Price :&nbsp;{{currency_symbol}}</span><span style=\"background-color: var(--bs-card-bg); text-align: var(--bs-body-text-align);\"><font color=\"#212529\">{{total_price}}</font></span><br></div><div>Transition :&nbsp;{{trx}}</div>', 'Product Name : {{product_name}}\r\nQuentity : {{quantity}}\r\nPer Price : {{currency_symbol}}{{price}}\r\nTotal Price : {{currency_symbol}}{{total_price}}\r\nTransition : {{trx}}', 'Product Name : {{product_name}}\r\nQuentity : {{quantity}}\r\nPer Price : {{currency_symbol}}{{price}}\r\nTotal Price : {{currency_symbol}}{{total_price}}\r\nTransition : {{trx}}', '{\r\n    \"product_name\": \"Product Name\",\r\n    \"quantity\": \"Order Quantity\",\r\n    \"price\": \"Product Price\",\r\n    \"total_price\": \"Total Price\",\r\n    \"trx\": \"Transaction Number\"\r\n}', 1, NULL, NULL, 1, NULL, 0, NULL, '2024-06-23 07:14:35'),
(25, 'MATCHING_BONUS', 'Matching bonus', 'Binary Commission', 'Congratulation, You get {{amount}} {{currency}}  For BV {{paid_bv}}. And your current balance is {{post_balance}}  {{currency}}.', 'Congratulation, You get  {{amount}}{{site_currency}}&nbsp; For BV {{paid_bv}}. And your current balance is {{post_balance}}{{site_currency}}. Transaction number {{trx}}.', 'Congratulation, You get {{amount}}{{site_currency}}  For BV {{paid_bv}}. And your current balance is {{post_balance}}{{site_currency}}. Transaction number {{trx}}.', 'Congratulation, You get {{amount}}{{site_currency}}  For BV {{paid_bv}}. And your current balance is {{post_balance}}{{site_currency}}. Transaction number {{trx}}.', '{\r\n    \"amount\": \"matching bonus amount\",\r\n    \"paid_bv\": \"For BV\",\r\n    \"post_balance\": \" After Balance\",\r\n    \"trx\": \"Transaction number\"\r\n}', 1, NULL, NULL, 1, NULL, 0, NULL, '2024-06-23 07:57:09');


-- ====================== notification_logs ======================


-- ====================== INSERT ======================
INSERT INTO `cron_jobs` (`id`, `name`, `alias`, `action`, `url`, `cron_schedule_id`, `next_run`, `last_run`, `is_running`, `is_default`, `created_at`, `updated_at`) VALUES
(4, 'Matching Bonus', 'matching-bonus', '[\"\\\\App\\\\Http\\\\Controllers\\\\CronController\",\"matchingBound\"]', NULL, 3, '2024-06-28 16:49:17', '2024-06-27 16:49:17', 1, 1, '2024-05-06 04:46:06', '2024-06-27 10:49:17');


INSERT INTO `cron_schedules` (`id`, `name`, `interval`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Hourly', 3600, 1, '2024-03-13 23:34:09', '2024-05-06 04:45:32'),
(3, 'Daily', 86400, 1, '2024-05-06 04:46:39', '2024-05-06 04:46:39'),
(4, 'Monthly', 2629743, 1, '2024-06-15 05:35:12', '2024-06-15 05:35:12'),
(5, 'Weekly', 604800, 1, '2024-06-15 05:35:46', '2024-06-15 05:35:46');


-- ====================== withdrawals ======================

ALTER TABLE `withdrawals` CHANGE `method_id` `method_id` INT UNSIGNED NOT NULL DEFAULT '0', CHANGE `user_id` `user_id` INT UNSIGNED NOT NULL DEFAULT '0';

ALTER TABLE `withdrawals` ADD `form_id` INT NOT NULL DEFAULT '0' AFTER `id`;
ALTER TABLE `withdraw_methods` DROP `user_data`;

-- ====================== withdrawals ======================

  
INSERT INTO `frontends` (`data_keys`, `data_values`, `seo_content`, `tempname`, `slug`, `created_at`, `updated_at`) VALUES
('maintenance.data', '{\"description\":\"<div class=\\\"mb-5\\\" style=\\\"font-family: Nunito, sans-serif; margin-bottom: 3rem !important;\\\"><h3 class=\\\"mb-3\\\" style=\\\"text-align: center; font-weight: 600; line-height: 1.3; font-size: 24px; font-family: Exo, sans-serif;\\\"><font color=\\\"#ff0000\\\">THE SITE IS UNDER MAINTENANCE<\\/font><\\/h3><p class=\\\"font-18\\\" style=\\\"color: rgb(111, 111, 111); text-align: center; margin-right: 0px; margin-left: 0px; font-size: 18px !important;\\\">We\'re just tuning up a few things.We apologize for the inconvenience but Front is currently undergoing planned maintenance. Thanks for your patience.<\\/p><\\/div>\",\"image\":\"6603c203472ad1711522307.png\"}', NULL, NULL, NULL, '2020-07-04 23:42:52', '2024-03-27 06:51:47');




-- ====================== device_tokens ======================
CREATE TABLE `device_tokens` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL DEFAULT '0',
  `is_app` tinyint(1) NOT NULL DEFAULT '0',
  `token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
ALTER TABLE `device_tokens`
  ADD PRIMARY KEY (`id`);

  ALTER TABLE `device_tokens`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;
-- ====================== device_tokens ======================

ALTER TABLE `general_settings` ADD `pn` TINYINT(1) NOT NULL DEFAULT '1' AFTER `sn`;


ALTER TABLE `languages` CHANGE `flag` `image` VARCHAR(255) NULL DEFAULT NULL;

DROP TABLE `migrations`;
DROP TABLE `personal_access_tokens`;


-- gateway & gateway currency update
INSERT INTO `gateways` (`id`, `form_id`, `code`, `name`, `alias`, `status`, `gateway_parameters`, `supported_currencies`, `crypto`, `extra`, `description`, `created_at`, `updated_at`) VALUES (NULL, '0', '510', 'Binance', 'Binance', '1', '{\"api_key\":{\"title\":\"API Key\",\"global\":true,\"value\":\"tsu3tjiq0oqfbtmlbevoeraxhfbp3brejnm9txhjxcp4to29ujvakvfl1ibsn3ja\"},\"secret_key\":{\"title\":\"Secret Key\",\"global\":true,\"value\":\"jzngq4t04ltw8d4iqpi7admfl8tvnpehxnmi34id1zvfaenbwwvsvw7llw3zdko8\"},\"merchant_id\":{\"title\":\"Merchant ID\",\"global\":true,\"value\":\"231129033\"}}', '{\"BTC\":\"Bitcoin\",\"USD\":\"USD\",\"BNB\":\"BNB\"}', '1', '', NULL, NULL, '2023-02-14 11:08:04');


INSERT INTO `gateways` (`id`, `form_id`, `code`, `name`, `alias`, `status`, `gateway_parameters`, `supported_currencies`, `crypto`, `extra`, `description`, `created_at`, `updated_at`) VALUES (NULL, '0', '124', 'SslCommerz', 'SslCommerz', '1', '{\"store_id\": {\"title\": \"Store ID\",\"global\": true,\"value\": \"---------\"},\"store_password\": {\"title\": \"Store Password\",\"global\": true,\"value\": \"----------\"}}', '{\"BDT\":\"BDT\",\"USD\":\"USD\",\"EUR\":\"EUR\",\"SGD\":\"SGD\",\"INR\":\"INR\",\"MYR\":\"MYR\"}', '0', NULL, NULL, NULL, '2023-05-06 13:43:01');

INSERT INTO `gateways` (`id`, `form_id`, `code`, `name`, `alias`, `status`, `gateway_parameters`, `supported_currencies`, `crypto`, `extra`, `description`, `created_at`, `updated_at`) VALUES (NULL, '0', '125', 'Aamarpay', 'Aamarpay', '1', '{\"store_id\": {\"title\": \"Store ID\",\"global\": true,\"value\": \"---------\"},\"signature_key\": {\"title\": \"Signature Key\",\"global\": true,\"value\": \"----------\"}}', '{\"BDT\":\"BDT\"}', '0', NULL, NULL, NULL, '2023-05-06 13:43:01');


UPDATE `gateways` SET `extra` = '{\"cron\":{\"title\": \"Cron Job URL\",\"value\":\"ipn.Binance\"}}' WHERE `gateways`.`alias` = 'Binance';

ALTER TABLE `users` ADD `kv` TINYINT(1) NOT NULL DEFAULT '0' AFTER `sv`;


-- ====================== projected related  ======================
ALTER TABLE `plans` CHANGE `name` `name` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL, CHANGE `price` `price` DECIMAL(28,8) NOT NULL DEFAULT '0.00000000', CHANGE `ref_com` `ref_com` DECIMAL(28,8) NULL DEFAULT '0', CHANGE `tree_com` `tree_com` DECIMAL(28,8) NULL DEFAULT '0';


ALTER TABLE `plans` CHANGE `bv` `bv` INT NULL DEFAULT '0';
ALTER TABLE `categories` CHANGE `status` `status` TINYINT(1) NOT NULL DEFAULT '1';

ALTER TABLE `products` CHANGE `category_id` `category_id` INT UNSIGNED NOT NULL DEFAULT '0';
ALTER TABLE `products` CHANGE `quantity` `quantity` INT NOT NULL DEFAULT '1', CHANGE `bv` `bv` INT NOT NULL DEFAULT '0', CHANGE `status` `status` TINYINT(1) NOT NULL DEFAULT '1', CHANGE `is_featured` `is_featured` TINYINT(1) NOT NULL DEFAULT '0';



CREATE TABLE `product_images` (
  `id` bigint UNSIGNED NOT NULL,
  `product_id` int UNSIGNED DEFAULT NULL,
  `name` varchar(40) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `product_images` MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;


ALTER TABLE `product_images` CHANGE `name` `name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;

INSERT INTO `product_images` (product_id, name)
SELECT 
  p.id AS product_id,
  JSON_UNQUOTE(JSON_EXTRACT(p.images, CONCAT('$[', numbers.n, ']'))) AS image
FROM 
  products p
  JOIN (
    SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
  ) numbers
  ON JSON_UNQUOTE(JSON_EXTRACT(p.images, CONCAT('$[', numbers.n, ']'))) IS NOT NULL;

ALTER TABLE `products` DROP `images`;


ALTER TABLE `users` CHANGE `ref_id` `ref_by` INT NOT NULL DEFAULT '0';

UPDATE `general_settings` SET `sms_config` = '{\"name\":\"clickatell\",\"clickatell\":{\"api_key\":\"----------------\"},\"infobip\":{\"username\":\"------------8888888\",\"password\":\"-----------------\"},\"message_bird\":{\"api_key\":\"-------------------\"},\"nexmo\":{\"api_key\":\"----------------------\",\"api_secret\":\"----------------------\"},\"sms_broadcast\":{\"username\":\"----------------------\",\"password\":\"-----------------------------\"},\"twilio\":{\"account_sid\":\"-----------------------\",\"auth_token\":\"---------------------------\",\"from\":\"----------------------\"},\"text_magic\":{\"username\":\"-----------------------\",\"apiv2_key\":\"-------------------------------\"},\"custom\":{\"method\":\"get\",\"url\":\"https:\\/\\/hostname.com\\/demo-api-v1\",\"headers\":{\"name\":[\"api_key\"],\"value\":[\"test_api 555\"]},\"body\":{\"name\":[\"from_number\"],\"value\":[\"5657545757\"]}}}' WHERE `general_settings`.`id` = 1;


INSERT INTO `frontends` (`data_keys`, `data_values`, `seo_content`, `tempname`, `slug`, `created_at`, `updated_at`) VALUES
('kyc.content', '{\"required\":\"Complete KYC to unlock the full potential of our platform! KYC helps us verify your identity and keep things secure. It is quick and easy just follow the on-screen instructions. Get started with KYC verification now!\",\"pending\":\"Your KYC verification is being reviewed. We might need some additional information. You will get an email update soon. In the meantime, explore our platform with limited features.\",\"reject\":\"We regret to inform you that the Know Your Customer (KYC) information provided has been reviewed and unfortunately, it has not met our verification standards.\"}', NULL, 'basic', '', '2024-05-18 11:06:56', '2024-05-18 11:06:56');


UPDATE `gateways`
SET `image` = CASE `alias`
    WHEN 'Paypal' THEN '663a38d7b455d1715091671.png'
    WHEN 'PerfectMoney' THEN '663a3920e30a31715091744.png'
    WHEN 'Stripe' THEN '663a39861cb9d1715091846.png'
    WHEN 'Skrill' THEN '663a39494c4a91715091785.png'
    WHEN 'Paytm' THEN '663a390f601191715091727.png'
    WHEN 'Payeer' THEN '663a38c9e2e931715091657.png'
    WHEN 'Paystack' THEN '663a38fc814e91715091708.png'
    WHEN 'Flutterwave' THEN '663a36c2c34d61715091138.png'
    WHEN 'Razorpay' THEN '663a393a527831715091770.png'
    WHEN 'StripeJs' THEN '663a3995417171715091861.png'
    WHEN 'Instamojo' THEN '663a384d54a111715091533.png'
    WHEN 'Blockchain' THEN '663a35efd0c311715090927.png'
    WHEN 'Coinpayments' THEN '663a36a8d8e1d1715091112.png'
    WHEN 'CoinpaymentsFiat' THEN '663a36b7b841a1715091127.png'
    WHEN 'Coingate' THEN '663a368e753381715091086.png'
    WHEN 'CoinbaseCommerce' THEN '663a367e46ae51715091070.png'
    WHEN 'PaypalSdk' THEN '663a38ed101a61715091693.png'
    WHEN 'StripeV3' THEN '663a39afb519f1715091887.png'
    WHEN 'Mollie' THEN '663a387ec69371715091582.png'
    WHEN 'Cashmaal' THEN '663a361b16bd11715090971.png'
    WHEN 'MercadoPago' THEN '663a386c714a91715091564.png'
    WHEN 'Authorize' THEN '663a35b9ca5991715090873.png'
    WHEN 'NMI' THEN '663a3897754cf1715091607.png'
    WHEN 'BTCPay' THEN '663a35cd25a8d1715090893.png'
    WHEN 'NowPaymentsHosted' THEN '663a38b8d57a81715091640.png'
    WHEN 'NowPaymentsCheckout' THEN '663a38a59d2541715091621.png'
    WHEN 'TwoCheckout' THEN '663a39b8e64b91715091896.png'
    WHEN 'Checkout' THEN '663a3628733351715090984.png'
    WHEN 'bank_transfer' THEN '663b50fc3b21c1715163388.png'
    WHEN 'Binance' THEN '663a35db4fd621715090907.png'
    WHEN 'SslCommerz' THEN '663a397a70c571715091834.png'
    WHEN 'Aamarpay' THEN '663a34d5d1dfc1715090645.png'
END
WHERE `alias` IN (
    'Paypal', 'PerfectMoney', 'Stripe', 'Skrill', 'Paytm', 'Payeer', 'Paystack', 'Flutterwave', 
    'Razorpay', 'StripeJs', 'Instamojo', 'Blockchain', 'Coinpayments', 'CoinpaymentsFiat', 
    'Coingate', 'CoinbaseCommerce', 'PaypalSdk', 'StripeV3', 'Mollie', 'Cashmaal', 'MercadoPago', 
    'Authorize', 'NMI', 'BTCPay', 'NowPaymentsHosted', 'NowPaymentsCheckout', 'TwoCheckout', 
    'Checkout', 'bank_transfer', 'Binance', 'SslCommerz', 'Aamarpay'
);

ALTER TABLE `products` DROP `sku`;

ALTER TABLE `users` ADD `profile_complete` TINYINT(1) NOT NULL DEFAULT '0' AFTER `kyc_rejection_reason`;
UPDATE `users` SET `profile_complete`=1;



TRUNCATE `extensions`;

INSERT INTO `extensions` (`id`, `act`, `name`, `description`, `image`, `script`, `shortcode`, `support`, `status`, `created_at`, `updated_at`) VALUES
(1, 'tawk-chat', 'Tawk.to', 'Key location is shown bellow', 'tawky_big.png', '<script>\r\n                        var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();\r\n                        (function(){\r\n                        var s1=document.createElement(\"script\"),s0=document.getElementsByTagName(\"script\")[0];\r\n                        s1.async=true;\r\n                        s1.src=\"https://embed.tawk.to/{{app_key}}\";\r\n                        s1.charset=\"UTF-8\";\r\n                        s1.setAttribute(\"crossorigin\",\"*\");\r\n                        s0.parentNode.insertBefore(s1,s0);\r\n                        })();\r\n                    </script>', '{\"app_key\":{\"title\":\"App Key\",\"value\":\"------\"}}', 'twak.png', 0, '2019-10-18 11:16:05', '2024-05-16 06:23:02'),
(2, 'google-recaptcha2', 'Google Recaptcha 2', 'Key location is shown bellow', 'recaptcha3.png', '\n<script src=\"https://www.google.com/recaptcha/api.js\"></script>\n<div class=\"g-recaptcha\" data-sitekey=\"{{site_key}}\" data-callback=\"verifyCaptcha\"></div>\n<div id=\"g-recaptcha-error\"></div>', '{\"site_key\":{\"title\":\"Site Key\",\"value\":\"6LdPC88fAAAAADQlUf_DV6Hrvgm-pZuLJFSLDOWV\"},\"secret_key\":{\"title\":\"Secret Key\",\"value\":\"6LdPC88fAAAAAG5SVaRYDnV2NpCrptLg2XLYKRKB\"}}', 'recaptcha.png', 0, '2019-10-18 11:16:05', '2024-06-13 03:22:18'),
(3, 'custom-captcha', 'Custom Captcha', 'Just put any random string', 'customcaptcha.png', NULL, '{\"random_key\":{\"title\":\"Random String\",\"value\":\"SecureString\"}}', 'na', 1, '2019-10-18 11:16:05', '2024-06-13 02:45:35'),
(4, 'google-analytics', 'Google Analytics', 'Key location is shown bellow', 'google_analytics.png', '<script async src=\"https://www.googletagmanager.com/gtag/js?id={{measurement_id}}\"></script>\n                <script>\n                  window.dataLayer = window.dataLayer || [];\n                  function gtag(){dataLayer.push(arguments);}\n                  gtag(\"js\", new Date());\n                \n                  gtag(\"config\", \"{{measurement_id}}\");\n                </script>', '{\"measurement_id\":{\"title\":\"Measurement ID\",\"value\":\"------\"}}', 'ganalytics.png', 0, NULL, '2021-05-03 22:19:12'),
(5, 'fb-comment', 'Facebook Comment ', 'Key location is shown bellow', 'Facebook.png', '<div id=\"fb-root\"></div><script async defer crossorigin=\"anonymous\" src=\"https://connect.facebook.net/en_GB/sdk.js#xfbml=1&version=v4.0&appId={{app_key}}&autoLogAppEvents=1\"></script>', '{\"app_key\":{\"title\":\"App Key\",\"value\":\"----\"}}', 'fb_com.png', 0, NULL, '2022-03-21 17:18:36');


DELETE FROM `gateways` WHERE `gateways`.`alias` = 'Blockio';
ALTER TABLE `withdraw_methods` ADD `form_id` INT(10) UNSIGNED NOT NULL AFTER `name`;

