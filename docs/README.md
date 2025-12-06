# MSLDA - Sistema de Gestão Clínica Integrada Mais Saúde

## 📋 Estrutura do Sistema

Sistema modular organizado para gestão completa de clínicas médicas, incluindo recepção, consultórios, laboratório, farmácia e financeiro.

## 🗂️ Organização de Diretórios

```
MSLDA/
│
├── 📄 Arquivos Raiz
│   ├── index.php              # Tela de login
│   ├── dashboard.php          # Dashboard principal
│   ├── config.php             # Configurações do banco de dados
│   ├── router.php             # Sistema de rotas (URLs amigáveis)
│   ├── .htaccess              # Configuração Apache
│   └── logout.php             # Logout
│
├── 📁 includes/               # Componentes reutilizáveis
│   ├── header.php            # Cabeçalho comum
│   └── footer.php            # Rodapé comum
│
├── 📁 modules/                # Módulos do sistema
│   │
│   ├── 📁 recepcao/          # Módulo de Recepção
│   │   └── index.php         # Dashboard da recepção
│   │
│   ├── 📁 laboratorio/       # Módulo de Laboratório
│   │   ├── index.php         # Dashboard do laboratório
│   │   └── sysmex_integration.php  # Integração Sysmex XN
│   │
│   ├── 📁 farmacia/          # Módulo de Farmácia
│   │   └── index.php         # Dashboard da farmácia
│   │
│   ├── 📁 consultorios/      # Módulo de Consultórios
│   │   ├── medico_dashboard.php   # Consultório médico
│   │   ├── psicologia.php         # Consultório psicologia
│   │   └── psiquiatria.php        # Consultório psiquiatria
│   │
│   ├── 📁 financeiro/        # Módulo Financeiro
│   │   ├── relatorios.php    # Relatórios financeiros
│   │   └── faturas.php       # Visualização de faturas
│   │
│   └── 📁 admin/             # Módulo Administrativo
│       └── chat.php          # Sistema de chat interno
│
├── 📁 api/                    # Endpoints API
│   └── chat_messages.php     # API de mensagens do chat
│
├── 📁 assets/                 # Arquivos estáticos
│   ├── css/                  # Folhas de estilo
│   └── js/                   # Scripts JavaScript
│
├── 📁 uploads/                # Arquivos enviados
│   ├── prescriptions/        # Prescrições
│   └── documents/            # Documentos
│
├── 📁 vendor/                 # Dependências Composer
│   └── dompdf/               # Biblioteca PDF
│
└── 📁 setup_scripts/          # Scripts de configuração
    ├── adicionar_*.php       # Scripts de população de dados
    └── sincronizar_*.php     # Scripts de sincronização
```

## 🔗 Rotas do Sistema

### URLs Amigáveis

| Módulo | URL | Arquivo |
|--------|-----|---------|
| **Dashboard** | `/MSLDA/dashboard` | `dashboard.php` |
| **Recepção** | `/MSLDA/recepcao` | `modules/recepcao/index.php` |
| **Laboratório** | `/MSLDA/laboratorio` | `modules/laboratorio/index.php` |
| **Sysmex XN** | `/MSLDA/laboratorio/sysmex` | `modules/laboratorio/sysmex_integration.php` |
| **Farmácia** | `/MSLDA/farmacia` | `modules/farmacia/index.php` |
| **Consultório Médico** | `/MSLDA/consultorio/medico` | `modules/consultorios/medico_dashboard.php` |
| **Psicologia** | `/MSLDA/consultorio/psicologia` | `modules/consultorios/psicologia.php` |
| **Psiquiatria** | `/MSLDA/consultorio/psiquiatria` | `modules/consultorios/psiquiatria.php` |
| **Relatórios** | `/MSLDA/financeiro/relatorios` | `modules/financeiro/relatorios.php` |
| **Faturas** | `/MSLDA/financeiro/faturas` | `modules/financeiro/faturas.php` |
| **Chat** | `/MSLDA/chat` | `chat.php` |

## 🚀 Instalação e Migração

### 1. Requisitos
- Apache 2.4+ com `mod_rewrite`
- PHP 7.4+
- MySQL 5.7+ ou MariaDB 10.3+
- Composer

### 2. Ativar mod_rewrite

Edite `C:\xampp\apache\conf\httpd.conf`:

```apache
# Descomente esta linha:
LoadModule rewrite_module modules/mod_rewrite.so

# Mude AllowOverride None para All:
<Directory "C:/xampp/htdocs">
    AllowOverride All
    Require all granted
</Directory>
```

Reinicie o Apache.

### 3. Executar Migração

No PowerShell (como Administrador), na pasta MSLDA:

```powershell
.\migrate.ps1
```

Ou manualmente:
```powershell
# Copiar arquivos para estrutura nova
Move-Item laboratorio.php modules\laboratorio\index.php
Move-Item recepcao.php modules\recepcao\index.php
Move-Item farmacia.php modules\farmacia\index.php
# ... (ver migrate.ps1 para lista completa)
```

### 4. Testar

Acesse: http://localhost/MSLDA/dashboard

## 📦 Módulos Implementados

### ✅ Recepção
- Cadastro de pacientes
- Agendamento de consultas
- Faturamento de serviços
- Gestão de filas

### ✅ Laboratório
- Solicitação de exames
- Entrada de resultados
- Liberação de laudos
- **Integração Sysmex XN** (importação automática de hemogramas)
- Protocolos do dia
- Exportação PDF

### ✅ Farmácia
- Dispensação de medicamentos
- Controle de estoque
- Gestão de prescrições
- Movimentações de estoque

### ✅ Consultórios
- Dashboard médico
- Atendimento de consultas
- Prescrições
- Solicitação de exames
- Psicologia/Psiquiatria especializado

### ✅ Financeiro
- Relatórios financeiros (6 tipos)
- Visualização de faturas
- Controle de caixa
- Fluxo de caixa

### ✅ Sistema
- Chat interno entre funcionários
- Autenticação de usuários
- Controle de sessões

## 🔧 Configuração

### Banco de Dados

Edite `config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
define('DB_NAME', 'mais_saude');
```

### URLs Base

O sistema detecta automaticamente o caminho base. Se necessário, ajuste em `router.php`:

```php
$base_path = '/MSLDA/';
```

## 🔌 Integrações

### Sysmex XN (Analisador Hematológico)
- Importação de arquivos CSV/TXT
- Parser automático de parâmetros (WBC, RBC, HGB, HCT, MCV, MCH, MCHC, PLT)
- Correspondência por código do paciente
- Formatação com valores de referência

## 📊 Tecnologias

- **Backend:** PHP 7.4+
- **Database:** MySQL/MariaDB
- **Frontend:** Tailwind CSS, Bootstrap Icons
- **PDF:** Dompdf
- **JavaScript:** Vanilla JS, AJAX

## 🛡️ Segurança

- Autenticação por sessão
- Sanitização de inputs
- Prepared statements (mysqli)
- Controle de acesso por rotas
- Proteção contra listagem de diretórios
- Arquivos sensíveis bloqueados (.htaccess)

## 📝 Licença

Sistema proprietário - Integrada Mais Saúde © 2025

## 👨‍💻 Suporte

Para suporte técnico, consulte a documentação completa em `REORGANIZACAO.md`
