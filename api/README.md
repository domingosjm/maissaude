# API REST - Sistema Integrado Mais Saúde

Backend PHP com APIs REST para o sistema de gestão clínica.

## 📡 Endpoints Disponíveis

### Autenticação

#### POST `/api/auth.php`
Login no sistema
```json
{
  "email": "usuario@email.com",
  "password": "senha123"
}
```

#### GET `/api/auth.php`
Verificar autenticação atual

#### DELETE `/api/auth.php`
Logout do sistema

---

### Dashboard

#### GET `/api/dashboard.php`
Obter estatísticas do dashboard
- Retorna: pacientes, agendamentos, exames, medicamentos, receitas, etc.

---

### Pacientes

#### GET `/api/patients.php`
Listar pacientes
- Parâmetros: `search`, `page`, `limit`

#### POST `/api/patients.php`
Criar novo paciente
```json
{
  "name": "Nome do Paciente",
  "cpf": "123.456.789-00",
  "birth_date": "1990-01-01",
  "phone": "(11) 99999-9999",
  "email": "paciente@email.com",
  "address": "Endereço completo"
}
```

---

### Agendamentos

#### GET `/api/appointments.php`
Listar agendamentos
- Parâmetros: `date`, `patient_id`, `status`

#### POST `/api/appointments.php`
Criar agendamento
```json
{
  "patient_id": 1,
  "doctor_id": 2,
  "service_id": 3,
  "appointment_date": "2025-12-01",
  "appointment_time": "14:30:00",
  "notes": "Observações"
}
```

#### PUT `/api/appointments.php`
Atualizar status do agendamento
```json
{
  "appointment_id": 1,
  "status": "completed"
}
```

---

### Laboratório

#### GET `/api/lab_exams.php`
Listar exames laboratoriais
- Parâmetros: `status`, `patient_id`, `page`, `limit`

#### POST `/api/lab_exams.php`
Solicitar novo exame
```json
{
  "patient_id": 1,
  "exam_type_id": 5
}
```

#### PUT `/api/lab_exams.php`
Atualizar resultado do exame
```json
{
  "exam_id": 1,
  "results": "Resultado do exame...",
  "status": "completed"
}
```

---

### Medicamentos

#### GET `/api/medications.php`
Listar medicamentos
- Parâmetros: `search`, `low_stock`, `page`, `limit`

#### POST `/api/medications.php`
Adicionar novo medicamento
```json
{
  "name": "Nome do Medicamento",
  "description": "Descrição",
  "barcode": "123456789",
  "manufacturer": "Fabricante",
  "quantity": 100,
  "minimum_stock": 10,
  "unit": "comprimido",
  "expiry_date": "2026-12-31"
}
```

#### PUT `/api/medications.php`
Atualizar stock
```json
{
  "medication_id": 1,
  "quantity": 50,
  "type": "adjustment"
}
```

---

### Financeiro

#### GET `/api/financial.php`
Listar transações financeiras
- Parâmetros: `start_date`, `end_date`, `type`

#### POST `/api/financial.php`
Registrar transação
```json
{
  "type": "income",
  "amount": 150.00,
  "description": "Consulta médica",
  "patient_id": 1,
  "category": "consultas",
  "payment_method": "dinheiro"
}
```

---

### Usuários

#### GET `/api/users.php`
Listar usuários
- Parâmetros: `search`, `role`

#### POST `/api/users.php`
Criar usuário
```json
{
  "name": "Nome do Usuário",
  "email": "usuario@email.com",
  "password": "senha123",
  "role": "medico"
}
```

#### PUT `/api/users.php`
Atualizar usuário
```json
{
  "user_id": 1,
  "name": "Novo Nome",
  "role": "admin",
  "active": true
}
```

#### DELETE `/api/users.php?id=1`
Desativar usuário

---

## 🔒 Autenticação

Todas as rotas (exceto `/api/auth.php` POST) requerem autenticação via sessão PHP.

## 📦 Respostas

### Sucesso
```json
{
  "success": true,
  "data": {...}
}
```

### Erro
```json
{
  "error": "Mensagem de erro"
}
```

## 🚀 Configuração

1. Configure o CORS em `api/common.php`
2. Ajuste a conexão do banco em `config.php`
3. As APIs usam a sessão PHP existente
4. Todas as respostas são em JSON

## 🔧 Headers Necessários

```
Content-Type: application/json
Access-Control-Allow-Origin: http://localhost:3001
Access-Control-Allow-Credentials: true
```

## 📝 Status HTTP

- `200` - Sucesso
- `201` - Criado
- `400` - Requisição inválida
- `401` - Não autenticado
- `404` - Não encontrado
- `405` - Método não permitido
- `500` - Erro interno
