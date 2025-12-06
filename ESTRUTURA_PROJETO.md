# ESTRUTURA DO PROJETO - MSLDA
## Sistema Integrado Mais Saúde

### 📁 ESTRUTURA ORGANIZADA

```
MSLDA/
│
├── 📄 index.php                 # Página de login
├── 📄 dashboard.php             # Dashboard principal
├── 📄 config.php                # Configurações do banco de dados
├── 📄 functions.php             # Funções utilitárias globais
├── 📄 router.php                # Roteamento de URLs
├── 📄 logout.php                # Logout do sistema
├── 📄 configuracoes.php         # Painel de configurações admin
├── 📄 documentacao.php          # Documentação web interativa
├── 📄 Logo.png                  # Logo da clínica
│
├── 📂 api/                      # APIs REST
│   ├── auth.php                 # Autenticação
│   ├── patients.php             # CRUD de pacientes
│   ├── appointments.php         # Agendamentos
│   ├── dashboard.php            # Dados do dashboard
│   ├── financial.php            # Operações financeiras
│   ├── lab_exams.php            # Exames laboratoriais
│   ├── medications.php          # Medicamentos
│   ├── users.php                # Gestão de usuários
│   ├── chat_messages.php        # Chat interno
│   └── common.php               # Funções comuns das APIs
│
├── 📂 assets/                   # Recursos estáticos
│   ├── css/                     # Estilos personalizados
│   └── js/                      # JavaScript customizado
│
├── 📂 includes/                 # Componentes reutilizáveis
│   ├── header.php               # Header padrão
│   ├── footer.php               # Footer padrão
│   └── global_search.php        # Modal de busca global (Ctrl+K)
│
├── 📂 modules/                  # Módulos do sistema
│   ├── admin/                   # Administração
│   ├── recepcao/                # Recepção e agendamentos
│   ├── consultorios/            # Consultórios médicos
│   │   ├── medico_dashboard.php
│   │   ├── psicologia.php
│   │   └── psiquiatria.php
│   ├── laboratorio/             # Laboratório
│   │   ├── laboratorio.php
│   │   └── laboratorio_validacao.php
│   ├── farmacia/                # Farmácia
│   │   └── farmacia.php
│   └── financeiro/              # Financeiro
│       └── financeiro.php
│
├── 📂 sql/                      # Scripts SQL
│   └── [scripts de banco de dados]
│
├── 📂 setup_scripts/            # Scripts de configuração inicial
│   ├── adicionar_exames.php
│   ├── adicionar_medicamentos.php
│   └── [outros scripts de setup]
│
├── 📂 docs/                     # 📚 DOCUMENTAÇÃO
│   ├── README.txt               # Sobre esta pasta
│   ├── ARQUITETURA.md           # Arquitetura do sistema
│   ├── BACKEND_COMPLETO.md      # Documentação backend
│   ├── README_FULLSTACK.md      # Guia fullstack
│   ├── CREDENCIAIS_LOGIN.md     # Credenciais de acesso
│   ├── USUARIOS_SISTEMA.md      # Gestão de usuários
│   ├── SERVICOS_FINANCEIRO.md   # Módulo financeiro
│   ├── REORGANIZACAO.md         # Histórico de reorganização
│   ├── TESTE_RAPIDO.md          # Guia de testes
│   ├── modelo_clinica.sql       # Modelo do banco de dados
│   ├── PHARMACY_README.sql      # Documentação farmácia
│   └── pharmacy_setup.sql       # Setup da farmácia
│
├── 📂 tests/                    # 🧪 TESTES E VERIFICAÇÕES
│   ├── README.txt               # Sobre esta pasta
│   ├── check_admin.php          # Verificar admins
│   ├── check_patients.php       # Verificar pacientes
│   ├── check_schema.php         # Verificar schema DB
│   ├── check_schema2.php        # Verificação avançada
│   ├── test_system_flow.php     # Teste de fluxo
│   └── test_system_flow_fixed.php
│
├── 📂 backups/                  # 💾 BACKUPS
│   ├── README.txt               # Sobre esta pasta
│   └── backup_20251127_074717/  # Backup antigo
│
├── 📂 fonts/                    # Fontes para PDFs
├── 📂 frontend/                 # Frontend React (desenvolvimento)
├── 📂 vendor/                   # Dependências PHP (Composer)
│
├── 📄 .gitignore                # Arquivos ignorados pelo Git
├── 📄 .htaccess                 # Configurações Apache
├── 📄 composer.json             # Dependências PHP
└── 📄 migrate.ps1               # Script de migração

```

### 🎯 PRINCIPAIS FUNCIONALIDADES

#### 1. **Recepção**
- Cadastro de pacientes
- Agendamento de consultas
- Check-in de pacientes
- Gestão de filas de espera

#### 2. **Consultórios**
- Medicina Geral
- Psicologia
- Psiquiatria
- Prescrição de medicamentos
- Solicitação de exames
- Registro de anamnese e diagnóstico

#### 3. **Laboratório**
- Protocolo diário de exames
- Lançamento de resultados
- Validação técnica
- Impressão de laudos

#### 4. **Farmácia**
- Dispensação de medicamentos
- Controle de estoque
- Alertas de validade
- Gestão de prescrições

#### 5. **Financeiro**
- Faturamento automático
- Registro de pagamentos
- Métodos: Dinheiro, M-Pesa, Mkesh, Cartão
- Relatórios financeiros
- Exportação PDF/Excel

#### 6. **Busca Global** (Ctrl+K)
- Busca instantânea em todo o sistema
- Pacientes, exames, medicamentos, faturas
- Histórico de pesquisas recentes

#### 7. **Configurações**
- Dados da clínica
- Parâmetros financeiros
- Configurações de consultas
- Configurações de laboratório
- Alertas de farmácia
- Segurança e auditoria

### 🔐 ACESSO AO SISTEMA

**URL:** http://localhost/MSLDA/

**Credenciais de Admin:**
- Email: heloisa.aldina@maissaude.co.mz
- Senha: senha123

### 📖 DOCUMENTAÇÃO

- **Web:** Acesse `documentacao.php` no sistema
- **Markdown:** Pasta `/docs/` contém toda documentação técnica
- **Testes:** Pasta `/tests/` contém scripts de verificação

### 🛠️ TECNOLOGIAS

- **Backend:** PHP 7.4+ com MySQLi
- **Frontend:** Tailwind CSS 3.x + Bootstrap Icons
- **Database:** MySQL/MariaDB
- **Server:** Apache (XAMPP)

### 📝 NOTAS IMPORTANTES

1. **Ambiente de Desenvolvimento:** Este sistema roda em XAMPP
2. **Backups:** Configure backups automáticos em Configurações → Sistema
3. **Segurança:** Altere as senhas padrão no primeiro acesso
4. **Testes:** Use os scripts em `/tests/` apenas em desenvolvimento

### 🆘 SUPORTE

**Suporte Técnico:**
- Domingos João Mangação
- Email: domingos.mangacao@maissaude.co.mz

**Administração:**
- Heloísa da Aldina Manuel
- Email: heloisa.aldina@maissaude.co.mz

---

**Versão:** 1.0.0  
**Última Atualização:** Dezembro 2024  
**Desenvolvido por:** Equipe Integrada Mais Saúde
