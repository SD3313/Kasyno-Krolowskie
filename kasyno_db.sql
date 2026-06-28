-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Cze 26, 2026 at 10:33 PM
-- Wersja serwera: 10.4.32-MariaDB
-- Wersja PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kasyno_db`
--

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `game_history`
--

CREATE TABLE `game_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `game` varchar(50) NOT NULL,
  `bet` decimal(10,2) NOT NULL,
  `win` decimal(10,2) NOT NULL,
  `balance_after` decimal(10,2) NOT NULL,
  `played_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `game_history`
--

INSERT INTO `game_history` (`id`, `user_id`, `game`, `bet`, `win`, `balance_after`, `played_at`) VALUES
(22, 9, 'Dice', 500.00, -500.00, 1000.00, '2026-06-26 20:03:34'),
(23, 9, 'Dice', 500.00, 1030.00, 500.00, '2026-06-26 20:03:35'),
(24, 9, 'Dice', 500.00, 219.00, 1530.00, '2026-06-26 20:03:37'),
(25, 9, 'Dice', 500.00, 219.00, 1749.00, '2026-06-26 20:03:39'),
(26, 9, 'Dice', 500.00, -500.00, 1968.00, '2026-06-26 20:03:41'),
(27, 9, 'Dice', 500.00, -500.00, 1468.00, '2026-06-26 20:03:41'),
(28, 9, 'Dice', 500.00, -500.00, 968.00, '2026-06-26 20:03:42'),
(29, 9, 'Dice', 117.00, -117.00, 468.00, '2026-06-26 20:03:48'),
(30, 9, 'Dice', 117.00, 241.00, 351.00, '2026-06-26 20:03:49'),
(31, 9, 'Dice', 117.00, 143.00, 592.00, '2026-06-26 20:03:53'),
(32, 9, 'Dice', 117.00, -117.00, 735.00, '2026-06-26 20:04:02'),
(33, 9, 'Dice', 117.00, 87.00, 618.00, '2026-06-26 20:04:03'),
(34, 9, 'Bomb Sweeper', 250.00, -250.00, 501.00, '2026-06-26 20:05:45'),
(35, 9, 'Bomb Sweeper', 250.00, 3077.00, 3578.00, '2026-06-26 20:05:59'),
(36, 9, 'Coin Flip', 3578.00, 3578.00, 7156.00, '2026-06-26 20:07:01');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `users`
--

CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `username` varchar(255) NULL DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `pass` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'user',
  `balance` int(11) NOT NULL DEFAULT 1000,
  `registration_date` datetime NOT NULL DEFAULT current_timestamp(),
  `profile_pic` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Struktura tabeli dla tabeli `friend_requests`
--

CREATE TABLE `friend_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `from_user_id` int(10) UNSIGNED NOT NULL,
  `to_user_id` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Struktura tabeli dla tabeli `friendships`
--

CREATE TABLE `friendships` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_one` int(10) UNSIGNED NOT NULL,
  `user_two` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `username`, `email`, `pass`, `role`, `balance`, `registration_date`, `profile_pic`) VALUES
(1, 'Dzien', 'Dobry', '', 'mail@mail.mail', '$2y$10$nJkDoIM66ge1hMtE6OE18.6CWQcEHeJkZpartugBIR9Ib9PPdrRZG', 'user', 1000, '2026-06-25 18:45:18', NULL),
(5, 'Michał', 'Janik', 'supercheater', 'aaaaaaaaahahahaah@gmail.com', '$2y$10$KQARoqbnplf5ifR1WIBXx.dE6UksjHLsvBucPcgAgHYXOTlZufvH.', 'user', 1000, '2026-06-26 21:47:03', NULL),
(7, 'Michał', 'Janik', 'supercheater', 'aaaaaaa2ah@gmail.com', '$2y$10$ugJErKxxPIWBh2u3cl41cOHQ.lIgkERDEI3kJEkg6IPcseRpTGQCy', 'user', 1000, '2026-06-26 21:49:37', NULL),
(8, 'Michał', 'Janik', 'gosc', 'mjanik@gmail.com', '$2y$10$lbz91qWofjW03p9GX6bW5uz45DGPLUnZ.IVA5tzSHnlT1AofnaQYG', 'user', 1000, '2026-06-26 21:53:14', NULL),
(9, 'Michał', 'Janik', 'wtorekSobota', 'mjanik167@gmail.com', '$2y$10$CJ3CCjgYF7sipkQFSC5Nsu/Yj9vyY6FFxekBdlyP0glllhY8/hzbe', 'user', 1000, '2026-06-26 22:00:01', NULL);

--
-- Indeksy dla zrzutów tabel
--

--
-- Indeksy dla tabeli `game_history`
--
ALTER TABLE `game_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `history_user` (`user_id`);

--
-- Indeksy dla tabeli `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indeksy dla tabeli `friend_requests`
--
ALTER TABLE `friend_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_request` (`from_user_id`,`to_user_id`),
  ADD KEY `idx_friend_requests_from` (`from_user_id`),
  ADD KEY `idx_friend_requests_to` (`to_user_id`);

--
-- Indeksy dla tabeli `friendships`
--
ALTER TABLE `friendships`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_friendship` (`user_one`,`user_two`),
  ADD KEY `idx_friendship_one` (`user_one`),
  ADD KEY `idx_friendship_two` (`user_two`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `game_history`
--
ALTER TABLE `game_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `friend_requests`
--
ALTER TABLE `friend_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `friendships`
--
ALTER TABLE `friendships`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `game_history`
--
ALTER TABLE `game_history`
  ADD CONSTRAINT `history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `friend_requests`
--
ALTER TABLE `friend_requests`
  ADD CONSTRAINT `friend_requests_from_user` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `friend_requests_to_user` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `friendships`
--
ALTER TABLE `friendships`
  ADD CONSTRAINT `friendship_user_one` FOREIGN KEY (`user_one`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `friendship_user_two` FOREIGN KEY (`user_two`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
