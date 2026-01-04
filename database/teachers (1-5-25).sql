-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 04, 2026 at 06:51 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rawrit`
--

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `teacher_id` int(11) NOT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `fname` varchar(50) NOT NULL,
  `lname` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile_pic` varchar(55) NOT NULL DEFAULT 'default_profile.png',
  `school_id` varchar(4) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` varchar(20) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`teacher_id`, `account_number`, `fname`, `lname`, `password`, `profile_pic`, `school_id`, `status`, `approved_by`, `approved_at`) VALUES
(2, 'T001', 'Jess', 'Constante', '$2y$10$/aS5swbECzfpBJrXAbualOl.yO6HVR09UgKX.zp/i5/zTGAqwyLNS', 'teacher_T001_1759312222.jpg', '', 'pending', NULL, NULL),
(3, 'T002', 'Lili', 'Meow', '$2y$10$lakeW.gqWPHzkIqrsUylpOkl64/ltR6ARm2mS/Q.fh7tYk5j2l6X.', 'default_profile.png', '', 'pending', NULL, NULL),
(7, 'T005', 'Stacey', 'Ganda', '$2y$10$ZN6nQ8ThCSVFU15MDjnD4u97pRggeOeKLU.AoKSzd6Mhot6dB36Ym', 'default_profile.png', '', 'pending', NULL, NULL),
(8, 'T006', 'Colet', 'Vergara', '$2y$10$ExgONVUKX9rBUlt27Nj7dekAO.TipImGOcC38GD0fpn0x6rxNNzfy', 'teacher_T006_1759318073.jpg', 'IT21', 'pending', NULL, NULL),
(9, 'T007', 'teacher', 'dudas', '$2y$10$ft/damthugQE.MtIl2jKGe6fFUW..uHW1swGLqZjahUGd8shlQrhS', 'teacher_T007_1751363138.jpg', '', 'pending', NULL, NULL),
(11, 'T008', 'Juan', 'Dela Cruz', '$2y$10$e3fXMrJOyoy.16uqAfCPce0LIa4DilIE3AdswqtaWWv2Lz0TULhzC', 'default_profile.png', 'DT21', 'pending', NULL, NULL),
(12, 'T009', 'Ariana', 'Grande', '$2y$10$nDvlaijf3MxTuklTJoi6zerSzz2aNbzOb/wV89zh3ehzBoZ7ueBuO', 'default_profile.png', 'UK74', 'pending', NULL, NULL),
(13, 'T010', 'Maria', 'Dela Cruz', '$2y$10$O3.CkskKE85GfIB1mIcjHeX80Z0Bs4i6J9NX0tm3FeOwQXzmzIWkK', 'default_profile.png', 'ZH07', 'pending', NULL, NULL),
(14, 'T011', 'third', 'luna', '$2y$10$lz3COBdpAp8p7R9Ui42/juwhSJ6maElFjB/0Bl.BxDIb/lZpl1MR.', 'default_profile.png', 'ZH09', 'pending', NULL, NULL),
(15, 'T012', 'Alys', 'Perez', '$2y$10$d7t3mcC0yyOQeCBi2uqG2.7yHQRYrKxx3X1ZJ4BmoxnMlavgsGVGy', 'teacher_T012_1759465966.jpg', 'DO66', 'approved', 'A001', '2026-01-04 17:36:12'),
(17, 'T013', 'Sample ', 'Teacher', '$2y$10$sjtwA9RXzDwztS3Hir5F/ebu/1U.FhJOU9nUflq/7c9V6nyIgMAHy', 'default_profile.png', 'CN31', 'approved', 'A001', '2026-01-04 17:48:36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`teacher_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
