-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 01, 2024 at 07:37 PM
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
-- Database: `rawrit`
--

-- --------------------------------------------------------

--
-- Table structure for table `answers`
--

CREATE TABLE `answers` (
  `answer_id` int(11) NOT NULL,
  `question_id` int(11) DEFAULT NULL,
  `answer_text` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `answers`
--

INSERT INTO `answers` (`answer_id`, `question_id`, `answer_text`, `is_correct`) VALUES
(1, 1, 'h', 0),
(2, 1, 'h', 1),
(3, 1, 'h', 0),
(4, 1, 'h', 0),
(5, 2, 'aSAS', 0),
(6, 2, 'a', 0),
(7, 2, 'sdsd', 0),
(8, 2, 'dsad', 1),
(9, 3, 'dsad', 0),
(10, 3, 'dasd', 0),
(11, 3, 'asdasd', 0),
(12, 3, 'add', 1),
(13, 4, 'True', 1),
(14, 4, 'False', 0),
(15, 22, '', 1),
(16, 23, '', 1),
(17, 24, '', 1),
(18, 25, '', 1),
(19, 26, '', 1),
(20, 27, '', 1),
(21, 29, '', 1),
(22, 30, '', 1),
(23, 31, '', 1),
(24, 32, '', 1),
(25, 33, '', 1),
(26, 34, '', 1),
(27, 35, '', 1),
(28, 36, '', 1),
(29, 37, '', 1),
(30, 38, '', 1),
(31, 39, '', 1),
(32, 40, '', 1),
(33, 41, '', 1),
(34, 42, '', 1),
(35, 43, '', 1),
(36, 44, 's', 0),
(37, 44, 's', 0),
(38, 44, 's', 0),
(39, 44, 's', 1),
(40, 45, 'True', 1),
(41, 45, 'False', 0),
(42, 46, 'True', 0),
(43, 46, 'False', 1),
(44, 47, 'True', 0),
(45, 47, 'False', 1),
(46, 48, 'True', 1),
(47, 48, 'False', 0),
(48, 49, 'True', 0),
(49, 49, 'False', 1),
(50, 50, 'd', 0),
(51, 50, 'd', 0),
(52, 50, 'd', 0),
(53, 50, 'd', 1),
(54, 51, 'True', 0),
(55, 51, 'False', 1),
(56, 52, 'True', 1),
(57, 52, 'False', 0),
(58, 53, 'True', 0),
(59, 53, 'False', 1),
(60, 54, 'True', 0),
(61, 54, 'False', 1),
(62, 55, 'True', 1),
(63, 55, 'False', 0),
(64, 56, '', 1),
(65, 57, '', 1),
(66, 58, '', 1),
(67, 59, 'True', 0),
(68, 59, 'False', 1),
(69, 60, '', 1),
(70, 61, 'True', 0),
(71, 61, 'False', 1),
(72, 62, 'True', 0),
(73, 62, 'False', 1),
(74, 63, '', 1),
(75, 64, '', 1),
(76, 65, '', 1),
(77, 66, '', 1),
(78, 67, '', 1),
(79, 68, '', 1),
(80, 69, 'True', 1),
(81, 69, 'False', 0),
(82, 72, '', 1),
(83, 73, '', 1),
(84, 81, '1', 1),
(85, 82, '1', 1),
(86, 83, '1', 1),
(87, 84, '1', 1),
(88, 85, '1', 1),
(89, 86, '1', 1),
(90, 87, '1', 1),
(91, 88, '1', 1),
(92, 89, 'd', 0),
(93, 89, 'd', 0),
(94, 89, 'd', 0),
(95, 89, 'd', 1),
(96, 90, '1', 1),
(97, 91, '1', 1),
(98, 92, '1', 1),
(99, 93, '1', 1),
(100, 94, '1', 1),
(101, 95, '1', 1),
(102, 96, '1', 1),
(103, 97, '1', 1),
(104, 98, '1', 1),
(105, 99, '1', 1),
(106, 104, 'True', 0),
(107, 104, 'False', 1),
(108, NULL, 'a', 1),
(109, NULL, 'a', 1),
(110, NULL, 'a', 1),
(111, 163, 'a', 1),
(112, 163, 'a', 1),
(113, 163, 'a', 1),
(114, 165, 'b', 1),
(115, 165, 'b', 1),
(116, 165, 'b', 1),
(117, 167, 'c', 1),
(118, 167, 'c', 1),
(119, 167, 'c', 1),
(120, 167, 'c', 1),
(121, 169, 'b', 1),
(122, 169, 'b', 1),
(123, 169, 'b', 1),
(124, 171, 'b', 1),
(125, 171, 'b', 1),
(126, 171, 'b', 1),
(127, 173, 'b', 1),
(128, 173, 'b', 1),
(129, 173, 'b', 1),
(130, 175, 'b', 1),
(131, 175, 'b', 1),
(132, 175, 'b', 1),
(133, 177, 'b', 1),
(134, 177, 'b', 1),
(135, 177, 'b', 1),
(136, 179, 'b', 1),
(137, 179, 'b', 1),
(138, 179, 'b', 1),
(139, 181, 'g', 1),
(140, 181, 'g', 1),
(141, 181, 'g', 1),
(142, 182, '', 1),
(143, 183, '', 1),
(144, 185, '', 1),
(145, 186, '', 1),
(146, 187, '', 1),
(147, 189, '', 1),
(148, 190, '', 1),
(149, 191, '', 1),
(150, 193, 'a', 1),
(151, 193, 'a', 1),
(152, 193, 'a', 1),
(153, 195, 'g', 1),
(154, 195, 'g', 1),
(155, 195, 'g', 1),
(156, 197, 'g', 1),
(157, 197, 'g', 1),
(158, 197, 'g', 1),
(159, 199, 'g', 1),
(160, 199, 'g', 1),
(161, 199, 'g', 1),
(162, 201, 'g', 1),
(163, 201, 'g', 1),
(164, 201, 'g', 1),
(165, 203, 'g', 1),
(166, 203, 'g', 1),
(167, 203, 'g', 1),
(168, 205, 'g', 1),
(169, 205, 'g', 1),
(170, 205, 'g', 1),
(171, 207, 'g', 1),
(172, 207, 'g', 1),
(173, 207, 'g', 1),
(174, 209, 'g', 1),
(175, 209, 'g', 1),
(176, 209, 'g', 1),
(177, 211, 'g', 1),
(178, 211, 'g', 1),
(179, 211, 'g', 1),
(180, 213, 'b', 1),
(181, 213, 'b', 1),
(182, 213, 'b', 1),
(183, 215, 'b', 1),
(184, 215, 'b', 1),
(185, 215, 'b', 1),
(186, 217, 'a', 1),
(187, 217, 'a', 1),
(188, 217, 'a', 1),
(189, 219, 'a', 1),
(190, 219, 'a', 1),
(191, 219, 'a', 1),
(192, 221, 'a', 1),
(193, 221, 'a', 1),
(194, 221, 'a', 1),
(195, 223, 'a', 1),
(196, 223, 'a', 1),
(197, 223, 'a', 1),
(198, 225, 'a', 1),
(199, 225, 'a', 1),
(200, 225, 'a', 1),
(201, 226, 'aa', 0),
(202, 226, 'aaaa', 0),
(203, 226, 'aaa', 0),
(204, 226, 'aaa', 1),
(205, 227, 'aa', 0),
(206, 227, 'aa', 0),
(207, 227, 'aa', 1),
(208, 227, 'aa', 0),
(214, 232, 'c', 1),
(215, 232, 'c', 1),
(216, 232, 'c', 1),
(217, 232, 'c', 1),
(222, 240, 'c', 1),
(223, 240, 'c', 1),
(224, 240, 'c', 1),
(225, 240, 'c', 1),
(226, 242, 'b', 1),
(227, 242, 'b', 1),
(228, 242, 'b', 1),
(229, 245, 'b', 1),
(230, 245, 'b', 1),
(231, 245, 'b', 1),
(232, 247, 'a', 1),
(233, 247, 'a', 1),
(234, 247, 'a', 1),
(235, 249, 'a', 1),
(236, 249, 'a', 1),
(237, 249, 'a', 1),
(240, 256, 'b', 1),
(241, 256, 'b', 1),
(242, 256, 'b', 1),
(243, 257, 'c', 1),
(244, 257, 'c', 1),
(245, 257, 'c', 1),
(246, 257, 'c', 1),
(247, 258, 'b', 1),
(248, 258, 'b', 1),
(249, 258, 'b', 1),
(250, 259, 'c', 1),
(251, 259, 'c', 1),
(252, 259, 'c', 1),
(253, 259, 'c', 1),
(254, 260, 'c', 1),
(255, 260, 'c', 1),
(256, 260, 'c', 1),
(257, 260, 'c', 1),
(258, 261, 'c', 1),
(259, 261, 'c', 1),
(260, 261, 'c', 1),
(261, 261, 'c', 1),
(262, 262, 'True', 0),
(263, 262, 'False', 1),
(264, 263, 'h', 0),
(265, 263, 'd', 1),
(266, 263, 'sample', 0),
(267, 263, 'sample answer', 0),
(268, 264, 'True', 0),
(269, 264, 'False', 1),
(270, 265, 'g', 1),
(271, 265, 'g', 1),
(272, 265, 'g', 1),
(273, 266, 'yes', 0),
(274, 266, 'bakit', 0),
(275, 266, 'oo', 0),
(276, 266, 'hindi', 1),
(277, 267, 'True', 1),
(278, 267, 'False', 0),
(279, 268, 'ano', 1),
(280, 268, 'ano', 1),
(281, 268, 'ano', 1),
(282, 269, 'True', 0),
(283, 269, 'False', 1),
(284, 270, 'True', 0),
(285, 270, 'False', 1),
(286, 271, 'True', 0),
(287, 271, 'False', 1),
(288, 272, 'a', 0),
(289, 272, 'ssss', 0),
(290, 272, 'sample', 0),
(291, 272, 'this is the answer', 1),
(292, 273, '', 1),
(293, 274, 'True', 1),
(294, 274, 'False', 0),
(295, 275, 'True', 0),
(296, 275, 'False', 1),
(297, 276, 's', 0),
(298, 276, 's', 0),
(299, 276, 's', 0),
(300, 276, 's', 1),
(301, 277, 'aaaa', 1),
(302, 277, 'sss', 0),
(303, 277, 'ssss', 0),
(304, 277, 'xxxx', 0),
(305, 278, 'sss', 0),
(306, 278, 'sss', 0),
(307, 278, 'ss', 1),
(308, 278, 'ss', 0),
(309, 279, '', 1),
(310, 280, 'ano', 1),
(311, 280, 'ano', 1),
(312, 280, 'ano', 1),
(313, 281, 'sss', 0),
(314, 281, 'sss', 0),
(315, 281, 'sss', 0),
(316, 281, 'ddd', 1),
(317, 282, '', 1),
(318, 283, '', 1),
(319, 284, 'aaaa', 0),
(320, 284, 'aaaa', 0),
(321, 284, 'aaa', 0),
(322, 284, 'aaa', 1),
(323, 285, 'sss', 0),
(324, 285, 'hhh', 0),
(325, 285, 'hhhh', 1),
(326, 285, 'hhh', 0),
(327, 286, 'aaaaa', 0),
(328, 286, 'aaaa', 0),
(329, 286, 'ssss', 1),
(330, 286, 'aaaa', 0),
(331, 287, 'True', 1),
(332, 287, 'False', 0),
(333, 288, '', 1),
(334, 290, 'aaaaa', 0),
(335, 290, 'aaaaa', 0),
(336, 290, 'aaaa', 0),
(337, 290, 'aaaa', 1),
(338, 291, 'True', 0),
(339, 291, 'False', 1),
(340, 293, 'ano', 1),
(341, 293, 'ano', 1),
(342, 293, 'ano', 1),
(343, 294, 'ano', 1),
(344, 294, 'ano', 1),
(345, 294, 'ano', 1),
(346, 295, 'aaaa', 0),
(347, 295, 'ssss', 0),
(348, 295, 'sss', 1),
(349, 295, 'sss', 0),
(350, 296, 'True', 0),
(351, 296, 'False', 1),
(352, 297, 'aaa', 0),
(353, 297, 'aaaa', 0),
(354, 297, 'aaaa', 0),
(355, 297, 'asss', 1),
(356, 298, 'True', 0),
(357, 298, 'False', 1),
(358, 302, 'ano', 1),
(359, 302, 'ano', 1),
(360, 302, 'ano', 1),
(361, 303, 'ano', 1),
(362, 303, 'ano', 1),
(363, 303, 'ano', 1),
(364, 304, 'ano', 1),
(365, 304, 'ano', 1),
(366, 304, 'ano', 1),
(367, 305, 'aaa', 0),
(368, 305, 'aaa', 0),
(369, 305, 'aaaa', 0),
(370, 305, 'aaa', 1),
(371, 306, 'True', 1),
(372, 306, 'False', 0),
(373, 307, 'ano', 1),
(374, 307, 'ano', 1),
(375, 307, 'ano', 1),
(376, 308, 'ano', 1),
(377, 308, 'ano', 1),
(378, 308, 'ano', 1),
(379, 309, 'g', 1),
(380, 309, 'g', 1),
(381, 309, 'g', 1),
(382, 310, 'A', 0),
(383, 310, 'B', 0),
(384, 310, 'C', 0),
(385, 310, 'D', 1),
(386, 311, 'aaaa', 0),
(387, 311, 'aaaa', 0),
(388, 311, 'aaaa', 0),
(389, 311, 'aaaaa', 1),
(390, 312, 'aaaaa', 1),
(391, 312, 'aaaaa', 0),
(392, 312, 'aaaa', 0),
(393, 312, 'aaaaaa', 0),
(394, 313, 'dasdsd', 1),
(395, 313, 'dasdsad', 0),
(396, 313, 'dsadsad', 0),
(397, 313, 'dasdds', 0),
(398, 314, 'square', 1),
(399, 314, 'circle', 0),
(400, 314, 'triangle', 0),
(401, 314, 'rectangle', 0),
(402, 315, 'True', 0),
(403, 315, 'False', 1),
(404, 316, 'a', 1),
(405, 316, 'a', 1),
(406, 316, 'a', 1),
(407, 317, 'mercury', 0),
(408, 317, 'earth', 1),
(409, 317, 'mars', 0),
(410, 317, 'venus', 0),
(411, 318, 'ano', 1),
(412, 318, 'ano', 1),
(413, 318, 'ano', 1),
(414, 319, 's', 0),
(415, 319, 's', 0),
(416, 319, 's', 0),
(417, 319, 's', 1),
(418, 320, 'True', 1),
(419, 320, 'False', 0),
(420, 321, 'True', 0),
(421, 321, 'False', 1),
(422, 322, 'True', 0),
(423, 322, 'False', 1),
(424, 323, 'ewan', 1),
(425, 323, 'di ko rin alam', 0),
(426, 323, 'kase ano', 0),
(427, 323, 'bahala sha', 0),
(428, 324, 'ewan', 1),
(429, 325, 'True', 0),
(430, 325, 'False', 0),
(431, 326, 'ewan', 1),
(432, 326, 'sino', 1),
(433, 326, 'ba', 1),
(434, 327, 'sha', 1),
(435, 328, 'sha', 1),
(436, 329, 'ewan', 1),
(437, 330, 'ewan', 1),
(438, 334, 'sha', 1),
(439, 346, 'True', 1),
(440, 348, 'try', 1),
(441, 349, 'kase ano', 1),
(442, 350, 'maki, haki, yaki', 1),
(443, 351, 'sha', 1),
(444, 352, 'try,lang,uli', 1),
(445, 353, 'kase ano', 1),
(446, 354, 'maki, haki, yaki', 1),
(447, 355, 'sha', 1),
(448, 356, 'try,lang,uli', 1),
(449, 357, 'try', 1),
(450, 358, 'False', 1),
(451, 359, 'ano', 1),
(452, 360, 'maki, haki, yaki', 1),
(453, 361, 'sha', 1),
(454, 362, 'True', 1),
(455, 363, 'True', 0),
(456, 363, 'False', 0),
(457, 364, 'True', 0),
(458, 364, 'False', 0),
(459, 365, 'True', 0),
(460, 365, 'False', 0),
(461, 366, 'True', 0),
(462, 366, 'False', 0),
(463, 367, 'True', 1),
(464, 368, 'haki|yaki', 1),
(465, 369, 'ewan', 1),
(466, 370, 'maki, haki, yaki', 1),
(467, 371, 'State', 1),
(468, 372, 'True', 1),
(469, 373, 'sample,lang,uli', 1),
(470, 374, '|', 1),
(471, 375, 'kahit,ano|pano,ba', 1),
(472, 376, '[]|[]', 1),
(473, 377, '[]|[]', 1),
(474, 379, '[\"674533df58182_pie graph 1.png\"]|[\"674533df5876f_pie graph.png\"]', 1),
(475, 380, '[\"674536f10c350_adminItemAnalysis 6.png\"]|[\"674536f10c87e_pie graph.png\"]', 1),
(476, 380, '[\"674536f10c350_adminItemAnalysis 6.png\"]|[\"674536f10c87e_pie graph.png\"]', 1),
(477, 381, '[\"67453ba0d03d3_createQuizMultiple.png\",\"67453ba0d0685_adminItemAnalysis 6.png\",\"67453ba0d0979_adminItemAnalysis 5.png\"]|[\"67453ba0d0bc8_pie graph 1.png\",\"67453ba0d116a_pie graph.png\",\"67453ba0d12e3_userList-teachers.png\"]', 1),
(478, 381, '[\"67453ba0d03d3_createQuizMultiple.png\",\"67453ba0d0685_adminItemAnalysis 6.png\",\"67453ba0d0979_adminItemAnalysis 5.png\"]|[\"67453ba0d0bc8_pie graph 1.png\",\"67453ba0d116a_pie graph.png\",\"67453ba0d12e3_userList-teachers.png\"]', 1),
(479, 382, 'siguro', 1),
(480, 382, 'nfgnfngfn', 0),
(481, 382, 'cdcsdds', 0),
(482, 382, 'dv sdvs', 0),
(483, 382, 'fvffdbvf', 0),
(484, 382, 'fvfdbfd', 0),
(485, 383, 'sha,si,ano', 1),
(486, 384, 'dfgbdfgbg', 1),
(487, 384, 'siguro', 0),
(488, 384, 'fbdfbdfgb', 0),
(489, 387, '[\"6745a7d3cc991_010D949B-3F2D-4959-915D-E5E447E877C5.JPG\",\"6745a7d3cd05f_1606409826517.jpg\",\"6745a7d3cd5cb_A82C5257-DF61-4E88-9475-8E7D51FE917E.JPG\"]|[\"6745a7d3cdada_IMG_1443.jpg\",\"6745a7d3ce01c_IMG_1448.jpg\",\"6745a7d3ce38d_IMG_1454.jpg\",\"6745a7d3ce6e2_IMG_1457.jpg\",\"6745a7d3ce87c_IMG_1463.jpg\"]', 1),
(490, 387, '[\"6745a7d3cc991_010D949B-3F2D-4959-915D-E5E447E877C5.JPG\",\"6745a7d3cd05f_1606409826517.jpg\",\"6745a7d3cd5cb_A82C5257-DF61-4E88-9475-8E7D51FE917E.JPG\"]|[\"6745a7d3cdada_IMG_1443.jpg\",\"6745a7d3ce01c_IMG_1448.jpg\",\"6745a7d3ce38d_IMG_1454.jpg\",\"6745a7d3ce6e2_IMG_1457.jpg\",\"6745a7d3ce87c_IMG_1463.jpg\"]', 1),
(491, 388, 'maki, haki, yaki', 1),
(492, 389, 'maki, haki, yaki', 1),
(493, 390, 'maki, haki, yaki', 1),
(494, 391, 'fbfgbfgb', 0),
(495, 391, 'fbdfbdfgb', 1),
(496, 392, 'ano', 1),
(497, 393, 'ano', 1),
(498, 394, 'ano', 1),
(499, 395, 'ewan', 1),
(500, 396, 'ewan', 1),
(501, 397, 'ewan', 1),
(502, 398, 'ewan', 1),
(503, 399, 'sha', 1),
(504, 400, 'ewan', 1),
(505, 401, 'siguro', 1),
(506, 401, 'dfgbdfgbg', 0),
(507, 402, 'siguro', 1),
(508, 402, 'dfgbdfgbg', 0),
(509, 403, 'siguro', 1),
(510, 403, 'dfgbdfgbg', 0),
(511, 404, 'siguro', 1),
(512, 404, 'dfgbdfgbg', 0),
(513, 405, 'siguro', 1),
(514, 405, 'dfgbdfgbg', 0),
(515, 406, 'siguro', 1),
(516, 406, 'nfgnfngfn', 0),
(517, 407, 'siguro', 1),
(518, 407, 'nfgnfngfn', 0),
(519, 408, 'siguro', 1),
(520, 408, 'nfgnfngfn', 0),
(521, 409, 'siguro', 1),
(522, 409, 'nfgnfngfn', 0),
(523, 410, 'siguro', 1),
(524, 410, 'nfgnfngfn', 0),
(525, 411, 'siguro', 1),
(526, 411, 'nfgnfngfn', 0),
(527, 412, 'siguro', 1),
(528, 412, 'nfgnfngfn', 0),
(529, 413, 'siguro', 1),
(530, 413, 'nfgnfngfn', 0),
(531, 414, 'siguro', 1),
(532, 414, 'nfgnfngfn', 0),
(533, 415, 'siguro', 1),
(534, 415, 'nfgnfngfn', 0),
(535, 416, 'siguro', 1),
(536, 416, 'nfgnfngfn', 0),
(537, 417, 'siguro', 1),
(538, 417, 'nfgnfngfn', 0),
(539, 418, 'siguro', 1),
(540, 418, 'nfgnfngfn', 0),
(541, 419, 'siguro', 1),
(542, 419, 'nfgnfngfn', 0),
(543, 420, 'siguro', 1),
(544, 420, 'nfgnfngfn', 0),
(545, 421, 'siguro', 1),
(546, 421, 'nfgnfngfn', 0),
(547, 422, 'siguro', 1),
(548, 422, 'nfgnfngfn', 0),
(549, 423, 'siguro', 1),
(550, 423, 'nfgnfngfn', 0),
(551, 424, 'bgngfnfg', 1),
(552, 424, 'nfgnfngfn', 0),
(553, 425, 'sila', 1),
(554, 426, 'sila', 1),
(555, 427, 'sha', 1),
(556, 428, 'nfgnfngfn', 1),
(557, 428, 'fbdfbdfgb', 0),
(558, 429, 'ewan', 1),
(559, 430, 'ewan', 1),
(560, 431, 'ewan', 1),
(561, 432, 'sila', 1),
(562, 433, 'sila', 1),
(563, 434, 'nfgnfngfn', 0),
(564, 434, 'bgngfnfg', 1),
(565, 435, 'nfgnfngfn', 0),
(566, 435, 'bgngfnfg', 1),
(567, 436, 'siguro', 1),
(568, 436, 'bgngfnfg', 0),
(569, 437, 'ewan', 1),
(570, 438, 'ewan', 1),
(571, 439, 'nfgnfngfn', 0),
(572, 439, 'nfgnfngfn', 0),
(573, 439, 'siguro', 1),
(574, 440, 'nfgnfngfn', 0),
(575, 440, 'nfgnfngfn', 0),
(576, 440, 'siguro', 1),
(577, 441, 'nfgnfngfn', 0),
(578, 441, 'nfgnfngfn', 0),
(579, 441, 'siguro', 1),
(580, 442, 'nfgnfngfn', 0),
(581, 442, 'nfgnfngfn', 0),
(582, 442, 'siguro', 1),
(583, 443, 'nfgnfngfn', 0),
(584, 443, 'siguro', 1),
(585, 444, 'sha', 1),
(586, 445, 'sha', 1),
(587, 446, 'ffdsrfsdv', 0),
(588, 446, 'f bfsbsf', 1),
(589, 447, 'ewan', 1),
(590, 451, 'ewan', 1),
(591, 452, 'anooo', 1),
(592, 453, 'anooo', 1),
(593, 454, 'ano', 1),
(594, 454, 'ang', 1),
(595, 454, 'ahh', 1),
(596, 455, 'anooo', 1),
(597, 456, 'True', 0),
(598, 456, 'False', 0),
(599, 457, 'sha', 1),
(600, 458, 'kase ano', 1),
(601, 459, '[\"6749bfb397059_010D949B-3F2D-4959-915D-E5E447E877C5.JPG\",\"6749bfb39723c_1606409826517.jpg\",\"6749bfb3973ba_A82C5257-DF61-4E88-9475-8E7D51FE917E.JPG\"]|[\"6749bfb397542_IMG_1422.jpg\",\"6749bfb397724_IMG_1431.jpg\",\"6749bfb397884_IMG_1434.jpg\"]', 1),
(602, 459, '[\"6749bfb397059_010D949B-3F2D-4959-915D-E5E447E877C5.JPG\",\"6749bfb39723c_1606409826517.jpg\",\"6749bfb3973ba_A82C5257-DF61-4E88-9475-8E7D51FE917E.JPG\"]|[\"6749bfb397542_IMG_1422.jpg\",\"6749bfb397724_IMG_1431.jpg\",\"6749bfb397884_IMG_1434.jpg\"]', 1),
(603, 460, 'False', 1),
(604, 461, 'try', 1),
(605, 462, 'maki, haki, yaki', 1),
(606, 463, 'ewan', 1),
(607, 464, 'True', 1),
(608, 465, 'maki, haki, yaki', 1),
(609, 466, 'sha', 1),
(610, 472, 'dfgbdfgbg', 1),
(611, 472, 'dfgbdfgbg', 1),
(612, 475, 'dfgbdfgbg', 1),
(613, 475, 'dfgbdfgbg', 1),
(614, 476, 'siguro', 1),
(615, 476, 'siguro', 1),
(616, 477, 'siguro', 1),
(617, 477, 'siguro', 1),
(618, 478, 'nfgnfngfn', 1),
(619, 478, 'nfgnfngfn', 1),
(620, 479, 'siguro', 1),
(621, 479, 'siguro', 1),
(622, 480, 'fbdfbdfgb', 1),
(623, 480, 'fbdfbdfgb', 1),
(624, 481, 'answer 1', 0),
(625, 481, 'answer 2', 0),
(626, 481, 'answer 3', 1),
(627, 481, 'answer 3', 1),
(628, 482, 'anooooooo', 1),
(629, 483, 'ewan', 0),
(630, 483, 'kase ano', 0),
(631, 483, 'anoo', 1),
(632, 483, 'anoooooo', 0),
(633, 483, 'anoooooo', 0),
(634, 484, 'option 1', 0),
(635, 484, 'option 2', 1),
(636, 484, 'option 3', 0),
(637, 484, 'option 4', 0),
(638, 484, 'option 4', 0),
(639, 485, 'Computer', 0),
(640, 485, 'Programming', 0),
(641, 485, 'Computer Programming', 1),
(642, 485, 'Computer Science', 0),
(643, 485, 'Computer Science', 0),
(644, 486, 'addition, subtraction, multiplication, division', 1),
(645, 487, 'Markup', 1),
(646, 488, 'True', 1),
(647, 489, '==', 0),
(648, 489, '+=', 1),
(649, 489, '!=', 0),
(650, 489, '!=', 0),
(651, 490, '[\"674ca1d3f08e1_images (1).jpeg\",\"674ca1d3f0b5c_images.jpeg\"]|[\"674ca1d3f0cd9_what-computer-programming-jobs-offer-remote-work-jpg.webp\",\"674ca1d3f0f78_7200.webp\"]', 1),
(652, 490, '[\"674ca1d3f08e1_images (1).jpeg\",\"674ca1d3f0b5c_images.jpeg\"]|[\"674ca1d3f0cd9_what-computer-programming-jobs-offer-remote-work-jpg.webp\",\"674ca1d3f0f78_7200.webp\"]', 1),
(653, 491, 'sha', 1),
(654, 492, 'siguro', 1),
(655, 492, 'nfgnfngfn', 0),
(656, 492, 'nfgnfngfn', 0),
(657, 493, 'ano', 1),
(658, 494, 'True', 1),
(659, 495, 'True', 1);

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `enrollment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`enrollment_id`, `student_id`, `subject_id`) VALUES
(19, 4, 10),
(20, 4, 100),
(21, 4, 102),
(28, 6, 10);

-- --------------------------------------------------------

--
-- Table structure for table `profile_pictures`
--

CREATE TABLE `profile_pictures` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `picture_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `question_id` int(11) NOT NULL,
  `quiz_id` int(11) DEFAULT NULL,
  `question_type` enum('multiple_choice','enumeration','true_or_false','fill_in_the_blanks','matching_type','drag_and_drop') DEFAULT NULL,
  `question_text` text NOT NULL,
  `left_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`left_items`)),
  `right_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`right_items`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`question_id`, `quiz_id`, `question_type`, `question_text`, `left_items`, `right_items`) VALUES
