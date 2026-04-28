-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Servidor: sql100.infinityfree.com
-- Tiempo de generación: 27-04-2026 a las 02:14:06
-- Versión del servidor: 11.4.10-MariaDB
-- Versión de PHP: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `if0_41551522_ioriscans`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `capitulos`
--

CREATE TABLE `capitulos` (
  `id` int(11) NOT NULL,
  `obra_id` int(11) DEFAULT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `contenido` text DEFAULT NULL,
  `fecha_subida` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `capitulos`
--

INSERT INTO `capitulos` (`id`, `obra_id`, `titulo`, `slug`, `contenido`, `fecha_subida`) VALUES
(1, 1, 'Capítulo 1: El encuentro', 'capitulo-1-el-encuentro', '[\"https://picsum.photos/800/1200?random=1\", \"https://picsum.photos/800/1200?random=2\"]', '2025-12-17 12:39:52'),
(2, 1, 'Capítulo 2: La duda', 'capitulo-2-la-duda', '[\"https://picsum.photos/800/1200?random=3\", \"https://picsum.photos/800/1200?random=4\"]', '2025-12-17 12:39:52'),
(9, 3, 'Capitulo 0.0 Solo Leveling', 'capitulo-0-0-solo-leveling', '[\"assets\\/img\\/capitulos\\/Solo_Leveling\\/Capitulo_0_0_Solo_Leveling\\/000_Captulo0.00SubidoporKnightNoScanlation00.jpg\",\"assets\\/img\\/capitulos\\/Solo_Leveling\\/Capitulo_0_0_Solo_Leveling\\/001_Captulo0.00SubidoporKnightNoScanlation01.jpg\",\"assets\\/img\\/capitulos\\/Solo_Leveling\\/Capitulo_0_0_Solo_Leveling\\/002_Captulo0.00SubidoporKnightNoScanlation02.jpg\",\"assets\\/img\\/capitulos\\/Solo_Leveling\\/Capitulo_0_0_Solo_Leveling\\/003_Captulo0.00SubidoporKnightNoScanlation03.jpg\",\"assets\\/img\\/capitulos\\/Solo_Leveling\\/Capitulo_0_0_Solo_Leveling\\/004_Captulo0.00SubidoporKnightNoScanlation04.jpg\",\"assets\\/img\\/capitulos\\/Solo_Leveling\\/Capitulo_0_0_Solo_Leveling\\/005_Captulo0.00SubidoporKnightNoScanlation05.jpg\",\"assets\\/img\\/capitulos\\/Solo_Leveling\\/Capitulo_0_0_Solo_Leveling\\/006_Captulo0.00SubidoporKnightNoScanlation06.jpg\",\"assets\\/img\\/capitulos\\/Solo_Leveling\\/Capitulo_0_0_Solo_Leveling\\/007_Captulo0.00SubidoporKnightNoScanlation07.jpg\",\"assets\\/img\\/capitulos\\/Solo_Leveling\\/Capitulo_0_0_Solo_Leveling\\/008_Captulo0.00SubidoporKnightNoScanlation08.jpg\",\"assets\\/img\\/capitulos\\/Solo_Leveling\\/Capitulo_0_0_Solo_Leveling\\/009_Captulo0.00SubidoporKnightNoScanlation09.jpg\",\"assets\\/img\\/capitulos\\/Solo_Leveling\\/Capitulo_0_0_Solo_Leveling\\/010_Captulo0.00SubidoporKnightNoScanlation10.jpg\",\"assets\\/img\\/capitulos\\/Solo_Leveling\\/Capitulo_0_0_Solo_Leveling\\/011_Captulo0.00SubidoporKnightNoScanlation11.jpg\",\"assets\\/img\\/capitulos\\/Solo_Leveling\\/Capitulo_0_0_Solo_Leveling\\/012_Captulo0.00SubidoporKnightNoScanlation12.jpg\",\"assets\\/img\\/capitulos\\/Solo_Leveling\\/Capitulo_0_0_Solo_Leveling\\/013_Captulo0.00SubidoporKnightNoScanlation13.jpg\",\"assets\\/img\\/capitulos\\/Solo_Leveling\\/Capitulo_0_0_Solo_Leveling\\/014_Captulo0.00SubidoporKnightNoScanlation14.jpg\",\"assets\\/img\\/capitulos\\/Solo_Leveling\\/Capitulo_0_0_Solo_Leveling\\/015_Captulo0.00SubidoporKnightNoScanlation15.jpg\",\"assets\\/img\\/capitulos\\/Solo_Leveling\\/Capitulo_0_0_Solo_Leveling\\/016_Captulo0.00SubidoporKnightNoScanlation16.jpg\"]', '2026-04-01 14:34:30'),
(16, 4, 'Capitulo 0: Prueba Webhook', 'capitulo-0-prueba-webhook', '[\"assets\\/img\\/capitulos\\/Prueba_Modificado\\/Capitulo_0_Prueba_Webhook\\/000_logoconfondoblanco.png\"]', '2026-04-08 14:43:40');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `capitulos_leidos`
--

CREATE TABLE `capitulos_leidos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `capitulo_id` int(11) NOT NULL,
  `fecha_leido` datetime DEFAULT current_timestamp(),
  `ultima_pagina` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `capitulos_leidos`
--

INSERT INTO `capitulos_leidos` (`id`, `usuario_id`, `capitulo_id`, `fecha_leido`, `ultima_pagina`) VALUES
(12, 5, 9, '2026-04-07 09:06:36', 17),
(20, 2, 2, '2026-04-09 14:10:44', 2),
(21, 1, 9, '2026-04-13 12:22:50', 17);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `comentarios`
--

CREATE TABLE `comentarios` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `capitulo_id` int(11) NOT NULL,
  `texto` text NOT NULL,
  `fecha` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `favoritos`
--

CREATE TABLE `favoritos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `obra_id` int(11) NOT NULL,
  `fecha_agregado` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `favoritos`
--

INSERT INTO `favoritos` (`id`, `usuario_id`, `obra_id`, `fecha_agregado`) VALUES
(3, 4, 3, '2026-01-13 09:06:21'),
(9, 2, 3, '2026-04-09 10:00:51'),
(10, 2, 1, '2026-04-09 14:10:40'),
(11, 2, 4, '2026-04-09 14:21:29'),
(12, 5, 3, '2026-04-20 08:18:42'),
(13, 5, 1, '2026-04-20 08:18:48');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `foro_respuestas`
--

CREATE TABLE `foro_respuestas` (
  `id` int(11) NOT NULL,
  `tema_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `mensaje` text NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `fecha_edicion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `foro_respuestas`
--

INSERT INTO `foro_respuestas` (`id`, `tema_id`, `usuario_id`, `mensaje`, `fecha`, `fecha_edicion`) VALUES
(1, 1, 2, 'Pues esta bastante bien la verdad', '2026-04-13 10:15:00', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `foro_temas`
--

CREATE TABLE `foro_temas` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `contenido` text NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `categoria` varchar(50) NOT NULL DEFAULT 'General',
  `fecha_edicion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `foro_temas`
--

INSERT INTO `foro_temas` (`id`, `usuario_id`, `titulo`, `slug`, `contenido`, `fecha`, `categoria`, `fecha_edicion`) VALUES
(1, 4, '¿Qué opináis sobre el nuevo manhwa solo leveling?', 'que-opinais-sobre-el-nuevo-manhwa-solo-leveling', 'Acabo de empezar el manwha y me parece increible, que os parece a vosotros, dejadme vuestras opiniones', '2026-01-13 09:14:52', 'General', '2026-01-13 09:30:36');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `generos`
--

CREATE TABLE `generos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `generos`
--

INSERT INTO `generos` (`id`, `nombre`) VALUES
(1, 'Acción'),
(2, 'Artes Marciales'),
(3, 'Aventura'),
(4, 'Comedia'),
(5, 'Drama'),
(6, 'Fantasía'),
(7, 'Fantasía Oscura'),
(8, 'Isekai'),
(9, 'Reencarnación'),
(10, 'Romance'),
(11, 'Sci-Fi'),
(12, 'Sistema'),
(13, 'Slice of Life');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `obras`
--

CREATE TABLE `obras` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `autor` varchar(100) NOT NULL,
  `sinopsis` text DEFAULT NULL,
  `portada` varchar(255) DEFAULT NULL,
  `fecha_subida` datetime DEFAULT current_timestamp(),
  `visitas` int(11) DEFAULT 0,
  `tipo_obra` enum('Manga','Manhwa','Manhua','Novela','Donghua','Libro','Desconocido') DEFAULT 'Desconocido',
  `demografia` enum('Shounen','Seinen','Shoujo','Josei','Kodomo','Desconocido') DEFAULT 'Desconocido',
  `estado_publicacion` enum('En Emisión','Hiatus','Finalizado','Cancelado') NOT NULL DEFAULT 'En Emisión'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `obras`
--

INSERT INTO `obras` (`id`, `titulo`, `slug`, `autor`, `sinopsis`, `portada`, `fecha_subida`, `visitas`, `tipo_obra`, `demografia`, `estado_publicacion`) VALUES
(1, 'Amor en la Ciudad', 'amor-en-la-ciudad', 'Laura Ruiz', 'Dos extraños se cruzan en el metro.', 'https://picsum.photos/300/450?random=1', '2025-12-17 12:39:52', 66, 'Libro', 'Desconocido', 'En Emisión'),
(3, 'Solo Leveling', 'solo-leveling', 'Chu-Gong ha', 'Hace 10 años, después de que \"La Puerta\" que conectaba el mundo real con el mundo de los monstruos se abriera, algunas de las personas recibieron el poder de cazar los monstruos que vivían al otro lado de esta. Se les conoce como \"cazadores\". Sin embargo, no todos los cazadores son poderosos. Sung Jin-Woo, un cazador de rango E, es alguien que tiene que arriesgar su vida en humildes calabozos, lo llaman el \"más débil del mundo\". Al no tener habilidades para mostrar, apenas gana dinero luchando en mazmorras de bajo nivel... Al menos hasta que se encontro con una mazmorra oculta durante una incursión. Luego de estar en el borde de la muerte,Sung Jin-Woo tiene un \"Segundo Despertar\" y con este consigue un poder invaluable.', 'assets/img/portadas/1765972097_SoloLeveling.jpg', '2025-12-17 12:48:17', 212, 'Manhwa', 'Shounen', 'En Emisión'),
(4, 'Prueba Modificado', 'prueba-modificado', 'AutorPrueba', 'Resumen prueba', 'assets/img/portadas/portada_1775627972.png', '2026-04-08 07:59:33', 22, 'Manhwa', 'Seinen', 'En Emisión');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `obra_genero`
--

CREATE TABLE `obra_genero` (
  `obra_id` int(11) NOT NULL,
  `genero_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `obra_genero`
--

INSERT INTO `obra_genero` (`obra_id`, `genero_id`) VALUES
(3, 1),
(3, 3),
(1, 5),
(3, 6),
(4, 6),
(3, 7),
(1, 10);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `resenas`
--

CREATE TABLE `resenas` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `obra_id` int(11) NOT NULL,
  `texto` text NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `puntuacion` int(11) NOT NULL DEFAULT 5
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `resenas`
--

INSERT INTO `resenas` (`id`, `usuario_id`, `obra_id`, `texto`, `fecha`, `puntuacion`) VALUES
(1, 4, 3, 'Me parece increible esta historia, lo nunca antes visto', '2026-01-13 09:09:43', 5),
(2, 2, 4, 'un cuatro', '2026-04-20 14:35:11', 4);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('admin','lector') DEFAULT 'lector',
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `foto` varchar(255) DEFAULT NULL,
  `fecha_desbloqueo` datetime DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expira` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password`, `rol`, `fecha_registro`, `foto`, `fecha_desbloqueo`, `reset_token`, `reset_expira`) VALUES
(1, 'Administrador', 'admin@lectorapp.com', 'admin', 'admin', '2025-12-17 12:39:52', NULL, NULL, NULL, NULL),
(2, 'Lector1', 'lector@test.com', 'lector', 'lector', '2025-12-17 12:39:52', NULL, NULL, NULL, NULL),
(4, 'iorittsu', 'ioritzecheverria@gmail.com', '$2y$10$jH7R7EBcEIPOAxvRb1WZCO9K9cvrCvbc811nD6zdYFjGAP5Le0wBG', 'lector', '2026-01-13 08:47:18', 'assets/img/avatars/user_4_1768291578.jpg', NULL, 'f4490cfa25465f6632b2d0a8af54938c0b80e3fbe2c9c5dd018e2495522eb75e', '2026-04-17 15:23:44'),
(5, 'Papito', 'papito@gmail.com', '$2y$10$Kx/nR81bv10lrt69GSiOx.iEHe918w98qLGhk5tqYsdnGr9ZdazB6', 'lector', '2026-01-13 09:01:40', NULL, '2026-04-14 09:06:13', NULL, NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `capitulos`
--
ALTER TABLE `capitulos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `obra_id` (`obra_id`);

--
-- Indices de la tabla `capitulos_leidos`
--
ALTER TABLE `capitulos_leidos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_leido` (`usuario_id`,`capitulo_id`),
  ADD KEY `fk_leidos_cap` (`capitulo_id`);

--
-- Indices de la tabla `comentarios`
--
ALTER TABLE `comentarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `capitulo_id` (`capitulo_id`);

--
-- Indices de la tabla `favoritos`
--
ALTER TABLE `favoritos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_fav` (`usuario_id`,`obra_id`),
  ADD KEY `obra_id` (`obra_id`);

--
-- Indices de la tabla `foro_respuestas`
--
ALTER TABLE `foro_respuestas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tema_id` (`tema_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `foro_temas`
--
ALTER TABLE `foro_temas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `generos`
--
ALTER TABLE `generos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre_unico` (`nombre`);

--
-- Indices de la tabla `obras`
--
ALTER TABLE `obras`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indices de la tabla `obra_genero`
--
ALTER TABLE `obra_genero`
  ADD PRIMARY KEY (`obra_id`,`genero_id`),
  ADD KEY `idx_genero` (`genero_id`);

--
-- Indices de la tabla `resenas`
--
ALTER TABLE `resenas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `obra_id` (`obra_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `capitulos`
--
ALTER TABLE `capitulos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `capitulos_leidos`
--
ALTER TABLE `capitulos_leidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de la tabla `comentarios`
--
ALTER TABLE `comentarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `favoritos`
--
ALTER TABLE `favoritos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `foro_respuestas`
--
ALTER TABLE `foro_respuestas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `foro_temas`
--
ALTER TABLE `foro_temas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `generos`
--
ALTER TABLE `generos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `obras`
--
ALTER TABLE `obras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `resenas`
--
ALTER TABLE `resenas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `capitulos`
--
ALTER TABLE `capitulos`
  ADD CONSTRAINT `capitulos_ibfk_1` FOREIGN KEY (`obra_id`) REFERENCES `obras` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `capitulos_leidos`
--
ALTER TABLE `capitulos_leidos`
  ADD CONSTRAINT `fk_leidos_cap` FOREIGN KEY (`capitulo_id`) REFERENCES `capitulos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_leidos_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `comentarios`
--
ALTER TABLE `comentarios`
  ADD CONSTRAINT `comentarios_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comentarios_ibfk_2` FOREIGN KEY (`capitulo_id`) REFERENCES `capitulos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `favoritos`
--
ALTER TABLE `favoritos`
  ADD CONSTRAINT `favoritos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favoritos_ibfk_2` FOREIGN KEY (`obra_id`) REFERENCES `obras` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `foro_respuestas`
--
ALTER TABLE `foro_respuestas`
  ADD CONSTRAINT `foro_respuestas_ibfk_1` FOREIGN KEY (`tema_id`) REFERENCES `foro_temas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `foro_respuestas_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `foro_temas`
--
ALTER TABLE `foro_temas`
  ADD CONSTRAINT `foro_temas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `obra_genero`
--
ALTER TABLE `obra_genero`
  ADD CONSTRAINT `fk_obra_gen_genero` FOREIGN KEY (`genero_id`) REFERENCES `generos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_obra_gen_obra` FOREIGN KEY (`obra_id`) REFERENCES `obras` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `resenas`
--
ALTER TABLE `resenas`
  ADD CONSTRAINT `resenas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resenas_ibfk_2` FOREIGN KEY (`obra_id`) REFERENCES `obras` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
