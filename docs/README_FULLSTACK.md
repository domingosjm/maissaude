# Sistema Integrado Mais Saúde - React + PHP

Sistema completo de gestão clínica com frontend em React TypeScript e backend em PHP com APIs REST.

## 🏗️ Arquitetura

```
MSLDA/
├── frontend/          # React + TypeScript + Vite
│   ├── src/
│   │   ├── components/   # Componentes React
│   │   ├── pages/        # Páginas da aplicação
│   │   ├── services/     # Serviços de API
│   │   ├── stores/       # Gerenciamento de estado (Zustand)
│   │   └── hooks/        # Custom hooks
│   └── package.json
│
├── api/               # Backend PHP REST API
│   ├── auth.php      # Autenticação
│   ├── patients.php  # Gestão de pacientes
│   ├── appointments.php  # Agendamentos
│   ├── lab_exams.php     # Exames laboratoriais
│   ├── medications.php   # Medicamentos
│   ├── financial.php     # Financeiro
│   ├── users.php         # Usuários
│   └── common.php        # Funções comuns
│
├── sql/               # Scripts SQL
│   └── api_tables.sql    # Criação das tabelas
│
├── config.php         # Configuração do banco
└── functions.php      # Funções auxiliares
```

## 🚀 Instalação e Configuração

### 1. Banco de Dados

Execute o script SQL para criar as tabelas:
```sql
mysql -u root -p mais_saude < sql/api_tables.sql
```

### 2. Backend (PHP)

Configure o arquivo `config.php`:
```php
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'mais_saude';
```

### 3. Frontend (React)

```powershell
cd frontend
npm install
npm run dev
```

## 🌐 URLs

- **Frontend**: http://localhost:3001
- **Backend**: http://localhost/MSLDA/api/
- **Banco de Dados**: localhost:3306

## 📡 Endpoints da API

### Autenticação
- `POST /api/auth.php` - Login
- `GET /api/auth.php` - Verificar sessão
- `DELETE /api/auth.php` - Logout

### Dashboard
- `GET /api/dashboard.php` - Estatísticas

### Pacientes
- `GET /api/patients.php` - Listar
- `POST /api/patients.php` - Criar

### Agendamentos
- `GET /api/appointments.php` - Listar
- `POST /api/appointments.php` - Criar
- `PUT /api/appointments.php` - Atualizar

### Laboratório
- `GET /api/lab_exams.php` - Listar exames
- `POST /api/lab_exams.php` - Solicitar exame
- `PUT /api/lab_exams.php` - Atualizar resultado

### Medicamentos
- `GET /api/medications.php` - Listar
- `POST /api/medications.php` - Adicionar
- `PUT /api/medications.php` - Atualizar stock

### Financeiro
- `GET /api/financial.php` - Listar transações
- `POST /api/financial.php` - Registrar transação

### Usuários
- `GET /api/users.php` - Listar
- `POST /api/users.php` - Criar
- `PUT /api/users.php` - Atualizar
- `DELETE /api/users.php` - Desativar

## 🔐 Autenticação

O sistema usa sessões PHP para autenticação:
- Login retorna dados do usuário
- Cookies de sessão mantêm a autenticação
- Todas as rotas (exceto login) requerem autenticação

## 🎨 Frontend

### Tecnologias
- React 18
- TypeScript
- Vite
- TailwindCSS
- React Router
- Zustand
- Axios

### Páginas Disponíveis
- Login
- Dashboard
- Recepção
- Laboratório
- Farmácia
- Financeiro
- Configurações
- Usuários
- Relatórios

## 🛠️ Backend

### Tecnologias
- PHP 7.4+
- MySQL 5.7+
- REST API
- JSON responses
- CORS habilitado

### Recursos
- Validação de dados
- Prepared statements (SQL Injection protection)
- Paginação
- Filtros e buscas
- Soft delete para usuários

## 📊 Banco de Dados

### Tabelas Principais
- `users` - Usuários do sistema
- `patients` - Pacientes
- `appointments` - Agendamentos
- `lab_exams` - Exames laboratoriais
- `exam_types` - Tipos de exames
- `medications` - Medicamentos
- `medication_stock` - Estoque
- `stock_movements` - Movimentações
- `financial_transactions` - Transações
- `services` - Serviços

## 🔧 Desenvolvimento

### Frontend
```powershell
cd frontend
npm run dev      # Desenvolvimento
npm run build    # Build produção
npm run preview  # Preview
```

### Backend
- Certifique-se de que o Apache/Nginx está rodando
- PHP deve estar configurado com extensões: mysqli, json
- Habilite CORS no php.ini se necessário

## 📝 TODO

- [ ] Implementar upload de arquivos (exames)
- [ ] Adicionar relatórios em PDF
- [ ] Sistema de notificações
- [ ] Chat entre usuários
- [ ] Dashboard com gráficos
- [ ] Histórico de alterações
- [ ] Logs de auditoria
- [ ] Backup automático

## 🔒 Segurança

- Senhas com hash bcrypt
- Prepared statements
- Validação de entrada
- CORS configurado
- Sanitização de dados
- Soft delete

## 📄 Licença

© 2025 Mais Saúde, LDA. Todos os direitos reservados.
