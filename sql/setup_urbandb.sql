CREATE DATABASE IF NOT EXISTS urbandb
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE urbandb;

SOURCE create_user_tables.sql;
SOURCE create_route_cache_table.sql;
SOURCE admin_analytics_setup.sql;
SOURCE create_performance_indexes.sql;
