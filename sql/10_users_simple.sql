-- Sistema de Usuários e Permissões - Versão Simplificada

-- Criar tabelas novas
CREATE TABLE IF NOT EXISTS user_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS role_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL,
    module_id INT NOT NULL,
    can_view TINYINT(1) DEFAULT 0,
    can_create TINYINT(1) DEFAULT 0,
    can_edit TINYINT(1) DEFAULT 0,
    can_delete TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_module (role_id, module_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role_id INT NULL,
    full_name VARCHAR(200) NULL,
    email VARCHAR(200) NULL,
    phone VARCHAR(20) NULL,
    department VARCHAR(100) NULL,
    status ENUM('ativo','inativo','suspenso') DEFAULT 'ativo',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
('Técnico de TI', 'Suporte técnico e configurações');

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

-- Criar usuários dos funcionários (senha padrão: senha123)
INSERT INTO system_users (username, password, role_id, full_name, department, status) VALUES
('heloisa.aldina', MD5('senha123'), 1, 'Heloísa da Aldina', 'Direção Geral', 'ativo'),
('zulfa.nhanssen', MD5('senha123'), 2, 'Zulfa Boavida Nhanssen', 'Administração e Financeiro', 'ativo'),
('claudio.diogo', MD5('senha123'), 3, 'Cláudio Celestino Diogo', 'Laboratório', 'ativo'),
('malber.muchiguel', MD5('senha123'), 6, 'Malber Muchiguel', 'Farmácia', 'ativo'),
('laurinda.canaogai', MD5('senha123'), 7, 'Laurinda Rosa Canaogai', 'Psicologia', 'ativo'),
('elizeth.joao', MD5('senha123'), 10, 'Elizeth de Fidalgo João', 'Laboratório', 'ativo'),
('inarah.madvgi', MD5('senha123'), 10, 'Inarah Madvgi', 'Laboratório', 'ativo'),
('elias.chimue', MD5('senha123'), 6, 'Elias Filipe Chimué', 'Farmácia', 'ativo'),
('tania.belo', MD5('senha123'), 7, 'Tânia Olívia Leão Belo', 'Psicologia', 'ativo'),
('maria.rosario', MD5('senha123'), 8, 'Maria Luisa de Rosário', 'Psiquiatria', 'ativo'),
('domingos.mangacao', MD5('senha123'), 12, 'Domingos João Mangação', 'TI', 'ativo'),
('juliana.feniasse', MD5('senha123'), 11, 'Juliana Mateus Feniasse', 'Recepção', 'ativo'),
('evanilda.ziba', MD5('senha123'), 4, 'Evanilda Arnaldo Ziba Tomo', 'Medicina Geral', 'ativo'),
('ivan.macdonald', MD5('senha123'), 5, 'Ivan Mac Donald', 'Enfermagem', 'ativo');
