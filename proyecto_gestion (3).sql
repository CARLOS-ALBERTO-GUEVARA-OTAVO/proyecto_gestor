-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 24-10-2025 a las 21:24:54
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `proyecto_gestion`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `archivos`
--

CREATE TABLE `archivos` (
  `id` int(11) NOT NULL,
  `drive_id` varchar(255) NOT NULL,
  `carpeta_id` int(11) DEFAULT NULL,
  `nombre` varchar(255) NOT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `ruta` text DEFAULT NULL,
  `tamanio` bigint(20) DEFAULT NULL,
  `subido_por` int(11) DEFAULT NULL,
  `fecha_subida` datetime DEFAULT NULL,
  `ultima_modificacion` datetime DEFAULT NULL,
  `estado` enum('activo','inactivo','eliminado') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cargos`
--

CREATE TABLE `cargos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cargos`
--

INSERT INTO `cargos` (`id`, `nombre`) VALUES
(1, 'Gestión Administrativa y Financiera'),
(2, 'Gestión de Control Interno'),
(3, 'Gestión de Comunicacion Estratégica y Marketing'),
(4, 'Gestión de Cumplimiento'),
(5, 'Gestión de Negocios Sostenibles'),
(6, 'Gestión de Proyectos'),
(7, 'Gestión del Conocimiento y Tics'),
(8, 'Gestión Estrategica'),
(9, 'Gestión del Conocimiento y Tics'),
(10, 'Gestión Estrategica'),
(11, 'Gestión Humana'),
(12, 'Gestión Jurídica'),
(13, 'Gestión Humana'),
(14, 'Gestión Jurídica');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `carpetas`
--

CREATE TABLE `carpetas` (
  `id` int(11) NOT NULL,
  `drive_id` varchar(255) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `ruta` text DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estados_usuario`
--

CREATE TABLE `estados_usuario` (
  `id` int(11) NOT NULL,
  `estado` varchar(20) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estados_usuario`
--

INSERT INTO `estados_usuario` (`id`, `estado`, `descripcion`) VALUES
(1, 'Activo', 'El usuario se encuentra habilitado en el sistema.'),
(2, 'Inactivo', 'El usuario está deshabilitado temporalmente.'),
(3, 'Pendiente', 'El usuario está en espera de aprobación o revisión.'),
(4, 'Suspendido', 'El usuario fue bloqueado por incumplimiento o fallo.'),
(5, 'Finalizado', 'El proceso relacionado con el usuario ha concluido.');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial`
--

CREATE TABLE `historial` (
  `id` int(11) NOT NULL,
  `archivo_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` enum('crear','modificar','eliminar','descargar','restaurar') DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `detalle` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_acciones`
--

CREATE TABLE `historial_acciones` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(50) DEFAULT NULL,
  `id_archivo` varchar(255) DEFAULT NULL,
  `nombre_archivo` varchar(255) DEFAULT NULL,
  `detalles` text DEFAULT NULL,
  `fecha_hora` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `historial_acciones`
--

INSERT INTO `historial_acciones` (`id`, `usuario_id`, `accion`, `id_archivo`, `nombre_archivo`, `detalles`, `fecha_hora`) VALUES
(1, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 15:29:01'),
(2, 2, 'vista de carpeta', '1aZsabQ2cLv7KUptAO5Zg8fYQoDxPICUh', 'donde', '', '2025-09-29 15:29:21'),
(3, 2, 'vista de carpeta', '1b7p0YU0sSgl9Ps2Qwig4byW_YUbJp8m-', 'Mitologias', '', '2025-09-29 15:29:29'),
(4, 2, 'vista de carpeta', '1P-LiYou8aRLuS8OnSImyrix2sUgCJuWC', 'como', '', '2025-09-29 15:29:37'),
(5, 2, 'vista de carpeta', '1gK_0U-F0HICbYcrN4vXBDK7JG-a5Ruhm', 'avisos_re', '', '2025-09-29 15:29:41'),
(6, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 15:29:43'),
(7, 2, 'vista de carpeta', '1gK_0U-F0HICbYcrN4vXBDK7JG-a5Ruhm', 'avisos_re', '', '2025-09-29 15:44:03'),
(8, 2, 'vista de carpeta', '1gK_0U-F0HICbYcrN4vXBDK7JG-a5Ruhm', 'avisos_re', '', '2025-09-29 15:44:05'),
(9, 2, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-09-29 15:44:36'),
(10, 2, 'vista de carpeta', '1b7p0YU0sSgl9Ps2Qwig4byW_YUbJp8m-', 'Mitologias', '', '2025-09-29 15:44:45'),
(11, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 15:51:41'),
(12, 2, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-09-29 15:51:56'),
(13, 2, 'vista de carpeta', '1b7p0YU0sSgl9Ps2Qwig4byW_YUbJp8m-', 'Mitologias', '', '2025-09-29 15:55:30'),
(14, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 15:56:51'),
(15, 2, 'vista de carpeta', '1gK_0U-F0HICbYcrN4vXBDK7JG-a5Ruhm', 'avisos_re', '', '2025-09-29 15:57:20'),
(16, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 15:57:35'),
(17, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 15:57:44'),
(18, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 15:58:19'),
(19, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 15:59:20'),
(20, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 16:00:14'),
(21, 2, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-09-29 16:00:26'),
(22, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 16:01:17'),
(23, 2, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-09-29 16:01:26'),
(24, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 16:03:07'),
(25, 2, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-09-29 16:03:31'),
(26, 2, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-09-29 16:03:40'),
(27, 2, 'vista de carpeta', '1aZsabQ2cLv7KUptAO5Zg8fYQoDxPICUh', 'donde', '', '2025-09-29 16:04:09'),
(28, 2, 'vista de carpeta', '1b7p0YU0sSgl9Ps2Qwig4byW_YUbJp8m-', 'Mitologias', '', '2025-09-29 16:04:13'),
(29, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 16:04:21'),
(30, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 16:26:59'),
(31, 2, 'vista de carpeta', '1hzuJR8CsQG4isnSkev9hlDofvyFrIYsL', 'Carlos Alberto Guevara Otavo', '', '2025-09-29 16:42:38'),
(32, 1, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 16:58:10'),
(33, 1, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 16:58:38'),
(34, 1, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-09-29 17:01:47'),
(35, 1, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-09-29 17:02:34'),
(36, 2, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-09-29 17:19:30'),
(37, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 17:20:15'),
(38, 2, 'vista de carpeta', '1gK_0U-F0HICbYcrN4vXBDK7JG-a5Ruhm', 'avisos_re', '', '2025-09-29 17:20:18'),
(39, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 19:24:55'),
(40, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 19:36:09'),
(41, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 19:36:51'),
(42, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 19:41:45'),
(43, 2, 'vista de carpeta', '1gK_0U-F0HICbYcrN4vXBDK7JG-a5Ruhm', 'avisos_re', '', '2025-09-29 19:42:10'),
(44, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 19:42:15'),
(45, 2, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-09-29 19:42:25'),
(46, 2, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-09-29 19:42:28'),
(47, 1, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-09-29 20:08:16'),
(48, 1, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 20:08:31'),
(49, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 20:09:08'),
(50, 1, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 20:13:00'),
(51, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 20:14:13'),
(52, 2, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-09-29 20:14:42'),
(53, 2, 'vista de carpeta', '1gK_0U-F0HICbYcrN4vXBDK7JG-a5Ruhm', 'avisos_re', '', '2025-09-29 20:15:02'),
(54, 2, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-09-29 20:15:40'),
(55, 2, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-09-29 20:21:23'),
(56, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 20:21:28'),
(57, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 20:33:30'),
(58, 2, 'vista de carpeta', '1P-LiYou8aRLuS8OnSImyrix2sUgCJuWC', 'como', '', '2025-09-29 20:34:13'),
(59, 2, 'vista de carpeta', '1b7p0YU0sSgl9Ps2Qwig4byW_YUbJp8m-', 'Mitologias', '', '2025-09-29 20:34:19'),
(60, 2, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-09-29 20:34:34'),
(61, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 20:43:13'),
(62, 2, 'vista de carpeta', '1gK_0U-F0HICbYcrN4vXBDK7JG-a5Ruhm', 'avisos_re', '', '2025-09-29 20:43:31'),
(63, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 20:43:36'),
(64, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 20:48:05'),
(65, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 21:04:40'),
(66, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-29 21:07:34'),
(67, 2, 'vista de carpeta', '1gK_0U-F0HICbYcrN4vXBDK7JG-a5Ruhm', 'avisos_re', '', '2025-09-29 21:07:45'),
(68, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-30 13:25:00'),
(69, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-30 13:28:32'),
(70, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-30 13:40:48'),
(71, 1, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-30 22:03:21'),
(72, 1, 'vista de carpeta', '14Hjs8feqjiwF6mBsATXyAAPjlWfilDHD', 'operaciones', '', '2025-09-30 22:03:28'),
(73, 1, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-09-30 22:03:34'),
(74, 1, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-09-30 22:03:43'),
(75, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-02 14:11:48'),
(76, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-02 14:12:39'),
(77, 2, 'vista de carpeta', '1gK_0U-F0HICbYcrN4vXBDK7JG-a5Ruhm', 'mantenimiento', '', '2025-10-02 14:12:43'),
(78, 2, 'vista de carpeta', '1P-LiYou8aRLuS8OnSImyrix2sUgCJuWC', 'como', '', '2025-10-02 14:12:48'),
(79, 2, 'vista de carpeta', '1b7p0YU0sSgl9Ps2Qwig4byW_YUbJp8m-', 'Mitologias', '', '2025-10-02 14:12:52'),
(80, 2, 'vista de carpeta', '1aZsabQ2cLv7KUptAO5Zg8fYQoDxPICUh', 'donde', '', '2025-10-02 14:12:58'),
(81, 2, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-10-02 14:13:03'),
(82, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-02 14:19:58'),
(83, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-02 14:25:32'),
(84, 2, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-10-02 14:25:47'),
(85, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-02 14:26:32'),
(86, 2, 'vista de carpeta', '14Hjs8feqjiwF6mBsATXyAAPjlWfilDHD', 'operaciones', '', '2025-10-02 14:27:24'),
(87, 2, 'vista de carpeta', '14Hjs8feqjiwF6mBsATXyAAPjlWfilDHD', 'operaciones', '', '2025-10-02 14:27:52'),
(88, 2, 'vista de carpeta', '14Hjs8feqjiwF6mBsATXyAAPjlWfilDHD', 'operaciones', '', '2025-10-02 14:35:45'),
(89, 2, 'vista de carpeta', '14Hjs8feqjiwF6mBsATXyAAPjlWfilDHD', 'operaciones', '', '2025-10-02 14:37:55'),
(90, 2, 'vista de carpeta', '14Hjs8feqjiwF6mBsATXyAAPjlWfilDHD', 'operaciones', '', '2025-10-02 14:38:39'),
(91, 2, 'vista de carpeta', '14Hjs8feqjiwF6mBsATXyAAPjlWfilDHD', 'operaciones', '', '2025-10-02 14:38:41'),
(92, 2, 'vista de carpeta', '1XDneOYUqLqVTDz-2tZTIN91GCGMlyIWV', 'cosas', '', '2025-10-02 14:39:00'),
(93, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-02 14:43:06'),
(94, 2, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-10-02 14:46:05'),
(95, 1, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-02 20:36:17'),
(96, 1, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-02 20:36:33'),
(97, 1, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-02 20:36:36'),
(98, 1, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-02 20:36:38'),
(99, 1, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-02 20:36:40'),
(100, 1, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-02 20:36:42'),
(101, 1, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-06 14:25:21'),
(102, 1, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-06 14:25:28'),
(103, 1, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-06 15:06:09'),
(104, 1, 'búsqueda', NULL, 'certi', 'Resultados: 1', '2025-10-06 15:08:19'),
(105, 2, 'búsqueda', NULL, 'cert', 'Resultados: 1', '2025-10-06 15:14:45'),
(106, 2, 'búsqueda', NULL, 'cer', 'Resultados: 1', '2025-10-06 15:22:31'),
(107, 2, 'búsqueda', NULL, 'certi', 'Resultados: 1', '2025-10-06 15:22:36'),
(108, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-06 15:24:14'),
(109, 2, 'vista de archivo', '1PFPaIGLtzJArizohHueYjMOGBj_BIOyA', 'estructura_basica_gestor_documental.docx', 'El usuario abrió el archivo en una nueva pestaña.', '2025-10-06 15:24:16'),
(110, 2, 'vista de archivo', '1PFPaIGLtzJArizohHueYjMOGBj_BIOyA', 'estructura_basica_gestor_documental.docx', 'El usuario abrió el archivo en una nueva pestaña.', '2025-10-06 15:24:37'),
(111, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-06 15:30:24'),
(112, 2, 'vista de archivo', '1PFPaIGLtzJArizohHueYjMOGBj_BIOyA', 'estructura_basica_gestor_documental.docx', 'El usuario abrió el archivo en una nueva pestaña.', '2025-10-06 15:30:25'),
(113, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-06 15:31:36'),
(114, 2, 'vista de archivo', '1PFPaIGLtzJArizohHueYjMOGBj_BIOyA', 'estructura_basica_gestor_documental.docx', 'El usuario abrió el archivo en una nueva pestaña.', '2025-10-06 15:31:37'),
(115, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-06 15:37:27'),
(116, 2, 'vista de archivo', '1PFPaIGLtzJArizohHueYjMOGBj_BIOyA', 'estructura_basica_gestor_documental.docx', 'El usuario abrió el archivo en una nueva pestaña.', '2025-10-06 15:37:29'),
(117, 2, 'búsqueda', NULL, 'certi', 'Resultados: 1', '2025-10-06 15:52:36'),
(118, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-06 17:11:37'),
(119, 2, 'vista de archivo', '1PFPaIGLtzJArizohHueYjMOGBj_BIOyA', 'estructura_basica_gestor_documental.docx', 'El usuario abrió el archivo en una nueva pestaña.', '2025-10-06 17:11:40'),
(120, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-06 17:16:54'),
(121, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-06 17:17:44'),
(122, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-06 17:24:23'),
(123, 2, 'vista de archivo', '1PFPaIGLtzJArizohHueYjMOGBj_BIOyA', 'estructura_basica_gestor_documental.docx', 'El usuario abrió el archivo en una nueva pestaña.', '2025-10-06 17:24:25'),
(124, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-06 17:26:16'),
(125, 2, 'vista de archivo', '1PFPaIGLtzJArizohHueYjMOGBj_BIOyA', 'estructura_basica_gestor_documental.docx', 'El usuario abrió el archivo en una nueva pestaña.', '2025-10-06 17:26:19'),
(126, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-06 17:26:32'),
(127, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-06 17:26:40'),
(128, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-06 17:30:04'),
(129, 1, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-24 17:02:58'),
(130, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-24 17:06:17'),
(131, 2, 'vista de carpeta', '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', 'proyecto_gestor_simu', '', '2025-10-24 17:26:17'),
(132, 2, 'vista de carpeta', '1NzLWHalpUhXHduhLecqJNxwOKBLKM6rP', 'subliminares', '', '2025-10-24 17:26:34'),
(133, 2, 'vista de carpeta', '1aZsabQ2cLv7KUptAO5Zg8fYQoDxPICUh', 'donde', '', '2025-10-24 17:26:46'),
(134, 2, 'vista de carpeta', '1P-LiYou8aRLuS8OnSImyrix2sUgCJuWC', 'como', '', '2025-10-24 17:26:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

CREATE TABLE `permisos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `rol_id` int(11) DEFAULT NULL,
  `carpeta_id` int(11) DEFAULT NULL,
  `archivo_id` int(11) DEFAULT NULL,
  `permiso` enum('ver','editar','eliminar','descargar') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reportes`
--

CREATE TABLE `reportes` (
  `id` int(11) NOT NULL,
  `tipo` varchar(100) DEFAULT NULL,
  `generado_por` int(11) DEFAULT NULL,
  `generado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `datos` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`, `descripcion`) VALUES
(1, 'Administrador', 'Acceso total al sistema, gestiona usuarios y configuraciones.'),
(2, 'Coordinador', 'Acceso a reportes, gestión de mantenimientos y supervisión.'),
(3, 'Empleado', 'Acceso limitado según área, puede registrar actividades.'),
(4, 'Cliente', 'Acceso a facturas, reportes y documentación propia.');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol_permisos_carpetas`
--

CREATE TABLE `rol_permisos_carpetas` (
  `cargo_id` int(11) NOT NULL,
  `folder_id` varchar(255) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rol_permisos_carpetas`
--

INSERT INTO `rol_permisos_carpetas` (`cargo_id`, `folder_id`, `descripcion`) VALUES
(2, '1w1X74_EI9LDVhkTrrgA89etnvofGhYSN', NULL),
(5, '14Hjs8feqjiwF6mBsATXyAAPjlWfilDHD', 'Carpeta principal del área Administrativa');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `cargo_id` int(11) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol_id` int(11) DEFAULT NULL,
  `estado_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `cargo_id`, `email`, `password_hash`, `rol_id`, `estado_id`, `creado_en`) VALUES
(1, 'carlos', 1, 'carlos@gmail.com', '$2y$10$rtHZP/0LbapfFlG/xE6k3eOqf252DuDKoETaaXmDGeVyEBR7slzzS', 1, 1, '2025-09-29 14:57:20'),
(2, 'arroz', 2, 'arroz@gmail.com', '$2y$10$2NcKDkFdnf2fvHs4ouFS8ejdA/58N/45QXmDjeMAar0BupBRQA00G', 3, 1, '2025-09-29 15:05:38');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `archivos`
--
ALTER TABLE `archivos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `carpeta_id` (`carpeta_id`),
  ADD KEY `subido_por` (`subido_por`);

--
-- Indices de la tabla `cargos`
--
ALTER TABLE `cargos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `carpetas`
--
ALTER TABLE `carpetas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `creado_por` (`creado_por`);

--
-- Indices de la tabla `estados_usuario`
--
ALTER TABLE `estados_usuario`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `historial`
--
ALTER TABLE `historial`
  ADD PRIMARY KEY (`id`),
  ADD KEY `archivo_id` (`archivo_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `historial_acciones`
--
ALTER TABLE `historial_acciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `rol_id` (`rol_id`),
  ADD KEY `carpeta_id` (`carpeta_id`),
  ADD KEY `archivo_id` (`archivo_id`);

--
-- Indices de la tabla `reportes`
--
ALTER TABLE `reportes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `generado_por` (`generado_por`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `rol_permisos_carpetas`
--
ALTER TABLE `rol_permisos_carpetas`
  ADD PRIMARY KEY (`cargo_id`,`folder_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `cargo_id` (`cargo_id`),
  ADD KEY `rol_id` (`rol_id`),
  ADD KEY `estado_id` (`estado_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `archivos`
--
ALTER TABLE `archivos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cargos`
--
ALTER TABLE `cargos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `carpetas`
--
ALTER TABLE `carpetas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `estados_usuario`
--
ALTER TABLE `estados_usuario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `historial`
--
ALTER TABLE `historial`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_acciones`
--
ALTER TABLE `historial_acciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=135;

--
-- AUTO_INCREMENT de la tabla `permisos`
--
ALTER TABLE `permisos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reportes`
--
ALTER TABLE `reportes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `archivos`
--
ALTER TABLE `archivos`
  ADD CONSTRAINT `archivos_ibfk_1` FOREIGN KEY (`carpeta_id`) REFERENCES `carpetas` (`id`),
  ADD CONSTRAINT `archivos_ibfk_2` FOREIGN KEY (`subido_por`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `carpetas`
--
ALTER TABLE `carpetas`
  ADD CONSTRAINT `carpetas_ibfk_1` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `historial`
--
ALTER TABLE `historial`
  ADD CONSTRAINT `historial_ibfk_1` FOREIGN KEY (`archivo_id`) REFERENCES `archivos` (`id`),
  ADD CONSTRAINT `historial_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `historial_acciones`
--
ALTER TABLE `historial_acciones`
  ADD CONSTRAINT `historial_acciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD CONSTRAINT `permisos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `permisos_ibfk_2` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `permisos_ibfk_3` FOREIGN KEY (`carpeta_id`) REFERENCES `carpetas` (`id`),
  ADD CONSTRAINT `permisos_ibfk_4` FOREIGN KEY (`archivo_id`) REFERENCES `archivos` (`id`);

--
-- Filtros para la tabla `reportes`
--
ALTER TABLE `reportes`
  ADD CONSTRAINT `reportes_ibfk_1` FOREIGN KEY (`generado_por`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `rol_permisos_carpetas`
--
ALTER TABLE `rol_permisos_carpetas`
  ADD CONSTRAINT `fk_rol_permisos` FOREIGN KEY (`cargo_id`) REFERENCES `cargos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`cargo_id`) REFERENCES `cargos` (`id`),
  ADD CONSTRAINT `usuarios_ibfk_2` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `usuarios_ibfk_3` FOREIGN KEY (`estado_id`) REFERENCES `estados_usuario` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
