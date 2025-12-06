-- Modelo SQL para Clínica Integrada Mais Saúde
-- Tabelas principais, relacionamentos e permissões

CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO roles (name) VALUES
 ('admin'), ('recepcionista'), ('medico'), ('psicologo'), ('psiquiatra'), ('laboratorio'), ('farmacia'), ('paciente');

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_roles (
    user_id INT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

CREATE TABLE patients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    birth_date DATE,
    cpf VARCHAR(20) UNIQUE,
    phone VARCHAR(30),
    email VARCHAR(255),
    address VARCHAR(255),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE specialties (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO specialties (name) VALUES ('Clínico Geral'), ('Psicologia'), ('Psiquiatria'), ('Laboratório');

CREATE TABLE professionals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    specialty_id INT UNSIGNED NOT NULL,
    crm_crp VARCHAR(30),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (specialty_id) REFERENCES specialties(id)
);

CREATE TABLE appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    professional_id INT UNSIGNED NOT NULL,
    scheduled_at DATETIME NOT NULL,
    status ENUM('agendado','realizado','cancelado') NOT NULL DEFAULT 'agendado',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (professional_id) REFERENCES professionals(id)
);

CREATE TABLE attendances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT UNSIGNED NOT NULL,
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
);

CREATE TABLE exams (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

CREATE TABLE exam_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attendance_id INT UNSIGNED NOT NULL,
    exam_id INT UNSIGNED NOT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attendance_id) REFERENCES attendances(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id)
);

CREATE TABLE exam_results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_request_id INT UNSIGNED NOT NULL,
    result TEXT NOT NULL,
    result_file VARCHAR(255),
    released_at TIMESTAMP,
    FOREIGN KEY (exam_request_id) REFERENCES exam_requests(id) ON DELETE CASCADE
);

CREATE TABLE prescriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attendance_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attendance_id) REFERENCES attendances(id) ON DELETE CASCADE
);

CREATE TABLE medications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

CREATE TABLE prescription_items (
    prescription_id INT UNSIGNED NOT NULL,
    medication_id INT UNSIGNED NOT NULL,
    dosage VARCHAR(100),
    instructions TEXT,
    PRIMARY KEY (prescription_id, medication_id),
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (medication_id) REFERENCES medications(id)
);

CREATE TABLE pharmacy_stock (
    medication_id INT UNSIGNED PRIMARY KEY,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (medication_id) REFERENCES medications(id)
);

-- Permite que pacientes acessem seus resultados e prescrições
-- O acesso é feito via user_id vinculado ao patient_id
-- O front-end deve garantir autenticação e autorização
