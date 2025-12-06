# Arquitetura do Sistema - Mais Saúde

## 📐 Visão Geral

```
┌─────────────────────────────────────────────────────────┐
│                    FRONTEND (React)                     │
│                  http://localhost:3001                  │
├─────────────────────────────────────────────────────────┤
│  • React 18 + TypeScript                                │
│  • Vite (Dev Server)                                    │
│  • TailwindCSS (Styling)                                │
│  • React Router (Routing)                               │
│  • Zustand (State Management)                           │
│  • Axios (HTTP Client)                                  │
└────────────────┬────────────────────────────────────────┘
                 │
                 │ HTTP/JSON
                 │ CORS Enabled
                 │
┌────────────────▼────────────────────────────────────────┐
│                   BACKEND (PHP)                         │
│              http://localhost/MSLDA/api                 │
├─────────────────────────────────────────────────────────┤
│  • PHP 7.4+ (REST API)                                  │
│  • Session-based Auth                                   │
│  • JSON Responses                                       │
│  • Prepared Statements                                  │
│  • CORS Headers                                         │
└────────────────┬────────────────────────────────────────┘
                 │
                 │ MySQLi
                 │
┌────────────────▼────────────────────────────────────────┐
│                  DATABASE (MySQL)                       │
│                 localhost:3306/mais_saude               │
├─────────────────────────────────────────────────────────┤
│  • MySQL 5.7+                                           │
│  • InnoDB Engine                                        │
│  • Foreign Keys                                         │
│  • Indexes                                              │
└─────────────────────────────────────────────────────────┘
```

## 🎨 Frontend Architecture

### Estrutura de Pastas

```
frontend/src/
├── components/          # Componentes reutilizáveis
│   ├── Layout.tsx      # Layout base com Navbar
│   └── Navbar.tsx      # Barra de navegação
│
├── pages/              # Páginas da aplicação
│   ├── Login.tsx       # Tela de login
│   ├── Dashboard.tsx   # Dashboard principal
│   ├── Recepcao.tsx    # Módulo de recepção
│   ├── Laboratorio.tsx # Módulo de laboratório
│   ├── Farmacia.tsx    # Módulo de farmácia
│   └── ...
│
├── services/           # Serviços de API
│   ├── api.ts          # Configuração Axios
│   ├── authService.ts  # Autenticação
│   ├── patientService.ts    # Pacientes
│   └── dashboardService.ts  # Dashboard
│
├── stores/             # Estado global
│   └── authStore.ts    # Zustand store de autenticação
│
├── hooks/              # Custom hooks
│   └── useDarkMode.ts  # Hook de modo escuro
│
├── App.tsx             # Rotas principais
├── main.tsx            # Entry point
└── index.css           # Estilos globais
```

### Fluxo de Dados

```
┌─────────┐      ┌──────────┐      ┌─────────┐      ┌─────┐
│  Page   │─────>│ Service  │─────>│   API   │─────>│ PHP │
│Component│      │(Axios)   │      │(Axios)  │      │     │
└─────────┘      └──────────┘      └─────────┘      └─────┘
     │                                                   │
     │                                                   │
     ▼                                                   ▼
┌─────────┐                                      ┌──────────┐
│ Zustand │                                      │  MySQL   │
│  Store  │                                      │ Database │
└─────────┘                                      └──────────┘
```

## 🔧 Backend Architecture

### Estrutura de Arquivos

```
api/
├── common.php         # Funções comuns + CORS
├── auth.php          # Autenticação (Login/Logout)
├── dashboard.php     # Estatísticas gerais
├── patients.php      # CRUD de pacientes
├── appointments.php  # CRUD de agendamentos
├── lab_exams.php     # CRUD de exames
├── medications.php   # CRUD de medicamentos
├── financial.php     # Transações financeiras
├── users.php         # CRUD de usuários
└── .htaccess         # Configuração Apache
```

### Fluxo de Requisição

```
1. Cliente envia requisição
   ↓
2. Apache recebe (CORS headers via .htaccess)
   ↓
3. PHP processa (common.php)
   ↓
4. Valida autenticação (validateAuth)
   ↓
5. Executa lógica de negócio
   ↓
6. Consulta MySQL (prepared statements)
   ↓
7. Retorna JSON (sendResponse)
   ↓
8. Cliente recebe resposta
```

### Padrão de Endpoint

Cada endpoint segue o padrão REST:

