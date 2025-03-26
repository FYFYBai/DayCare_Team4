-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 26, 2025 at 06:03 AM
-- Server version: 10.3.39-MariaDB-log
-- PHP Version: 8.1.32

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cp5114_team4`
--

-- --------------------------------------------------------

--
-- Table structure for table `children`
--

CREATE TABLE `children` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `date_of_birth` date NOT NULL,
  `profile_photo_path` varchar(200) NOT NULL,
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `educator_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `children`
--

INSERT INTO `children` (`id`, `parent_id`, `name`, `date_of_birth`, `profile_photo_path`, `isDeleted`, `educator_id`) VALUES
(1, 1, 'Alice', '2016-01-15', 'alice.png', 0, 9),
(2, 1, 'Bob', '2017-03-10', 'bob.png', 0, 9),
(3, 1, 'Charlie', '2018-07-20', 'charlie.png', 0, 9),
(4, 2, 'Daisy', '2016-05-25', 'daisy.png', 0, 9),
(5, 2, 'Ethan', '2017-11-03', 'ethan.png', 0, 9),
(6, 3, 'Fiona', '2018-02-14', 'fiona.png', 0, 9),
(7, 3, 'George', '2017-08-30', 'george.png', 0, 9),
(8, 4, 'Hannah', '2016-12-05', 'hannah.png', 0, 11),
(9, 4, 'Ian', '2017-04-18', 'ian.png', 0, 11),
(10, 4, 'Julia', '2018-09-22', 'julia.png', 0, 11),
(11, 5, 'Kevin', '2017-06-15', 'kevin.png', 0, 11),
(12, 5, 'Lily', '2018-01-10', 'lily.png', 0, 11),
(13, 6, 'Mason', '2016-03-20', 'mason.png', 0, 11),
(14, 6, 'Nora', '2017-07-07', 'nora.png', 0, 11),
(15, 6, 'Olivia', '2018-10-05', 'olivia.png', 0, 12),
(16, 7, 'Patrick', '2017-05-12', 'patrick.png', 0, 12),
(17, 7, 'Quinn', '2018-08-14', 'quinn.png', 0, 12),
(18, 8, 'Rachel', '2016-09-30', 'rachel.png', 0, 12),
(19, 8, 'Sam', '2017-02-27', 'sam.png', 0, 12),
(20, 10, 'Tina', '2018-04-16', '64f7ebc8-1ff8-42fa-8303-5dcc0baf68a5-baby1.jpg', 0, 12),
(21, 10, 'Tina2', '2024-05-19', '', 1, 0),
(22, 10, 'Joseph', '2010-05-19', '', 1, 0),
(23, 21, 'Tina', '2022-02-09', '', 0, 0),
(24, 10, 'Child2', '2022-03-16', '0dfd9409-52ab-4983-99e7-f685fa35ac40-baby2.jpg', 0, 0),
(25, 10, 'Child3', '2025-03-22', 'ca6ac7b9-f84c-4262-8c59-87f496eb5d58-baby3.jpg', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `description` text DEFAULT NULL,
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_children`
--

CREATE TABLE `event_children` (
  `event_id` int(11) NOT NULL,
  `child_id` int(11) NOT NULL,
  `registration_date` datetime DEFAULT current_timestamp(),
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` datetime NOT NULL,
  `payment_status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `child_count` int(11) NOT NULL DEFAULT 1,
  `stripe_session_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `user_id`, `amount`, `payment_date`, `payment_status`, `isDeleted`, `child_count`, `stripe_session_id`) VALUES
(62, 21, 100.00, '2025-03-25 23:32:26', 'completed', 0, 1, 'cs_test_a1x3YzZYdDMQDl0jTb0CiYryqePau1YZp6KOQg7GYgj2OwIVMrXHDfnrAe'),
(63, 21, 500.00, '2025-03-25 23:36:58', 'completed', 0, 5, 'cs_test_a1z0Szn8dLcQTvjJlFFRhvsItXWtjOG1R4F0BNZjEZecQv8yxxSlx1ozk1'),
(64, 21, 1000.00, '2025-03-26 03:20:10', 'completed', 0, 10, 'cs_test_a1WXHjPsyUIRjwPdmoLcb9t0T6L7q8cPcJtGJXNAZVwZaC5rHEXhe3AEKn');

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `id` int(11) NOT NULL,
  `child_id` int(11) NOT NULL,
  `registration_date` date NOT NULL,
  `status` enum('present','absent') NOT NULL DEFAULT 'absent',
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(320) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('parent','educator','manager','admin') NOT NULL,
  `activation_status` tinyint(1) NOT NULL DEFAULT 0,
  `isDeleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `profile_photo_path` varchar(255) DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `activation_token` varchar(64) DEFAULT NULL,
  `isAdmin` tinyint(1) NOT NULL DEFAULT 0,
  `reset_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `activation_status`, `isDeleted`, `created_at`, `profile_photo_path`, `reset_token`, `activation_token`, `isAdmin`, `reset_expires`) VALUES
