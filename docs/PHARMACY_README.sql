-- Adicionar tabela para pagamentos de faturas
CREATE TABLE IF NOT EXISTS invoice_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    received_by INT NOT NULL,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES patient_invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id),
    INDEX idx_invoice (invoice_id),
    INDEX idx_received_at (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Instruções de instalação e uso do sistema de farmácia

-- PASSO 1: Executar o script pharmacy_setup.sql para criar as tabelas
-- Execute este arquivo no MySQL/phpMyAdmin para criar todas as tabelas necessárias

-- PASSO 2: Instalar biblioteca DomPDF para geração de PDFs
-- Para exportar diagnósticos em PDF, você precisa instalar a biblioteca DomPDF
-- 
-- Com Composer (recomendado):
-- composer require dompdf/dompdf
--
-- Se não tiver Composer, instale de: https://getcomposer.org/download/
-- 
-- Após instalar o Composer, execute no terminal dentro da pasta MSLDA:
-- cd c:\xampp\htdocs\MSLDA
-- composer require dompdf/dompdf

-- PASSO 3: Verificar permissões de usuários
-- Certifique-se de que os usuários têm as roles corretas:
-- - 'farmaceutico' para acessar o módulo de farmácia
-- - 'recepcao' para visualizar faturas e exportar diagnósticos
-- - 'admin' tem acesso a tudo

-- Atualizar role de usuários (exemplo):
-- UPDATE users SET role = 'farmaceutico' WHERE id = X;
-- UPDATE users SET role = 'recepcao' WHERE id = Y;

-- PASSO 4: FUNCIONALIDADES IMPLEMENTADAS

-- ✅ MÓDULO DE FARMÁCIA (pharmacy.php)
-- - Visualização de prescrições pendentes
-- - Dispensação de medicamentos com controle de stock
-- - Faturamento automático ao dispensar medicamentos
-- - Gestão completa de medicamentos (adicionar, editar)
-- - Gestão de stock geral integrada
-- - Controle de medicamentos com stock baixo
-- - Histórico de dispensações
-- - Preços baseados no mercado moçambicano

-- ✅ SISTEMA DE FATURAS (patient_invoices.php)
-- - Visualização de faturas por paciente na recepção
-- - Faturas vinculadas ao perfil do paciente
-- - Suporte para faturas de: Farmácia, Consultas, Exames, Procedimentos
-- - Registro de pagamentos (Dinheiro, MPesa, E-Mola, Cartão, etc.)
-- - Controle de status: Pendente, Pago, Parcialmente Pago, Cancelado
-- - Detalhamento de medicamentos na fatura
-- - Resumo financeiro por paciente

-- ✅ EXPORTAÇÃO DE DIAGNÓSTICOS EM PDF (export_diagnoses.php)
-- - Exportação de diagnósticos psiquiátricos e psicológicos
-- - PDF profissional com todas as informações clínicas
-- - Disponível na recepção para facilitar encaminhamentos
-- - Inclui: diagnóstico principal, secundário, sintomas, observações,
--   plano de tratamento e recomendações de acompanhamento
-- - Nota de confidencialidade incluída

-- ✅ LISTA DE MEDICAMENTOS
-- 60+ medicamentos cadastrados com preços reais do mercado moçambicano
-- Categorias: Antibióticos, Analgésicos, Antihipertensivos, Antidiabéticos,
--             Antimaláricos, Vitaminas, Antialérgicos, Psiquiátricos, etc.
-- Preços variam de 25 MZN (Soro Oral) até 450 MZN (Insulina)

-- PASSO 5: NAVEGAÇÃO DO SISTEMA

-- Para FARMACÊUTICOS:
-- Acesse: pharmacy.php
-- - Tab "Prescrições": Ver e dispensar medicamentos prescritos
-- - Tab "Gestão de Medicamentos": Adicionar/editar medicamentos
-- - Tab "Gestão de Stock": Controlar entradas e saídas de estoque
-- - Tab "Histórico": Visualizar dispensações realizadas

-- Para RECEPÇÃO:
-- Acesse: patient_invoices.php
-- - Buscar paciente pelo nome ou telefone
-- - Visualizar todas as faturas do paciente
-- - Registrar pagamentos
-- - Exportar diagnósticos psiquiátricos/psicológicos em PDF
-- - Ver saldo pendente do paciente

-- PASSO 6: FLUXO DE TRABALHO

-- 1. CONSULTA MÉDICA:
--    - Médico atende o paciente
--    - Registra diagnóstico (se psiquiatria/psicologia)
--    - Cria prescrição de medicamentos
--    Status: Prescrição PENDENTE

-- 2. FARMÁCIA:
--    - Farmacêutico visualiza prescrição pendente
--    - Verifica disponibilidade de stock
--    - Clica em "Dispensar Medicamentos"
--    - Sistema:
--      * Debita medicamentos do stock
--      * Cria fatura automática para o paciente
--      * Atualiza prescrição para DISPENSADO

-- 3. RECEPÇÃO:
--    - Busca o paciente
--    - Visualiza todas as faturas (farmácia, consultas, etc.)
--    - Paciente efetua pagamento
--    - Registra pagamento na fatura
--    - Se necessário, exporta diagnósticos em PDF

-- PASSO 7: ALERTAS DE STOCK
-- O sistema automaticamente alerta quando:
-- - Medicamento atinge o stock mínimo
-- - Medicamento está esgotado (stock = 0)
-- - Prescrição não pode ser dispensada por falta de stock

-- PASSO 8: RELATÓRIOS DISPONÍVEIS
-- - Histórico de dispensações por período
-- - Movimentos de stock
-- - Faturas pendentes/pagas por paciente
-- - Diagnósticos psiquiátricos/psicológicos (PDF)

-- OBSERVAÇÕES IMPORTANTES:
-- 1. Os preços dos medicamentos são em Meticais (MZN)
-- 2. O sistema suporta pagamentos parciais
-- 3. Todas as transações são registradas com rastreabilidade
-- 4. Controle de permissões por role de usuário
-- 5. O stock é atualizado em tempo real
-- 6. Faturas incluem detalhamento completo

-- MANUTENÇÃO:
-- Para adicionar novos medicamentos, use a interface em pharmacy.php
-- ou execute INSERT direto no banco:
-- INSERT INTO medications (name, generic_name, category, price, stock_quantity, minimum_stock) 
-- VALUES ('Nome do Medicamento', 'Nome Genérico', 'Categoria', 100.00, 50, 10);

-- BACKUP RECOMENDADO:
-- Faça backup regular das tabelas:
-- - medications
-- - prescriptions
-- - prescription_medications
-- - medication_dispensing
-- - patient_invoices
-- - invoice_payments
-- - stock_movements
-- - psychiatric_diagnoses
