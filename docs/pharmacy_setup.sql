-- Script SQL para criar tabelas do módulo de farmácia
-- Sistema de prescrições, medicamentos, stock e faturamento

-- Tabela de medicamentos
CREATE TABLE IF NOT EXISTS medications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    generic_name VARCHAR(255),
    category VARCHAR(100),
    description TEXT,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    stock_quantity INT NOT NULL DEFAULT 0,
    minimum_stock INT NOT NULL DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_category (category),
    INDEX idx_stock (stock_quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de prescrições
CREATE TABLE IF NOT EXISTS prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    status ENUM('pendente', 'dispensado', 'cancelado') DEFAULT 'pendente',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dispensed_at TIMESTAMP NULL,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_appointment (appointment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de medicamentos da prescrição
CREATE TABLE IF NOT EXISTS prescription_medications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT NOT NULL,
    medication_id INT NOT NULL,
    dosage VARCHAR(100) NOT NULL,
    frequency VARCHAR(100) NOT NULL,
    duration VARCHAR(100) NOT NULL,
    quantity INT NOT NULL,
    instructions TEXT,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (medication_id) REFERENCES medications(id),
    INDEX idx_prescription (prescription_id),
    INDEX idx_medication (medication_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de dispensação de medicamentos
CREATE TABLE IF NOT EXISTS medication_dispensing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT NOT NULL,
    medication_id INT NOT NULL,
    patient_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    dispensed_by INT NOT NULL,
    dispensed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id),
    FOREIGN KEY (medication_id) REFERENCES medications(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (dispensed_by) REFERENCES users(id),
    INDEX idx_patient (patient_id),
    INDEX idx_dispensed_at (dispensed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de faturas do paciente
CREATE TABLE IF NOT EXISTS patient_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(100) UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    prescription_id INT NULL,
    appointment_id INT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    paid_amount DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('pendente', 'pago', 'parcialmente_pago', 'cancelado') DEFAULT 'pendente',
    invoice_type ENUM('farmacia', 'consulta', 'exames', 'procedimentos') NOT NULL,
    payment_method VARCHAR(50),
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_patient (patient_id),
    INDEX idx_status (status),
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de movimentos de stock
CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medication_id INT NOT NULL,
    quantity INT NOT NULL,
    operation ENUM('add', 'remove') NOT NULL,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medication_id) REFERENCES medications(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_medication (medication_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de diagnósticos psiquiátricos e psicológicos
CREATE TABLE IF NOT EXISTS psychiatric_diagnoses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    patient_id INT NOT NULL,
    professional_id INT NOT NULL,
    diagnosis_type ENUM('psiquiatria', 'psicologia') NOT NULL,
    primary_diagnosis TEXT NOT NULL,
    secondary_diagnosis TEXT,
    symptoms TEXT,
    observations TEXT,
    treatment_plan TEXT,
    follow_up_recommendations TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (professional_id) REFERENCES professionals(id),
    INDEX idx_patient (patient_id),
    INDEX idx_diagnosis_type (diagnosis_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir medicamentos comuns no mercado moçambicano com preços realistas
INSERT INTO medications (name, generic_name, category, price, stock_quantity, minimum_stock, description) VALUES
-- Analgésicos e Antitérmicos
('Paracetamol 500mg', 'Paracetamol', 'Analgésico/Antitérmico', 50.00, 500, 50, 'Analgésico e antitérmico de uso comum'),
('Paracetamol 1000mg', 'Paracetamol', 'Analgésico/Antitérmico', 75.00, 300, 30, 'Analgésico e antitérmico concentração alta'),
('Ibuprofeno 400mg', 'Ibuprofeno', 'Anti-inflamatório', 80.00, 400, 40, 'Anti-inflamatório não esteroide'),
('Ibuprofeno 600mg', 'Ibuprofeno', 'Anti-inflamatório', 120.00, 250, 25, 'Anti-inflamatório potente'),
('Diclofenaco 50mg', 'Diclofenaco', 'Anti-inflamatório', 95.00, 350, 35, 'Anti-inflamatório e analgésico'),
('Aspirina 100mg', 'Ácido Acetilsalicílico', 'Analgésico', 45.00, 600, 60, 'Analgésico e antiagregante plaquetário'),
('Tramadol 50mg', 'Tramadol', 'Analgésico', 180.00, 200, 20, 'Analgésico opioide para dor moderada a severa'),

-- Antibióticos
('Amoxicilina 500mg', 'Amoxicilina', 'Antibiótico', 150.00, 400, 40, 'Antibiótico de largo espectro'),
('Amoxicilina + Clavulanato 875mg', 'Amoxicilina + Clavulanato', 'Antibiótico', 280.00, 250, 25, 'Antibiótico com inibidor de beta-lactamase'),
('Azitromicina 500mg', 'Azitromicina', 'Antibiótico', 320.00, 300, 30, 'Antibiótico macrolídeo'),
('Ciprofloxacina 500mg', 'Ciprofloxacina', 'Antibiótico', 250.00, 200, 20, 'Antibiótico quinolona'),
('Metronidazol 250mg', 'Metronidazol', 'Antibiótico', 120.00, 350, 35, 'Antibiótico para infecções anaeróbicas'),
('Cefalexina 500mg', 'Cefalexina', 'Antibiótico', 200.00, 300, 30, 'Antibiótico cefalosporina'),
('Doxiciclina 100mg', 'Doxiciclina', 'Antibiótico', 180.00, 250, 25, 'Antibiótico tetraciclina'),

-- Antihipertensivos
('Losartana 50mg', 'Losartana', 'Antihipertensivo', 95.00, 500, 50, 'Antagonista dos receptores da angiotensina II'),
('Enalapril 10mg', 'Enalapril', 'Antihipertensivo', 85.00, 450, 45, 'Inibidor da ECA'),
('Amlodipina 5mg', 'Amlodipina', 'Antihipertensivo', 90.00, 400, 40, 'Bloqueador dos canais de cálcio'),
('Hidroclorotiazida 25mg', 'Hidroclorotiazida', 'Diurético', 60.00, 500, 50, 'Diurético tiazídico'),
('Atenolol 50mg', 'Atenolol', 'Antihipertensivo', 75.00, 350, 35, 'Beta-bloqueador'),

-- Antidiabéticos
('Metformina 500mg', 'Metformina', 'Antidiabético', 80.00, 600, 60, 'Antidiabético oral'),
('Metformina 850mg', 'Metformina', 'Antidiabético', 110.00, 400, 40, 'Antidiabético oral concentração alta'),
('Glibenclamida 5mg', 'Glibenclamida', 'Antidiabético', 95.00, 350, 35, 'Sulfonilureia antidiabética'),
('Insulina NPH', 'Insulina Humana', 'Antidiabético', 450.00, 100, 20, 'Insulina de ação intermediária'),
('Insulina Regular', 'Insulina Humana', 'Antidiabético', 420.00, 100, 20, 'Insulina de ação rápida'),

-- Antiácidos e Gastroprotetores
('Omeprazol 20mg', 'Omeprazol', 'Gastroprotetor', 120.00, 500, 50, 'Inibidor da bomba de prótons'),
('Ranitidina 150mg', 'Ranitidina', 'Antiácido', 90.00, 400, 40, 'Antagonista H2'),
('Hidróxido de Alumínio', 'Hidróxido de Alumínio', 'Antiácido', 65.00, 300, 30, 'Antiácido'),

-- Antimaláricos
('Artemeter + Lumefantrina', 'Artemeter + Lumefantrina', 'Antimalárico', 180.00, 800, 80, 'Tratamento da malária não complicada'),
('Quinina 300mg', 'Quinina', 'Antimalárico', 150.00, 500, 50, 'Tratamento da malária'),
('Artesunato 50mg', 'Artesunato', 'Antimalárico', 200.00, 400, 40, 'Tratamento da malária severa'),

-- Vitaminas e Suplementos
('Vitamina C 1000mg', 'Ácido Ascórbico', 'Vitamina', 85.00, 400, 40, 'Suplemento vitamínico'),
('Complexo B', 'Vitaminas do Complexo B', 'Vitamina', 120.00, 350, 35, 'Suplemento vitamínico'),
('Sulfato Ferroso 40mg', 'Ferro', 'Suplemento', 70.00, 500, 50, 'Suplemento de ferro para anemia'),
('Ácido Fólico 5mg', 'Ácido Fólico', 'Vitamina', 50.00, 600, 60, 'Suplemento para prevenção de anemia'),
('Vitamina D 7000 UI', 'Colecalciferol', 'Vitamina', 180.00, 250, 25, 'Suplemento vitamínico'),

-- Antialérgicos
('Loratadina 10mg', 'Loratadina', 'Anti-histamínico', 75.00, 400, 40, 'Antialérgico não sedativo'),
('Cetirizina 10mg', 'Cetirizina', 'Anti-histamínico', 80.00, 350, 35, 'Antialérgico'),
('Dexametasona 4mg', 'Dexametasona', 'Corticosteroide', 95.00, 300, 30, 'Anti-inflamatório esteroide'),

-- Broncodilatadores e Respiratórios
('Salbutamol 100mcg (inalador)', 'Salbutamol', 'Broncodilatador', 280.00, 150, 15, 'Broncodilatador para asma'),
('Ambroxol 30mg', 'Ambroxol', 'Expectorante', 90.00, 300, 30, 'Mucolítico e expectorante'),

-- Antiparasitários
('Mebendazol 100mg', 'Mebendazol', 'Antiparasitário', 80.00, 500, 50, 'Tratamento de verminoses'),
('Albendazol 400mg', 'Albendazol', 'Antiparasitário', 95.00, 400, 40, 'Tratamento de parasitoses intestinais'),

-- Antipsicóticos e Psiquiátricos
('Fluoxetina 20mg', 'Fluoxetina', 'Antidepressivo', 120.00, 200, 20, 'Inibidor seletivo da recaptação de serotonina'),
('Sertralina 50mg', 'Sertralina', 'Antidepressivo', 150.00, 180, 18, 'Antidepressivo ISRS'),
('Amitriptilina 25mg', 'Amitriptilina', 'Antidepressivo', 95.00, 200, 20, 'Antidepressivo tricíclico'),
('Diazepam 5mg', 'Diazepam', 'Ansiolítico', 85.00, 250, 25, 'Benzodiazepínico ansiolítico'),
('Clonazepam 2mg', 'Clonazepam', 'Ansiolítico', 110.00, 200, 20, 'Benzodiazepínico anticonvulsivante'),
('Risperidona 2mg', 'Risperidona', 'Antipsicótico', 180.00, 150, 15, 'Antipsicótico atípico'),
('Haloperidol 5mg', 'Haloperidol', 'Antipsicótico', 95.00, 200, 20, 'Antipsicótico típico'),
('Carbamazepina 200mg', 'Carbamazepina', 'Anticonvulsivante', 120.00, 250, 25, 'Antiepiléptico e estabilizador de humor'),

-- Cardiovasculares
('Sinvastatina 20mg', 'Sinvastatina', 'Hipolipemiante', 110.00, 350, 35, 'Redutor de colesterol'),
('AAS 100mg', 'Ácido Acetilsalicílico', 'Antiagregante', 45.00, 600, 60, 'Prevenção cardiovascular'),
('Clopidogrel 75mg', 'Clopidogrel', 'Antiagregante', 280.00, 200, 20, 'Antiagregante plaquetário'),

-- Outros
('Prednisolona 20mg', 'Prednisolona', 'Corticosteroide', 120.00, 300, 30, 'Anti-inflamatório esteroide sistêmico'),
('Ondansetrona 8mg', 'Ondansetrona', 'Antiemético', 150.00, 200, 20, 'Antiemético potente'),
('Soro Oral (sachê)', 'Eletrólitos', 'Reidratante', 25.00, 1000, 100, 'Sais de reidratação oral');