(1, 'Jakarta', 'jakarta@gmail.com', '$2y$10$orrlXJ1j9JjjurCez4CrCeiGOLsuPcwP2WovcJ4L7JeUiKnucOm.W', 'parent', 0, 0, '2025-03-21 21:06:58', 'default.png', NULL, '5be57f349875379634dd1af6d18cbac854b6fe08b16d892ec6e1a61e4e23ae0d', 0, NULL),
(2, 'Rita', 'rita@gmail.com', '$2y$10$PTle3uWHf5QYrf1yAw44iODGi6fLI7Ujhhso3oU/WF/pnZgwNxjsS', 'parent', 0, 0, '2025-03-21 20:35:01', 'default.png', NULL, '7d7def843b7408542b8d0aaf9d51cc5f5e489d65c36b4879a7569e32baebb8e5', 0, NULL),
(3, 'Anita', 'aniita@gmail.com', '$2y$10$yjq1nd.VGkJ0aMeOGtqDo.TXaBjOLjMlRVfBwTl.NpVxQViRk.kBW', 'parent', 0, 0, '2025-03-21 20:39:57', 'default.png', NULL, '022cc576e6f2140e7c09bafda793a033b182f8221699301178220e4fe257672d', 0, NULL),
(4, 'Natasha', 'natasha@gmail.com', '$2y$10$8EgzD5AFV1v3uopOrWsmUeeLTehprIBCaaNR0v4NKbsZKhpoOZEw6', 'parent', 1, 0, '2025-03-21 21:44:51', 'default.png', NULL, 'bc229454a4dc8d3cadf702470fd9bca747a6221bae2c542284f1e08fe2c04d53', 0, NULL),
(5, 'Ananya', 'ananya@gmail.com', '$2y$10$6/tmAzBbp5UWyGadjf/PEerWQGQvb5qu4fNR8ANtEV3gKr8XKwlLG', 'parent', 0, 0, '2025-03-21 22:53:18', 'default.png', NULL, 'a083951a2168e5f85d338eb1ab436deaa99dfb1b1edcea9f6494a07f0be00c3f', 0, NULL),
(6, 'Indra', 'indra@gmail.com', '$2y$10$JMpj2we3E6DtFopPO.qoLeprHU28fNv6wHbl7Aa.fKoM9lR.QmRFu', 'parent', 1, 0, '2025-03-22 00:06:16', 'default.png', NULL, '3302b6892c64e2915010f47f71a593d956b8aca25c8021fe2c2e88d0ab1d92d2', 0, NULL),
(7, 'Indrani', 'indrani@gmail.com', '$2y$10$6txc0CdrubO.JzXqr.M7U.vNWydyp19qwHp9mWLnuQPHkMSwjVtv.', 'parent', 0, 0, '2025-03-22 00:14:19', 'default.png', NULL, '1f4ef7f968ae13e453aa79166cf94b9805a4ce8606227ef6ddac8f26e6fa202f', 0, NULL),
(8, 'Rani', 'rani@gmail.com', '$2y$10$7OqG4BbAIjtOsuO4.ozoe.pEkoc5LmiVkgkNZ0fKtBJpXdRnMRiUW', 'parent', 1, 0, '2025-03-22 00:26:28', 'default.png', NULL, '9c51c642ea24f0218df4e890c421023ca74e205594a1cf9c5e64d2a60a691da0', 0, NULL),
(9, 'Kriti', 'kriti@gmail.com', '$2y$10$6aoapJfRK.BMd51lGHgdWeSlEeAcsEu1qF4JPXByeQdjhL3HyfXeS', 'educator', 1, 0, '2025-03-22 03:51:58', '57654b03-2e8b-4b05-a180-875fe5db349e-1.png', 'fe542666e7a98b7c37e25921847b3a7e21310fd1923e76e4cdcb224d411dca6e', '51c050d0ce3725df6af8a769dc5fe1f15d2140699a5edfe0abbac7dafb6e7531', 0, '2025-03-22 17:12:23'),
(10, 'Kriti', 'kriti2@gmail.com', '$2y$10$ulLmICjL4AJOdvDkFX7cweSC/zmBMDY.xLt8dmWHPLDS1sDU0/upC', 'parent', 1, 0, '2025-03-22 04:16:07', 'default.png', 'bccc8d130ac015134528c74996cb37872d9f98bee7431286db542cc80e618772', 'bac8f127e4376ce19c2884aa4bf9d828b2c9c98e63e9e2e1c6100edae1f72f9c', 0, '2025-03-22 05:24:54'),
(11, 'Educator One', 'educator1@example.com', '$2y$10$ICYFkD05U/KU4uufSA9GwOVe/IiasQVJZkfRIQqu7p07ZDkPJzhcW', 'educator', 1, 0, '2025-03-23 09:14:44', 'default.png', NULL, NULL, 0, NULL),
(12, 'Educator Two', 'educator2@example.com', '$2y$10$Rsx27IlJjS68roR6gFa3du6o3B2vnWAyxEEg6RrooyiTXxVMXSLkS', 'educator', 1, 0, '2025-03-23 09:14:44', 'default.png', NULL, NULL, 0, NULL),
(13, 'Manager One', 'manager1@example.com', '$2y$10$o0SxebghsaJLeVXhyXV5MeESye5znKgNrQHK1545fI4Gv6beAHAY2', 'manager', 1, 0, '2025-03-23 09:14:44', 'default.png', NULL, NULL, 0, NULL),
(14, 'Admin One', 'admin1@example.com', '$2y$10$cYeeUbbRURq466EksgnUYOc32ppIpWyVaKx/X8FUFxTgTBmSP317C', 'admin', 1, 0, '2025-03-23 09:14:44', 'default.png', NULL, NULL, 1, NULL),
(15, 'Milan Joshi', 'milan@gmail.com', '$2y$10$bZ.8LBmh29.0AmTrLLwC2OwiIDlr66Cv2NcGAXCgb8U9ckq95uhMG', 'parent', 1, 0, '2025-03-23 23:21:09', 'default.png', NULL, '0ddc19c3d1845d462b972a710995a79e3407c01631a841893a289abf7df39123', 0, NULL),
(18, 'Aditya Jain', 'aditya@gmail.com', '$2y$10$PZAVJfMfefUmgIdz6k8/XukobIsVBEmaFdC1wZ5DlrACSE/2hnJFO', 'parent', 0, 0, '2025-03-23 23:33:24', 'default.png', NULL, '046b7a594ddfa71c5547005e682fd3fe3901fdfc31317daaff8758952ac6f7a0', 0, NULL),
(19, 'Aditya Jain', 'aditya2@gmail.com', '$2y$10$1QgBgzIBwm6j3drBZzKI4Ou.PqHKfrzZ/xaIoiFxcLUNUwnYSwwD2', 'parent', 0, 0, '2025-03-23 23:39:15', 'default.png', NULL, 'a70550f5c891ce8ffa5d2ae4b55627c41c46ceae242f7efa805c1cc27bc410fd', 0, NULL),
(20, 'parent1', 'parent1@hotmail.com', '$2y$10$W68xmNubhOWi5VuzMW7ZD.Nn7rXMLk6L6zYr4TMUmufJ/rJ8CU9Wq', 'parent', 0, 0, '2025-03-23 23:42:36', 'default.png', NULL, '2e37466ba4ac128a1cb94bd366e0631c6facc0436434376630ce0eb947cec7af', 0, NULL),
(21, 'Nina', 'nina@gmail.com', '$2y$10$4evxVqOMwXSy3o8.t2K7rOZ8hMqwnbvZL0OmHRv9NZpsc7oRF1DOi', 'parent', 1, 0, '2025-03-24 15:40:20', 'default.png', NULL, 'eabb6f16724c7343eef9e6e38fb890d62371b60daaadb17dec3ca997bd9a072e', 0, NULL),
(22, 'New User', 'newuser@gmail.com', '$2y$10$iw7/0zwTwd9peiUMqyGr0.mVL78OYnC3I6dEwFzEPMVukgGsYmFc2', 'parent', 0, 0, '2025-03-24 17:08:56', 'default.png', NULL, '25c8cca1b810c3ec299c8e4af246275c0e8fbd1e36739a5d0cc2a98908d5d71b', 0, NULL),
(23, 'New User', 'newuser2@gmail.com', '$2y$10$VjSwwEB7MaLCLImeq2/vTeuj1ENl5U1EoJVUrR6CHCxsc7BATebeO', 'parent', 0, 0, '2025-03-24 16:10:37', 'default.png', NULL, 'a9f4b7fe5ad59c2f811d07c9c46b1cbf87646af1b5c5d96be1386396e2802720', 0, NULL),
(24, 'John Smith', 'john@gmail.com', '$2y$10$6KVBtRWA497dU1jKcD4zYuZPa17/NwGWGHDE5UGvLpYGqAeSk.YPq', 'parent', 0, 0, '2025-03-24 19:54:15', 'default.png', NULL, '026e14898239c69376892a5d30a24ad8f216c271dc0b6285110a61e500f75725', 0, NULL),
(25, 'New User3', 'newuser3@gmail.com', '$2y$10$f7A.Nnh24Jjt8C.K9UBfkeWvB9YrifmP1mMlrC4BiUKoXp1MLO5Ie', 'parent', 0, 0, '2025-03-24 20:25:17', 'default.png', NULL, '8102e0f8399cfad02801669c0c760a8d93a8747e801bd06eed0ddce213c25e42', 0, NULL),
(26, 'New User4', 'newuser4@gmail.com', '$2y$10$buahhgonSH8l.eCFE2GHe.MjBHOHbvRJDJNMRPPkOH/xnei0pfC0u', 'parent', 0, 0, '2025-03-24 21:26:23', 'default.png', 'c5b6ed4721a31e4cc3cd75a273a06e5b182bbcea52d8ea2c50f04dd6d6c31381', '4e05b25d021c718858f04443ab68e902b399121aac5bd4d1dba1515d3946b527', 0, '2025-03-25 02:32:46'),
(27, 'New User 5', 'newuser5@gmail.com', '$2y$10$b/moshcJwQN.OTAxU.n3Su8LBAKg357K6CffP79nlYTqsZawWoKvy', 'parent', 1, 0, '2025-03-24 21:40:55', 'default.png', NULL, 'aa0d86f860b084b26609ae999de97753591a0753c74ea98598598fccdeb98aa6', 0, NULL),
(28, 'New User 6', 'newuser6@gmail.com', '$2y$10$Eec2nfbzh3BNS5u7riAWEu8NSI9H/UIZIX2Gy7Ape4Wn.fKtoscHS', 'parent', 0, 0, '2025-03-24 20:44:30', 'default.png', NULL, 'b664473ab597c4b9c421f82b4d9543d322958eb044c36eae9c5d3a33b995474e', 0, NULL),
(29, 'newuser7', 'newuser7@gmail.com', '$2y$10$to6UoOv.EyQsdIqcgk07seOR.X0txLV45PvE0scNaDksuJivvp7vq', 'parent', 0, 0, '2025-03-24 20:45:27', 'default.png', NULL, '13aade11c6a5461e00dc70e43487216726c9838e1d0a5c7bac94982ee5fb8fad', 0, NULL),
(30, 'New User 7', 'newuser8@gmail.com', '$2y$10$zHx2Vw.BkHnhoMYAyZxLk.q2Uca7GZ/QCOdhj/LOpR.xckwB31FNe', 'parent', 0, 0, '2025-03-24 21:00:57', 'default.png', NULL, '7834daef96234446995b5d501acd48a63cd47b7282af64802c0e53b6412f39b4', 0, NULL),
(31, 'New User 9', 'newuser9@gmail.com', '$2y$10$cwKmILEQ7IjOabuEOedqouFy/KxiDpwUI.RwAhTh9oKGVM9GJyOTe', 'parent', 0, 0, '2025-03-24 21:12:49', 'default.png', NULL, '9f6fa13fbfe7a3a6bbdaa64d3b72034286eb6eceabe0293d542481008c922f26', 0, NULL),
(32, 'New User 10', 'newuser10@gmail.com', '$2y$10$N4HzsxQUOGBPlwubPwGF4Oe2uEPBnUc7PNm2HvnfvwzKh2EiEhi6G', 'parent', 1, 0, '2025-03-24 21:28:07', 'default.png', NULL, '7fbcb051358c647bd7b5bfb3a0ad6d500baf13a3229546bc5121d8febc01be36', 0, NULL),
(33, 'newuser11', 'newuser11@gmail.com', '$2y$10$Tt7n5l08kMomqKi1ZTJBmu8DJTTUwtMCVTPCjjjFXAhl/zmEAooyW', 'parent', 1, 0, '2025-03-25 13:46:07', 'default.png', NULL, '9ca56426bc5b98cf539ac821599d33e126b752f915ab86bb1559e41cb5a686cf', 0, NULL),
(34, 'newuser12', 'newuser12@gmail.com', '$2y$10$b1peUd4j8hxfMg.2f6ptzu.GiI9n/8zSHYCxEfVUua3n59ZT/oIUu', 'educator', 1, 0, '2025-03-25 13:49:24', 'fa9c7148-2e10-4196-b710-da9673401f7b-bee-705412_1280.png', NULL, '16311099b3b569c7ec563daefda4186eefe09ba2716a4d7969d87292bbfafae8', 0, NULL),
(35, 'New user 15', 'newuser16@gmail.com', '$2y$10$u3NzT/19o6g475jhkNL7Je/XhWV5HLuO3oKvJzH.kX7JNg28Q9Xvm', 'parent', 0, 0, '2025-03-25 14:35:40', '77865dae-887d-46f7-bcce-052cc05a6c46-girl-in-dress.jpg', NULL, '14c9fa4a1ca32853465749738fca9ec7b06ad35003560a1e72761967f26699ff', 0, NULL),
(36, 'New User 17', 'newuser17@gmail.com', '$2y$10$lnSlThMfj4iI8kjkZM7KAuXdSg4thUd7wEJT95XtDh83bKL41rrEG', 'parent', 0, 0, '2025-03-25 15:00:52', 'default.png', NULL, 'c3585a13d3ba17483a820cd2212164de2297070348914eef5bd4d2e66375a21c', 0, NULL),
(37, 'New User 18', 'newuser18@gmail.com', '$2y$10$JLqW57vK/YDMQvsqPhFW1OWFFZG6cYTQgPAy0N7PlXKsat3jiF5dW', 'parent', 1, 0, '2025-03-25 15:02:19', 'default.png', NULL, '750bb9adbecb17bd8c233d535617af74e2cf685a384287a07d31df1a81ca51c0', 0, NULL),
(38, 'New User14', 'newuser14@gmail.com', '$2y$10$MTmfvCAjMczQTC0Q7lya1epK.TDANZYIVcKg/GHDeAi3hL5g29KOu', 'parent', 0, 0, '2025-03-25 15:45:13', 'default.png', NULL, '8f8eb4cca599a8c5ecc73077a8f7c503aead7a308c662b364d6e077b89f55cb4', 0, NULL),
(39, 'New User14', 'newuser15@gmail.com', '$2y$10$nasn.PyB1TtMHjwSqMjJIuYgbWU.7Q7GgutPnnmPiRN6WErpOxSKe', 'parent', 1, 0, '2025-03-25 15:51:13', 'default.png', NULL, 'c6827010a2ee2cf2f8ea3b2d2759ae10ba6bd16886108be04428ada62d54e2c9', 0, NULL),
(40, 'Newuser16', 'newuser19@gmail.com', '$2y$10$wadwh/6b2MwcmRYDmMf7F.knYOUBUwElyZa8j5NHYI3AHtyjdUWPy', 'parent', 0, 0, '2025-03-25 15:52:14', 'default.png', NULL, '759559d1072e521944c784719337afdc179db153d64a7a3e4510073444bf040c', 0, NULL),
(41, 'newuser20', 'newuser20@gmail.com', '$2y$10$liuVcv8KhX1leObgXrsjrOziY3mumCWilqN1NnHUDCtYeGuzP2Tiu', 'parent', 1, 0, '2025-03-25 16:12:22', 'default.png', NULL, 'cdc4542ca14ab81bf80216a90be55aa2d8dc15253c560b73f2ab112dcb8a46be', 0, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `children`
--
ALTER TABLE `children`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `event_children`
--
ALTER TABLE `event_children`
  ADD PRIMARY KEY (`event_id`,`child_id`),
  ADD KEY `child_id` (`child_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `child_id` (`child_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `children`
--
ALTER TABLE `children`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `children`
--
ALTER TABLE `children`
  ADD CONSTRAINT `children_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `event_children`
--
ALTER TABLE `event_children`
  ADD CONSTRAINT `event_children_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  ADD CONSTRAINT `event_children_ibfk_2` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`child_id`) REFERENCES `children` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
