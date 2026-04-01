-- 1. Crear la Base de Datos
DROP DATABASE IF EXISTS lectorapp;
CREATE DATABASE IF NOT EXISTS lectorapp;
USE lectorapp;

-- 2. Tabla de Usuarios
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'lector') DEFAULT 'lector',
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 3. Tabla de Obras
CREATE TABLE obras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    autor VARCHAR(100) NOT NULL,
    generos VARCHAR(100), -- Ej: "Acción, Fantasía"
    sinopsis TEXT,
    portada VARCHAR(255), -- URL de la imagen
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 4. Tabla de Capítulos
CREATE TABLE capitulos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    obra_id INT,
    titulo VARCHAR(255),
    contenido TEXT, -- Aquí irán las URLs de las imágenes separadas por comas o JSON
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE
);

-- 5. DATOS DE PRUEBA (INSERT)

-- Usuario Admin (Contraseña: admin)
-- Nota: En un caso real usaríamos password_hash, pero para probar usamos texto plano por ahora
INSERT INTO usuarios (nombre, email, password, rol) VALUES 
('Administrador', 'admin@lectorapp.com', 'admin', 'admin'),
('Lector1', 'lector@test.com', 'lector', 'lector');

-- Obras de prueba
INSERT INTO obras (titulo, autor, generos, sinopsis, portada) VALUES 
('Amor en la Ciudad', 'Laura Ruiz', 'Romance, Drama', 'Dos extraños se cruzan en el metro.', 'https://picsum.photos/300/450?random=1'),
('Cazador de Sombras', 'Chugong', 'Acción, Fantasía', 'El cazador más débil se convierte en el más fuerte.', 'https://picsum.photos/300/450?random=2');

-- Capítulos para la Obra 1 (Amor en la Ciudad)
INSERT INTO capitulos (obra_id, titulo, contenido) VALUES 
(1, 'Capítulo 1: El encuentro', '["https://picsum.photos/800/1200?random=1", "https://picsum.photos/800/1200?random=2"]'),
(1, 'Capítulo 2: La duda', '["https://picsum.photos/800/1200?random=3", "https://picsum.photos/800/1200?random=4"]');

-- Capítulos para la Obra 2 (Cazador de Sombras)
INSERT INTO capitulos (obra_id, titulo, contenido) VALUES 
(2, 'Capítulo 1: El despertar', '["https://picsum.photos/800/1200?random=5", "https://picsum.photos/800/1200?random=6"]'),
(2, 'Capítulo 2: Misiones diarias', '["https://picsum.photos/800/1200?random=7", "https://picsum.photos/800/1200?random=8"]'),
(2, 'Capítulo 3: El jefe de mazmorra', '["https://picsum.photos/800/1200?random=9"]');




CREATE TABLE favoritos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    obra_id INT NOT NULL,
    fecha_agregado DATETIME DEFAULT CURRENT_TIMESTAMP,
    -- Claves foráneas: Si se borra el usuario o la obra, se borra el favorito
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE,
    -- Evitar duplicados: Un usuario no puede guardar 2 veces la misma obra
    UNIQUE KEY unique_fav (usuario_id, obra_id)
);

ALTER TABLE usuarios ADD foto VARCHAR(255) DEFAULT NULL;


CREATE TABLE comentarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    capitulo_id INT NOT NULL,
    texto TEXT NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (capitulo_id) REFERENCES capitulos(id) ON DELETE CASCADE
);

CREATE TABLE resenas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    obra_id INT NOT NULL,
    texto TEXT NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE CASCADE
);


-- Tabla para los Temas (Hilos principales)
CREATE TABLE foro_temas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    contenido TEXT NOT NULL, -- El mensaje principal del creador
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabla para las Respuestas de la gente
CREATE TABLE foro_respuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tema_id INT NOT NULL,
    usuario_id INT NOT NULL,
    mensaje TEXT NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tema_id) REFERENCES foro_temas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

ALTER TABLE foro_temas ADD categoria VARCHAR(50) NOT NULL DEFAULT 'General';

-- Añadir fecha de edición a los TEMAS
ALTER TABLE foro_temas ADD fecha_edicion DATETIME DEFAULT NULL;

-- Añadir fecha de edición a las RESPUESTAS
ALTER TABLE foro_respuestas ADD fecha_edicion DATETIME DEFAULT NULL;

ALTER TABLE obras ADD visitas INT DEFAULT 0;