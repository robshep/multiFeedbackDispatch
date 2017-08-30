CREATE TABLE `entry` (
 `id` INTEGER PRIMARY KEY AUTOINCREMENT,
 `category` varchar(20) NOT NULL,
 `incident` varchar(20) NOT NULL,
 `who` varchar(100) NOT NULL,
 `msg` varchar(1000) NOT NULL,
 `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
 `deleted_at` timestamp NULL
);

CREATE TABLE category ( id varchar(30) PRIMARY KEY, name varchar(50) NOT NULL, email varchar(100) NOT NULL );

