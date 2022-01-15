-- phpMyAdmin SQL Dump
-- version 4.9.7
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 14, 2022 at 07:48 PM
-- Server version: 5.6.41-84.1
-- PHP Version: 7.3.32

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `nftcryptoblocker`
--
CREATE DATABASE IF NOT EXISTS `nftcryptoblocker` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `nftcryptoblocker`;

-- --------------------------------------------------------

--
-- Table structure for table `automationblockingwhitelist`
--

CREATE TABLE `automationblockingwhitelist` (
  `id` int(11) NOT NULL,
  `usertwitterid` bigint(20) NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blockablephrases`
--

CREATE TABLE `blockablephrases` (
  `id` int(11) NOT NULL,
  `phrase` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blockableurls`
--

CREATE TABLE `blockableurls` (
  `id` int(11) NOT NULL,
  `url` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blockableusernameregexes`
--

CREATE TABLE `blockableusernameregexes` (
  `id` int(11) NOT NULL,
  `regex` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blocklistentries`
--

CREATE TABLE `blocklistentries` (
  `id` int(11) NOT NULL,
  `blocklistid` int(11) NOT NULL,
  `blockusertwitterid` bigint(20) NOT NULL,
  `dateadded` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blocklists`
--

CREATE TABLE `blocklists` (
  `id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `centralconfiguration`
--

CREATE TABLE `centralconfiguration` (
  `id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `centralisedblocklist`
--

CREATE TABLE `centralisedblocklist` (
  `id` int(11) NOT NULL,
  `blockableusertwitterid` bigint(20) NOT NULL,
  `matchedfiltertype` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `matchedfiltercontent` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dateadded` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedfrom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Home or mention timelines',
  `markedfordeletion` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `entriestoprocess`
--

CREATE TABLE `entriestoprocess` (
  `id` int(11) NOT NULL,
  `blocklistid` int(11) DEFAULT NULL,
  `subjectusertwitterid` bigint(20) NOT NULL,
  `objectusertwitterid` bigint(20) NOT NULL,
  `operation` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `matchedfiltertype` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `matchedfiltercontent` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addtocentraldb` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `dateadded` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `addedfrom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Unknown'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `twitterendpointlogs`
--

CREATE TABLE `twitterendpointlogs` (
  `date` date NOT NULL,
  `endpoint` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `callcount` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `userautomationrecords`
--

CREATE TABLE `userautomationrecords` (
  `id` int(11) NOT NULL,
  `subjectusertwitterid` bigint(20) NOT NULL,
  `objectusertwitterid` bigint(20) NOT NULL,
  `operation` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `userautomationsettings`
--

CREATE TABLE `userautomationsettings` (
  `id` int(11) NOT NULL,
  `usertwitterid` bigint(20) NOT NULL,
  `matchingphraseoperation` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nftprofilepictureoperation` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `urlsoperation` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cryptousernamesoperation` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nftfollowersoperation` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `centraldatabaseoperation` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `whitelistfollowings` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Y'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `userblocklistrecords`
--

CREATE TABLE `userblocklistrecords` (
  `id` int(11) NOT NULL,
  `usertwitterid` bigint(20) NOT NULL,
  `blocklistid` int(11) NOT NULL,
  `lastoperation` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `userblockrecords`
--

CREATE TABLE `userblockrecords` (
  `id` int(11) NOT NULL,
  `blocklistid` int(11) DEFAULT NULL,
  `subjectusertwitterid` bigint(20) NOT NULL,
  `objectusertwitterid` bigint(20) NOT NULL,
  `operation` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `matchedfiltertype` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `matchedfiltercontent` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dateprocessed` datetime NOT NULL,
  `addedfrom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Unknown'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `userfollowerscache`
--

CREATE TABLE `userfollowerscache` (
  `id` int(11) NOT NULL,
  `usertwitterid` bigint(20) NOT NULL,
  `recentfollowerid` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `userinitialblockrecords`
--

CREATE TABLE `userinitialblockrecords` (
  `id` int(11) NOT NULL,
  `subjectusertwitterid` bigint(20) NOT NULL,
  `objectusertwitterid` bigint(20) NOT NULL,
  `operation` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `twitterid` bigint(20) NOT NULL,
  `accesstoken` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `accesstokensecret` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mentionstimelinesinceid` bigint(20) DEFAULT NULL,
  `mentionstimelinepaginationtoken` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hometimelinesinceid` bigint(20) DEFAULT NULL,
  `hometimelinemaxid` bigint(20) DEFAULT NULL,
  `mentionstimelineendreached` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `hometimelineendreached` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `followerspaginationtoken` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `followersendreached` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `followerslastcheckedtime` datetime DEFAULT NULL,
  `highestactionedcentraldbid` int(11) DEFAULT NULL,
  `locked` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `followingcount` int(11) DEFAULT NULL,
  `followingcountlastcheckeddate` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `automationblockingwhitelist`
--
ALTER TABLE `automationblockingwhitelist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usertwitterid` (`usertwitterid`);

--
-- Indexes for table `blockablephrases`
--
ALTER TABLE `blockablephrases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phrase` (`phrase`);

--
-- Indexes for table `blockableurls`
--
ALTER TABLE `blockableurls`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `url` (`url`);

--
-- Indexes for table `blockableusernameregexes`
--
ALTER TABLE `blockableusernameregexes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phrase` (`regex`);

--
-- Indexes for table `blocklistentries`
--
ALTER TABLE `blocklistentries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `blocklistid_2` (`blocklistid`,`blockusertwitterid`),
  ADD KEY `blocklistid` (`blocklistid`);

--
-- Indexes for table `blocklists`
--
ALTER TABLE `blocklists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `centralconfiguration`
--
ALTER TABLE `centralconfiguration`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `centralisedblocklist`
--
ALTER TABLE `centralisedblocklist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `blockableusertwitterid` (`blockableusertwitterid`);

--
-- Indexes for table `entriestoprocess`
--
ALTER TABLE `entriestoprocess`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subjectusertwitterid` (`subjectusertwitterid`,`objectusertwitterid`),
  ADD KEY `blocklistid` (`blocklistid`);

--
-- Indexes for table `twitterendpointlogs`
--
ALTER TABLE `twitterendpointlogs`
  ADD UNIQUE KEY `date` (`date`,`endpoint`);

--
-- Indexes for table `userautomationrecords`
--
ALTER TABLE `userautomationrecords`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `userautomationsettings`
--
ALTER TABLE `userautomationsettings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usertwitterid` (`usertwitterid`);

--
-- Indexes for table `userblocklistrecords`
--
ALTER TABLE `userblocklistrecords`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `userid_2` (`usertwitterid`,`blocklistid`),
  ADD KEY `userid` (`usertwitterid`),
  ADD KEY `blocklistid` (`blocklistid`);

--
-- Indexes for table `userblockrecords`
--
ALTER TABLE `userblockrecords`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subjectusertwitterid` (`subjectusertwitterid`,`objectusertwitterid`);

--
-- Indexes for table `userfollowerscache`
--
ALTER TABLE `userfollowerscache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usertwitterid_2` (`usertwitterid`,`recentfollowerid`),
  ADD KEY `usertwitterid` (`usertwitterid`);

--
-- Indexes for table `userinitialblockrecords`
--
ALTER TABLE `userinitialblockrecords`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subjectusertwitterid` (`subjectusertwitterid`,`objectusertwitterid`,`operation`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `twitterid` (`twitterid`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `automationblockingwhitelist`
--
ALTER TABLE `automationblockingwhitelist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blockablephrases`
--
ALTER TABLE `blockablephrases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blockableurls`
--
ALTER TABLE `blockableurls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blockableusernameregexes`
--
ALTER TABLE `blockableusernameregexes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blocklistentries`
--
ALTER TABLE `blocklistentries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blocklists`
--
ALTER TABLE `blocklists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `centralconfiguration`
--
ALTER TABLE `centralconfiguration`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `centralisedblocklist`
--
ALTER TABLE `centralisedblocklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `entriestoprocess`
--
ALTER TABLE `entriestoprocess`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `userautomationrecords`
--
ALTER TABLE `userautomationrecords`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `userautomationsettings`
--
ALTER TABLE `userautomationsettings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `userblocklistrecords`
--
ALTER TABLE `userblocklistrecords`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `userblockrecords`
--
ALTER TABLE `userblockrecords`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `userinitialblockrecords`
--
ALTER TABLE `userinitialblockrecords`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `blocklistentries`
--
ALTER TABLE `blocklistentries`
  ADD CONSTRAINT `blocklistentries_ibfk_1` FOREIGN KEY (`blocklistid`) REFERENCES `blocklists` (`id`);

--
-- Constraints for table `entriestoprocess`
--
ALTER TABLE `entriestoprocess`
  ADD CONSTRAINT `entriestoprocess_ibfk_1` FOREIGN KEY (`blocklistid`) REFERENCES `blocklists` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `entriestoprocess_ibfk_2` FOREIGN KEY (`subjectusertwitterid`) REFERENCES `users` (`twitterid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `userautomationsettings`
--
ALTER TABLE `userautomationsettings`
  ADD CONSTRAINT `userautomationsettings_ibfk_1` FOREIGN KEY (`usertwitterid`) REFERENCES `users` (`twitterid`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `userblocklistrecords`
--
ALTER TABLE `userblocklistrecords`
  ADD CONSTRAINT `userblocklistrecords_ibfk_2` FOREIGN KEY (`usertwitterid`) REFERENCES `users` (`twitterid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `userblocklistrecords_ibfk_3` FOREIGN KEY (`blocklistid`) REFERENCES `blocklists` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `userfollowerscache`
--
ALTER TABLE `userfollowerscache`
  ADD CONSTRAINT `userfollowerscache_ibfk_1` FOREIGN KEY (`usertwitterid`) REFERENCES `users` (`twitterid`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
