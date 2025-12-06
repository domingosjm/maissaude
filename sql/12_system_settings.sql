-- Tabela de Configurações do Sistema
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    category VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurações Padrão
INSERT INTO system_settings (setting_key, setting_value, category, description) VALUES
-- Geral
('clinic_name', 'Mais Saúde Integrada', 'general', 'Nome da clínica'),
('clinic_nuit', '', 'general', 'NUIT da clínica'),
('clinic_address', 'Maputo, Moçambique', 'general', 'Endereço da clínica'),
('clinic_phone', '+258 84 000 0000', 'general', 'Telefone da clínica'),
('clinic_email', 'contato@maissaude.co.mz', 'general', 'Email da clínica'),
('clinic_website', 'https://maissaude.co.mz', 'general', 'Website da clínica'),

-- Financeiro
('currency', 'MZN', 'financial', 'Moeda padrão'),
('tax_rate', '16', 'financial', 'Taxa de IVA'),
('payment_methods', 'Dinheiro,Cartão,Transferência,M-Pesa', 'financial', 'Métodos de pagamento'),
('invoice_prefix', 'INV', 'financial', 'Prefixo de fatura'),
('invoice_next_number', '1', 'financial', 'Próximo número de fatura'),

-- Agendamento
('consultation_duration', '30', 'appointment', 'Duração da consulta em minutos'),
('working_hours_start', '08:00', 'appointment', 'Horário de início'),
('working_hours_end', '18:00', 'appointment', 'Horário de término'),
('working_days', 'Segunda,Terça,Quarta,Quinta,Sexta', 'appointment', 'Dias de funcionamento'),
('max_appointments_per_day', '20', 'appointment', 'Máximo de agendamentos por dia'),

-- Notificações
('email_notifications', '1', 'notification', 'Notificações por email'),
('sms_notifications', '0', 'notification', 'Notificações por SMS'),
('whatsapp_notifications', '0', 'notification', 'Notificações por WhatsApp'),
('smtp_host', '', 'notification', 'Host SMTP'),
('smtp_port', '587', 'notification', 'Porta SMTP'),
('smtp_user', '', 'notification', 'Usuário SMTP'),
('smtp_password', '', 'notification', 'Senha SMTP'),

-- Segurança
('session_timeout', '30', 'security', 'Timeout de sessão em minutos'),
('password_min_length', '8', 'security', 'Tamanho mínimo de senha'),
('password_require_special', '1', 'security', 'Exigir caracteres especiais'),
('max_login_attempts', '5', 'security', 'Máximo de tentativas de login'),
('enable_2fa', '0', 'security', 'Autenticação de dois fatores')

ON DUPLICATE KEY UPDATE setting_value=setting_value;
