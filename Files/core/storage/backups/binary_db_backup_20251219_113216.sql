-- MySQL dump 10.13  Distrib 5.7.44, for Linux (x86_64)
--
-- Host: localhost    Database: binary_db
-- ------------------------------------------------------
-- Server version	5.7.44-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `adjustment_batches`
--

DROP TABLE IF EXISTS `adjustment_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `adjustment_batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `batch_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_type` enum('refund_before_finalize','refund_after_finalize','manual_correction') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_type` enum('order','weekly_settlement','quarterly_settlement') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `finalized_by` bigint(20) unsigned DEFAULT NULL,
  `finalized_at` timestamp NULL DEFAULT NULL,
  `snapshot` json NOT NULL COMMENT '调整详情快照',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `adjustment_batches_batch_key_unique` (`batch_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `adjustment_batches`
--

LOCK TABLES `adjustment_batches` WRITE;
/*!40000 ALTER TABLE `adjustment_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `adjustment_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `adjustment_entries`
--

DROP TABLE IF EXISTS `adjustment_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `adjustment_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` bigint(20) unsigned NOT NULL,
  `asset_type` enum('wallet','pv','points') COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(20,8) NOT NULL COMMENT '调整量（可为负）',
  `reversal_of_id` bigint(20) unsigned DEFAULT NULL COMMENT '被冲正的原始流水ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `adjustment_entries_batch_id_index` (`batch_id`),
  KEY `adjustment_entries_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `adjustment_entries`
--

LOCK TABLES `adjustment_entries` WRITE;
/*!40000 ALTER TABLE `adjustment_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `adjustment_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` bigint(20) unsigned DEFAULT NULL,
  `action_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_admin_id_index` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dividend_logs`
--

DROP TABLE IF EXISTS `dividend_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dividend_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `quarter_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `pool_type` enum('stockist','leader') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'stockist=1%消费商池, leader=3%领导人池',
  `shares` int(11) DEFAULT NULL COMMENT '消费商池份数',
  `score` decimal(20,8) DEFAULT NULL COMMENT '领导人池积分',
  `dividend_amount` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT '分红金额',
  `status` enum('paid','skipped') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'paid=已发, skipped=未达标未发',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '跳过原因',
  `trx_id` bigint(20) unsigned DEFAULT NULL COMMENT '交易流水ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dividend_logs_quarter_key_index` (`quarter_key`),
  KEY `dividend_logs_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dividend_logs`
--

LOCK TABLES `dividend_logs` WRITE;
/*!40000 ALTER TABLE `dividend_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `dividend_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `extensions`
--

DROP TABLE IF EXISTS `extensions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `extensions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alias` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1.0.0',
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `script` longtext COLLATE utf8mb4_unicode_ci,
  `shortcode` json DEFAULT NULL,
  `act` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `extensions_name_unique` (`name`),
  UNIQUE KEY `extensions_alias_unique` (`alias`),
  KEY `extensions_act_index` (`act`),
  KEY `extensions_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `extensions`
--

LOCK TABLES `extensions` WRITE;
/*!40000 ALTER TABLE `extensions` DISABLE KEYS */;
/*!40000 ALTER TABLE `extensions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `general_settings`
--

DROP TABLE IF EXISTS `general_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `general_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `site_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `available_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bonus_config` json DEFAULT NULL,
  `force_ssl` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `general_settings`
--

LOCK TABLES `general_settings` WRITE;
/*!40000 ALTER TABLE `general_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `general_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `languages`
--

DROP TABLE IF EXISTS `languages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `languages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(4) NOT NULL DEFAULT '0',
  `status` tinyint(4) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `languages_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `languages`
--

LOCK TABLES `languages` WRITE;
/*!40000 ALTER TABLE `languages` DISABLE KEYS */;
/*!40000 ALTER TABLE `languages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2024_06_10_113610_create_product_images_table',1),(5,'2025_12_18_000000_create_pv_ledger_table',1),(6,'2025_12_18_000001_create_pending_bonuses_table',1),(7,'2025_12_18_000002_create_weekly_settlements_table',1),(8,'2025_12_18_000003_create_weekly_settlement_user_summaries_table',1),(9,'2025_12_18_000004_create_quarterly_settlements_table',1),(10,'2025_12_18_000005_create_dividend_logs_table',1),(11,'2025_12_18_000006_create_adjustment_batches_table',1),(12,'2025_12_18_000007_create_adjustment_entries_table',1),(13,'2025_12_18_000008_create_user_points_log_table',1),(14,'2025_12_18_000009_create_user_level_hits_table',1),(15,'2025_12_18_000010_add_v101_user_fields',1),(16,'2025_12_18_000011_add_source_fields_to_transactions_table',1),(17,'2025_12_18_000012_alter_user_level_hits_add_columns',1),(18,'2025_12_18_000013_add_bonus_config_to_general_settings',1),(19,'2025_12_18_000014_create_user_assets_table',1),(20,'2025_12_18_000015_add_adjustment_fields_to_user_points_log',1),(21,'2025_12_18_000016_create_audit_logs_table',1),(22,'2025_12_18_000017_create_general_settings_table',1),(23,'2025_12_18_000018_alter_users_add_v101_columns',1),(24,'2025_12_18_000019_create_user_extras_table',1),(25,'2025_12_18_000020_create_withdraw_methods_table',1),(26,'2025_12_18_000021_create_transactions_table',1),(27,'2025_12_18_000022_create_languages_table',1),(28,'2025_12_18_000023_alter_user_points_log_source_type',1),(29,'2025_12_19_000024_create_extensions_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pending_bonuses`
--

DROP TABLE IF EXISTS `pending_bonuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pending_bonuses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recipient_id` bigint(20) unsigned NOT NULL,
  `bonus_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'direct/level_pair/pair/matching',
  `amount` decimal(16,8) NOT NULL,
  `source_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'order/weekly_settlement',
  `source_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `accrued_week_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','released','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `release_mode` enum('auto','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'auto',
  `released_trx` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pb_unique_key` (`recipient_id`,`bonus_type`,`source_type`,`source_id`),
  KEY `pending_bonuses_recipient_id_index` (`recipient_id`),
  KEY `pending_bonuses_source_id_index` (`source_id`),
  KEY `pending_bonuses_accrued_week_key_index` (`accrued_week_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pending_bonuses`
--

LOCK TABLES `pending_bonuses` WRITE;
/*!40000 ALTER TABLE `pending_bonuses` DISABLE KEYS */;
/*!40000 ALTER TABLE `pending_bonuses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_images`
--

DROP TABLE IF EXISTS `product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_images`
--

LOCK TABLES `product_images` WRITE;
/*!40000 ALTER TABLE `product_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pv_ledger`
--

DROP TABLE IF EXISTS `pv_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pv_ledger` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `from_user_id` bigint(20) unsigned DEFAULT NULL,
  `position` tinyint(4) NOT NULL COMMENT '1=Left, 2=Right',
  `level` int(11) NOT NULL,
  `amount` decimal(16,8) NOT NULL,
  `trx_type` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '+ or -',
  `source_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'order/weekly_settlement/adjustment',
  `source_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adjustment_batch_id` bigint(20) unsigned DEFAULT NULL,
  `reversal_of_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pv_ledger_source_type_source_id_user_id_position_trx_type_unique` (`source_type`,`source_id`,`user_id`,`position`,`trx_type`),
  KEY `pv_ledger_user_id_index` (`user_id`),
  KEY `pv_ledger_from_user_id_index` (`from_user_id`),
  KEY `pv_ledger_source_id_index` (`source_id`),
  KEY `pv_ledger_adjustment_batch_id_index` (`adjustment_batch_id`),
  KEY `pv_ledger_reversal_of_id_index` (`reversal_of_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pv_ledger`
--

LOCK TABLES `pv_ledger` WRITE;
/*!40000 ALTER TABLE `pv_ledger` DISABLE KEYS */;
/*!40000 ALTER TABLE `pv_ledger` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quarterly_settlements`
--

DROP TABLE IF EXISTS `quarterly_settlements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quarterly_settlements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `quarter_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` timestamp NOT NULL,
  `end_date` timestamp NOT NULL,
  `total_pv` decimal(20,8) NOT NULL,
  `pool_stockist` decimal(20,8) NOT NULL COMMENT '1%消费商池',
  `pool_leader` decimal(20,8) NOT NULL COMMENT '3%领导人池',
  `total_shares` int(11) NOT NULL DEFAULT '0' COMMENT '消费商池总份数',
  `total_score` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT '领导人池总积分',
  `unit_value_stockist` decimal(20,8) NOT NULL COMMENT '消费商池单位价值',
  `unit_value_leader` decimal(20,8) NOT NULL COMMENT '领导人池单位价值',
  `finalized_at` timestamp NULL DEFAULT NULL,
  `config_snapshot` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quarterly_settlements_quarter_key_unique` (`quarter_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quarterly_settlements`
--

LOCK TABLES `quarterly_settlements` WRITE;
/*!40000 ALTER TABLE `quarterly_settlements` DISABLE KEYS */;
/*!40000 ALTER TABLE `quarterly_settlements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `trx` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trx_type` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(20,8) NOT NULL,
  `charge` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `post_balance` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `remark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_type` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reversal_of_id` bigint(20) unsigned DEFAULT NULL,
  `adjustment_batch_id` bigint(20) unsigned DEFAULT NULL,
  `details` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transactions_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_assets`
--

DROP TABLE IF EXISTS `user_assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_assets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `points` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT '莲子积分余额',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_assets_user_id_unique` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_assets`
--

LOCK TABLES `user_assets` WRITE;
/*!40000 ALTER TABLE `user_assets` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_assets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_extras`
--

DROP TABLE IF EXISTS `user_extras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_extras` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `bv_left` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `bv_right` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `points` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_extras_user_id_unique` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_extras`
--

LOCK TABLES `user_extras` WRITE;
/*!40000 ALTER TABLE `user_extras` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_extras` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_level_hits`
--

DROP TABLE IF EXISTS `user_level_hits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_level_hits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `level` int(11) NOT NULL COMMENT '层级',
  `position` tinyint(4) NOT NULL COMMENT '1=Left, 2=Right',
  `amount` decimal(16,8) NOT NULL COMMENT '该层该侧累计PV',
  `first_hit_at` timestamp NOT NULL COMMENT '首次点亮时间',
  `order_trx` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '首次点亮来源订单',
  `bonus_amount` decimal(16,8) NOT NULL DEFAULT '0.00000000' COMMENT '层碰奖金额快照',
  `rewarded` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0=未发放,1=已发放',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_level_hits_user_id_level_position_unique` (`user_id`,`level`,`position`),
  KEY `user_level_hits_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_level_hits`
--

LOCK TABLES `user_level_hits` WRITE;
/*!40000 ALTER TABLE `user_level_hits` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_level_hits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_points_log`
--

DROP TABLE IF EXISTS `user_points_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_points_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `source_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '关联的订单ID或week_key',
  `points` decimal(20,8) NOT NULL COMMENT '莲子数量',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adjustment_batch_id` bigint(20) unsigned DEFAULT NULL,
  `reversal_of_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_points_log_user_id_source_type_source_id_unique` (`user_id`,`source_type`,`source_id`),
  KEY `user_points_log_user_id_index` (`user_id`),
  KEY `user_points_log_source_id_index` (`source_id`),
  KEY `user_points_log_adjustment_batch_id_index` (`adjustment_batch_id`),
  KEY `user_points_log_reversal_of_id_index` (`reversal_of_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_points_log`
--

LOCK TABLES `user_points_log` WRITE;
/*!40000 ALTER TABLE `user_points_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_points_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `rank_level` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0=未激活,1=初级,2=中级,3=高级',
  `personal_purchase_count` int(11) NOT NULL DEFAULT '0' COMMENT '个人累计请购数量',
  `last_activity_date` date DEFAULT NULL COMMENT '季度活跃判定',
  `leader_rank_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '领导人职级代码',
  `leader_rank_multiplier` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT '领导人分红系数',
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `firstname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plan_id` tinyint(4) NOT NULL DEFAULT '0',
  `pos_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `position` tinyint(4) NOT NULL DEFAULT '0',
  `ref_by` bigint(20) unsigned NOT NULL DEFAULT '0',
  `status` tinyint(4) NOT NULL DEFAULT '1',
  `ev` tinyint(4) NOT NULL DEFAULT '1',
  `sv` tinyint(4) NOT NULL DEFAULT '1',
  `balance` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_username_unique` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `weekly_settlement_user_summaries`
--

DROP TABLE IF EXISTS `weekly_settlement_user_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `weekly_settlement_user_summaries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `week_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `left_pv_initial` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `right_pv_initial` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `left_pv_end` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `right_pv_end` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `pair_count` int(11) NOT NULL DEFAULT '0' COMMENT '对碰次数',
  `pair_theoretical` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT '对碰理论金额',
  `pair_capped_potential` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT '对碰封顶后理论',
  `pair_paid` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT '对碰实发',
  `matching_potential` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT '管理奖理论',
  `matching_paid` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT '管理奖实发',
  `cap_amount` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT '周封顶额度',
  `cap_used` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT '封顶使用额度',
  `k_factor` decimal(10,6) NOT NULL COMMENT 'K值',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `weekly_settlement_user_summaries_week_key_user_id_unique` (`week_key`,`user_id`),
  KEY `weekly_settlement_user_summaries_week_key_index` (`week_key`),
  KEY `weekly_settlement_user_summaries_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `weekly_settlement_user_summaries`
--

LOCK TABLES `weekly_settlement_user_summaries` WRITE;
/*!40000 ALTER TABLE `weekly_settlement_user_summaries` DISABLE KEYS */;
/*!40000 ALTER TABLE `weekly_settlement_user_summaries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `weekly_settlements`
--

DROP TABLE IF EXISTS `weekly_settlements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `weekly_settlements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `week_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` timestamp NOT NULL,
  `end_date` timestamp NOT NULL,
  `total_pv` decimal(20,8) NOT NULL,
  `fixed_sales` decimal(20,8) NOT NULL COMMENT '直推+层碰（已发+应计预留）',
  `global_reserve` decimal(20,8) NOT NULL COMMENT '功德池4%',
  `variable_potential` decimal(20,8) NOT NULL COMMENT '对碰封顶后理论+管理理论（未乘K）',
  `k_factor` decimal(10,6) NOT NULL COMMENT 'K值',
  `finalized_at` timestamp NULL DEFAULT NULL,
  `config_snapshot` json DEFAULT NULL COMMENT '配置快照（比例/封顶等）',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `weekly_settlements_week_key_unique` (`week_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `weekly_settlements`
--

LOCK TABLES `weekly_settlements` WRITE;
/*!40000 ALTER TABLE `weekly_settlements` DISABLE KEYS */;
/*!40000 ALTER TABLE `weekly_settlements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `withdraw_methods`
--

DROP TABLE IF EXISTS `withdraw_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `withdraw_methods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `min_limit` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `max_limit` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `fixed_charge` decimal(20,8) NOT NULL DEFAULT '0.00000000',
  `percent_charge` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `rate` decimal(20,8) NOT NULL DEFAULT '1.00000000',
  `status` tinyint(4) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `withdraw_methods`
--

LOCK TABLES `withdraw_methods` WRITE;
/*!40000 ALTER TABLE `withdraw_methods` DISABLE KEYS */;
/*!40000 ALTER TABLE `withdraw_methods` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-12-19 11:32:16