(36, 36, NULL, 'aa', NULL, NULL),
(37, 37, NULL, 'e', NULL, NULL),
(38, 38, NULL, 'a', NULL, NULL),
(39, 39, NULL, 'sa', NULL, NULL),
(40, 40, NULL, 'd', NULL, NULL),
(41, 41, NULL, 'd', NULL, NULL),
(42, 42, NULL, 'd', NULL, NULL),
(43, 43, NULL, 's', NULL, NULL),
(44, 44, NULL, 's', NULL, NULL),
(45, 45, NULL, 'd', NULL, NULL),
(46, 46, NULL, 'e', NULL, NULL),
(47, 47, NULL, 'vvf', NULL, NULL),
(48, 48, NULL, 'xx', NULL, NULL),
(49, 49, NULL, 'xx', NULL, NULL),
(50, 50, NULL, 'd', NULL, NULL),
(51, 51, NULL, 'dd', NULL, NULL),
(52, 52, NULL, 'ss', NULL, NULL),
(53, 53, NULL, 'ss', NULL, NULL),
(54, 54, NULL, 'd', NULL, NULL),
(55, 55, NULL, 's', NULL, NULL),
(56, 56, NULL, 's', NULL, NULL),
(57, 57, NULL, 's', NULL, NULL),
(58, 58, NULL, 's', NULL, NULL),
(59, 59, NULL, 's', NULL, NULL),
(60, 60, NULL, 's', NULL, NULL),
(61, 61, NULL, 'd', NULL, NULL),
(62, 62, NULL, 'cxz', NULL, NULL),
(63, 63, NULL, 'sas', NULL, NULL),
(64, 64, NULL, 'sas', NULL, NULL),
(65, 65, NULL, 'sss', NULL, NULL),
(66, 66, NULL, 'ddsd', NULL, NULL),
(67, 67, NULL, 'ss', NULL, NULL),
(68, 68, NULL, 'ss', NULL, NULL),
(69, 69, NULL, 'ww', NULL, NULL),
(70, 70, NULL, 'djdk', NULL, NULL),
(71, 71, NULL, 'djdk', NULL, NULL),
(72, 72, NULL, 'sksj', NULL, NULL),
(73, 73, NULL, 'ss', NULL, NULL),
(74, 74, NULL, 'assa', NULL, NULL),
(75, 75, NULL, 'try', NULL, NULL),
(76, 76, NULL, 'ew', NULL, NULL),
(77, 77, NULL, 'sj', NULL, NULL),
(78, 78, NULL, 'sj', NULL, NULL),
(79, 79, NULL, 'sj', NULL, NULL),
(80, 80, NULL, 'sj', NULL, NULL),
(81, 81, NULL, 'ada', NULL, NULL),
(82, 82, NULL, 's', NULL, NULL),
(83, 83, NULL, 'wew', NULL, NULL),
(84, 84, NULL, 'wew', NULL, NULL),
(85, 85, NULL, 'd', NULL, NULL),
(86, 86, NULL, 'sas', NULL, NULL),
(87, 87, NULL, 'hsjash', NULL, NULL),
(88, 88, NULL, 's', NULL, NULL),
(89, 89, NULL, 'd', NULL, NULL),
(90, 90, NULL, 'd', NULL, NULL),
(91, 91, NULL, 's', NULL, NULL),
(92, 92, NULL, 'd', NULL, NULL),
(93, 93, NULL, 's', NULL, NULL),
(94, 94, NULL, 'ss', NULL, NULL),
(95, 95, NULL, 'ss', NULL, NULL),
(96, 96, NULL, 'd', NULL, NULL),
(97, 97, NULL, 'a', NULL, NULL),
(98, 98, NULL, 'w', NULL, NULL),
(99, 99, NULL, 's', NULL, NULL),
(100, 100, NULL, 's', NULL, NULL),
(101, 101, NULL, 's', NULL, NULL),
(102, 102, NULL, 'aa', NULL, NULL),
(103, 103, NULL, 'sss', NULL, NULL),
(104, 104, NULL, 'z', NULL, NULL),
(105, 105, NULL, 'try', NULL, NULL),
(106, 106, NULL, 'try', NULL, NULL),
(107, 107, NULL, 'try', NULL, NULL),
(108, 108, NULL, 'try', NULL, NULL),
(109, 109, NULL, 'try', NULL, NULL),
(110, 110, NULL, 'try', NULL, NULL),
(111, 111, NULL, 'try', NULL, NULL),
(112, 112, NULL, 'try', NULL, NULL),
(113, 113, NULL, 'try', NULL, NULL),
(114, 114, NULL, 'try', NULL, NULL),
(115, 115, NULL, 'try', NULL, NULL),
(116, 116, NULL, 'try', NULL, NULL),
(117, 117, NULL, 'try', NULL, NULL),
(118, 118, NULL, 'try', NULL, NULL),
(119, 119, NULL, 'try', NULL, NULL),
(120, 120, NULL, 'try', NULL, NULL),
(121, 121, NULL, 'try', NULL, NULL),
(122, 122, NULL, 'try', NULL, NULL),
(123, 123, NULL, 'try', NULL, NULL),
(124, 124, NULL, 'try', NULL, NULL),
(125, 125, NULL, 'try', NULL, NULL),
(126, 126, NULL, 'try', NULL, NULL),
(127, 127, NULL, 'try', NULL, NULL),
(128, 128, NULL, 'try', NULL, NULL),
(129, 129, NULL, 'try', NULL, NULL),
(130, 130, NULL, 'try', NULL, NULL),
(131, 131, NULL, 'try', NULL, NULL),
(132, 132, NULL, 'try', NULL, NULL),
(133, 133, NULL, 'try', NULL, NULL),
(134, 134, NULL, 'try', NULL, NULL),
(135, 135, NULL, 'try', NULL, NULL),
(136, 136, NULL, 'try', NULL, NULL),
(137, 137, NULL, 'try', NULL, NULL),
(138, 138, NULL, 'try', NULL, NULL),
(139, 139, NULL, 'try', NULL, NULL),
(140, 140, NULL, 'try', NULL, NULL),
(141, 141, NULL, 'try', NULL, NULL),
(142, 142, NULL, 'try', NULL, NULL),
(143, 143, NULL, 'try', NULL, NULL),
(144, 144, NULL, 'try', NULL, NULL),
(145, 145, NULL, 'try', NULL, NULL),
(146, 146, NULL, 'try', NULL, NULL),
(147, 147, NULL, 'try', NULL, NULL),
(148, 148, NULL, 'try', NULL, NULL),
(149, 149, NULL, 'try', NULL, NULL),
(150, 150, NULL, 'try', NULL, NULL),
(151, 151, NULL, 'try', NULL, NULL),
(152, 152, NULL, 'try', NULL, NULL),
(153, 153, NULL, 'try', NULL, NULL),
(154, 154, NULL, 'try', NULL, NULL),
(155, 155, NULL, 'try', NULL, NULL),
(156, 156, NULL, 'try', NULL, NULL),
(157, 157, NULL, 'try', NULL, NULL),
(158, 158, NULL, 'e', NULL, NULL),
(159, 159, NULL, 'e', NULL, NULL),
(160, 160, NULL, 'e', NULL, NULL),
(161, 160, NULL, 'e', NULL, NULL),
(162, 161, NULL, 'e', NULL, NULL),
(163, 161, NULL, 'e', NULL, NULL),
(164, 162, NULL, 'ee', NULL, NULL),
(165, 162, NULL, 'ee', NULL, NULL),
(166, 163, NULL, 'ss', NULL, NULL),
(167, 163, NULL, 'ss', NULL, NULL),
(168, 170, NULL, 'ee', NULL, NULL),
(169, 170, NULL, 'ee', NULL, NULL),
(170, 171, NULL, 'ee', NULL, NULL),
(171, 171, NULL, 'ee', NULL, NULL),
(172, 172, NULL, 'ee', NULL, NULL),
(173, 172, NULL, 'ee', NULL, NULL),
(174, 173, NULL, 'ee', NULL, NULL),
(175, 173, NULL, 'ee', NULL, NULL),
(176, 174, NULL, 'ee', NULL, NULL),
(177, 174, NULL, 'ee', NULL, NULL),
(178, 175, NULL, 'ee', NULL, NULL),
(179, 175, NULL, 'ee', NULL, NULL),
(180, 176, NULL, 'gg', NULL, NULL),
(181, 176, NULL, 'gg', NULL, NULL),
(182, 176, NULL, 'ggg', NULL, NULL),
(183, 176, NULL, 'hhh', NULL, NULL),
(184, 176, NULL, 'ggg', NULL, NULL),
(185, 176, NULL, 'gg', NULL, NULL),
(186, 176, NULL, 'ggg', NULL, NULL),
(187, 176, NULL, 'hhh', NULL, NULL),
(188, 176, NULL, 'hhh', NULL, NULL),
(189, 176, NULL, 'gg', NULL, NULL),
(190, 176, NULL, 'ggg', NULL, NULL),
(191, 176, NULL, 'hhh', NULL, NULL),
(192, 177, NULL, 'try', NULL, NULL),
(193, 177, NULL, 'try', NULL, NULL),
(194, 178, NULL, 'q1', NULL, NULL),
(195, 178, NULL, 'q1', NULL, NULL),
(196, 179, NULL, 'q1', NULL, NULL),
(197, 179, NULL, 'q1', NULL, NULL),
(198, 180, NULL, 'q1', NULL, NULL),
(199, 180, NULL, 'q1', NULL, NULL),
(200, 181, NULL, 'q1', NULL, NULL),
(201, 181, NULL, 'q1', NULL, NULL),
(202, 182, NULL, 'q1', NULL, NULL),
(203, 182, NULL, 'q1', NULL, NULL),
(204, 183, NULL, 'q1', NULL, NULL),
(205, 183, NULL, 'q1', NULL, NULL),
(206, 184, NULL, 'q1', NULL, NULL),
(207, 184, NULL, 'q1', NULL, NULL),
(208, 185, NULL, 'q1', NULL, NULL),
(209, 185, NULL, 'q1', NULL, NULL),
(210, 222, NULL, 'try', NULL, NULL),
(211, 222, NULL, 'try', NULL, NULL),
(212, 223, NULL, 'try', NULL, NULL),
(213, 223, NULL, 'try', NULL, NULL),
(214, 224, NULL, 'tryyy', NULL, NULL),
(215, 224, NULL, 'tryyy', NULL, NULL),
(216, 225, NULL, 'tryy', NULL, NULL),
(217, 225, NULL, 'tryy', NULL, NULL),
(218, 226, NULL, 'try', NULL, NULL),
(219, 226, NULL, 'try', NULL, NULL),
(220, 227, NULL, 'tryy', NULL, NULL),
(221, 227, NULL, 'tryy', NULL, NULL),
(222, 228, NULL, 'try', NULL, NULL),
(223, 228, NULL, 'try', NULL, NULL),
(224, 229, NULL, 'try', NULL, NULL),
(225, 229, NULL, 'try', NULL, NULL),
(226, 230, NULL, 'try', NULL, NULL),
(227, 230, NULL, 'aaa', NULL, NULL),
(228, 231, NULL, 'try', NULL, NULL),
(229, 232, NULL, 'try', NULL, NULL),
(230, 233, NULL, 'try', NULL, NULL),
(231, 234, NULL, 'try', NULL, NULL),
(232, 234, NULL, 'try', NULL, NULL),
(233, 235, NULL, 'try', NULL, NULL),
(234, 236, NULL, 'try', NULL, NULL),
(235, 237, NULL, 'try', NULL, NULL),
(236, 238, NULL, 'try', NULL, NULL),
(237, 239, NULL, 'try', NULL, NULL),
(238, 240, NULL, 'try', NULL, NULL),
(239, 241, NULL, 'try', NULL, NULL),
(240, 241, NULL, 'try', NULL, NULL),
(241, 242, NULL, 'try', NULL, NULL),
(242, 242, NULL, 'try', NULL, NULL),
(243, 243, NULL, 'try', NULL, NULL),
(244, 244, NULL, 'try', NULL, NULL),
(245, 244, NULL, 'try', NULL, NULL),
(246, 245, NULL, 'try', NULL, NULL),
(247, 245, NULL, 'try', NULL, NULL),
(248, 246, NULL, 'try 33', NULL, NULL),
(249, 246, NULL, 'try 33', NULL, NULL),
(250, 247, NULL, 'try 34', NULL, NULL),
(251, 248, NULL, 'try35', NULL, NULL),
(252, 248, NULL, 'try35', NULL, NULL),
(253, 249, NULL, 'try35', NULL, NULL),
(254, 249, NULL, 'try35', NULL, NULL),
(255, 250, NULL, 'try 36', NULL, NULL),
(256, 250, NULL, 'try 36', NULL, NULL),
(257, 251, NULL, 'try 37', NULL, NULL),
(258, 252, NULL, 'tryy', NULL, NULL),
(259, 252, NULL, 'tryyy', NULL, NULL),
(260, 252, NULL, 'tryyy', NULL, NULL),
(261, 253, NULL, 'try 39', NULL, NULL),
(262, 254, NULL, 'aa', NULL, NULL),
(263, 255, NULL, 'aaa', NULL, NULL),
(264, 256, NULL, 'ff', NULL, NULL),
(265, 257, NULL, 'ee', NULL, NULL),
(266, 258, NULL, 'What is ano?', NULL, NULL),
(267, 259, NULL, 'true ba?', NULL, NULL),
(268, 260, NULL, '4', NULL, NULL),
(269, 261, NULL, 'e', NULL, NULL),
(270, 262, NULL, 'e', NULL, NULL),
(271, 263, NULL, 'aa', NULL, NULL),
(272, 264, NULL, 'aa', NULL, NULL),
(273, 265, NULL, 'what is the three layers of the earth?', NULL, NULL),
(274, 266, NULL, 'aa', NULL, NULL),
(275, 267, NULL, 'aa', NULL, NULL),
(276, 268, NULL, 'e', NULL, NULL),
(277, 269, NULL, 'multiple choice', NULL, NULL),
(278, 270, NULL, 'aaa', NULL, NULL),
(279, 271, NULL, 'tryy', NULL, NULL),
(280, 272, NULL, 'tryy', NULL, NULL),
(281, 273, NULL, 'aa', NULL, NULL),
(282, 274, NULL, 'tryyy', NULL, NULL),
(283, 275, NULL, 'aaa', NULL, NULL),
(284, 276, NULL, 'a', NULL, NULL),
(285, 277, NULL, 'aaa', NULL, NULL),
(286, 278, NULL, 'aaaa', NULL, NULL),
(287, 279, NULL, 'aaa', NULL, NULL),
(288, 280, NULL, 'aaa', NULL, NULL),
(289, 281, NULL, 'aaaa', NULL, NULL),
(290, 282, NULL, 'aaaa', NULL, NULL),
(291, 283, NULL, 'aaa', NULL, NULL),
(292, 284, NULL, 'qaaa', NULL, NULL),
(293, 285, NULL, 'tryyy', NULL, NULL),
(294, 286, NULL, 'aaa', NULL, NULL),
(295, 287, NULL, 'aaaa', NULL, NULL),
(296, 288, NULL, 'aaaa', NULL, NULL),
(297, 289, NULL, 'aa', NULL, NULL),
(298, 290, NULL, 'aaa', NULL, NULL),
(299, 291, NULL, 'aaaa', NULL, NULL),
(300, 292, NULL, 'aaaa', NULL, NULL),
(301, 293, NULL, 'aaaaa', NULL, NULL),
(302, 294, NULL, 'aaaa', NULL, NULL),
(303, 295, NULL, 'aaaa', NULL, NULL),
(304, 296, NULL, 'aaaa', NULL, NULL),
(305, 297, NULL, 'aaaa', NULL, NULL),
(306, 298, NULL, 'aaaaa', NULL, NULL),
(307, 299, NULL, '3', NULL, NULL),
(308, 300, NULL, 'aaaa', NULL, NULL),
(309, 300, NULL, 'sanaaaa', NULL, NULL),
(310, 301, NULL, 'What is the fourth letter in the alphabet?', NULL, NULL),
(311, 302, NULL, 'aaaaa', NULL, NULL),
(312, 303, NULL, 'aaaaa', NULL, NULL),
(313, 303, NULL, 'sdsdsda', NULL, NULL),
(314, 304, NULL, 'what shape that has four sides? ', NULL, NULL),
(315, 305, NULL, 'aaaaa', NULL, NULL),
(316, 306, NULL, 'ssss', NULL, NULL),
(317, 307, NULL, 'what is the third planet in the solar system?', NULL, NULL),
(318, 308, NULL, 'aaaa', NULL, NULL),
(319, 309, NULL, 'd', NULL, NULL),
(320, 310, NULL, 's', NULL, NULL),
(321, 310, NULL, 's', NULL, NULL),
(322, 310, NULL, 's', NULL, NULL),
(323, 311, NULL, 'bakit sha ganon?', NULL, NULL),
(324, 312, NULL, 'bakit ano ____________', NULL, NULL),
(325, 313, NULL, 'si ano ay ano', NULL, NULL),
(326, 314, NULL, 'sino sino sila?', NULL, NULL),
(327, 321, NULL, 'bakit ano ____________', NULL, NULL),
(328, 322, NULL, 'bakit ano ____________', NULL, NULL),
(329, 323, NULL, 'bakit ano ____________', NULL, NULL),
(330, 327, NULL, 'bakit ano ____________', NULL, NULL),
(334, 337, NULL, 'gfndgnf___________', NULL, NULL),
(336, 339, NULL, 'gfndgnf___________', NULL, NULL),
(337, 340, NULL, 'gfndgnf___________', NULL, NULL),
(338, 341, NULL, 'bakit ano ____________', NULL, NULL),
(339, 342, NULL, 'bakit ano?', NULL, NULL),
(340, 342, NULL, 'sino sino ba sila?', NULL, NULL),
(341, 342, NULL, 'gfndgnf___________', NULL, NULL),
(342, 342, NULL, 'bakit ano', NULL, NULL),
(343, 342, NULL, 'gngng', NULL, NULL),
(344, 343, NULL, 'totoo ba', NULL, NULL),
(345, 344, NULL, 'si ano ay ano daw', NULL, NULL),
(346, 345, NULL, 'totoo ba na ano ung ano?', NULL, NULL),
(347, 346, NULL, 'si ano ay ano daaw', NULL, NULL),
(348, 347, NULL, 'bakit ano___________', NULL, NULL),
(349, 348, NULL, 'bakit ganon sila?', NULL, NULL),
(350, 348, NULL, 'sino sino ba sila?', NULL, NULL),
(351, 348, NULL, 'dcbjkdsbvkj_____mcbfdjk', NULL, NULL),
(352, 348, NULL, 'sino ba kase', NULL, NULL),
(353, 349, NULL, 'bakit ganon sila?', NULL, NULL),
(354, 349, NULL, 'sino sino ba sila?', NULL, NULL),
(355, 349, NULL, 'dcbjkdsbvkj_____mcbfdjk', NULL, NULL),
(356, 349, NULL, 'sino ba kase', NULL, NULL),
(357, 350, NULL, 'bakit ano ____________', NULL, NULL),
(358, 352, NULL, 'gfndgnf___________', NULL, NULL),
(359, 353, NULL, 'si ano ay ano ni ano?', NULL, NULL),
(360, 353, NULL, 'sino sino ba sila?', NULL, NULL),
(361, 353, NULL, 'bakit ano___________', NULL, NULL),
(362, 353, NULL, 'totoo ba na ano?', NULL, NULL),
(363, 354, NULL, 'si ano ay ano daw', NULL, NULL),
(364, 355, NULL, 'si ano ay ano daw', NULL, NULL),
(365, 356, NULL, 'si ano ay ano daw', NULL, NULL),
(366, 357, NULL, 'si ano ay ano daw', NULL, NULL),
(367, 358, NULL, 'si ano ay ano daw', NULL, NULL),
(368, 359, NULL, 'sino sino ba sila?', NULL, NULL),
(369, 360, NULL, 'ka ano ano ni ano si ano?', NULL, NULL),
(370, 360, NULL, 'sino sino ba kase sila?', NULL, NULL),
(371, 360, NULL, 'Cavite _________ University', NULL, NULL),
(372, 360, NULL, 'Ipinangalan ang lungsod ng trece martires sa labintatlong martir ng cavite', NULL, NULL),
(373, 360, NULL, 'wala akong maisip na tanong', NULL, NULL),
(374, 360, NULL, 'bakit ano', NULL, NULL),
(375, 361, NULL, 'bakit ano', NULL, NULL),
(376, 362, NULL, 'bakit ano', NULL, NULL),
(377, 363, NULL, 'bakit ano', '[]', '[]'),
(379, 365, NULL, 'gngng', '[\"674533df58182_pie graph 1.png\"]', '[\"674533df5876f_pie graph.png\"]'),
(380, 366, NULL, 'gngng', '[\"674536f10c350_adminItemAnalysis 6.png\"]', '[\"674536f10c87e_pie graph.png\"]'),
(381, 367, NULL, 'gngng', '[\"67453ba0d03d3_createQuizMultiple.png\",\"67453ba0d0685_adminItemAnalysis 6.png\",\"67453ba0d0979_adminItemAnalysis 5.png\"]', '[\"67453ba0d0bc8_pie graph 1.png\",\"67453ba0d116a_pie graph.png\",\"67453ba0d12e3_userList-teachers.png\"]'),
(382, 368, NULL, 'bakit ano', NULL, NULL),
(383, 369, 'enumeration', 'sino sino ba sila?', NULL, NULL),
(384, 370, NULL, 'gngng', NULL, NULL),
(387, 373, 'matching_type', 'sino sino ba sila?', '[\"6745a7d3cc991_010D949B-3F2D-4959-915D-E5E447E877C5.JPG\",\"6745a7d3cd05f_1606409826517.jpg\",\"6745a7d3cd5cb_A82C5257-DF61-4E88-9475-8E7D51FE917E.JPG\"]', '[\"6745a7d3cdada_IMG_1443.jpg\",\"6745a7d3ce01c_IMG_1448.jpg\",\"6745a7d3ce38d_IMG_1454.jpg\",\"6745a7d3ce6e2_IMG_1457.jpg\",\"6745a7d3ce87c_IMG_1463.jpg\"]'),
(388, 374, 'enumeration', 'sino sino ba sila?', NULL, NULL),
(389, 375, 'enumeration', 'bakit ano', NULL, NULL),
(390, 376, 'enumeration', 'bakit ano', NULL, NULL),
(391, 377, NULL, 'gngng', NULL, NULL),
(392, 378, 'enumeration', 'sino sino ba sila?', NULL, NULL),
(393, 379, 'enumeration', 'sino sino ba sila?', NULL, NULL),
(394, 380, 'enumeration', 'sino sino ba sila?', NULL, NULL),
(395, 381, NULL, 'ang ano n ano', NULL, NULL),
(396, 381, NULL, 'gngng', NULL, NULL),
(397, 382, NULL, 'gfndgnf___________', NULL, NULL),
(398, 383, NULL, 'gfndgnf___________', NULL, NULL),
(399, 384, NULL, 'gfndgnf___________', NULL, NULL),
(400, 385, NULL, 'bakit ano ____________', NULL, NULL),
(401, 386, NULL, 'bakit ano', NULL, NULL),
(402, 387, NULL, 'bakit ano', NULL, NULL),
(403, 388, NULL, 'bakit ano', NULL, NULL),
(404, 389, NULL, 'bakit ano', NULL, NULL),
(405, 390, NULL, 'bakit ano', NULL, NULL),
(406, 391, NULL, 'bakit ano', NULL, NULL),
(407, 392, NULL, 'bakit ano', NULL, NULL),
(408, 393, NULL, 'bakit ano', NULL, NULL),
(409, 394, NULL, 'bakit ano', NULL, NULL),
(410, 395, NULL, 'bakit ano', NULL, NULL),
(411, 396, NULL, 'bakit ano', NULL, NULL),
(412, 397, NULL, 'bakit ano', NULL, NULL),
(413, 398, NULL, 'bakit ano', NULL, NULL),
(414, 399, NULL, 'bakit ano', NULL, NULL),
(415, 400, NULL, 'bakit ano', NULL, NULL),
(416, 401, NULL, 'bakit ano', NULL, NULL),
(417, 402, NULL, 'bakit ano', NULL, NULL),
(418, 403, NULL, 'bakit ano', NULL, NULL),
(419, 404, NULL, 'bakit ano', NULL, NULL),
(420, 405, NULL, 'bakit ano', NULL, NULL),
(421, 406, NULL, 'bakit ano', NULL, NULL),
(422, 407, NULL, 'bakit ano', NULL, NULL),
(423, 408, NULL, 'bakit ano', NULL, NULL),
(424, 409, NULL, 'bakit ano', NULL, NULL),
(425, 410, NULL, 'bakit ano___________', NULL, NULL),
(426, 411, NULL, 'bakit ano___________', NULL, NULL),
(427, 412, NULL, 'bakit ano___________', NULL, NULL),
(428, 413, NULL, 'bakit ano', NULL, NULL),
(429, 414, NULL, 'bakit ano ____________', NULL, NULL),
(430, 415, NULL, 'gfndgnf___________', NULL, NULL),
(431, 416, NULL, 'gfndgnf___________', NULL, NULL),
(432, 417, NULL, 'gfndgnf___________', NULL, NULL),
(433, 418, NULL, 'gfndgnf___________', NULL, NULL),
(434, 419, NULL, 'gfndgnf', NULL, NULL),
(435, 420, NULL, 'gfndgnf', NULL, NULL),
(436, 421, NULL, 'gfndgnf___________', NULL, NULL),
(437, 422, NULL, 'gfndgnf___________', NULL, NULL),
(438, 423, NULL, 'gfndgnf___________', NULL, NULL),
(439, 424, NULL, 'sino sino ba sila?', NULL, NULL),
(440, 425, NULL, 'sino sino ba sila?', NULL, NULL),
(441, 426, NULL, 'sino sino ba sila?', NULL, NULL),
(442, 427, NULL, 'sino sino ba sila?', NULL, NULL),
(443, 428, NULL, 'bakit ano', NULL, NULL),
(444, 429, NULL, 'gfndgnf___________', NULL, NULL),
(445, 430, NULL, 'gfndgnf___________', NULL, NULL),
(446, 431, NULL, 'vsfvfvfsvf', NULL, NULL),
(447, 432, NULL, 'bakit ano ____________', NULL, NULL),
(448, 433, NULL, 'ano si ano', NULL, NULL),
(449, 434, NULL, 'ano si ano', NULL, NULL),
(450, 435, NULL, 'ano si ano', NULL, NULL),
(451, 436, NULL, 'gngng', NULL, NULL),
(452, 437, NULL, 'bakit ano ', NULL, NULL),
(453, 438, NULL, 'bakit ano', NULL, NULL),
(454, 439, NULL, 'sino sino ba sila?', NULL, NULL),
(455, 440, NULL, 'bakit ano', NULL, NULL),
(456, 441, NULL, 'gngng', NULL, NULL),
(457, 442, NULL, 'sdvsvsvs_______', NULL, NULL),
(458, 443, 'multiple_choice', 'bakit ano ?', NULL, NULL),
(459, 443, 'matching_type', 'gngng', '[\"6749bfb397059_010D949B-3F2D-4959-915D-E5E447E877C5.JPG\",\"6749bfb39723c_1606409826517.jpg\",\"6749bfb3973ba_A82C5257-DF61-4E88-9475-8E7D51FE917E.JPG\"]', '[\"6749bfb397542_IMG_1422.jpg\",\"6749bfb397724_IMG_1431.jpg\",\"6749bfb397884_IMG_1434.jpg\"]'),
(460, 444, 'true_or_false', 'totoo ba na ano ung ano?', NULL, NULL),
(461, 444, 'fill_in_the_blanks', 'bakit ano ____________?', NULL, NULL),
(462, 444, 'drag_and_drop', 'bakit ano yan sha', NULL, NULL),
(463, 445, 'multiple_choice', 'bakit ba?', NULL, NULL),
(464, 445, 'true_or_false', 'totoo ba?', NULL, NULL),
(465, 445, 'enumeration', 'sino sino ba sila?', NULL, NULL),
(466, 445, 'fill_in_the_blanks', 'bakit ano___________', NULL, NULL),
(472, 451, 'drag_and_drop', 'bakit ano', NULL, NULL),
(475, 454, 'drag_and_drop', 'gngng', NULL, NULL),
(476, 455, 'drag_and_drop', 'sino sino ba sila?', NULL, NULL),
(477, 456, 'drag_and_drop', 'bakit ano', NULL, NULL),
(478, 457, 'drag_and_drop', 'bakit ano', NULL, NULL),
(479, 458, 'drag_and_drop', 'gumana ka na', NULL, NULL),
(480, 459, 'drag_and_drop', 'sino sino ba sila?', NULL, NULL),
(481, 460, 'drag_and_drop', 'bakit ano', NULL, NULL),
(482, 461, 'multiple_choice', 'bakit ano?', NULL, NULL),
(483, 462, 'multiple_choice', 'multiple', NULL, NULL),
(484, 463, 'multiple_choice', 'gngng', NULL, NULL),
(485, 464, 'multiple_choice', 'The composition of sequences of instructions, called programs, that computers can follow to perform tasks', NULL, NULL),
(486, 464, 'enumeration', 'What are the 4 basic operations of programming?', NULL, NULL),
(487, 464, 'fill_in_the_blanks', 'HTML stands for HyperText ___________ Language', NULL, NULL),
(488, 464, 'true_or_false', 'HTML and CSS are not considered to be programming languages', NULL, NULL),
(489, 464, 'drag_and_drop', 'The addition assignment operator performs addition', NULL, NULL),
(490, 464, 'matching_type', 'Computer Programming', '[\"674ca1d3f08e1_images (1).jpeg\",\"674ca1d3f0b5c_images.jpeg\"]', '[\"674ca1d3f0cd9_what-computer-programming-jobs-offer-remote-work-jpg.webp\",\"674ca1d3f0f78_7200.webp\"]'),
(491, 465, NULL, 'bakit ano ____________', NULL, NULL),
(492, 466, NULL, 'bakit ano', NULL, NULL),
(493, 467, 'fill_in_the_blanks', 'bakit ano ____________', NULL, NULL),
(494, 468, 'true_or_false', 'gngng', NULL, NULL),
(495, 469, 'true_or_false', 'gngng', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `question_options`
--

CREATE TABLE `question_options` (
  `option_id` int(11) NOT NULL,
  `question_id` int(11) DEFAULT NULL,
  `option_text` text NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `question_options`
--

INSERT INTO `question_options` (`option_id`, `question_id`, `option_text`, `is_correct`) VALUES
(1, 339, 'ewan', 0),
(2, 339, 'ano', 0),
(3, 339, 'kahit', 0),
(4, 339, 'oo', 0),
(5, 349, 'kase ano', 0),
(6, 349, 'ano siguro', 0),
(7, 349, 'ewan ko rin', 0),
(8, 349, 'oo', 0),
(9, 353, 'kase ano', 0),
(10, 353, 'ano siguro', 0),
(11, 353, 'ewan ko rin', 0),
(12, 353, 'oo', 0),
(13, 359, 'ewan', 0),
(14, 359, 'ano', 0),
(15, 359, 'kahit ano', 0),
(16, 359, 'oo', 0),
(17, 369, 'ewan', 0),
(18, 369, 'kapatid', 0),
(19, 369, 'kaibigan', 0),
(20, 369, 'nanay', 0),
(21, 458, 'ewan', 0),
(22, 458, 'kase ano', 0),
(23, 458, 'siguro', 0),
(24, 458, 'ewan', 0),
(25, 463, 'ewan', 0),
(26, 463, 'kase ano', 0),
(27, 463, 'siguro', 0),
(28, 463, 'haha', 0),
(29, 482, 'ewan', 0),
(30, 482, 'kase ano', 0),
(31, 482, 'ano', 0),
(32, 482, 'anooooooo', 0);

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `quiz_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `timer` int(11) DEFAULT NULL,
  `quiz_type` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`quiz_id`, `title`, `subject_id`, `timer`, `quiz_type`) VALUES
(1, 'gf', 50, -1, 'multiple_choice'),
(2, 'Quiz 1', 99, 3, 'multiple_choice'),
(3, 'Quiz 2', 99, 4, 'true_false'),
(16, 'enum2', 99, 3, 'enumeration'),
(17, 'enum3', 99, 4, 'enumeration'),
(18, 'enum4', 99, 4, 'enumeration'),
(19, 'enum5', 99, 4, 'enumeration'),
(20, 'enum6', 99, 5, 'enumeration'),
(21, 'enum7', 99, 4, 'enumeration'),
(22, 'enum 8', 99, 5, 'enumeration'),
(23, 'enum 8', 99, 5, 'enumeration'),
(24, 'enum9', 99, 4, 'enumeration'),
(25, 'enum10', 99, 4, 'enumeration'),
(26, 'enum10', 99, 4, 'enumeration'),
(27, 'enum10', 99, 4, 'enumeration'),
(28, 'enum11', 99, 4, 'enumeration'),
(29, 'enum12', 99, 4, 'enumeration'),
(30, 'enum13', 99, 5, 'enumeration'),
(31, 'enum14', 99, 4, 'enumeration'),
(32, 'enum15', 99, 4, 'enumeration'),
(33, 'enum16', 99, 4, 'enumeration'),
(35, 'enum17', 99, 6, 'enumeration'),
(36, 'enum18', 99, 4, 'enumeration'),
(37, 'enum19', 99, 5, 'enumeration'),
(38, 'enum20', 99, 7, 'enumeration'),
(39, 'enum21', 99, 4, 'enumeration'),
(40, 'enum22', 99, 4, 'enumeration'),
(41, 'enum22', 99, 4, 'enumeration'),
(42, 'enum22', 99, 4, 'enumeration'),
(43, 'enum22', 99, 3, 'enumeration'),
(44, 'try', 99, 2, 'multiple_choice'),
(45, 'tryyy', 99, 4, 'true_false'),
(46, 'tryy', 99, 4, 'true_false'),
(47, 'tryy', 99, 4, 'true_false'),
(48, 'gg', 99, 4, 'true_false'),
(49, 'gg', 99, 4, 'true_false'),
(50, 'fff', 99, 3, 'multiple_choice'),
(51, 'dd', 99, 4, 'true_false'),
(52, 'qq', 99, 4, 'true_false'),
(53, 'aa', 99, 3, 'true_false'),
(54, 'try lang uli', 99, 4, 'true_false'),
(55, 'try lang', 99, 4, 'true_false'),
(56, 'enum try', 99, 3, 'enumeration'),
(57, 'enum try 2', 99, 3, 'enumeration'),
(58, 'enum try 3', 99, 4, 'enumeration'),
(59, 'tf try', 99, 3, 'true_false'),
(60, 'enum try 5`', 99, 4, 'enumeration'),
(61, 'tf try2', 99, 3, 'true_false'),
(62, 'jhdjd', 99, 4, 'true_false'),
(63, 'enum 23', 99, 3, 'enumeration'),
(64, 'enum 23', 99, 3, 'enumeration'),
(65, 'enum 24', 99, 3, 'enumeration'),
(66, 'enum 24', 99, 4, 'enumeration'),
(67, 'enum 25', 99, 4, 'enumeration'),
(68, 'enum 26', 99, 4, 'enumeration'),
(69, 'try', 99, 3, 'true_false'),
(70, 'enum 27', 99, 4, 'enumeration'),
(71, 'enum 27', 99, 4, 'enumeration'),
(72, 'enum 28', 99, 3, 'enumeration'),
(73, 'enum 29', 99, 4, 'enumeration'),
(74, 'enum 29', 99, 4, 'enumeration'),
(75, 'enum 30', 99, 4, 'enumeration'),
(76, 'enum 31', 99, 4, 'enumeration'),
(77, 'enum 31', 99, 3, 'enumeration'),
(78, 'enum 31', 99, 3, 'enumeration'),
(79, 'enum 31', 99, 3, 'enumeration'),
(80, 'enum 31', 99, 3, 'enumeration'),
(81, 'enum 33', 99, 3, 'enumeration'),
(82, 'enum 34', 99, 3, 'enumeration'),
(83, 'enum 35', 99, 3, 'enumeration'),
(84, 'enum 35', 99, 3, 'enumeration'),
(85, 'enum 36', 99, 4, 'enumeration'),
(86, 'enum 37', 99, 4, 'enumeration'),
(87, 'enum 38', 99, 3, 'enumeration'),
(88, 'enum 39', 99, 4, 'enumeration'),
(89, 'multiple', 99, 3, 'multiple_choice'),
(90, 'enum40', 99, 4, 'enumeration'),
(91, 'enum 40', 99, 4, 'enumeration'),
(92, 'enum 31', 99, 4, 'enumeration'),
(93, 'enum 42', 99, 3, 'enumeration'),
(94, 'enum 43', 99, 3, 'enumeration'),
(95, 'enum44', 99, 4, 'enumeration'),
(96, 'enum 35', 99, 4, 'enumeration'),
(97, 'enum 36', 99, 3, 'enumeration'),
(98, 'enum 36', 99, 12, 'enumeration'),
(99, 'enum 38', 99, 3, 'enumeration'),
(100, 'enum 38', 99, 3, 'enumeration'),
(101, 'enum 39', 99, 3, 'enumeration'),
(102, 'enum 40', 99, 4, 'enumeration'),
(103, 'enum 41', 99, 3, 'enumeration'),
(104, 'tf1', 99, 3, 'true_false'),
(105, 'enum 1', NULL, 4, 'enumeration'),
(106, 'enum 1', NULL, 3, 'enumeration'),
(107, 'enum 1', NULL, 3, 'enumeration'),
(108, 'enum 1', NULL, 3, 'enumeration'),
(109, 'enum 1', NULL, 3, 'enumeration'),
(110, 'enum 1', NULL, 3, 'enumeration'),
(111, 'enum 1', NULL, 3, 'enumeration'),
(112, 'enum 1', NULL, 3, 'enumeration'),
(113, 'enum 1', NULL, 3, 'enumeration'),
(114, 'enum 1', NULL, 3, 'enumeration'),
(115, 'enum 1', NULL, 3, 'enumeration'),
(116, 'enum 1', NULL, 3, 'enumeration'),
(117, 'enum 1', NULL, 3, 'enumeration'),
(118, 'enum 1', NULL, 3, 'enumeration'),
(119, 'enum 1', NULL, 3, 'enumeration'),
(120, 'enum 1', NULL, 3, 'enumeration'),
(121, 'enum 1', NULL, 3, 'enumeration'),
(122, 'enum 1', NULL, 3, 'enumeration'),
(123, 'enum 1', NULL, 3, 'enumeration'),
(124, 'enum 1', NULL, 3, 'enumeration'),
(125, 'enum 1', NULL, 3, 'enumeration'),
(126, 'enum 1', NULL, 3, 'enumeration'),
(127, 'enum 1', NULL, 3, 'enumeration'),
(128, 'enum 1', NULL, 3, 'enumeration'),
(129, 'enum 1', NULL, 3, 'enumeration'),
(130, 'enum 1', NULL, 3, 'enumeration'),
(131, 'enum 1', NULL, 3, 'enumeration'),
(132, 'enum 1', NULL, 3, 'enumeration'),
(133, 'enum 1', NULL, 3, 'enumeration'),
(134, 'enum 1', NULL, 3, 'enumeration'),
(135, 'enum 1', NULL, 3, 'enumeration'),
(136, 'enum 1', NULL, 3, 'enumeration'),
(137, 'enum 1', NULL, 3, 'enumeration'),
(138, 'enum 1', NULL, 3, 'enumeration'),
(139, 'enum 1', NULL, 3, 'enumeration'),
(140, 'enum 1', NULL, 3, 'enumeration'),
(141, 'enum 1', NULL, 3, 'enumeration'),
(142, 'enum 1', NULL, 3, 'enumeration'),
(143, 'enum 1', NULL, 3, 'enumeration'),
(144, 'enum 1', NULL, 3, 'enumeration'),
(145, 'enum 1', NULL, 3, 'enumeration'),
(146, 'enum 1', NULL, 3, 'enumeration'),
(147, 'enum 1', NULL, 3, 'enumeration'),
(148, 'enum 1', NULL, 3, 'enumeration'),
(149, 'enum 1', NULL, 3, 'enumeration'),
(150, 'enum 1', NULL, 3, 'enumeration'),
(151, 'enum 1', NULL, 3, 'enumeration'),
(152, 'enum 1', NULL, 3, 'enumeration'),
(153, 'enum 1', NULL, 3, 'enumeration'),
(154, 'enum 1', NULL, 3, 'enumeration'),
(155, 'enum 1', NULL, 3, 'enumeration'),
(156, 'enum 1', NULL, 3, 'enumeration'),
(157, 'enum 1', NULL, 3, 'enumeration'),
(158, 'enum try', 100, 3, 'enumeration'),
(159, 'enum try', 100, 3, 'enumeration'),
(160, 'enum try', 100, 3, 'enumeration'),
(161, 'enum try', 100, 3, 'enumeration'),
(162, 'enum 2', 100, 0, 'enumeration'),
(163, 'enum 3', 100, 4, 'enumeration'),
(170, 'enum 4', 100, 4, 'enumeration'),
(171, 'enum 4', 100, 4, 'enumeration'),
(172, 'enum 4', 100, 4, 'enumeration'),
(173, 'enum 4', 100, 4, 'enumeration'),
(174, 'enum 4', 100, 4, 'enumeration'),
(175, 'enum 4', 100, 4, 'enumeration'),
(176, 'enum', 100, 4, 'enumeration'),
(177, 'enum 5', 100, 4, 'enumeration'),
(178, 'enum 6', 100, 3, 'enumeration'),
(179, 'enum 6', 100, 3, 'enumeration'),
(180, 'enum 6', 100, 3, 'enumeration'),
(181, 'enum 6', 100, 3, 'enumeration'),
(182, 'enum 6', 100, 3, 'enumeration'),
(183, 'enum 6', 100, 3, 'enumeration'),
(184, 'enum 6', 100, 3, 'enumeration'),
(185, 'enum 6', 100, 3, 'enumeration'),
(187, 'enum 7', 100, 3, 'enumeration'),
(188, 'enum 7', 100, 3, 'enumeration'),
(189, 'enum 7', 100, 3, 'enumeration'),
(190, 'enum 7', 100, 3, 'enumeration'),
(191, 'enum 7', 100, 3, 'enumeration'),
(192, 'enum 7', 100, 3, 'enumeration'),
(193, 'enum 7', 100, 3, 'enumeration'),
(194, 'enum 7', 100, 3, 'enumeration'),
(195, 'enum 7', 100, 3, 'enumeration'),
(196, 'enum 7', 100, 3, 'enumeration'),
(197, 'enum 7', 100, 3, 'enumeration'),
(198, 'enum 8', 100, 4, 'enumeration'),
(199, 'enum9', 100, 3, 'enumeration'),
(200, 'enum9', 100, 3, 'enumeration'),
(201, 'enum9', 100, 3, 'enumeration'),
(202, 'enum9', 100, 3, 'enumeration'),
(203, 'enum9', 100, 3, 'enumeration'),
(204, 'enum9', 100, 3, 'enumeration'),
(205, 'enum9', 100, 3, 'enumeration'),
(206, 'enum9', 100, 3, 'enumeration'),
(207, 'enum9', 100, 3, 'enumeration'),
(208, 'enum9', 100, 3, 'enumeration'),
(209, 'enum9', 100, 3, 'enumeration'),
(210, 'enum9', 100, 3, 'enumeration'),
(211, 'enum9', 100, 3, 'enumeration'),
(212, 'enum9', 100, 3, 'enumeration'),
(213, 'enum9', 100, 3, 'enumeration'),
(214, 'enum9', 100, 3, 'enumeration'),
(215, 'enum9', 100, 3, 'enumeration'),
(216, 'enum9', 100, 3, 'enumeration'),
(217, 'enum9', 100, 3, 'enumeration'),
(218, 'enum9', 100, 3, 'enumeration'),
(219, 'enum9', 100, 3, 'enumeration'),
(220, 'enum9', 100, 3, 'enumeration'),
(221, 'enum9', 100, 3, 'enumeration'),
(222, 'enum10', 100, 2, 'enumeration'),
(223, 'enum 11', 100, 3, 'enumeration'),
(224, 'enum 12', 100, 4, 'enumeration'),
(225, 'enum 13', 100, 4, 'enumeration'),
(226, 'enum 14', 100, 3, 'enumeration'),
(227, 'enum 15', 100, 3, 'enumeration'),
(228, 'enum 16', 100, 4, 'enumeration'),
(229, 'enum 16', 100, 4, 'enumeration'),
(230, 'mc', 100, 4, 'multiple_choice'),
(231, 'enum 17', 100, 3, 'enumeration'),
(232, 'enum 18', 100, 3, 'enumeration'),
(233, 'enum 19', 100, 2, 'enumeration'),
(234, 'enum 20', 100, 2, 'enumeration'),
(235, 'enum 21', 100, 2, 'enumeration'),
(236, 'enum 22', 100, 3, 'enumeration'),
(237, 'enum 23', 100, 4, 'enumeration'),
(238, 'enum 24', 100, 2, 'enumeration'),
(239, 'enum 25', 100, 4, 'enumeration'),
(240, 'enu 27', 100, 3, 'enumeration'),
(241, 'enum 28', 100, 3, 'enumeration'),
(242, 'enum 29', 100, 3, 'enumeration'),
(243, 'enum 30', 100, 3, 'enumeration'),
(244, 'enum 31', 100, 3, 'enumeration'),
(245, 'enum 32', 100, 4, 'enumeration'),
(246, 'enum 33', 100, 2, 'enumeration'),
(247, 'enum 34', 100, 3, 'enumeration'),
(248, 'enum 35', 100, 2, 'enumeration'),
(249, 'enum 35', 100, 2, 'enumeration'),
(250, 'enum 36', 100, 4, 'enumeration'),
(251, 'enum 37', 100, 3, 'enumeration'),
(252, 'enum 38', 100, 3, 'enumeration'),
(253, 'enum 39', 100, 5, 'enumeration'),
(254, 'tf try', 100, 4, 'true_false'),
(255, 'mc', 100, 3, 'multiple_choice'),
(256, 't or f', 100, 3, 'true_false'),
(257, 'enum', 100, 3, 'enumeration'),
(258, 'Multiple Choice', 101, 3, 'multiple_choice'),
(259, 'True or False', 101, 4, 'true_false'),
(260, 'Yes ba or no', 101, 3, 'enumeration'),
(261, 'True or False Try', 101, 2, 'true_false'),
(262, 'True or False Try', 101, 2, 'true_false'),
(263, 'True or False ulet', 101, 2, 'true_false'),
(264, 'multiple choice ulet', 101, 3, 'multiple_choice'),
(265, 'Enumeration', 101, 2, 'enumeration'),
(266, 'true or false try', 98, 3, 'true_false'),
(267, 'true or false ulet', 100, 3, 'true_false'),
(268, 'ww', 75, 3, 'multiple_choice'),
(269, 'try', 75, 3, 'multiple_choice'),
(270, 'try ulet', 75, 3, 'multiple_choice'),
(271, 'enum try', 75, 3, 'enumeration'),
(272, 'enum try', 75, 2, 'enumeration'),
(273, 'multiple Choice', 75, 3, 'multiple_choice'),
(274, 'tryyy', 75, 2, 'enumeration'),
(275, 'enum try ulet na naman', 75, 3, 'enumeration'),
(276, 'multiple choice ulet', 75, 3, 'multiple_choice'),
(277, 'multiple choice na naman ', 75, 3, 'multiple_choice'),
(278, 'multiple choice na naman ulet', 75, 3, 'multiple_choice'),
(279, 'true or false na naman', 75, 3, 'true_false'),
(280, 'enum ulet', 75, 3, 'enumeration'),
(281, 'enum ulet', 75, 2, 'enumeration'),
(282, 'multiple Choice', 100, 2, 'multiple_choice'),
(299, 'Quiz 3 Enum ', 100, 3, 'Enumeration'),
(304, 'Quiz  1', 102, 3, 'Multiple Choice'),
(305, 'quiz 2', 102, 3, 'True or False'),
(306, 'quiz 3', 102, 3, 'Enumeration'),
(307, 'quiz 4 (Multiple Choice)', 102, 2, 'Multiple Choice'),
(310, 'quiz try', 102, 2, 'True or False'),
(311, 'multiple choice', 10, 1, 'Multiple Choice'),
(312, 'fill in the blanks', 10, 1, 'Fill in the Blanks'),
(313, 'True or false', 10, 1, 'True or False'),
(444, 'All Zapped', 10, 1, 'All Zapped'),
(445, 'try all', 10, 1, 'All Zapped'),
(463, 'multiple', 10, 1, 'All Zapped'),
(464, 'All Zapped', 10, 1, 'All Zapped');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_attempts`
--

CREATE TABLE `quiz_attempts` (
  `attempt_id` int(11) NOT NULL,
  `quiz_id` int(11) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_attempts`
--

INSERT INTO `quiz_attempts` (`attempt_id`, `quiz_id`, `account_number`, `score`, `attempt_time`) VALUES
(17, 255, 'S001', 0, '2024-09-05 14:22:48'),
(18, 255, 'S001', 0, '2024-09-05 14:22:55'),
(19, 255, 'S001', 0, '2024-09-05 14:23:00'),
(20, 255, 'S001', 1, '2024-09-05 14:24:08'),
(21, 256, 'S001', 1, '2024-09-05 14:24:31'),
(22, 257, 'S001', 0, '2024-09-05 14:25:00'),
(23, 257, 'S001', 1, '2024-09-05 14:25:16'),
(24, 258, 'S001', 0, '2024-09-05 14:31:28'),
(25, 258, 'S001', 1, '2024-09-05 14:31:34'),
(26, 259, 'S001', 1, '2024-09-05 14:31:40'),
(27, 259, 'S001', 0, '2024-09-05 14:31:44'),
(28, 260, 'S001', 1, '2024-09-05 14:31:56'),
(29, 263, 'S001', 0, '2024-09-05 15:57:31'),
(30, 264, 'S001', 1, '2024-09-05 15:57:38'),
(31, 265, 'S001', 0, '2024-09-05 15:58:03'),
(32, 267, 'S001', 0, '2024-09-05 16:48:24'),
(33, 282, 'S001', 1, '2024-09-05 17:54:06'),
(64, 299, 'S002', 0, '2024-09-11 08:23:57'),
(72, 299, 'S002', 1, '2024-09-11 08:27:04'),
(73, 299, 'S002', 1, '2024-09-11 08:27:43'),
(74, 299, 'S002', 1, '2024-09-14 08:17:17'),
(75, 299, 'S002', 1, '2024-09-14 08:17:34'),
(76, 299, 'S002', 0, '2024-09-14 08:17:51'),
(89, 299, 'S002', 0, '2024-10-10 13:46:34'),
(105, 304, 'S002', 1, '2024-10-18 15:56:36'),
(106, 305, 'S002', 1, '2024-10-18 15:56:54'),
(107, 306, 'S002', 0, '2024-10-18 15:57:04'),
(108, 307, 'S002', 0, '2024-10-18 15:57:14'),
(109, 307, 'S002', 1, '2024-10-27 07:43:11'),
(110, 310, 'S002', 2, '2024-10-27 15:26:53'),
(111, 310, 'S001', 2, '2024-10-27 15:50:08'),
(112, 310, 'S001', 1, '2024-10-28 08:03:07'),
(115, 313, 'S004', 0, '2024-11-04 14:16:36'),
(116, 312, 'S004', 0, '2024-11-04 14:16:49'),
(117, 312, 'S004', 0, '2024-11-04 14:17:20'),
(118, 311, 'S004', 1, '2024-11-04 14:28:50'),
(119, 311, 'S004', 0, '2024-11-04 14:28:57'),
(120, 311, 'S004', 0, '2024-11-04 14:29:13'),
(121, 312, 'S004', 0, '2024-11-04 14:29:39'),
(123, 312, 'S004', 0, '2024-11-04 14:44:59'),
(124, 312, 'S004', 0, '2024-11-04 14:45:07'),
(125, 311, 'S004', 1, '2024-11-04 14:45:15'),
(126, 313, 'S004', 0, '2024-11-04 14:45:25'),
(127, 313, 'S004', 0, '2024-11-04 14:46:27'),
(128, 313, 'S004', 0, '2024-11-04 14:46:31'),
(133, 312, 'S004', 0, '2024-11-04 14:48:56'),
(134, 312, 'S004', 0, '2024-11-04 14:49:01'),
(135, 311, 'S004', 0, '2024-11-04 14:50:11'),
(136, 311, 'S004', 0, '2024-11-04 14:50:30'),
(137, 311, 'S004', 0, '2024-11-04 14:52:44'),
(139, 311, 'S004', 0, '2024-11-04 15:07:31'),
(141, 313, 'S004', 0, '2024-11-04 15:21:03'),
(142, 312, 'S004', 0, '2024-11-04 15:21:32'),
(143, 312, 'S004', 0, '2024-11-04 15:21:41'),
(144, 312, 'S004', 0, '2024-11-04 15:24:20'),
(146, 311, 'S004', 1, '2024-11-04 15:26:37'),
(147, 311, 'S004', 0, '2024-11-05 03:10:41'),
(152, 311, 'S004', 1, '2024-11-05 03:14:36'),
(153, 311, 'S004', 0, '2024-11-05 03:14:41'),
(155, 313, 'S004', 0, '2024-11-09 02:01:14'),
(156, 311, 'S004', 0, '2024-11-10 22:23:49'),
(157, 312, 'S004', 0, '2024-11-10 22:24:01'),
(158, 311, 'S004', 1, '2024-11-10 22:29:50'),
(159, 311, 'S004', 0, '2024-11-10 22:29:55'),
(162, 313, 'S004', 0, '2024-11-10 22:33:45'),
(163, 313, 'S004', 0, '2024-11-10 22:33:50'),
(164, 321, 'S004', 0, '2024-11-10 22:34:01'),
(165, 311, 'S004', 0, '2024-11-10 22:34:09'),
(166, 311, 'S004', 1, '2024-11-11 07:37:58'),
(167, 312, 'S004', 0, '2024-11-18 13:18:02'),
(168, 327, 'S004', 0, '2024-11-18 13:18:57'),
(169, 327, 'S004', 0, '2024-11-23 10:25:04'),
(170, 360, 'S004', 0, '2024-11-26 11:47:31'),
(171, 360, 'S004', 0, '2024-11-27 06:53:14'),
(172, 360, 'S004', 0, '2024-11-27 13:26:13'),
(173, 360, 'S004', 0, '2024-11-27 14:35:41'),
(174, 381, 'S004', 0, '2024-11-27 15:25:49'),
(175, 377, 'S004', 0, '2024-11-29 09:25:51'),
(176, 377, 'S004', 1, '2024-11-29 09:26:00'),
(177, 383, 'S004', 0, '2024-11-29 09:26:11'),
(178, 437, 'S004', 0, '2024-11-29 09:26:25'),
(189, NULL, 'S004', 0, '2024-12-01 16:13:27'),
(190, NULL, 'S004', 0, '2024-12-01 16:14:56'),
(191, NULL, 'S004', 0, '2024-12-01 16:15:06'),
(192, NULL, 'S004', 0, '2024-12-01 16:15:13'),
(193, NULL, 'S004', 0, '2024-12-01 16:17:04'),
(194, NULL, 'S004', 0, '2024-12-01 17:12:38');

-- --------------------------------------------------------

--
-- Table structure for table `rankings`
--

CREATE TABLE `rankings` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `rank` int(11) NOT NULL,
  `score` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `fname` varchar(50) NOT NULL,
  `lname` varchar(50) NOT NULL,
  `glevel` varchar(3) NOT NULL,
  `strand` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile_pic` varchar(255) NOT NULL DEFAULT 'default_profile.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `account_number`, `fname`, `lname`, `glevel`, `strand`, `password`, `profile_pic`) VALUES
(3, 'S001', 'Lili', 'Meow', '11', 'TVL', '$2y$10$QQthBwSlJlRVYx4ZkPBKGuxfCyNYLsivycQdhyE89FqYUdEzzv7oO', 'uploads/BLACKPINK DESKTOP WALLPAPERS.jpg'),
(4, 'S002', 'Jhoanna', 'Robles', '12', 'TVL', '$2y$10$uiS/TWj8KKPqDbxfj78p6u2hFUPrQkbUF93Nc5.F00iP7Fcszki4a', 'uploads/IMG-5550.JPG'),
(6, 'S004', 'Eyes', 'Kopi', '11', '', '$2y$10$mhKbQWyZM1k70T/icNvnVO1BWnEgy5HOi.zk9wlCnquYhMkqQwPRi', 'uploads/pie graph.png'),
(13, 'S005', 'maki', 'yaki', 'G11', 'ABM', '$2y$10$NAS9CWsVUFBBqOIE2FmdnuJLDbHntgq1Fyq33MiIqGcXlX9BFgZx.', 'default_profile.png'),
(14, 'S006', 'jess', 'constante', 'G12', 'ABM', '$2y$10$t1DkH7RXjom3dTgCwOn5/.85QhfEbi1U7V1fr2cG/LfT7dhAYxSr2', 'default_profile.png');

-- --------------------------------------------------------

--
-- Table structure for table `student_answers`
--

CREATE TABLE `student_answers` (
  `student_answer_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer` varchar(255) DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_answers`
--

INSERT INTO `student_answers` (`student_answer_id`, `student_id`, `quiz_id`, `question_id`, `answer`, `is_correct`, `answered_at`) VALUES
(15, 4, 304, 314, '398', 1, '2024-10-18 15:56:36'),
(16, 4, 305, 315, '403', 1, '2024-10-18 15:56:53'),
(17, 4, 306, 316, 'b,b,b,b', 0, '2024-10-18 15:57:04'),
(18, 4, 307, 317, '407', 0, '2024-10-18 15:57:14'),
(19, 4, 307, 317, '408', 1, '2024-10-27 07:43:11'),
(20, 4, 310, 320, '418', 1, '2024-10-27 15:26:52'),
(21, 4, 310, 321, '421', 1, '2024-10-27 15:26:52'),
(22, 4, 310, 322, '422', 0, '2024-10-27 15:26:53'),
(23, 3, 310, 320, '418', 1, '2024-10-27 15:50:07'),
(24, 3, 310, 321, '421', 1, '2024-10-27 15:50:08'),
(25, 3, 310, 322, '422', 0, '2024-10-27 15:50:08'),
(26, 3, 310, 320, '418', 1, '2024-10-28 08:03:07'),
(27, 6, 360, 369, '465', NULL, '2024-11-26 11:47:31'),
(28, 6, 360, 369, '465', NULL, '2024-11-27 06:53:14'),
(29, 6, 360, 370, '466', NULL, '2024-11-27 06:53:14'),
(30, 6, 360, 371, '467', NULL, '2024-11-27 06:53:14'),
(31, 6, 360, 372, '468', NULL, '2024-11-27 06:53:14'),
(32, 6, 360, 373, '469', NULL, '2024-11-27 06:53:14'),
(33, 6, 360, 374, '470', NULL, '2024-11-27 06:53:14'),
(34, 6, 360, 369, '465', NULL, '2024-11-27 13:26:13'),
(35, 6, 360, 370, '466', NULL, '2024-11-27 13:26:13'),
(36, 6, 360, 371, '467', NULL, '2024-11-27 13:26:13'),
(37, 6, 360, 372, '468', NULL, '2024-11-27 13:26:13'),
(38, 6, 360, 373, '469', NULL, '2024-11-27 13:26:13'),
(39, 6, 360, 374, '470', NULL, '2024-11-27 13:26:13'),
(40, 6, 360, 369, '465', NULL, '2024-11-27 14:35:41'),
(41, 6, 360, 370, '466', NULL, '2024-11-27 14:35:41'),
(42, 6, 360, 371, '467', NULL, '2024-11-27 14:35:41'),
(43, 6, 360, 372, '468', NULL, '2024-11-27 14:35:41'),
(44, 6, 360, 373, '469', NULL, '2024-11-27 14:35:41'),
(45, 6, 360, 374, '470', NULL, '2024-11-27 14:35:41'),
(46, 6, 381, 395, 'xxfcvfx', NULL, '2024-11-27 15:25:49'),
(47, 6, 381, 396, 'v xfbfxd', NULL, '2024-11-27 15:25:49'),
(48, 6, 377, 391, '494', 0, '2024-11-29 09:25:51'),
(49, 6, 377, 391, '495', 1, '2024-11-29 09:26:00'),
(50, 6, 383, 398, 'ano', NULL, '2024-11-29 09:26:11'),
(51, 6, 437, 452, 'ano', NULL, '2024-11-29 09:26:25');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `subject_id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `teacher_id` varchar(50) NOT NULL,
  `subject_code` varchar(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`subject_id`, `subject_name`, `teacher_id`, `subject_code`) VALUES
(10, 'Computer Programming 2', 'T001', 'LE65'),
(12, 'Comprog1', 'T001', 'IH61'),
(20, 'Computer Programming 2', 'T001', 'TK69'),
(21, 'Computer Programming 2', 'T001', 'JE45'),
(22, 'Computer Programming 2', 'T001', 'GB79'),
(23, 'Personal Development', 'T001', 'OY53'),
(27, 'Information Technology', 'T004', 'CH16'),
(48, 'gen math', 'T001', 'GO90'),
(50, 'Empowerment Technologies', 'T005', 'UC13'),
(99, 'Science', 'T006', 'ST07'),
(100, 'math', 'T006', 'VS99');

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
  `profile_pic` varchar(55) NOT NULL DEFAULT 'default_profile.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`teacher_id`, `account_number`, `fname`, `lname`, `password`, `profile_pic`) VALUES
(2, 'T001', 'Jess', 'Constante', '$2y$10$/aS5swbECzfpBJrXAbualOl.yO6HVR09UgKX.zp/i5/zTGAqwyLNS', 'default_profile.png'),
(3, 'T002', 'Lili', 'Meow', '$2y$10$lakeW.gqWPHzkIqrsUylpOkl64/ltR6ARm2mS/Q.fh7tYk5j2l6X.', 'default_profile.png'),
(6, 'T004', 'Aiah', 'Arceta', '$2y$10$40FDo1D5Knf1x5azMQS4iOP3ztAJE//oT4zOm8qYKodPPgxt4z7Ei', 'default_profile.png'),
(7, 'T005', 'Stacey', 'Ganda', '$2y$10$ZN6nQ8ThCSVFU15MDjnD4u97pRggeOeKLU.AoKSzd6Mhot6dB36Ym', 'default_profile.png'),
(8, 'T006', 'Colet', 'Vergara', '$2y$10$ExgONVUKX9rBUlt27Nj7dekAO.TipImGOcC38GD0fpn0x6rxNNzfy', 'default_profile.png');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `answers`
--
ALTER TABLE `answers`
  ADD PRIMARY KEY (`answer_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `profile_pictures`
--
ALTER TABLE `profile_pictures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`question_id`),
  ADD KEY `quiz_id` (`quiz_id`);

--
-- Indexes for table `question_options`
--
ALTER TABLE `question_options`
  ADD PRIMARY KEY (`option_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`quiz_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `fk_quiz_attempts_account_number` (`account_number`),
  ADD KEY `quiz_attempts_ibfk_1` (`quiz_id`);

--
-- Indexes for table `rankings`
--
ALTER TABLE `rankings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `quiz_id` (`quiz_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `account_number` (`account_number`);

--
-- Indexes for table `student_answers`
--
ALTER TABLE `student_answers`
  ADD PRIMARY KEY (`student_answer_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `quiz_id` (`quiz_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`subject_id`),
  ADD UNIQUE KEY `subject_code` (`subject_code`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`teacher_id`),
  ADD UNIQUE KEY `account_number` (`account_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `answers`
--
ALTER TABLE `answers`
  MODIFY `answer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=660;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `profile_pictures`
--
ALTER TABLE `profile_pictures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=496;

--
-- AUTO_INCREMENT for table `question_options`
--
ALTER TABLE `question_options`
  MODIFY `option_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `quiz_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=470;

--
-- AUTO_INCREMENT for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=195;

--
-- AUTO_INCREMENT for table `rankings`
--
ALTER TABLE `rankings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `student_answers`
--
ALTER TABLE `student_answers`
  MODIFY `student_answer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `subject_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `answers`
--
ALTER TABLE `answers`
  ADD CONSTRAINT `answers_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `questions` (`question_id`);

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`);

--
-- Constraints for table `profile_pictures`
--
ALTER TABLE `profile_pictures`
  ADD CONSTRAINT `profile_pictures_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `question_options`
--
ALTER TABLE `question_options`
  ADD CONSTRAINT `question_options_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `questions` (`question_id`);

--
-- Constraints for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `fk_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`),
  ADD CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`);

--
-- Constraints for table `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD CONSTRAINT `fk_quiz_attempts_account_number` FOREIGN KEY (`account_number`) REFERENCES `students` (`account_number`),
  ADD CONSTRAINT `quiz_attempts_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`quiz_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_attempts_ibfk_2` FOREIGN KEY (`account_number`) REFERENCES `students` (`account_number`);

--
-- Constraints for table `rankings`
--
ALTER TABLE `rankings`
  ADD CONSTRAINT `rankings_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`),
  ADD CONSTRAINT `rankings_ibfk_2` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`quiz_id`),
  ADD CONSTRAINT `rankings_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`);

--
-- Constraints for table `student_answers`
--
ALTER TABLE `student_answers`
  ADD CONSTRAINT `student_answers_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_answers_ibfk_2` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`quiz_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_answers_ibfk_3` FOREIGN KEY (`question_id`) REFERENCES `questions` (`question_id`) ON DELETE CASCADE;

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`account_number`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
