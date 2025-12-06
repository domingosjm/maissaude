-- Sistema de Usuários e Permissões

-- Tabela de perfis/roles
CREATE TABLE IF NOT EXISTS user_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de módulos do sistema
CREATE TABLE IF NOT EXISTS system_modules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    code VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(50),
    url VARCHAR(255),
    display_order INT DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de permissões (role -> módulos)
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL,
    module_id INT NOT NULL,
    can_view TINYINT(1) DEFAULT 0,
    can_create TINYINT(1) DEFAULT 0,
    can_edit TINYINT(1) DEFAULT 0,
    can_delete TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES user_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES system_modules(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_module (role_id, module_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar campos na tabela users
ALTER TABLE users 
  ADD COLUMN username VARCHAR(100) NULL AFTER name,
  ADD COLUMN role_id INT NULL AFTER username,
  ADD COLUMN full_name VARCHAR(200) NULL AFTER role_id,
  ADD COLUMN phone VARCHAR(20) NULL AFTER email,
  ADD COLUMN department VARCHAR(100) NULL AFTER phone,
  ADD COLUMN status ENUM('ativo','inativo','suspenso') DEFAULT 'ativo' AFTER department,
  ADD COLUMN last_login DATETIME NULL AFTER status,
  ADD UNIQUE KEY unique_username (username),
  ADD FOREIGN KEY fk_user_role (role_id) REFERENCES user_roles(id) ON DELETE SET NULL;

-- Inserir perfis/roles
INSERT INTO user_roles (name, description) VALUES
('Diretor Geral', 'Acesso total ao sistema'),
('Diretor Administrativo', 'Gestão administrativa e financeira'),
('Diretor Técnico', 'Gestão técnica de departamentos específicos'),
('Médico', 'Acesso a consultas e prontuários'),
('Enfermeiro', 'Acesso a atendimentos e procedimentos'),
('Farmacêutico', 'Gestão de farmácia e medicamentos'),
('Psicólogo', 'Atendimento psicológico'),
('Psiquiatra', 'Atendimento psiquiátrico'),
('Técnico de Laboratório', 'Gestão de exames laboratoriais'),
('Analista Clínico', 'Análise de exames'),
('Recepcionista', 'Recepção e agendamentos'),
('Técnico de TI', 'Suporte técnico e configurações'),
('Visualizador', 'Apenas visualização (sem edição)');

-- Inserir módulos do sistema
INSERT INTO system_modules (name, code, description, icon, url, display_order) VALUES
('Dashboard', 'dashboard', 'Painel principal com estatísticas', 'bi-speedometer2', 'dashboard.php', 1),
('Recepção', 'recepcao', 'Cadastro de pacientes e agendamentos', 'bi-person-plus', 'recepcao.php', 2),
('Consultas', 'consultas', 'Gestão de consultas médicas', 'bi-clipboard2-pulse', 'consultas.php', 3),
('Internação', 'internacao', 'Gestão de internações', 'bi-hospital', 'internacao.php', 4),
('Enfermagem', 'enfermagem', 'Procedimentos de enfermagem', 'bi-heart-pulse', 'enfermagem.php', 5),
('Laboratório', 'laboratorio', 'Gestão de exames laboratoriais', 'bi-droplet', 'laboratorio.php', 6),
('Farmácia', 'farmacia', 'Gestão de medicamentos', 'bi-capsule', 'farmacia.php', 7),
('Psicologia', 'psicologia', 'Atendimento psicológico', 'bi-brain', 'psicologia.php', 8),
('Financeiro', 'financeiro', 'Gestão financeira e faturamento', 'bi-cash-coin', 'financeiro.php', 9),
('Relatórios', 'relatorios', 'Relatórios gerenciais', 'bi-graph-up', 'relatorios.php', 10),
('Configurações', 'configuracoes', 'Configurações do sistema', 'bi-gear', 'configuracoes.php', 11),
('Usuários', 'usuarios', 'Gestão de usuários e permissões', 'bi-people', 'usuarios.php', 12);

-- Permissões para Diretor Geral (acesso total)
INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 1, id, 1, 1, 1, 1 FROM system_modules;

-- Permissões para Diretor Administrativo
INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 2, id, 1, 1, 1, 1 FROM system_modules WHERE code IN ('dashboard', 'recepcao', 'financeiro', 'relatorios', 'usuarios');

INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 2, id, 1, 0, 0, 0 FROM system_modules WHERE code IN ('consultas', 'internacao', 'enfermagem', 'laboratorio', 'farmacia', 'psicologia');

-- Permissões para Diretor Técnico (Laboratório)
INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 3, id, 1, 1, 1, 1 FROM system_modules WHERE code IN ('dashboard', 'laboratorio', 'relatorios');

INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 3, id, 1, 0, 0, 0 FROM system_modules WHERE code IN ('recepcao', 'consultas');

-- Permissões para Médico
INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 4, id, 1, 1, 1, 0 FROM system_modules WHERE code IN ('dashboard', 'consultas', 'internacao');

INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 4, id, 1, 0, 0, 0 FROM system_modules WHERE code IN ('recepcao', 'laboratorio', 'farmacia', 'relatorios');

-- Permissões para Enfermeiro
INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 5, id, 1, 1, 1, 0 FROM system_modules WHERE code IN ('dashboard', 'enfermagem', 'internacao');

INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 5, id, 1, 0, 0, 0 FROM system_modules WHERE code IN ('recepcao', 'consultas', 'farmacia');

-- Permissões para Farmacêutico
INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 6, id, 1, 1, 1, 1 FROM system_modules WHERE code IN ('dashboard', 'farmacia');

INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 6, id, 1, 0, 0, 0 FROM system_modules WHERE code IN ('recepcao', 'consultas');

-- Permissões para Psicólogo
INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 7, id, 1, 1, 1, 0 FROM system_modules WHERE code IN ('dashboard', 'psicologia');

INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 7, id, 1, 0, 0, 0 FROM system_modules WHERE code IN ('recepcao', 'consultas');

-- Permissões para Psiquiatra
INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 8, id, 1, 1, 1, 0 FROM system_modules WHERE code IN ('dashboard', 'psicologia', 'consultas');

INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 8, id, 1, 0, 0, 0 FROM system_modules WHERE code IN ('recepcao', 'farmacia');

-- Permissões para Técnico de Laboratório
INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 9, id, 1, 1, 1, 1 FROM system_modules WHERE code = 'laboratorio';

INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 9, id, 1, 0, 0, 0 FROM system_modules WHERE code IN ('dashboard', 'recepcao');

-- Permissões para Analista Clínico
INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 10, id, 1, 1, 1, 0 FROM system_modules WHERE code = 'laboratorio';

INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 10, id, 1, 0, 0, 0 FROM system_modules WHERE code IN ('dashboard', 'recepcao');

-- Permissões para Recepcionista
INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 11, id, 1, 1, 1, 0 FROM system_modules WHERE code IN ('dashboard', 'recepcao', 'financeiro');

-- Permissões para Técnico de TI
INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 12, id, 1, 1, 1, 1 FROM system_modules WHERE code IN ('configuracoes', 'usuarios');

INSERT INTO role_permissions (role_id, module_id, can_view, can_create, can_edit, can_delete)
SELECT 12, id, 1, 0, 0, 0 FROM system_modules WHERE code NOT IN ('configuracoes', 'usuarios');

-- Criar usuários dos funcionários
-- Senha padrão: senha123 (hash MD5)

-- 1. Heloísa da Aldina - Diretora Geral
INSERT INTO users (username, password, role_id, full_name, department, status) 
VALUES ('heloisa.aldina', MD5('senha123'), 1, 'Heloísa da Aldina', 'Direção Geral', 'ativo');

-- 2. Zulfa Boavida Nhanssen - Diretora de Administração e Financeiro
INSERT INTO users (username, password, role_id, full_name, department, status) 
VALUES ('zulfa.nhanssen', MD5('senha123'), 2, 'Zulfa Boavida Nhanssen', 'Administração e Financeiro', 'ativo');

-- 3. Cláudio Celestino Diogo - Director técnico do laboratório
INSERT INTO users (username, password, role_id, full_name, department, status) 
VALUES ('claudio.diogo', MD5('senha123'), 3, 'Cláudio Celestino Diogo', 'Laboratório', 'ativo');

-- 4. Malber Muchiguel - Directora técnica da farmácia
INSERT INTO users (username, password, role_id, full_name, department, status) 
VALUES ('malber.muchiguel', MD5('senha123'), 6, 'Malber Muchiguel', 'Farmácia', 'ativo');

-- 5. Laurinda Rosa Canaogai - Directora técnica da psicologia
INSERT INTO users (username, password, role_id, full_name, department, status) 
VALUES ('laurinda.canaogai', MD5('senha123'), 7, 'Laurinda Rosa Canaogai', 'Psicologia', 'ativo');

-- 6. Elizeth de fidalgo João - analista clínica
INSERT INTO users (username, password, role_id, full_name, department, status) 
VALUES ('elizeth.joao', MD5('senha123'), 10, 'Elizeth de Fidalgo João', 'Laboratório', 'ativo');

-- 7. Inarah Madvgi - analista clínica
INSERT INTO users (username, password, role_id, full_name, department, status) 
VALUES ('inarah.madvgi', MD5('senha123'), 10, 'Inarah Madvgi', 'Laboratório', 'ativo');

-- 8. Elias Filipe Chimué - farmacêutico
INSERT INTO users (username, password, role_id, full_name, department, status) 
VALUES ('elias.chimue', MD5('senha123'), 6, 'Elias Filipe Chimué', 'Farmácia', 'ativo');

-- 9. Tânia Olívia Leão belo - psicólogo
INSERT INTO users (username, password, role_id, full_name, department, status) 
VALUES ('tania.belo', MD5('senha123'), 7, 'Tânia Olívia Leão Belo', 'Psicologia', 'ativo');

-- 10. Maria Luisa de Rosário - psiquiatra
INSERT INTO users (username, password, role_id, full_name, department, status) 
VALUES ('maria.rosario', MD5('senha123'), 8, 'Maria Luisa de Rosário', 'Psiquiatria', 'ativo');

-- 11. Domingos João Mangação - técnico de informática
INSERT INTO users (username, password, role_id, full_name, department, status) 
VALUES ('domingos.mangacao', MD5('senha123'), 12, 'Domingos João Mangação', 'TI', 'ativo');

-- 12. Juliana Mateus Feniasse - recepcionista
INSERT INTO users (username, password, role_id, full_name, department, status) 
VALUES ('juliana.feniasse', MD5('senha123'), 11, 'Juliana Mateus Feniasse', 'Recepção', 'ativo');

-- 13. Evanilda Arnaldo Ziba - Medica de clinica geral
INSERT INTO users (username, password, role_id, full_name, department, status) 
VALUES ('evanilda.ziba', MD5('senha123'), 4, 'Evanilda Arnaldo Ziba Tomo', 'Medicina Geral', 'ativo');

-- 14. Ivan Mac Donald - enfermeiro
INSERT INTO users (username, password, role_id, full_name, department, status) 
VALUES ('ivan.macdonald', MD5('senha123'), 5, 'Ivan Mac Donald', 'Enfermagem', 'ativo');