```php
<?php
require_once __DIR__ . '/common.php';

$user = validateAuth();  // Valida sessão
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Listar/Buscar
} elseif ($method === 'POST') {
    // Criar
} elseif ($method === 'PUT') {
    // Atualizar
} elseif ($method === 'DELETE') {
    // Deletar
} else {
    sendResponse(['error' => 'Método não permitido'], 405);
}
```

## 💾 Database Schema

### Relacionamentos Principais

```
users (1) ──────> (*) patients
  │                      │
  │                      │
  └──> (*) appointments  │
          │               │
          └───────────────┘
  
patients (1) ───> (*) lab_exams
             ───> (*) appointments
             ───> (*) financial_transactions

medications (1) ──> (1) medication_stock
                ──> (*) stock_movements
```

### Tabelas Core

1. **users** - Usuários do sistema
2. **patients** - Pacientes
3. **appointments** - Agendamentos
4. **lab_exams** - Exames laboratoriais
5. **medications** - Medicamentos
6. **medication_stock** - Estoque
7. **financial_transactions** - Financeiro

## 🔐 Autenticação

### Fluxo de Login

```
1. Usuário envia email + senha
   ↓
2. Backend valida credenciais
   ↓
3. Cria sessão PHP (session_regenerate_id)
   ↓
4. Retorna dados do usuário
   ↓
5. Frontend armazena no Zustand + localStorage
   ↓
6. Cookie de sessão mantém autenticação
```

### Validação de Sessão

```php
function validateAuth() {
    if (!isset($_SESSION['user_id'])) {
        sendResponse(['error' => 'Não autenticado'], 401);
    }
    return ['user_id' => $_SESSION['user_id'], ...];
}
```

## 🌐 API Communication

### Request Flow

```javascript
// Frontend
const response = await api.get('/dashboard.php')

// Axios interceptor adiciona:
// - withCredentials: true (envia cookie de sessão)
// - baseURL: http://localhost/MSLDA/api

// Backend recebe e valida sessão
// Retorna JSON response
```

### Response Format

**Sucesso:**
```json
{
  "success": true,
  "data": {...},
  "message": "Operação realizada"
}
```

**Erro:**
```json
{
  "error": "Mensagem de erro"
}
```

## 🎯 Design Patterns

### Frontend Patterns

1. **Container/Presentational Components**
   - Pages = Containers (lógica)
   - Components = Presentational (UI)

2. **Service Layer**
   - Separação entre UI e API
   - Reutilização de lógica

3. **State Management**
   - Zustand para estado global
   - useState para estado local

### Backend Patterns

1. **RESTful API**
   - Recursos como URLs
   - Métodos HTTP semânticos

2. **DRY (Don't Repeat Yourself)**
   - common.php com funções compartilhadas
   - validateAuth() reutilizada

3. **Security First**
   - Prepared statements
   - Password hashing
   - Session management

## 🚀 Performance

### Frontend Optimizations

- Vite (build rápido)
- Code splitting (React Router)
- Lazy loading de componentes
- CSS purge (TailwindCSS)

### Backend Optimizations

- Prepared statements (cache)
- Indexes no banco
- Paginação de resultados
- Query optimization

## 📊 Monitoring & Logging

### Frontend Errors

```javascript
try {
  await api.get('/endpoint')
} catch (error) {
  console.error('Erro:', error)
  // Tratar erro
}
```

### Backend Errors

```php
// Em desenvolvimento: display_errors = On
// Em produção: log para arquivo
error_log($error_message);
```

## 🔄 Development Workflow

```
1. Criar tabela no MySQL
   ↓
2. Criar endpoint PHP na /api
   ↓
3. Criar service no frontend
   ↓
4. Criar/atualizar componente React
   ↓
5. Testar integração
   ↓
6. Commit & Deploy
```

## 📦 Deployment

### Frontend Build

```bash
cd frontend
npm run build
# Output: frontend/dist/
```

### Backend Deploy

- Upload arquivos PHP
- Configurar Apache/Nginx
- Ajustar config.php
- Executar migrations SQL

## 🔒 Security Checklist

- [x] Password hashing (bcrypt)
- [x] Prepared statements
- [x] CSRF protection
- [x] Session security
- [x] Input validation
- [x] SQL injection prevention
- [x] XSS prevention
- [ ] Rate limiting (TODO)
- [ ] API authentication tokens (TODO)
