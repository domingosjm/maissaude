-- Tabela de configurações financeiras
CREATE TABLE IF NOT EXISTS financial_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir configurações padrão
INSERT INTO financial_settings (setting_key, setting_value, description) VALUES
('company_name', 'MSLDA - Mais Saúde Integrada', 'Nome da empresa'),
('company_nuit', '123456789', 'NUIT da empresa'),
('company_address', 'Maputo, Moçambique', 'Endereço da empresa'),
('company_phone', '+258 84 000 0000', 'Telefone da empresa'),
('company_email', 'contacto@maissaude.co.mz', 'Email da empresa'),
('company_logo', '', 'URL do logo da empresa'),
('invoice_prefix', 'FT', 'Prefixo das faturas'),
('invoice_next_number', '1', 'Próximo número de fatura'),
('tax_rate', '16', 'Taxa de IVA (%)'),
('currency', 'MT', 'Moeda padrão (Metical)'),
('currency_symbol', 'MT', 'Símbolo da moeda'),
('receipt_prefix', 'RC', 'Prefixo dos recibos'),
('receipt_next_number', '1', 'Próximo número de recibo'),
('payment_terms', '30', 'Prazo de pagamento padrão (dias)'),
('bank_name', 'Banco Comercial de Investimentos', 'Nome do banco'),
('bank_account', '0000000000', 'Número da conta bancária'),
('bank_iban', '', 'IBAN da conta'),
('notes_default', 'Obrigado pela preferência!', 'Nota padrão nas faturas')
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    description = VALUES(description);
