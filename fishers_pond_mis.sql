-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 23, 2026 at 10:32 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fishers_pond_mis`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `AdminID` int(11) NOT NULL,
  `Username` varchar(50) NOT NULL,
  `PasswordHash` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`AdminID`, `Username`, `PasswordHash`) VALUES
(1, 'superAdmin', '$2y$10$hMru4iJxmNa84qV/l3gKaOc15MvCxMHyulTKsVw3huiztD5.KJogu');

-- --------------------------------------------------------

--
-- Table structure for table `employee`
--

CREATE TABLE `employee` (
  `staffID` int(5) NOT NULL,
  `FirstName` varchar(64) NOT NULL,
  `LastName` varchar(64) NOT NULL,
  `BirthDate` date DEFAULT NULL,
  `Email` varchar(64) DEFAULT NULL,
  `ContactNumber` int(11) NOT NULL,
  `PositionID` int(2) DEFAULT NULL,
  `IsActive` tinyint(1) DEFAULT 1,
  `Username` varchar(50) DEFAULT NULL,
  `PasswordHash` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee`
--

INSERT INTO `employee` (`staffID`, `FirstName`, `LastName`, `BirthDate`, `Email`, `ContactNumber`, `PositionID`, `IsActive`, `Username`, `PasswordHash`) VALUES
(1, 'cemen', 'miranda', '2026-03-18', 'markantipo@gmail.com', 2147483647, 1, 1, 'cemen', '$2y$10$9IZl1FL9GcP.ftSgZlAcp.WFT0KmRtReFMfllnVnHKRaaDpyXTdh2'),
(2, 'ffa', 'ffa', '2026-03-18', 'cmer@danielprints.ai', 2147483647, 4, 0, NULL, NULL),
(3, 'ino', 'chavez', '2026-12-25', 'markantipo@gmail.com', 2147483647, 2, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `employeeshift`
--

CREATE TABLE `employeeshift` (
  `StaffID` int(11) NOT NULL,
  `ShiftID` int(11) NOT NULL,
  `ShiftDate` date NOT NULL,
  `ClockIn` datetime DEFAULT NULL,
  `ClockOut` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employeeshift`
--

INSERT INTO `employeeshift` (`StaffID`, `ShiftID`, `ShiftDate`, `ClockIn`, `ClockOut`) VALUES
(1, 2, '2026-03-19', '2026-03-19 15:50:28', '2026-03-19 15:50:39'),
(1, 3, '2026-03-20', '2026-03-20 21:18:48', '2026-03-20 21:24:41'),
(1, 4, '2026-03-20', '2026-03-20 21:27:51', '2026-03-20 21:28:06'),
(1, 5, '2026-03-20', '2026-03-20 22:01:13', '2026-03-20 22:01:18'),
(1, 6, '2026-03-23', '2026-03-23 09:49:15', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `position`
--

CREATE TABLE `position` (
  `PositionID` int(2) NOT NULL,
  `PositionName` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `position`
--

INSERT INTO `position` (`PositionID`, `PositionName`) VALUES
(1, 'Manager'),
(2, 'Cook'),
(3, 'Cashier'),
(4, 'Waiter'),
(5, 'Dishwasher');

-- --------------------------------------------------------

--
-- Table structure for table `shift`
--

CREATE TABLE `shift` (
  `ShiftID` int(11) NOT NULL,
  `ShiftName` varchar(64) DEFAULT NULL,
  `StartTime` time DEFAULT NULL,
  `EndTime` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`AdminID`),
  ADD UNIQUE KEY `Username` (`Username`);

--
-- Indexes for table `employee`
--
ALTER TABLE `employee`
  ADD PRIMARY KEY (`staffID`),
  ADD UNIQUE KEY `Username` (`Username`),
  ADD KEY `PositionID` (`PositionID`);

--
-- Indexes for table `employeeshift`
--
ALTER TABLE `employeeshift`
  ADD PRIMARY KEY (`StaffID`,`ShiftID`,`ShiftDate`),
  ADD KEY `ShiftID` (`ShiftID`);

--
-- Indexes for table `position`
--
ALTER TABLE `position`
  ADD PRIMARY KEY (`PositionID`);

--
-- Indexes for table `shift`
--
ALTER TABLE `shift`
  ADD PRIMARY KEY (`ShiftID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `AdminID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employee`
--
ALTER TABLE `employee`
  MODIFY `staffID` int(5) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `employeeshift`
--
ALTER TABLE `employeeshift`
  MODIFY `ShiftID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `position`
--
ALTER TABLE `position`
  MODIFY `PositionID` int(2) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `shift`
--
ALTER TABLE `shift`
  MODIFY `ShiftID` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `employee`
--
ALTER TABLE `employee`
  ADD CONSTRAINT `employee_ibfk_1` FOREIGN KEY (`PositionID`) REFERENCES `position` (`PositionID`),
  ADD CONSTRAINT `employee_ibfk_2` FOREIGN KEY (`PositionID`) REFERENCES `position` (`PositionID`);

--
-- Constraints for table `employeeshift`
--
ALTER TABLE `employeeshift`
  ADD CONSTRAINT `employeeshift_ibfk_1` FOREIGN KEY (`StaffID`) REFERENCES `employee` (`staffID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
