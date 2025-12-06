-- Atualizar usuários existentes com emails e vincular à tabela system_users

-- Adicionar emails aos usuários do system_users
UPDATE system_users SET email = 'heloisa.aldina@maissaude.co.mz' WHERE username = 'heloisa.aldina';
UPDATE system_users SET email = 'zulfa.nhanssen@maissaude.co.mz' WHERE username = 'zulfa.nhanssen';
UPDATE system_users SET email = 'claudio.diogo@maissaude.co.mz' WHERE username = 'claudio.diogo';
UPDATE system_users SET email = 'malber.muchiguel@maissaude.co.mz' WHERE username = 'malber.muchiguel';
UPDATE system_users SET email = 'laurinda.canaogai@maissaude.co.mz' WHERE username = 'laurinda.canaogai';
UPDATE system_users SET email = 'elizeth.joao@maissaude.co.mz' WHERE username = 'elizeth.joao';
UPDATE system_users SET email = 'inarah.madvgi@maissaude.co.mz' WHERE username = 'inarah.madvgi';
UPDATE system_users SET email = 'elias.chimue@maissaude.co.mz' WHERE username = 'elias.chimue';
UPDATE system_users SET email = 'tania.belo@maissaude.co.mz' WHERE username = 'tania.belo';
UPDATE system_users SET email = 'maria.rosario@maissaude.co.mz' WHERE username = 'maria.rosario';
UPDATE system_users SET email = 'domingos.mangacao@maissaude.co.mz' WHERE username = 'domingos.mangacao';
UPDATE system_users SET email = 'juliana.feniasse@maissaude.co.mz' WHERE username = 'juliana.feniasse';
UPDATE system_users SET email = 'evanilda.ziba@maissaude.co.mz' WHERE username = 'evanilda.ziba';
UPDATE system_users SET email = 'ivan.macdonald@maissaude.co.mz' WHERE username = 'ivan.macdonald';

-- Inserir usuários na tabela users (para compatibilidade com o sistema de login atual)
-- Senha: senha123 (hash bcrypt)

INSERT INTO users (name, email, password) VALUES
('Heloísa da Aldina', 'heloisa.aldina@maissaude.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Zulfa Boavida Nhanssen', 'zulfa.nhanssen@maissaude.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Cláudio Celestino Diogo', 'claudio.diogo@maissaude.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Malber Muchiguel', 'malber.muchiguel@maissaude.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Laurinda Rosa Canaogai', 'laurinda.canaogai@maissaude.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Elizeth de Fidalgo João', 'elizeth.joao@maissaude.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Inarah Madvgi', 'inarah.madvgi@maissaude.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Elias Filipe Chimué', 'elias.chimue@maissaude.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Tânia Olívia Leão Belo', 'tania.belo@maissaude.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Maria Luisa de Rosário', 'maria.rosario@maissaude.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Domingos João Mangação', 'domingos.mangacao@maissaude.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Juliana Mateus Feniasse', 'juliana.feniasse@maissaude.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Evanilda Arnaldo Ziba Tomo', 'evanilda.ziba@maissaude.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Ivan Mac Donald', 'ivan.macdonald@maissaude.co.mz', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE password = VALUES(password);

-- Vincular users com system_users via email
ALTER TABLE users ADD COLUMN system_user_id INT NULL AFTER id;
ALTER TABLE users ADD COLUMN role_id INT NULL AFTER system_user_id;

UPDATE users u 
JOIN system_users su ON u.email COLLATE utf8mb4_unicode_ci = su.email 
SET u.system_user_id = su.id, u.role_id = su.role_id;
