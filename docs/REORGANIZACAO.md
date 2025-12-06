# Guia de Reorganização do Sistema MSLDA

## Estrutura Nova

```
MSLDA/
├── index.php                    # Login (mantém na raiz)
├── dashboard.php                # Dashboard principal (mantém na raiz)
├── config.php                   # Configurações (mantém na raiz)
├── router.php                   # Sistema de rotas (NOVO)
├── .htaccess                    # Reescrita de URLs (NOVO)
├── logout.php                   # Logout (mantém na raiz)
│
├── includes/                    # Arquivos compartilhados (NOVO)
│   ├── header.php              # Header comum
│   └── footer.php              # Footer comum
│
├── modules/                     # Módulos organizados (NOVO)
│   ├── recepcao/
│   │   ├── index.php           # MOVER: recepcao.php
│   │   ├── pacientes.php       # Gestão de pacientes
│   │   ├── agendamentos.php    # Agendamentos
│   │   └── faturamento.php     # Faturamento
│   │
│   ├── laboratorio/
│   │   ├── index.php           # MOVER: laboratorio.php
│   │   ├── protocolos.php      # Protocolos do dia
│   │   ├── resultados.php      # Resultados
│   │   └── sysmex_integration.php  # MOVER: sysmex_integration.php
│   │
│   ├── farmacia/
│   │   ├── index.php           # MOVER: farmacia.php
│   │   ├── estoque.php         # Gestão de estoque
│   │   └── prescricoes.php     # Prescrições
│   │
│   ├── consultorios/
│   │   ├── medico_dashboard.php    # MOVER: medico_dashboard.php
│   │   ├── psicologia.php          # MOVER: psicologia.php
│   │   └── psiquiatria.php         # MOVER: psiquiatria.php
│   │
│   ├── financeiro/
│   │   ├── index.php           # Dashboard financeiro
│   │   ├── relatorios.php      # MOVER: relatorios_financeiros.php
│   │   ├── faturas.php         # MOVER: ver_fatura.php
│   │   └── caixa.php           # Controle de caixa
│   │
│   └── admin/
│       ├── usuarios.php        # Gestão de usuários
│       ├── servicos.php        # Gestão de serviços
│       └── configuracoes.php   # Configurações do sistema
│
├── api/                         # Endpoints API (NOVO)
│   ├── chat_messages.php       # MOVER: chat_messages.php
│   ├── chat_send.php           # Envio de mensagens
│   └── pacientes_search.php    # Busca de pacientes
│
├── assets/                      # Assets estáticos (NOVO)
│   ├── css/
│   │   └── custom.css          # Estilos customizados
│   └── js/
│       └── common.js           # JavaScript comum
│
├── uploads/                     # Arquivos enviados (mantém)
│   ├── prescriptions/
│   └── documents/
│
└── vendor/                      # Dependências (mantém)
    └── autoload.php
```

## Comandos PowerShell para Migração

Execute estes comandos no PowerShell (na pasta MSLDA):

```powershell
# 1. RECEPÇÃO
Move-Item recepcao.php modules\recepcao\index.php

# 2. LABORATÓRIO
Move-Item laboratorio.php modules\laboratorio\index.php
Move-Item sysmex_integration.php modules\laboratorio\sysmex_integration.php

# 3. FARMÁCIA
Move-Item farmacia.php modules\farmacia\index.php

# 4. CONSULTÓRIOS
Move-Item medico_dashboard.php modules\consultorios\medico_dashboard.php
Move-Item psicologia.php modules\consultorios\psicologia.php
Move-Item psiquiatria.php modules\consultorios\psiquiatria.php

# 5. FINANCEIRO
Move-Item relatorios_financeiros.php modules\financeiro\relatorios.php
Move-Item ver_fatura.php modules\financeiro\faturas.php

# 6. API/CHAT
Move-Item chat_messages.php api\chat_messages.php
Move-Item chat.php modules\admin\chat.php

# 7. SCRIPTS DE SETUP (mover para pasta temporária)
New-Item -ItemType Directory -Path "setup_scripts" -Force
Move-Item adicionar_*.php setup_scripts\
Move-Item sincronizar_*.php setup_scripts\
Move-Item check_*.php setup_scripts\
```

## URLs Novas vs Antigas

| ANTIGA | NOVA |
|--------|------|
| `recepcao.php` | `/MSLDA/recepcao` |
| `laboratorio.php` | `/MSLDA/laboratorio` |
| `farmacia.php` | `/MSLDA/farmacia` |
| `medico_dashboard.php` | `/MSLDA/consultorio/medico` |
| `psicologia.php` | `/MSLDA/consultorio/psicologia` |
| `psiquiatria.php` | `/MSLDA/consultorio/psiquiatria` |
| `relatorios_financeiros.php` | `/MSLDA/financeiro/relatorios` |
| `ver_fatura.php?id=X` | `/MSLDA/financeiro/faturas?id=X` |
| `sysmex_integration.php` | `/MSLDA/laboratorio/sysmex` |
| `chat_messages.php` | `/MSLDA/api/chat/messages` |

## Ajustes Necessários nos Arquivos

### 1. Atualizar includes nos arquivos movidos:

**ANTES:**
```php
require_once 'config.php';
```

**DEPOIS (para arquivos em modules/):**
```php
require_once '../../config.php';
```

**DEPOIS (para arquivos em modules/subpasta/):**
```php
require_once '../../../config.php';
```

### 2. Atualizar links/redirects:

**ANTES:**
```php
header('Location: laboratorio.php');
```

**DEPOIS:**
```php
header('Location: /MSLDA/laboratorio');
```

### 3. Usar header comum (opcional):

**ADICIONAR no início dos arquivos:**
```php
<?php
$page_title = 'Laboratório';
$module_name = 'Módulo Laboratório';
require_once '../../includes/header.php';
?>

<!-- Seu conteúdo HTML aqui -->

<?php require_once '../../includes/footer.php'; ?>
```

## Teste de Migração

1. **Ativar mod_rewrite no Apache:**
   - Abra `C:\xampp\apache\conf\httpd.conf`
   - Descomente: `LoadModule rewrite_module modules/mod_rewrite.so`
   - Mude `AllowOverride None` para `AllowOverride All`
   - Reinicie o Apache

2. **Testar URLs:**
   - Acesse: http://localhost/MSLDA/recepcao
   - Acesse: http://localhost/MSLDA/laboratorio
   - Acesse: http://localhost/MSLDA/farmacia

3. **Compatibilidade:**
   - O router.php mantém compatibilidade com arquivos na raiz
   - URLs antigas continuam funcionando durante a transição

## Benefícios

✅ **Organização clara** por módulos
✅ **URLs amigáveis** sem .php
✅ **Manutenção facilitada**
✅ **Código reutilizável** (header/footer)
✅ **APIs separadas** da lógica de apresentação
✅ **Segurança melhorada** (controle de rotas)
✅ **Escalabilidade** para novos módulos

## Próximos Passos

1. Execute os comandos de migração
2. Teste cada módulo
3. Atualize links internos gradualmente
4. Mantenha backups dos arquivos originais
