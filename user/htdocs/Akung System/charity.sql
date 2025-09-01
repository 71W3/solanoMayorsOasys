/*
 Navicat Premium Data Transfer

 Source Server         : localhost
 Source Server Type    : MySQL
 Source Server Version : 100432
 Source Host           : localhost:3306
 Source Schema         : charity

 Target Server Type    : MySQL
 Target Server Version : 100432
 File Encoding         : 65001

 Date: 09/12/2024 22:02:38
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for admin
-- ----------------------------
DROP TABLE IF EXISTS `admin`;
CREATE TABLE `admin`  (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`admin_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of admin
-- ----------------------------
INSERT INTO `admin` VALUES (1, 'Lad Sunga', 'bosslad', 'admin123');

-- ----------------------------
-- Table structure for cash_donations
-- ----------------------------
DROP TABLE IF EXISTS `cash_donations`;
CREATE TABLE `cash_donations`  (
  `donation_id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_id` int(11) NULL DEFAULT NULL,
  `charity_id` int(11) NULL DEFAULT NULL,
  `amount` decimal(10, 2) NULL DEFAULT NULL,
  `date` date NULL DEFAULT NULL,
  PRIMARY KEY (`donation_id`) USING BTREE,
  INDEX `donor_id`(`donor_id`) USING BTREE,
  INDEX `charity_id`(`charity_id`) USING BTREE,
  CONSTRAINT `cash_donations_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `cash_donations_ibfk_3` FOREIGN KEY (`charity_id`) REFERENCES `charities` (`charity_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 43 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of cash_donations
-- ----------------------------
INSERT INTO `cash_donations` VALUES (1, 6, 9, 12000.00, '2024-11-28');
INSERT INTO `cash_donations` VALUES (2, 7, 1, 1000.00, '2024-12-02');
INSERT INTO `cash_donations` VALUES (3, 8, 6, 1000000.00, '2024-12-02');
INSERT INTO `cash_donations` VALUES (4, 7, 4, 199999.00, '2024-12-04');
INSERT INTO `cash_donations` VALUES (5, 7, 4, 199999.00, '2024-12-04');
INSERT INTO `cash_donations` VALUES (6, 10, 3, 29292929.00, '2024-12-01');
INSERT INTO `cash_donations` VALUES (7, 7, 8, 23232323.00, '2024-12-01');
INSERT INTO `cash_donations` VALUES (8, 9, 3, 1000000.00, '2024-12-08');
INSERT INTO `cash_donations` VALUES (9, 7, 1, 88888.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (10, 1, 3, 20.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (11, 1, 10, 202.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (12, 1, 5, 199999.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (13, 1, 5, 199999.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (14, 1, 8, 1000000.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (15, 5, 1, 222.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (16, 1, 5, 2022.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (17, 5, 4, 232323.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (18, 5, 5, 202.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (19, 5, 9, 202.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (20, 6, 10, 1000000.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (21, 1, 5, 12000.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (22, 1, 5, 12000.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (23, 8, 1, 1000000.00, '2024-12-11');
INSERT INTO `cash_donations` VALUES (24, 11, 5, 199999.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (25, 11, 9, 202.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (26, 8, 9, 199999.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (27, 10, 6, 202.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (28, 11, 2, 12000.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (29, 11, 2, 12000.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (30, 8, 5, 1000000.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (31, 8, 9, 199999.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (32, 3, 5, 1000000.00, '2024-12-26');
INSERT INTO `cash_donations` VALUES (33, 11, 5, 199999.00, '2024-12-19');
INSERT INTO `cash_donations` VALUES (34, 11, 6, 20.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (35, 7, 5, 323232.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (36, 3, 9, 202.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (37, 9, 1, 202.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (38, 3, 5, 1000000.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (39, 3, 5, 1000000.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (40, 17, 1, 1000000.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (41, 17, 1, 1000000.00, '2024-12-09');
INSERT INTO `cash_donations` VALUES (42, 5, 9, 1000000.00, '2024-12-09');

-- ----------------------------
-- Table structure for charities
-- ----------------------------
DROP TABLE IF EXISTS `charities`;
CREATE TABLE `charities`  (
  `charity_id` int(11) NOT NULL AUTO_INCREMENT,
  `charity_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `contact_information` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`charity_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of charities
-- ----------------------------
INSERT INTO `charities` VALUES (1, 'Gawad Kalinga', 'Focuses on ending poverty for communities in the Philippines.', '+63-2-1234567', 'Quezon City, Philippines', 'https://www.gk1world.com', 'active');
INSERT INTO `charities` VALUES (2, 'Philippine Red Cross', 'Provides humanitarian aid and disaster relief.', '+63-2-8523560', 'Mandaluyong City, Philippines', 'https://redcross.org.ph', 'active');
INSERT INTO `charities` VALUES (3, 'Caritas Manila', 'Supports social services, livelihood, and youth development.', '+63-2-5640205', 'Manila, Philippines', 'https://www.caritasmanila.org.ph', 'active');
INSERT INTO `charities` VALUES (4, 'UNICEF Philippines', 'Focuses on child welfare, education, and health.', '+63-2-7581000', 'Pasig City, Philippines', 'https://www.unicef.org/philippines', 'active');
INSERT INTO `charities` VALUES (5, 'Habitat for Humanity Philippines', 'Builds homes and communities for families in need.', '+63-2-8462150', 'Makati City, Philippines', 'https://www.habitat.org.ph', 'active');
INSERT INTO `charities` VALUES (6, 'Save the Children Philippines', 'Promotes children’s rights and provides disaster response.', '+63-2-8530150', 'Taguig City, Philippines', 'https://www.savethechildren.org.ph', 'active');
INSERT INTO `charities` VALUES (7, 'Tahanan ng Pagmamahal', 'Offers care and shelter for abandoned children.', '+63-2-6673133', 'Pasig City, Philippines', 'http://tahananngpagmamahal.org', 'active');
INSERT INTO `charities` VALUES (8, 'World Vision Philippines', 'Provides education, health, and livelihood programs.', '+63-2-3727777', 'Quezon City, Philippines', 'https://www.worldvision.org.ph', 'active');
INSERT INTO `charities` VALUES (9, 'Philippine Animal Welfare Society (PAWS)', 'Promotes animal welfare and rights.', '+63-2-4751689', 'Quezon City, Philippines', 'https://paws.org.ph', 'inactive');
INSERT INTO `charities` VALUES (10, 'Children’s Hour Philippines Foundation', 'Raises funds to help disadvantaged children.', '+63-2-8502112', 'Makati City, Philippines', 'https://childrenshour.org.ph', 'active');

-- ----------------------------
-- Table structure for donation_status
-- ----------------------------
DROP TABLE IF EXISTS `donation_status`;
CREATE TABLE `donation_status`  (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `donor_id` int(10) NULL DEFAULT NULL,
  `donation_id` int(10) NULL DEFAULT NULL,
  `status` enum('Pending','Completed','Cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `donate_id` int(11) NULL DEFAULT NULL,
  `charity_id` int(11) NULL DEFAULT NULL,
  `amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `date` date NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `donor_id`(`donor_id`) USING BTREE,
  INDEX `donation_id`(`donation_id`) USING BTREE,
  INDEX `donate_id`(`donate_id`) USING BTREE,
  INDEX `charity_id`(`charity_id`) USING BTREE,
  CONSTRAINT `donation_status_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `donation_status_ibfk_2` FOREIGN KEY (`donation_id`) REFERENCES `cash_donations` (`donation_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `donation_status_ibfk_3` FOREIGN KEY (`donate_id`) REFERENCES `item_donations` (`donate_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `donation_status_ibfk_4` FOREIGN KEY (`charity_id`) REFERENCES `charities` (`charity_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 84 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of donation_status
-- ----------------------------
INSERT INTO `donation_status` VALUES (67, 11, 34, 'Pending', NULL, 6, '20', '2024-12-09');
INSERT INTO `donation_status` VALUES (72, 9, 37, 'Pending', NULL, 1, '202', '2024-12-09');
INSERT INTO `donation_status` VALUES (73, 3, 38, 'Pending', NULL, 5, '1000000', '2024-12-09');
INSERT INTO `donation_status` VALUES (74, 3, 39, 'Pending', NULL, 5, '1000000', '2024-12-09');
INSERT INTO `donation_status` VALUES (75, 7, NULL, 'Pending', 38, 9, NULL, '2024-12-09');
INSERT INTO `donation_status` VALUES (76, 7, NULL, 'Pending', 39, 9, NULL, '2024-12-09');
INSERT INTO `donation_status` VALUES (78, 17, 41, 'Pending', NULL, 1, '1000000', '2024-12-09');
INSERT INTO `donation_status` VALUES (79, 5, 42, 'Pending', NULL, 9, '1000000', '2024-12-09');
INSERT INTO `donation_status` VALUES (80, 5, NULL, 'Pending', 40, 6, NULL, '2024-12-09');
INSERT INTO `donation_status` VALUES (81, 5, NULL, 'Pending', 41, 6, NULL, '2024-12-09');

-- ----------------------------
-- Table structure for donationhistory
-- ----------------------------
DROP TABLE IF EXISTS `donationhistory`;
CREATE TABLE `donationhistory`  (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_id` int(11) NULL DEFAULT NULL,
  `charity_id` int(11) NULL DEFAULT NULL,
  `donation_id` int(11) NULL DEFAULT NULL,
  `amount` decimal(10, 2) NULL DEFAULT NULL,
  `donation_date` date NULL DEFAULT NULL,
  `donate_id` int(11) NULL DEFAULT NULL,
  PRIMARY KEY (`history_id`) USING BTREE,
  INDEX `donor_id`(`donor_id`) USING BTREE,
  INDEX `charity_id`(`charity_id`) USING BTREE,
  INDEX `donation_id`(`donation_id`) USING BTREE,
  INDEX `donate_id`(`donate_id`) USING BTREE,
  CONSTRAINT `donationhistory_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `donationhistory_ibfk_2` FOREIGN KEY (`charity_id`) REFERENCES `charities` (`charity_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `donationhistory_ibfk_3` FOREIGN KEY (`donation_id`) REFERENCES `cash_donations` (`donation_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `donationhistory_ibfk_4` FOREIGN KEY (`donate_id`) REFERENCES `item_donations` (`donate_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 23 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of donationhistory
-- ----------------------------
INSERT INTO `donationhistory` VALUES (1, 1, 1, 1, 10000.00, '2024-12-02', NULL);
INSERT INTO `donationhistory` VALUES (2, 6, 7, NULL, NULL, '2024-12-20', 2);
INSERT INTO `donationhistory` VALUES (3, 6, 1, 1, 10000.00, '2024-12-02', NULL);
INSERT INTO `donationhistory` VALUES (4, 3, NULL, 3, NULL, '2024-12-03', NULL);
INSERT INTO `donationhistory` VALUES (5, 7, 1, 1, NULL, '2024-12-03', NULL);
INSERT INTO `donationhistory` VALUES (6, 8, 2, NULL, NULL, '2024-12-03', 2);
INSERT INTO `donationhistory` VALUES (7, 1, NULL, NULL, 0.00, '2024-12-04', 3);
INSERT INTO `donationhistory` VALUES (8, 8, 2, 4, NULL, '2024-12-04', NULL);
INSERT INTO `donationhistory` VALUES (9, 10, 3, 6, 29292929.00, '2024-12-04', NULL);
INSERT INTO `donationhistory` VALUES (10, 11, 8, NULL, NULL, '2024-12-04', 11);
INSERT INTO `donationhistory` VALUES (11, 7, 8, 7, 23232323.00, '2024-12-01', NULL);
INSERT INTO `donationhistory` VALUES (12, 7, 4, 5, 199999.00, '2024-12-04', NULL);
INSERT INTO `donationhistory` VALUES (13, 11, 3, NULL, NULL, '2024-12-05', 18);
INSERT INTO `donationhistory` VALUES (14, 11, 3, NULL, NULL, '2024-12-05', 17);
INSERT INTO `donationhistory` VALUES (15, 11, 3, NULL, NULL, '2024-12-05', 16);
INSERT INTO `donationhistory` VALUES (16, 9, 3, NULL, NULL, '2024-12-05', 19);
INSERT INTO `donationhistory` VALUES (17, 9, 3, NULL, NULL, '2024-12-05', 20);
INSERT INTO `donationhistory` VALUES (18, 7, 5, NULL, NULL, '2024-12-06', 23);
INSERT INTO `donationhistory` VALUES (19, 7, 5, NULL, NULL, '2024-12-06', 22);
INSERT INTO `donationhistory` VALUES (20, 17, 1, 40, 1000000.00, '2024-12-09', NULL);
INSERT INTO `donationhistory` VALUES (21, 17, 5, NULL, NULL, '2024-12-09', 42);
INSERT INTO `donationhistory` VALUES (22, 17, 7, NULL, NULL, '2024-12-09', 43);

-- ----------------------------
-- Table structure for donors
-- ----------------------------
DROP TABLE IF EXISTS `donors`;
CREATE TABLE `donors`  (
  `donor_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `phone` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  PRIMARY KEY (`donor_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 18 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of donors
-- ----------------------------
INSERT INTO `donors` VALUES (1, 'Lad Sunga', 'sungagerald6000@gmail.com', '09957233909', 'Brgy. Quirino Solano Nueva VIzcaya', 'bossLad', 'system1234');
INSERT INTO `donors` VALUES (3, 'Gerald', '09957233909', '09957233908', 'bahay', 'dwd', 'dwdw');
INSERT INTO `donors` VALUES (5, 'lad', '09957233909', '33', 'bahay', 'gerald', 'sunga');
INSERT INTO `donors` VALUES (6, 'berto', 'yalokgino@gmail.com', '09957233908', 'bahay', NULL, NULL);
INSERT INTO `donors` VALUES (7, 'Lebron James', 'lbj@gmail.com', '09957233908', 'Los Angeles', NULL, NULL);
INSERT INTO `donors` VALUES (8, 'Emiliol Paladin', 'emailpaladin@gmail.com', '09957233909', 'Brgy. Quirino Solano Nueva VIzcaya', NULL, NULL);
INSERT INTO `donors` VALUES (9, 'Emil Paladin', 'sungaglenn@gmail.com', '09957233909', 'Brgy. Quirino Solano Nueva VIzcaya', NULL, NULL);
INSERT INTO `donors` VALUES (10, 'Glenn Sunga', 'sungaglenn@gmail.com', '09678650366', 'Brgy. Quirino Solano Nueva VIzcaya', NULL, NULL);
INSERT INTO `donors` VALUES (11, 'Jerome Sunga', 'sungajerome@gmail.com', '2222222222', 'Brgy. Quirino Solano Nueva VIzcaya', NULL, NULL);
INSERT INTO `donors` VALUES (17, 'Kyrie Irving', 'acanganalbert@gmail.com', '09957233908', 'Brgy. Quirino Solano Nueva VIzcaya', 'uncledrew', 'kyrie11');

-- ----------------------------
-- Table structure for item_donations
-- ----------------------------
DROP TABLE IF EXISTS `item_donations`;
CREATE TABLE `item_donations`  (
  `donate_id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_id` int(11) NOT NULL,
  `charity_id` int(11) NOT NULL,
  `item_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `category` enum('Food','Clothing','Others') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `quantity` int(11) NOT NULL,
  `expiration_date` date NULL DEFAULT NULL,
  `date` date NULL DEFAULT NULL,
  PRIMARY KEY (`donate_id`) USING BTREE,
  INDEX `donor_id`(`donor_id`) USING BTREE,
  INDEX `charity_id`(`charity_id`) USING BTREE,
  CONSTRAINT `item_donations_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `item_donations_ibfk_2` FOREIGN KEY (`charity_id`) REFERENCES `charities` (`charity_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 44 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of item_donations
-- ----------------------------
INSERT INTO `item_donations` VALUES (1, 1, 8, 'Noodles', 'Food', 20, '2024-12-20', '2024-12-26');
INSERT INTO `item_donations` VALUES (2, 1, 1, 'Pajamas', 'Clothing', 5, '2024-12-02', '2024-12-28');
INSERT INTO `item_donations` VALUES (3, 7, 3, 'T-Shirts', 'Clothing', 300, '2024-12-26', '2024-12-02');
INSERT INTO `item_donations` VALUES (4, 8, 2, 'Blankets', 'Others', 5, '2024-12-04', '2024-12-04');
INSERT INTO `item_donations` VALUES (5, 5, 5, 'Canned Goods', 'Food', 5, '2024-12-04', '2024-12-04');
INSERT INTO `item_donations` VALUES (6, 5, 5, 'Canned Goods', 'Food', 5, '2024-12-04', '2024-12-04');
INSERT INTO `item_donations` VALUES (7, 5, 5, 'Canned Goods', 'Food', 5, '2024-12-04', '2024-12-04');
INSERT INTO `item_donations` VALUES (8, 5, 5, 'Canned Goods', 'Food', 5, '2024-12-04', '2024-12-04');
INSERT INTO `item_donations` VALUES (9, 5, 5, 'Canned Goods', 'Food', 5, '2024-12-04', '2024-12-04');
INSERT INTO `item_donations` VALUES (10, 5, 5, 'Canned Goods', 'Food', 5, '2024-12-04', '2024-12-04');
INSERT INTO `item_donations` VALUES (11, 11, 8, 'Jersey', 'Clothing', 100, '2024-12-04', '2024-12-01');
INSERT INTO `item_donations` VALUES (13, 11, 3, 'Pajamas', 'Clothing', 10, NULL, '2024-12-05');
INSERT INTO `item_donations` VALUES (14, 11, 3, 'Blankets', 'Others', 10, NULL, '2024-12-05');
INSERT INTO `item_donations` VALUES (15, 11, 3, 'T-Shirts', 'Clothing', 10, NULL, '2024-12-05');
INSERT INTO `item_donations` VALUES (16, 11, 3, 'Pajamas', 'Clothing', 10, NULL, '2024-12-05');
INSERT INTO `item_donations` VALUES (17, 11, 3, 'Blankets', 'Others', 10, NULL, '2024-12-05');
INSERT INTO `item_donations` VALUES (18, 11, 3, 'T-Shirts', 'Clothing', 10, NULL, '2024-12-05');
INSERT INTO `item_donations` VALUES (19, 9, 3, 'short', 'Clothing', 5, NULL, '2024-12-05');
INSERT INTO `item_donations` VALUES (20, 9, 3, 'damit', 'Clothing', 5, NULL, '2024-12-05');
INSERT INTO `item_donations` VALUES (21, 7, 1, 'short', 'Clothing', 2, NULL, '2024-12-06');
INSERT INTO `item_donations` VALUES (22, 7, 5, 'short', 'Clothing', 2, NULL, '2024-12-06');
INSERT INTO `item_donations` VALUES (23, 7, 5, 'brief', 'Clothing', 2, NULL, '2024-12-06');
INSERT INTO `item_donations` VALUES (24, 7, 5, 'panty', 'Clothing', 2, NULL, '2024-12-06');
INSERT INTO `item_donations` VALUES (25, 7, 3, 'Ball', 'Others', 2, NULL, '2024-12-09');
INSERT INTO `item_donations` VALUES (26, 7, 3, 'Ball', 'Others', 2, NULL, '2024-12-09');
INSERT INTO `item_donations` VALUES (27, 7, 3, 'Ring', 'Others', 2, NULL, '2024-12-09');
INSERT INTO `item_donations` VALUES (28, 7, 3, 'Net', 'Others', 2, NULL, '2024-12-09');
INSERT INTO `item_donations` VALUES (29, 7, 3, 'Ring', 'Others', 2, NULL, '2024-12-09');
INSERT INTO `item_donations` VALUES (30, 7, 3, 'Net', 'Others', 2, NULL, '2024-12-09');
INSERT INTO `item_donations` VALUES (31, 1, 10, 'Pajamas', 'Clothing', 10, NULL, '2024-12-09');
INSERT INTO `item_donations` VALUES (32, 1, 8, 'Fruits', 'Food', 10, '2025-01-11', '2024-12-09');
INSERT INTO `item_donations` VALUES (33, 5, 9, 'Damit', 'Clothing', 10, NULL, '2024-12-09');
INSERT INTO `item_donations` VALUES (34, 5, 9, 'Short', 'Clothing', 10, NULL, '2024-12-09');
INSERT INTO `item_donations` VALUES (35, 7, 2, 'Fruits', 'Food', 10, '2025-01-11', '2025-01-02');
INSERT INTO `item_donations` VALUES (36, 7, 8, 'Ring', 'Others', 10, NULL, '2024-12-09');
INSERT INTO `item_donations` VALUES (37, 6, 9, 'Ball', 'Others', 2, NULL, '2024-12-12');
INSERT INTO `item_donations` VALUES (38, 7, 9, 'Ball', 'Others', 5, NULL, '2024-12-09');
INSERT INTO `item_donations` VALUES (39, 7, 9, 'Ring', 'Others', 5, NULL, '2024-12-09');
INSERT INTO `item_donations` VALUES (40, 5, 6, 'Ring', 'Others', 2, NULL, '2024-12-09');
INSERT INTO `item_donations` VALUES (41, 5, 6, 'Ball', 'Others', 10, NULL, '2024-12-09');
INSERT INTO `item_donations` VALUES (42, 17, 5, 'Fruits', 'Food', 10, '2025-01-10', '2024-12-09');
INSERT INTO `item_donations` VALUES (43, 17, 7, 'Ball', 'Others', 10, NULL, '2024-12-09');

SET FOREIGN_KEY_CHECKS = 1;
